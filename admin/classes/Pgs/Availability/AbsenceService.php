<?php

declare(strict_types=1);

namespace Pgs\Availability;

use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Integrations\SyncEnqueuer;

final class AbsenceService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function request(
        Principal $p,
        array|int $in,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $reasonType = null,
        ?string $reasonText = null
    ): array {
        if (is_int($in)) {
            $in = [
                'employee_id' => $in,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'reason_type' => $reasonType,
                'reason_text' => $reasonText,
            ];
        }
        if ($p->isUnitAdmin() && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Unit admins cannot manage time off.');
        }

        $canApprove = $p->can('disponibilidad.absences.approve') || $p->isSystemAdmin();
        $canRequest = $p->can('disponibilidad.absences.request');

        if (!$canApprove && !$canRequest) {
            throw new ForbiddenException('Cannot request time off.');
        }

        // Determine target employee
        $employeeId = isset($in['employee_id']) ? (int) $in['employee_id'] : 0;
        if ($employeeId <= 0) {
            $employeeId = $p->employeeId ?? 0;
        }

        if ($employeeId <= 0) {
            throw new ValidationException('No employee associated with this request.');
        }

        if (!$canApprove && $p->employeeId !== $employeeId) {
            throw new ForbiddenException('You can only request time off for yourself.');
        }

        // Enforce day_part = 'full'
        if (isset($in['day_part']) && $in['day_part'] !== 'full') {
            throw new ValidationException('Only full-day absences are allowed.');
        }
        $dayPart = 'full';

        $dateFrom = trim((string) ($in['date_from'] ?? ''));
        $dateTo = trim((string) ($in['date_to'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            throw new ValidationException('Valid date_from and date_to (YYYY-MM-DD) are required.');
        }

        if ($dateFrom > $dateTo) {
            throw new ValidationException('date_from cannot be after date_to.');
        }

        $today = Clock::todayLocal()->format('Y-m-d');
        if (!$canApprove && $dateFrom < $today) {
            throw new ValidationException('Cannot request time off in the past.');
        }

        $maxDays = (int) Config::get('leave', 'max_request_days', 30);
        $d1 = new \DateTimeImmutable($dateFrom);
        $d2 = new \DateTimeImmutable($dateTo);
        $calendarDays = (int) $d1->diff($d2)->days + 1;
        if ($calendarDays > $maxDays) {
            throw new ValidationException("Time off request exceeds the maximum allowed range of {$maxDays} days.");
        }

        // Count working days
        $counter = new LeaveDayCounter();
        $counted = $counter->count($dateFrom, $dateTo);
        if ($counted['days'] <= 0) {
            throw new ValidationException('The selected range has no working days.');
        }

        // Check for overlapping absences (requested or approved)
        $stmtOverlap = $this->pdo->prepare(
            'SELECT id, status, date_from, date_to FROM tec_worker_absences
             WHERE employee_id = :e AND status IN ("requested", "approved")
               AND date_from <= :to AND date_to >= :from
             LIMIT 1'
        );
        $stmtOverlap->execute([
            ':e' => $employeeId,
            ':from' => $dateFrom,
            ':to' => $dateTo,
        ]);
        if ($stmtOverlap->fetch()) {
            throw new ConflictException('An absence already exists or is requested for this period.');
        }

        // Reason & deductibility
        $reasonType = strtolower(trim((string) ($in['reason_type'] ?? 'vacation')));
        if (!in_array($reasonType, ['vacation', 'sick', 'personal', 'other'], true)) {
            $reasonType = 'vacation';
        }
        $reasonText = isset($in['reason_text']) ? trim((string) $in['reason_text']) : null;

        $deductibleStr = (string) Config::get('leave', 'deductible_reasons', 'vacation,personal');
        $deductibles = array_filter(array_map('trim', explode(',', strtolower($deductibleStr))));
        $deductsBalance = in_array($reasonType, $deductibles, true) ? 1 : 0;

        // If deductible, check available balance per year
        if ($deductsBalance) {
            foreach ($counted['by_year'] as $yr => $neededDays) {
                $bal = LeaveBalanceService::balance($this->pdo, $employeeId, (int) $yr);
                if ($neededDays > $bal['available']) {
                    throw new ValidationException(
                        "Insufficient time off balance for year {$yr}. Available: {$bal['available']}, Requested: {$neededDays}."
                    );
                }
            }
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $channel = (string) ($in['channel'] ?? 'web');

        $stmt = $this->pdo->prepare(
            'INSERT INTO tec_worker_absences (
                employee_id, date_from, date_to, day_part, reason_type, reason_text,
                days_count, deducts_balance, status, requested_by_user_id, channel,
                created_at, updated_at
            ) VALUES (
                :e, :from, :to, :part, :reason, :text,
                :days, :deducts, "requested", :uid, :channel,
                :now, :now
            )'
        );
        $stmt->execute([
            ':e' => $employeeId,
            ':from' => $dateFrom,
            ':to' => $dateTo,
            ':part' => $dayPart,
            ':reason' => $reasonType,
            ':text' => $reasonText,
            ':days' => $counted['days'],
            ':deducts' => $deductsBalance,
            ':uid' => $p->userId,
            ':channel' => $channel,
            ':now' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        AuditLogger::log($p, 'absence.requested', 'absence', $id, null, [
            'employee_id' => $employeeId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'days_count' => $counted['days'],
            'reason_type' => $reasonType,
        ]);

        return [
            'id' => $id,
            'employee_id' => $employeeId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'days_count' => $counted['days'],
            'reason_type' => $reasonType,
            'reason_text' => $reasonText,
            'deducts_balance' => (bool) $deductsBalance,
            'status' => 'requested',
            'created_at' => $now,
        ];
    }

    public function decide(Principal $p, int $id, string $decision, ?string $note): array
    {
        if (!$p->can('disponibilidad.absences.approve') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot approve or reject absences.');
        }

        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new ValidationException('Decision must be "approved" or "rejected".');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_worker_absences WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $absence = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$absence) {
                throw new NotFoundException('Absence request not found.');
            }

            if ($absence['status'] !== 'requested') {
                throw new ConflictException("Cannot decide on absence with status '{$absence['status']}'.");
            }

            $conflicts = [];

            if ($decision === 'approved') {
                $employeeId = (int) $absence['employee_id'];

                // Revalidate balance if deductible
                if ((int) $absence['deducts_balance'] === 1) {
                    $counter = new LeaveDayCounter();
                    $cnt = $counter->count($absence['date_from'], $absence['date_to']);

                    foreach ($cnt['by_year'] as $yr => $days) {
                        $bal = LeaveBalanceService::balance($this->pdo, $employeeId, (int) $yr);
                        // Net balance without considering pending requested since we are approving this one
                        if ($days > $bal['net_balance']) {
                            throw new ConflictException("Insufficient balance to approve for year {$yr}.");
                        }
                    }

                    $lbs = new LeaveBalanceService($this->pdo);
                    $lbs->consume($this->pdo, $absence, $p);
                }

                // Find conflicting reservations in date range
                $stmtConf = $this->pdo->prepare(
                    'SELECT r.id, r.code, r.work_date, r.day_part, r.unidad_id, u.nombre as unidad_nombre
                     FROM tec_worker_reservations r
                     JOIN tbl_unidades u ON u.id = r.unidad_id
                     WHERE r.employee_id = :e
                       AND r.work_date BETWEEN :from AND :to
                       AND r.status IN ("pending", "confirmed")'
                );
                $stmtConf->execute([
                    ':e' => $employeeId,
                    ':from' => $absence['date_from'],
                    ':to' => $absence['date_to'],
                ]);
                $conflicts = $stmtConf->fetchAll(\PDO::FETCH_ASSOC);

                SyncEnqueuer::absence($this->pdo, $id);
            }

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_worker_absences SET
                    status = :status,
                    decided_by_user_id = :uid,
                    decided_at = :now,
                    decision_note = :note,
                    updated_at = :now
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                ':status' => $decision,
                ':uid' => $p->userId,
                ':note' => $note,
                ':now' => $now,
                ':id' => $id,
            ]);

            AuditLogger::log(
                $p,
                $decision === 'approved' ? 'absence.approved' : 'absence.rejected',
                'absence',
                $id,
                ['status' => 'requested'],
                ['status' => $decision, 'decision_note' => $note]
            );

            $this->pdo->commit();

            return [
                'id' => $id,
                'status' => $decision,
                'decision_note' => $note,
                'decided_at' => $now,
                'conflicts' => $conflicts,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancel(Principal $p, int $id): void
    {
        $canApprove = $p->can('disponibilidad.absences.approve') || $p->isSystemAdmin();

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_worker_absences WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $absence = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$absence) {
                throw new NotFoundException('Absence request not found.');
            }

            if (!in_array($absence['status'], ['requested', 'approved'], true)) {
                throw new ConflictException("Cannot cancel an absence with status '{$absence['status']}'.");
            }

            $isOwn = $p->employeeId !== null && (int) $absence['employee_id'] === $p->employeeId;
            if (!$canApprove && !$isOwn) {
                throw new ForbiddenException('Cannot cancel this absence.');
            }

            // If not approver, can only cancel future absences
            $today = Clock::todayLocal()->format('Y-m-d');
            if (!$canApprove && $absence['date_from'] <= $today) {
                throw new ValidationException('Cannot cancel past or ongoing absences.');
            }

            // Reverse balance if it was approved and deductible
            if ($absence['status'] === 'approved' && (int) $absence['deducts_balance'] === 1) {
                $lbs = new LeaveBalanceService($this->pdo);
                $lbs->reverse($this->pdo, $absence, $p);
            }

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_worker_absences SET status = "cancelled", updated_at = :now WHERE id = :id'
            );
            $stmtUpd->execute([':now' => $now, ':id' => $id]);

            AuditLogger::log($p, 'absence.cancelled', 'absence', $id, ['status' => $absence['status']], ['status' => 'cancelled']);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function list(Principal $p, array $f): array
    {
        if ($p->isUnitAdmin() && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Unit admins cannot view time off.');
        }

        $canApprove = $p->can('disponibilidad.absences.approve') || $p->isSystemAdmin();
        $canRequest = $p->can('disponibilidad.absences.request');

        if (!$canApprove && !$canRequest) {
            throw new ForbiddenException('Cannot view absences.');
        }

        $where = ['1=1'];
        $params = [];

        if (!$canApprove) {
            // Strictly own absences
            if ($p->employeeId === null) {
                return [];
            }
            $where[] = 'a.employee_id = :own_emp';
            $params[':own_emp'] = $p->employeeId;
        } elseif (!empty($f['employee_id'])) {
            $where[] = 'a.employee_id = :emp';
            $params[':emp'] = (int) $f['employee_id'];
        }

        if (!empty($f['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = (string) $f['status'];
        }

        if (!empty($f['year'])) {
            $yr = (int) $f['year'];
            $where[] = '(YEAR(a.date_from) = :yr OR YEAR(a.date_to) = :yr)';
            $params[':yr'] = $yr;
        }

        if (!empty($f['date_from'])) {
            $where[] = 'a.date_to >= :df';
            $params[':df'] = (string) $f['date_from'];
        }

        if (!empty($f['date_to'])) {
            $where[] = 'a.date_from <= :dt';
            $params[':dt'] = (string) $f['date_to'];
        }

        $limit = isset($f['limit']) ? max(1, min(200, (int) $f['limit'])) : 100;
        $offset = isset($f['offset']) ? max(0, (int) $f['offset']) : 0;

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT a.*,
                       e.nombre as employee_nombre,
                       u_req.nombre as requested_by_nombre,
                       u_dec.nombre as decided_by_nombre
                FROM tec_worker_absences a
                JOIN tec_employee e ON e.id = a.employee_id
                LEFT JOIN tec_usuarios u_req ON u_req.id = a.requested_by_user_id
                LEFT JOIN tec_usuarios u_dec ON u_dec.id = a.decided_by_user_id
                WHERE {$whereSql}
                ORDER BY a.date_from DESC, a.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
