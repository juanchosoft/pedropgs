<?php

declare(strict_types=1);

namespace Pgs\Expenses;

use Pgs\Auth\Policy\ExpensePolicy;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Sequence;
use Pgs\Core\PrivateStorage;
use Pgs\Core\Ulid;
use Pgs\Integrations\SyncEnqueuer;

final class ExpenseService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function validateDraft(Principal $p, array $in): array
    {
        $unitId = (int) ($in['unidad_id'] ?? $in['unit_id'] ?? 0);
        if ($unitId <= 0) {
            throw new ValidationException(['unit_id' => 'Property (unit) is required.']);
        }

        $uStmt = $this->pdo->prepare('SELECT id, nombre FROM tbl_unidades WHERE id = :u');
        $uStmt->execute([':u' => $unitId]);
        $unit = $uStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$unit) {
            throw new ValidationException(['unit_id' => 'Selected property does not exist.']);
        }

        if (!ExpensePolicy::canCreate($p, $unitId)) {
            throw new ForbiddenException('Cannot register expenses for this property.');
        }

        $purchaseDate = trim((string) ($in['purchase_date'] ?? ''));
        if ($purchaseDate === '') {
            $purchaseDate = Clock::todayLocal()->format('Y-m-d');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            throw new ValidationException(['purchase_date' => 'Valid purchase date (YYYY-MM-DD) is required.']);
        }

        $today = Clock::todayLocal()->format('Y-m-d');
        $minDate = (new \DateTimeImmutable($today))->modify('-365 days')->format('Y-m-d');
        if ($purchaseDate < $minDate || $purchaseDate > $today) {
            throw new ValidationException(['purchase_date' => 'Purchase date must be between the past 365 days and today.']);
        }

        $vendor = isset($in['vendor']) ? trim((string) $in['vendor']) : null;
        if ($vendor !== null && mb_strlen($vendor) > 150) {
            throw new ValidationException('Vendor cannot exceed 150 characters.');
        }

        $receiptNumber = isset($in['receipt_number']) ? trim((string) $in['receipt_number']) : null;
        if ($receiptNumber !== null && mb_strlen($receiptNumber) > 60) {
            throw new ValidationException('Receipt number cannot exceed 60 characters.');
        }

        $locationLabel = isset($in['location_label']) ? trim((string) $in['location_label']) : null;
        if ($locationLabel !== null && mb_strlen($locationLabel) > 120) {
            throw new ValidationException('Location label cannot exceed 120 characters.');
        }

        $notes = isset($in['notes']) ? trim((string) $in['notes']) : null;
        $currency = 'USD';

        $rawItems = $in['items'] ?? ($in['lines'] ?? []);
        if (!is_array($rawItems) || empty($rawItems)) {
            throw new ValidationException('At least one expense line item is required.');
        }
        if (count($rawItems) > 50) {
            throw new ValidationException('Maximum 50 line items allowed per expense.');
        }

        $maxLineAmount = (float) Config::get('expenses', 'max_line_amount', 10000);
        $lines = [];
        $lineNo = 1;
        $totalCents = 0;

        foreach ($rawItems as $item) {
            $desc = trim((string) ($item['description'] ?? ''));
            if ($desc === '' || mb_strlen($desc) > 255) {
                throw new ValidationException("Item line #{$lineNo}: description must be between 1 and 255 characters.");
            }

            try {
                $qtyStr = \Pgs\Core\Money::normalize($item['quantity'] ?? 0, 3);
                $priceStr = \Pgs\Core\Money::normalize($item['unit_price'] ?? 0, 2);
            } catch (ValidationException $e) {
                throw new ValidationException("Item line #{$lineNo}: quantity allows up to 3 decimals and unit price up to 2 decimals.");
            }
            if (str_starts_with($priceStr, '-')) {
                throw new ValidationException("Item line #{$lineNo}: unit price cannot be negative.");
            }
            $qtyMilli = (int) str_replace('.', '', $qtyStr);
            if ($qtyMilli <= 0 || $qtyMilli > 9999000) {
                throw new ValidationException("Item line #{$lineNo}: quantity must be greater than 0 and up to 9999.");
            }
            $priceCents = (int) str_replace('.', '', $priceStr);
            if ($priceCents > (int) round($maxLineAmount * 100)) {
                throw new ValidationException("Item line #{$lineNo}: unit price exceeds maximum allowed ({$maxLineAmount}).");
            }
            $lineCents = intdiv($qtyMilli * $priceCents + 500, 1000);
            $totalCents += $lineCents;
            $qty = (float) ($qtyMilli / 1000);
            $unitPrice = (float) ($priceCents / 100);
            $lineTotal = (float) ($lineCents / 100);

            $lines[] = [
                'line_no' => $lineNo++,
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        $total = (float) ($totalCents / 100);

        return [
            'normalized' => [
                'unidad_id' => $unitId,
                'location_label' => $locationLabel,
                'purchase_date' => $purchaseDate,
                'vendor' => $vendor,
                'receipt_number' => $receiptNumber,
                'notes' => $notes,
                'currency' => $currency,
            ],
            'lines' => $lines,
            'total' => $total,
            'currency' => $currency,
            'unit_name' => $unit['nombre'],
            'warnings' => [],
        ];
    }

    public function create(
        Principal $p,
        array $in,
        string $channel = 'web',
        ?int $pendingActionId = null,
        ?string $iaAttachmentPublicId = null
    ): array {
        $draft = $this->validateDraft($p, $in);
        $norm = $draft['normalized'];
        $unitId = (int) $norm['unidad_id'];

        $receiptPath = null;
        if (!empty($_FILES['receipt_file']['tmp_name'])) {
            $receiptPath = $this->saveReceiptFile($_FILES['receipt_file']);
        }

        $sourceAttachmentId = null;
        if ($iaAttachmentPublicId !== null && $iaAttachmentPublicId !== '') {
            $iaStmt = $this->pdo->prepare(
                'SELECT id, storage_path, mime FROM tec_ia_adjuntos WHERE public_id = :pid AND user_id = :uid AND deleted_at IS NULL'
            );
            $iaStmt->execute([':pid' => $iaAttachmentPublicId, ':uid' => $p->userId]);
            $ia = $iaStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$ia) {
                throw new NotFoundException('Attachment not found.');
            }
            $sourceAttachmentId = (int) $ia['id'];
            $extByMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            if ($receiptPath === null && isset($extByMime[$ia['mime']])) {
                $sourceAbs = PrivateStorage::abs((string) $ia['storage_path']);
                if (is_file($sourceAbs)) {
                    $receiptPath = PrivateStorage::put('expenses', $extByMime[$ia['mime']], $sourceAbs);
                }
            }
        }

        $now = Clock::nowUtc();
        $nowStr = $now->format('Y-m-d H:i:s');
        $code = Sequence::code($this->pdo, 'EXP', $now);

        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tec_expenses (
                    code, unidad_id, location_label, purchase_date, vendor, receipt_number,
                    notes, currency, total_amount, items_count, billing_status, channel,
                    created_by_user_id, created_by_employee_id, pending_action_id,
                    receipt_path, source_attachment_id, created_at, updated_at, row_version
                ) VALUES (
                    :code, :u, :loc, :pdate, :vendor, :recnum,
                    :notes, :currency, :total, :cnt, "pending", :channel,
                    :uid, :empid, :paid,
                    :rpath, :srcatt, :now, :now, 1
                )'
            );

            $stmt->execute([
                ':code' => $code,
                ':u' => $unitId,
                ':loc' => $norm['location_label'],
                ':pdate' => $norm['purchase_date'],
                ':vendor' => $norm['vendor'],
                ':recnum' => $norm['receipt_number'],
                ':notes' => $norm['notes'],
                ':currency' => $norm['currency'],
                ':total' => $draft['total'],
                ':cnt' => count($draft['lines']),
                ':channel' => $channel,
                ':uid' => $p->userId,
                ':empid' => $p->employeeId,
                ':paid' => $pendingActionId,
                ':rpath' => $receiptPath,
                ':srcatt' => $sourceAttachmentId,
                ':now' => $nowStr,
            ]);

            $expenseId = (int) $this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare(
                'INSERT INTO tec_expense_items (
                    expense_id, line_no, description, quantity, unit_price, line_total
                ) VALUES (
                    :eid, :lno, :desc, :qty, :price, :ltotal
                )'
            );

            foreach ($draft['lines'] as $line) {
                $stmtItem->execute([
                    ':eid' => $expenseId,
                    ':lno' => $line['line_no'],
                    ':desc' => $line['description'],
                    ':qty' => $line['quantity'],
                    ':price' => $line['unit_price'],
                    ':ltotal' => $line['line_total'],
                ]);
            }

            SyncEnqueuer::expense($this->pdo, $expenseId);

            AuditLogger::log($p, 'expense.created', 'expense', $expenseId, null, [
                'code' => $code,
                'total_amount' => $draft['total'],
                'items_count' => count($draft['lines']),
            ], $unitId);

            if ($ownsTx) {
                $this->pdo->commit();
            }

            return $this->get($p, $expenseId);
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(Principal $p, int $id, array $in, int $rowVersion): array
    {
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_expenses WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $expense = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$expense) {
                throw new NotFoundException('Expense not found.');
            }

            if (!ExpensePolicy::canUpdate($p, $expense)) {
                throw new ForbiddenException('Cannot edit this expense.');
            }

            if ($expense['billing_status'] !== 'pending') {
                throw new ConflictException("Cannot edit an expense with status '{$expense['billing_status']}'.");
            }

            if ((int) $expense['row_version'] !== $rowVersion) {
                throw new ConflictException('Concurrent modification detected. Please refresh and try again.');
            }

            // Staff window check
            if (!$p->isSystemAdmin()) {
                $windowHours = (int) Config::get('expenses', 'staff_edit_window_hours', 24);
                $createdAt = new \DateTimeImmutable($expense['created_at']);
                if ($createdAt->modify("+{$windowHours} hours") < Clock::nowUtc()) {
                    throw new ValidationException("Staff can only edit expenses within {$windowHours} hours of registration.");
                }
            }

            $draft = $this->validateDraft($p, $in);
            $norm = $draft['normalized'];
            $unitId = (int) $norm['unidad_id'];

            $receiptPath = $expense['receipt_path'];
            if (!empty($_FILES['receipt_file']['tmp_name'])) {
                $receiptPath = $this->saveReceiptFile($_FILES['receipt_file']);
            }

            // Replace items
            $this->pdo->prepare('DELETE FROM tec_expense_items WHERE expense_id = :eid')->execute([':eid' => $id]);

            $stmtItem = $this->pdo->prepare(
                'INSERT INTO tec_expense_items (
                    expense_id, line_no, description, quantity, unit_price, line_total
                ) VALUES (
                    :eid, :lno, :desc, :qty, :price, :ltotal
                )'
            );
            foreach ($draft['lines'] as $line) {
                $stmtItem->execute([
                    ':eid' => $id,
                    ':lno' => $line['line_no'],
                    ':desc' => $line['description'],
                    ':qty' => $line['quantity'],
                    ':price' => $line['unit_price'],
                    ':ltotal' => $line['line_total'],
                ]);
            }

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_expenses SET
                    unidad_id = :u,
                    location_label = :loc,
                    purchase_date = :pdate,
                    vendor = :vendor,
                    receipt_number = :recnum,
                    notes = :notes,
                    total_amount = :total,
                    items_count = :cnt,
                    receipt_path = :rpath,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                ':u' => $unitId,
                ':loc' => $norm['location_label'],
                ':pdate' => $norm['purchase_date'],
                ':vendor' => $norm['vendor'],
                ':recnum' => $norm['receipt_number'],
                ':notes' => $norm['notes'],
                ':total' => $draft['total'],
                ':cnt' => count($draft['lines']),
                ':rpath' => $receiptPath,
                ':now' => $now,
                ':id' => $id,
            ]);

            SyncEnqueuer::expense($this->pdo, $id);

            AuditLogger::log($p, 'expense.updated', 'expense', $id, [
                'total_amount' => $expense['total_amount'],
            ], [
                'total_amount' => $draft['total'],
                'items_count' => count($draft['lines']),
            ], $unitId);

            $this->pdo->commit();

            return $this->get($p, $id);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function void(Principal $p, int $id, string $reason): array
    {
        if (!ExpensePolicy::canVoid($p)) {
            throw new ForbiddenException('Cannot void expenses.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException('A reason is required to void an expense.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_expenses WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $expense = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$expense) {
                throw new NotFoundException('Expense not found.');
            }

            if (!ExpenseStateMachine::canTransition($expense['billing_status'], 'void')) {
                throw new ConflictException("Cannot void expense with status '{$expense['billing_status']}'.");
            }

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_expenses SET
                    billing_status = "void",
                    void_reason = :reason,
                    voided_at = :now,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                ':reason' => $reason,
                ':now' => $now,
                ':id' => $id,
            ]);

            $unitId = (int) $expense['unidad_id'];
            SyncEnqueuer::expense($this->pdo, $id);

            AuditLogger::log($p, 'expense.voided', 'expense', $id, [
                'billing_status' => $expense['billing_status'],
            ], [
                'billing_status' => 'void',
                'void_reason' => $reason,
            ], $unitId);

            $this->pdo->commit();
            return $this->get($p, $id);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function list(Principal $p, array $f, int $page = 1, int $perPage = 25): array
    {
        if ($p->isUnitAdmin()) {
            throw new ForbiddenException('Unit admins cannot access expenses.');
        }

        $canViewAll = $p->can('gastos.expenses.view_all');
        $canViewOwn = $p->can('gastos.expenses.view_own');

        if (!$canViewAll && !$canViewOwn) {
            throw new ForbiddenException('Cannot view expenses.');
        }

        $where = ['1=1'];
        $params = [];

        // If without view_all, restrict to own expenses
        if (!$canViewAll) {
            $where[] = 'e.created_by_user_id = :uid';
            $params[':uid'] = $p->userId;
        } elseif (!empty($f['created_by_user_id'])) {
            $where[] = 'e.created_by_user_id = :uid';
            $params[':uid'] = (int) $f['created_by_user_id'];
        }

        if (!empty($f['unidad_id'])) {
            $uId = (int) $f['unidad_id'];
            UnitScope::assertUnit($p, $uId);
            $where[] = 'e.unidad_id = :u';
            $params[':u'] = $uId;
        }

        if (!empty($f['location_label'])) {
            $where[] = 'e.location_label LIKE :loc';
            $params[':loc'] = '%' . trim((string) $f['location_label']) . '%';
        }

        if (!empty($f['billing_status'])) {
            $where[] = 'e.billing_status = :status';
            $params[':status'] = (string) $f['billing_status'];
        }

        if (!empty($f['channel'])) {
            $where[] = 'e.channel = :chan';
            $params[':chan'] = (string) $f['channel'];
        }

        if (!empty($f['from_date'])) {
            $where[] = 'e.purchase_date >= :df';
            $params[':df'] = (string) $f['from_date'];
        }

        if (!empty($f['to_date'])) {
            $where[] = 'e.purchase_date <= :dt';
            $params[':dt'] = (string) $f['to_date'];
        }

        if (!empty($f['q'])) {
            $q = '%' . trim((string) $f['q']) . '%';
            $where[] = '(e.code LIKE :q OR e.vendor LIKE :q OR e.receipt_number LIKE :q OR e.notes LIKE :q)';
            $params[':q'] = $q;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_expenses e WHERE {$whereSql}");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT e.*,
                       u.nombre as unidad_nombre,
                       usr.nombre as created_by_nombre,
                       inv.invoice_number as linked_invoice_number
                FROM tec_expenses e
                JOIN tbl_unidades u ON u.id = e.unidad_id
                JOIN tec_usuarios usr ON usr.id = e.created_by_user_id
                LEFT JOIN tec_invoices inv ON inv.id = e.invoice_id
                WHERE {$whereSql}
                ORDER BY e.purchase_date DESC, e.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public function get(Principal $p, int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*,
                    u.nombre as unidad_nombre,
                    usr.nombre as created_by_nombre,
                    inv.invoice_number as linked_invoice_number
             FROM tec_expenses e
             JOIN tbl_unidades u ON u.id = e.unidad_id
             LEFT JOIN tec_usuarios usr ON usr.id = e.created_by_user_id
             LEFT JOIN tec_invoices inv ON inv.id = e.invoice_id
             WHERE e.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Expense not found.');
        }

        if (!ExpensePolicy::canView($p, $row)) {
            throw new ForbiddenException('Cannot view this expense.');
        }

        // Fetch items
        $iStmt = $this->pdo->prepare('SELECT * FROM tec_expense_items WHERE expense_id = :id ORDER BY line_no ASC');
        $iStmt->execute([':id' => $id]);
        $row['items'] = $iStmt->fetchAll(\PDO::FETCH_ASSOC);

        return $row;
    }

    public function totals(Principal $p, array $f): array
    {
        if ($p->isUnitAdmin() || !$p->can('gastos.expenses.totals')) {
            throw new ForbiddenException('Cannot view expense totals.');
        }

        $where = ["e.billing_status != 'void'"];
        $params = [];

        if (!empty($f['unidad_id'])) {
            $where[] = 'e.unidad_id = :u';
            $params[':u'] = (int) $f['unidad_id'];
        }

        if (!empty($f['from_date'])) {
            $where[] = 'e.purchase_date >= :df';
            $params[':df'] = (string) $f['from_date'];
        }

        if (!empty($f['to_date'])) {
            $where[] = 'e.purchase_date <= :dt';
            $params[':dt'] = (string) $f['to_date'];
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT e.unidad_id,
                       u.nombre as unidad_nombre,
                       COUNT(e.id) as expenses_count,
                       COALESCE(SUM(e.total_amount), 0) as total_amount,
                       COALESCE(SUM(CASE WHEN e.billing_status = 'pending' THEN e.total_amount ELSE 0 END), 0) as pending_amount,
                       COALESCE(SUM(CASE WHEN e.billing_status = 'invoiced' THEN e.total_amount ELSE 0 END), 0) as invoiced_amount
                FROM tec_expenses e
                JOIN tbl_unidades u ON u.id = e.unidad_id
                WHERE {$whereSql}
                GROUP BY e.unidad_id, u.nombre
                ORDER BY u.nombre ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function pendingByUnit(Principal $p, int $unitId): array
    {
        if (!ExpensePolicy::canMarkInvoiced($p)) {
            throw new ForbiddenException('Cannot view pending billing expenses.');
        }

        UnitScope::assertUnit($p, $unitId);

        $stmt = $this->pdo->prepare(
            'SELECT e.*, usr.nombre as created_by_nombre
             FROM tec_expenses e
             JOIN tec_usuarios usr ON usr.id = e.created_by_user_id
             WHERE e.unidad_id = :u AND e.billing_status = "pending"
             ORDER BY e.purchase_date ASC, e.id ASC'
        );
        $stmt->execute([':u' => $unitId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markInvoiced(Principal $p, array $expenseIds, int $invoiceId): void
    {
        if (!ExpensePolicy::canMarkInvoiced($p)) {
            throw new ForbiddenException('Cannot mark expenses as invoiced.');
        }

        if (empty($expenseIds)) {
            throw new ValidationException('At least one expense must be selected.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            // Verify invoice
            $invStmt = $this->pdo->prepare('SELECT id, unidad_id, payment_status FROM tec_invoices WHERE id = :id FOR UPDATE');
            $invStmt->execute([':id' => $invoiceId]);
            $invoice = $invStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new NotFoundException('Target invoice not found.');
            }
            if ($invoice['payment_status'] === 'void') {
                throw new ValidationException('Cannot link expenses to a void invoice.');
            }

            $invUnitId = (int) $invoice['unidad_id'];

            $expStmt = $this->pdo->prepare('SELECT id, unidad_id, billing_status FROM tec_expenses WHERE id = :id FOR UPDATE');
            $updStmt = $this->pdo->prepare(
                'UPDATE tec_expenses SET
                    billing_status = "invoiced",
                    invoice_id = :invid,
                    invoiced_at = :now,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );

            foreach ($expenseIds as $rawEid) {
                $eid = (int) $rawEid;
                $expStmt->execute([':id' => $eid]);
                $exp = $expStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$exp) {
                    throw new NotFoundException("Expense #{$eid} not found.");
                }

                if ($exp['billing_status'] !== 'pending') {
                    throw new ConflictException("Expense #{$eid} is not in pending status.");
                }

                // EXP-04: Must match unit of invoice
                if ((int) $exp['unidad_id'] !== $invUnitId) {
                    throw new ValidationException("Expense #{$eid} belongs to a different property than the invoice.");
                }

                $updStmt->execute([
                    ':invid' => $invoiceId,
                    ':now' => $now,
                    ':id' => $eid,
                ]);

                SyncEnqueuer::expense($this->pdo, $eid);

                AuditLogger::log($p, 'expense.invoiced', 'expense', $eid, [
                    'billing_status' => 'pending',
                ], [
                    'billing_status' => 'invoiced',
                    'invoice_id' => $invoiceId,
                ], $invUnitId);
            }

            if ($ownsTx) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function unmarkInvoiced(Principal $p, int $expenseId, string $reason): void
    {
        if (!ExpensePolicy::canMarkInvoiced($p)) {
            throw new ForbiddenException('Cannot unmark invoiced expenses.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_expenses WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $expenseId]);
            $exp = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$exp) {
                throw new NotFoundException('Expense not found.');
            }

            if ($exp['billing_status'] !== 'invoiced') {
                throw new ConflictException("Expense is not marked as invoiced.");
            }

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_expenses SET
                    billing_status = "pending",
                    invoice_id = NULL,
                    invoiced_at = NULL,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );
            $stmtUpd->execute([':now' => $now, ':id' => $expenseId]);

            $unitId = (int) $exp['unidad_id'];
            SyncEnqueuer::expense($this->pdo, $expenseId);

            AuditLogger::log($p, 'expense.uninvoiced', 'expense', $expenseId, [
                'billing_status' => 'invoiced',
                'invoice_id' => $exp['invoice_id'],
            ], [
                'billing_status' => 'pending',
                'reason' => $reason,
            ], $unitId);

            if ($ownsTx) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function saveReceiptFile(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Receipt file upload failed.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            throw new ValidationException('Receipt file must be between 1 byte and 10 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($allowed[$mime])) {
            throw new ValidationException('Invalid receipt file type. Only PDF, JPG, or PNG are accepted.');
        }

        return PrivateStorage::put('expenses', $allowed[$mime], (string) $file['tmp_name']);
    }

    public function streamReceipt(Principal $p, int $id): void
    {
        $expense = $this->get($p, $id);
        if (empty($expense['receipt_path'])) {
            throw new NotFoundException('This expense has no receipt.');
        }

        $abs = PrivateStorage::abs((string) $expense['receipt_path']);
        if (!is_file($abs)) {
            throw new NotFoundException('Receipt file missing from storage.');
        }

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
        AuditLogger::log($p, 'expense.receipt_downloaded', 'expense', $id, null, ['receipt_path' => $expense['receipt_path']], (int) $expense['unidad_id']);

        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $expense['code'] . '-receipt.' . $ext . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
        exit;
    }

    /**
     * CSV of the expenses matching the filters. Only for users who may see totals.
     */
    public function exportCsv(Principal $p, array $f): string
    {
        if ($p->isUnitAdmin() || !($p->isSystemAdmin() || $p->can('gastos.expenses.totals'))) {
            throw new ForbiddenException('Cannot export expenses.');
        }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Code', 'Purchase date', 'Property', 'Location', 'Vendor', 'Receipt #', 'Total', 'Currency', 'Billing status', 'Invoice', 'Registered by', 'Channel'], ',', '"', '\\');

        $page = 1;
        do {
            $res = $this->list($p, $f, $page, 100);
            foreach ($res['items'] as $row) {
                fputcsv($out, [
                    $this->csvSafe($row['code']),
                    $row['purchase_date'],
                    $this->csvSafe($row['unidad_nombre']),
                    $this->csvSafe($row['location_label'] ?? ''),
                    $this->csvSafe($row['vendor'] ?? ''),
                    $this->csvSafe($row['receipt_number'] ?? ''),
                    number_format((float) $row['total_amount'], 2, '.', ''),
                    $row['currency'] ?? 'USD',
                    $row['billing_status'],
                    $this->csvSafe($row['linked_invoice_number'] ?? ''),
                    $this->csvSafe($row['created_by_nombre'] ?? ''),
                    $row['channel'] ?? '',
                ], ',', '"', '\\');
            }
            $page++;
        } while ($page <= (int) $res['pages']);

        AuditLogger::log($p, 'expense.exported', 'expense', null, null, ['filters' => array_intersect_key($f, array_flip(['unidad_id', 'billing_status', 'from_date', 'to_date', 'q']))]);

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    private function csvSafe(mixed $value): string
    {
        $text = (string) $value;
        return $text !== '' && strpbrk($text[0], "=+-@\t\r") !== false ? "'" . $text : $text;
    }
}
