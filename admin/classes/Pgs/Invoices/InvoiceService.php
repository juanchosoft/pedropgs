<?php

declare(strict_types=1);

namespace Pgs\Invoices;

use Pgs\Auth\Policy\InvoicePolicy;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\PrivateStorage;
use Pgs\Core\Ulid;
use Pgs\Expenses\ExpenseService;

final class InvoiceService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function save(Principal $p, array $in, ?array $uploadedFile = null, ?string $pdfFromPrivatePath = null): array
    {
        if (!InvoicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot manage invoices.');
        }

        $id = !empty($in['id']) ? (int) $in['id'] : null;

        $invoiceNum = trim((string) ($in['invoice_number'] ?? ''));
        if ($invoiceNum === '' || mb_strlen($invoiceNum) > 40) {
            throw new ValidationException('Invoice number is required (up to 40 characters).');
        }

        // Check invoice number uniqueness
        $uSql = 'SELECT id FROM tec_invoices WHERE invoice_number = :num';
        $uParams = [':num' => $invoiceNum];
        if ($id !== null) {
            $uSql .= ' AND id != :id';
            $uParams[':id'] = $id;
        }
        $uStmt = $this->pdo->prepare($uSql);
        $uStmt->execute($uParams);
        if ($uStmt->fetch()) {
            throw new ConflictException("Invoice number '{$invoiceNum}' is already in use.");
        }

        $unitId = (int) ($in['unidad_id'] ?? 0);
        if ($unitId <= 0) {
            throw new ValidationException('Property (unit) is required.');
        }
        UnitScope::assertUnit($p, $unitId);

        $issueDate = trim((string) ($in['issue_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
            throw new ValidationException('Valid issue date (YYYY-MM-DD) is required.');
        }

        $dueDate = !empty($in['due_date']) ? trim((string) $in['due_date']) : null;
        if ($dueDate !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                throw new ValidationException('Due date must be YYYY-MM-DD.');
            }
            if ($dueDate < $issueDate) {
                throw new ValidationException('Due date cannot be before issue date.');
            }
        }

        $amount = (float) ($in['amount'] ?? 0);
        if ($amount <= 0) {
            throw new ValidationException('Invoice amount must be greater than zero.');
        }
        $amount = round($amount, 2);

        $currency = 'USD';
        $locationLabel = isset($in['location_label']) ? trim((string) $in['location_label']) : null;
        $notes = isset($in['notes']) ? trim((string) $in['notes']) : null;

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            if ($id === null) {
                // Creating new invoice -> PDF is mandatory
                $pdfMeta = $this->processPdf($uploadedFile, $pdfFromPrivatePath);
                if (!$pdfMeta) {
                    throw new ValidationException('A valid PDF invoice file is required.');
                }

                $stmt = $this->pdo->prepare(
                    'INSERT INTO tec_invoices (
                        invoice_number, unidad_id, location_label, issue_date, due_date,
                        currency, amount, amount_paid, payment_status, notes,
                        pdf_path, pdf_original_name, pdf_size_bytes, pdf_sha256,
                        created_by_user_id, created_at, updated_at, row_version
                    ) VALUES (
                        :num, :u, :loc, :idate, :ddate,
                        :currency, :amt, 0.00, "pending", :notes,
                        :pdf_path, :pdf_name, :pdf_size, :pdf_sha,
                        :uid, :now, :now, 1
                    )'
                );
                $stmt->execute([
                    ':num' => $invoiceNum,
                    ':u' => $unitId,
                    ':loc' => $locationLabel,
                    ':idate' => $issueDate,
                    ':ddate' => $dueDate,
                    ':currency' => $currency,
                    ':amt' => $amount,
                    ':notes' => $notes,
                    ':pdf_path' => $pdfMeta['path'],
                    ':pdf_name' => $pdfMeta['name'],
                    ':pdf_size' => $pdfMeta['size'],
                    ':pdf_sha' => $pdfMeta['sha256'],
                    ':uid' => $p->userId,
                    ':now' => $now,
                ]);

                $newId = (int) $this->pdo->lastInsertId();

                // Link pending expenses if requested
                $linkExpIds = $in['link_expense_ids'] ?? ($in['expense_ids'] ?? []);
                if (!empty($linkExpIds) && is_array($linkExpIds)) {
                    (new ExpenseService($this->pdo))->markInvoiced($p, $linkExpIds, $newId);
                }

                AuditLogger::log($p, 'invoice.created', 'invoice', $newId, null, [
                    'invoice_number' => $invoiceNum,
                    'amount' => $amount,
                    'unidad_id' => $unitId,
                ], $unitId);

                $this->pdo->commit();
                return $this->get($p, $newId);
            }

            // Updating existing invoice
            $rowVer = (int) ($in['row_version'] ?? 1);
            $stmt = $this->pdo->prepare('SELECT * FROM tec_invoices WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new NotFoundException('Invoice not found.');
            }
            if ($existing['payment_status'] === 'void') {
                throw new ConflictException('Cannot edit a void invoice.');
            }
            if ((int) $existing['row_version'] !== $rowVer) {
                throw new ConflictException('Concurrent modification detected. Please refresh and try again.');
            }

            $pdfPath = $existing['pdf_path'];
            $pdfName = $existing['pdf_original_name'];
            $pdfSize = $existing['pdf_size_bytes'];
            $pdfSha = $existing['pdf_sha256'];

            $newPdf = $this->processPdf($uploadedFile, $pdfFromPrivatePath);
            if ($newPdf) {
                $pdfPath = $newPdf['path'];
                $pdfName = $newPdf['name'];
                $pdfSize = $newPdf['size'];
                $pdfSha = $newPdf['sha256'];
            }

            $upd = $this->pdo->prepare(
                'UPDATE tec_invoices SET
                    invoice_number = :num,
                    unidad_id = :u,
                    location_label = :loc,
                    issue_date = :idate,
                    due_date = :ddate,
                    amount = :amt,
                    notes = :notes,
                    pdf_path = :pdf_path,
                    pdf_original_name = :pdf_name,
                    pdf_size_bytes = :pdf_size,
                    pdf_sha256 = :pdf_sha,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );
            $upd->execute([
                ':num' => $invoiceNum,
                ':u' => $unitId,
                ':loc' => $locationLabel,
                ':idate' => $issueDate,
                ':ddate' => $dueDate,
                ':amt' => $amount,
                ':notes' => $notes,
                ':pdf_path' => $pdfPath,
                ':pdf_name' => $pdfName,
                ':pdf_size' => $pdfSize,
                ':pdf_sha' => $pdfSha,
                ':now' => $now,
                ':id' => $id,
            ]);

            // Link additional expenses if provided
            if (!empty($in['link_expense_ids']) && is_array($in['link_expense_ids'])) {
                (new ExpenseService($this->pdo))->markInvoiced($p, $in['link_expense_ids'], $id);
            }

            AuditLogger::log($p, 'invoice.updated', 'invoice', $id, [
                'amount' => $existing['amount'],
            ], [
                'amount' => $amount,
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

    public function registerPayment(Principal $p, int $id, string $amount, string $paidDate): array
    {
        if (!InvoicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot register invoice payments.');
        }

        $paidAmount = (float) $amount;
        if ($paidAmount <= 0) {
            throw new ValidationException('Payment amount must be greater than zero.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
            throw new ValidationException('Valid payment date (YYYY-MM-DD) is required.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_invoices WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $inv = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new NotFoundException('Invoice not found.');
            }
            if (in_array($inv['payment_status'], ['void', 'paid'], true)) {
                throw new ConflictException("Cannot register payment on an invoice with status '{$inv['payment_status']}'.");
            }

            $currentPaid = (float) $inv['amount_paid'];
            $invoiceTotal = (float) $inv['amount'];
            $newPaid = round($currentPaid + $paidAmount, 2);

            $newStatus = ($newPaid >= $invoiceTotal) ? 'paid' : 'partially_paid';

            $upd = $this->pdo->prepare(
                'UPDATE tec_invoices SET
                    amount_paid = :paid,
                    payment_status = :status,
                    paid_at = :paid_at,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );
            $upd->execute([
                ':paid' => $newPaid,
                ':status' => $newStatus,
                ':paid_at' => $newStatus === 'paid' ? ($paidDate . ' 12:00:00') : $inv['paid_at'],
                ':now' => $now,
                ':id' => $id,
            ]);

            $unitId = (int) $inv['unidad_id'];
            AuditLogger::log($p, 'invoice.payment_registered', 'invoice', $id, [
                'amount_paid' => $currentPaid,
                'payment_status' => $inv['payment_status'],
            ], [
                'payment_added' => $paidAmount,
                'amount_paid' => $newPaid,
                'payment_status' => $newStatus,
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
        if (!InvoicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot void invoices.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException('A reason is required to void an invoice.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_invoices WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $inv = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$inv) {
                throw new NotFoundException('Invoice not found.');
            }
            if ($inv['payment_status'] === 'void') {
                throw new ConflictException('Invoice is already void.');
            }

            // Unlink all linked expenses
            $expStmt = $this->pdo->prepare('SELECT id FROM tec_expenses WHERE invoice_id = :id FOR UPDATE');
            $expStmt->execute([':id' => $id]);
            $linkedExpenses = $expStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($linkedExpenses)) {
                $this->pdo->prepare(
                    'UPDATE tec_expenses SET
                        billing_status = "pending",
                        invoice_id = NULL,
                        invoiced_at = NULL,
                        row_version = row_version + 1,
                        updated_at = :now
                     WHERE invoice_id = :id'
                )->execute([':now' => $now, ':id' => $id]);
            }

            $upd = $this->pdo->prepare(
                'UPDATE tec_invoices SET
                    payment_status = "void",
                    void_reason = :reason,
                    voided_at = :now,
                    row_version = row_version + 1,
                    updated_at = :now
                 WHERE id = :id'
            );
            $upd->execute([
                ':reason' => $reason,
                ':now' => $now,
                ':id' => $id,
            ]);

            $unitId = (int) $inv['unidad_id'];
            AuditLogger::log($p, 'invoice.voided', 'invoice', $id, [
                'payment_status' => $inv['payment_status'],
            ], [
                'payment_status' => 'void',
                'void_reason' => $reason,
                'unlinked_expenses_count' => count($linkedExpenses),
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

    public function linkExpenses(Principal $p, int $id, array $expenseIds): void
    {
        (new ExpenseService($this->pdo))->markInvoiced($p, $expenseIds, $id);
    }

    public function listForPrincipal(Principal $p, array $f, int $page = 1, int $perPage = 25): array
    {
        $canManage = InvoicePolicy::canManage($p);
        $canViewUnits = $p->can('facturas.invoices.view_own_units');

        if (!$canManage && !$canViewUnits) {
            throw new ForbiddenException('Cannot view invoices.');
        }

        $where = ['1=1'];
        $params = [];

        UnitScope::scopeSql($p, 'i.unidad_id', $where, $params);

        if (!empty($f['unidad_id'])) {
            $uId = (int) $f['unidad_id'];
            UnitScope::assertUnit($p, $uId);
            $where[] = 'i.unidad_id = :u';
            $params[':u'] = $uId;
        }

        if (!empty($f['payment_status'])) {
            $where[] = 'i.payment_status = :status';
            $params[':status'] = (string) $f['payment_status'];
        }

        if (!empty($f['from_date'])) {
            $where[] = 'i.issue_date >= :df';
            $params[':df'] = (string) $f['from_date'];
        }

        if (!empty($f['to_date'])) {
            $where[] = 'i.issue_date <= :dt';
            $params[':dt'] = (string) $f['to_date'];
        }

        if (!empty($f['q'])) {
            $q = '%' . trim((string) $f['q']) . '%';
            $where[] = '(i.invoice_number LIKE :q OR i.location_label LIKE :q OR i.notes LIKE :q)';
            $params[':q'] = $q;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_invoices i WHERE {$whereSql}");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT i.*,
                       u.nombre as unidad_nombre,
                       usr.nombre as created_by_nombre,
                       (SELECT COUNT(*) FROM tec_expenses e WHERE e.invoice_id = i.id) as linked_expenses_count
                FROM tec_invoices i
                JOIN tbl_unidades u ON u.id = i.unidad_id
                LEFT JOIN tec_usuarios usr ON usr.id = i.created_by_user_id
                WHERE {$whereSql}
                ORDER BY i.issue_date DESC, i.id DESC
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
            'SELECT i.*,
                    u.nombre as unidad_nombre,
                    usr.nombre as created_by_nombre,
                    (SELECT COUNT(*) FROM tec_expenses e WHERE e.invoice_id = i.id) as linked_expenses_count
             FROM tec_invoices i
             JOIN tbl_unidades u ON u.id = i.unidad_id
             LEFT JOIN tec_usuarios usr ON usr.id = i.created_by_user_id
             WHERE i.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('Invoice not found.');
        }

        if (!InvoicePolicy::canView($p, $row)) {
            throw new NotFoundException('Invoice not found.');
        }

        // Linked expenses
        $eStmt = $this->pdo->prepare('SELECT id, code, purchase_date, vendor, total_amount FROM tec_expenses WHERE invoice_id = :id ORDER BY purchase_date ASC');
        $eStmt->execute([':id' => $id]);
        $row['linked_expenses'] = $eStmt->fetchAll(\PDO::FETCH_ASSOC);

        return $row;
    }

    public function streamPdf(Principal $p, int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tec_invoices WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $inv = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$inv) {
            throw new NotFoundException('Invoice not found.');
        }

        if (!InvoicePolicy::canDownload($p, $inv)) {
            throw new ForbiddenException('Cannot download this invoice PDF.');
        }

        if (empty($inv['pdf_path'])) {
            throw new NotFoundException('Invoice PDF file not recorded.');
        }

        $absPath = PrivateStorage::abs($inv['pdf_path']);
        if (!is_file($absPath)) {
            throw new NotFoundException('Invoice PDF file missing from storage.');
        }

        $unitId = (int) $inv['unidad_id'];
        AuditLogger::log($p, 'invoice.downloaded', 'invoice', $id, null, [
            'pdf_path' => $inv['pdf_path'],
        ], $unitId);

        $filename = $inv['pdf_original_name'] ?: ($inv['invoice_number'] . '.pdf');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($absPath));

        readfile($absPath);
        exit;
    }

    public function markOverdue(\DateTimeImmutable $today): int
    {
        $todayStr = $today->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            'UPDATE tec_invoices SET
                payment_status = "overdue",
                updated_at = NOW()
             WHERE payment_status IN ("pending", "partially_paid")
               AND due_date IS NOT NULL
               AND due_date < :today'
        );
        $stmt->execute([':today' => $todayStr]);
        return $stmt->rowCount();
    }

    private function processPdf(?array $uploadedFile, ?string $pdfFromPrivatePath): ?array
    {
        if ($uploadedFile && !empty($uploadedFile['tmp_name']) && ($uploadedFile['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $tmpPath = $uploadedFile['tmp_name'];
            $size = (int) ($uploadedFile['size'] ?? 0);

            if ($size <= 0 || $size > 15 * 1024 * 1024) {
                throw new ValidationException('PDF file size must be between 1 byte and 15 MB.');
            }

            // MIME check
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpPath);
            if ($mime !== 'application/pdf') {
                throw new ValidationException('Uploaded file is not a valid PDF.');
            }

            // Magic bytes check (%PDF-)
            $handle = fopen($tmpPath, 'rb');
            $header = fread($handle, 5);
            fclose($handle);
            if ($header !== '%PDF-') {
                throw new ValidationException('File does not start with valid PDF signature (%PDF-).');
            }

            $sha256 = hash_file('sha256', $tmpPath);
            $relPath = PrivateStorage::put('invoices', 'pdf', $tmpPath);

            return [
                'path' => $relPath,
                'name' => basename((string) ($uploadedFile['name'] ?? 'invoice.pdf')),
                'size' => $size,
                'sha256' => $sha256,
            ];
        }

        if ($pdfFromPrivatePath !== null) {
            $absPath = PrivateStorage::abs($pdfFromPrivatePath);
            if (!is_file($absPath)) {
                throw new ValidationException('Private file not found.');
            }
            $size = filesize($absPath);
            $handle = fopen($absPath, 'rb');
            $header = fread($handle, 5);
            fclose($handle);
            if ($header !== '%PDF-') {
                throw new ValidationException('Private file does not start with valid PDF signature (%PDF-).');
            }

            $sha256 = hash_file('sha256', $absPath);

            return [
                'path' => $pdfFromPrivatePath,
                'name' => basename($pdfFromPrivatePath),
                'size' => $size,
                'sha256' => $sha256,
            ];
        }

        return null;
    }

    public function createFromAttachment(Principal $p, array $payload): array
    {
        if (!InvoicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot manage invoices.');
        }

        $attachmentId = $payload['attachment_id'] ?? null;
        if (empty($attachmentId)) {
            throw new ValidationException('attachment_id is required');
        }

        $stmt = $this->pdo->prepare("SELECT * FROM tec_ia_adjuntos WHERE (id = :id OR public_id = :pid) AND user_id = :uid AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => is_numeric($attachmentId) ? (int) $attachmentId : 0, ':pid' => (string) $attachmentId, ':uid' => $p->userId]);
        $adj = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$adj) {
            throw new NotFoundException('Attachment not found');
        }

        if ($adj['kind'] !== 'pdf') {
            throw new ValidationException('Attachment must be a PDF document to create an invoice.');
        }

        $srcPath = PrivateStorage::abs((string) $adj['storage_path']);
        if (!file_exists($srcPath)) {
            throw new NotFoundException('Attachment file not found in storage');
        }

        $filename = 'inv_' . Ulid::generate() . '.pdf';
        $destRel = 'invoices/' . $filename;
        $destFull = PrivateStorage::abs($destRel);
        PrivateStorage::ensureDirectory(dirname($destFull));
        if (!copy($srcPath, $destFull)) {
            throw new \RuntimeException('Failed to copy attachment to invoices storage');
        }

        $in = [
            'invoice_number' => $payload['invoice_number'] ?? '',
            'unidad_id' => $payload['unit_id'] ?? 0,
            'issue_date' => $payload['issue_date'] ?? date('Y-m-d'),
            'due_date' => $payload['due_date'] ?? null,
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'USD',
            'notes' => $payload['notes'] ?? null,
        ];

        $inv = $this->save($p, $in, null, $destRel);

        // Link expense codes if provided
        if (!empty($payload['expense_codes']) && is_array($payload['expense_codes'])) {
            $this->linkExpensesByCode($p, (int) $inv['id'], $payload['expense_codes']);
        }

        return $inv;
    }

    private function linkExpensesByCode(Principal $p, int $invoiceId, array $codes): void
    {
        $expSvc = new ExpenseService($this->pdo);
        foreach ($codes as $code) {
            $stmt = $this->pdo->prepare("SELECT id FROM tec_expenses WHERE code = :code LIMIT 1");
            $stmt->execute([':code' => trim((string) $code)]);
            $expId = $stmt->fetchColumn();
            if ($expId) {
                try {
                    $expSvc->markInvoiced($p, [(int) $expId], $invoiceId);
                } catch (\Throwable $e) {
                    // Ignore non-billable or already linked
                }
            }
        }
    }
}
