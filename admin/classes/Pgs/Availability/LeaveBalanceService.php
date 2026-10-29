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

final class LeaveBalanceService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public static function balance(\PDO $pdo, int $employeeId, int $year): array
    {
        // 1. Quota & row_version
        $allocStmt = $pdo->prepare('SELECT allocated_days, row_version FROM tec_leave_allocations WHERE employee_id = :e AND leave_year = :y');
        $allocStmt->execute([':e' => $employeeId, ':y' => $year]);
        $allocRow = $allocStmt->fetch(\PDO::FETCH_ASSOC);
        $quota = $allocRow ? (int) $allocRow['allocated_days'] : 0;
        $rowVersion = $allocRow ? (int) $allocRow['row_version'] : 1;

        // 2. Net ledger
        $ledgerStmt = $pdo->prepare(
            'SELECT
                COALESCE(SUM(days), 0) as net_total,
                COALESCE(SUM(CASE WHEN movement = "consumption" THEN ABS(days) ELSE 0 END), 0) as used_days
             FROM tec_leave_ledger
             WHERE employee_id = :e AND leave_year = :y'
        );
        $ledgerStmt->execute([':e' => $employeeId, ':y' => $year]);
        $lRow = $ledgerStmt->fetch(\PDO::FETCH_ASSOC);
        $netTotal = (int) ($lRow['net_total'] ?? 0);
        $usedDays = (int) ($lRow['used_days'] ?? 0);

        // 3. Pending requests that deduct balance
        $deductibleStr = (string) Config::get('leave', 'deductible_reasons', 'vacation,personal');
        $deductibles = array_filter(array_map('trim', explode(',', strtolower($deductibleStr))));

        $reqStmt = $pdo->prepare(
            'SELECT date_from, date_to, reason_type
             FROM tec_worker_absences
             WHERE employee_id = :e AND status = "requested"'
        );
        $reqStmt->execute([':e' => $employeeId]);
        $pendingAbsences = $reqStmt->fetchAll(\PDO::FETCH_ASSOC);

        $pendingDays = 0;
        $counter = new LeaveDayCounter();
        foreach ($pendingAbsences as $pa) {
            if (in_array(strtolower($pa['reason_type']), $deductibles, true)) {
                try {
                    $cnt = $counter->count($pa['date_from'], $pa['date_to']);
                    $pendingDays += (int) ($cnt['by_year'][$year] ?? 0);
                } catch (\Throwable) {
                    // Skip invalid ranges
                }
            }
        }

        $available = $netTotal - $pendingDays;

        return [
            'employee_id' => $employeeId,
            'year' => $year,
            'quota' => $quota,
            'used' => $usedDays,
            'pending' => $pendingDays,
            'available' => max(0, $available),
            'raw_available' => $available,
            'net_balance' => $netTotal,
            'row_version' => $rowVersion,
        ];
    }

    public function myBalance(Principal $p, ?int $year = null): array
    {
        if (!$p->can('disponibilidad.absences.request')) {
            throw new ForbiddenException('Cannot view time off balance.');
        }
        if ($p->employeeId === null) {
            throw new ValidationException('Your account is not linked to an employee profile.');
        }

        $year = $year ?? (int) Clock::nowUtc()->format('Y');
        return self::balance($this->pdo, $p->employeeId, $year);
    }

    public function balances(Principal $p, int $year): array
    {
        if (!$p->can('disponibilidad.absences.approve') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot view employee leave balances.');
        }

        $empStmt = $this->pdo->query("SELECT id, nombre FROM tec_employee WHERE enable = 'si' ORDER BY nombre ASC");
        $employees = $empStmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($employees as $e) {
            $bal = self::balance($this->pdo, (int) $e['id'], $year);
            $bal['employee_nombre'] = $e['nombre'];
            $results[] = $bal;
        }

        return $results;
    }

    public function setQuota(Principal $p, int $employeeId, int $year, int $days, int $rowVersion, ?string $notes): array
    {
        if (!$p->can('disponibilidad.absences.approve') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot modify leave quotas.');
        }

        if ($days < 0) {
            throw new ValidationException('Quota days cannot be negative.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            // Check existing allocation
            $stmt = $this->pdo->prepare('SELECT allocated_days, row_version FROM tec_leave_allocations WHERE employee_id = :e AND leave_year = :y FOR UPDATE');
            $stmt->execute([':e' => $employeeId, ':y' => $year]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $oldDays = $existing ? (int) $existing['allocated_days'] : 0;
            $currentVer = $existing ? (int) $existing['row_version'] : 1;

            if ($existing && $currentVer !== $rowVersion) {
                throw new ConflictException('Concurrent modification detected. Please refresh and try again.');
            }

            $diff = $days - $oldDays;

            // Check if reducing quota would result in negative available balance
            if ($diff < 0) {
                $curBal = self::balance($this->pdo, $employeeId, $year);
                if (($curBal['net_balance'] + $diff) < 0) {
                    throw new ValidationException('Cannot reduce quota below currently used time off days.');
                }
            }

            if ($existing) {
                $upd = $this->pdo->prepare(
                    'UPDATE tec_leave_allocations SET
                        allocated_days = :days, notes = :notes, updated_by_user_id = :uid, updated_at = :now, row_version = row_version + 1
                     WHERE employee_id = :e AND leave_year = :y'
                );
                $upd->execute([':days' => $days, ':notes' => $notes, ':uid' => $p->userId, ':now' => $now, ':e' => $employeeId, ':y' => $year]);
            } else {
                $ins = $this->pdo->prepare(
                    'INSERT INTO tec_leave_allocations (employee_id, leave_year, allocated_days, notes, created_by_user_id, created_at, updated_at, row_version)
                     VALUES (:e, :y, :days, :notes, :uid, :now, :now, 1)'
                );
                $ins->execute([':e' => $employeeId, ':y' => $year, ':days' => $days, ':notes' => $notes, ':uid' => $p->userId, ':now' => $now]);
            }

            // Ledger entry if there is a diff
            if ($diff !== 0) {
                $movKey = sprintf('quota:%d:%d:%s', $employeeId, $year, bin2hex(random_bytes(6)));
                $led = $this->pdo->prepare(
                    'INSERT INTO tec_leave_ledger (employee_id, leave_year, movement, days, movement_key, reason, actor_user_id, channel, created_at)
                     VALUES (:e, :y, "allocation", :d, :k, :r, :uid, "web", :now)'
                );
                $led->execute([
                    ':e' => $employeeId,
                    ':y' => $year,
                    ':d' => $diff,
                    ':k' => $movKey,
                    ':r' => $notes ?: "Annual quota updated from {$oldDays} to {$days} days",
                    ':uid' => $p->userId,
                    ':now' => $now,
                ]);
            }

            $this->syncLegacy($this->pdo, $employeeId);

            AuditLogger::log($p, 'leave.quota_set', 'employee', $employeeId, ['quota' => $oldDays], ['quota' => $days, 'year' => $year]);

            $this->pdo->commit();

            return self::balance($this->pdo, $employeeId, $year);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function adjust(Principal $p, int $employeeId, int $year, int $days, string $reason): array
    {
        if (!$p->can('disponibilidad.absences.approve') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot adjust leave balance.');
        }

        if ($days === 0) {
            throw new ValidationException('Adjustment days must be non-zero.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new ValidationException('A reason is required for manual balance adjustments.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $curBal = self::balance($this->pdo, $employeeId, $year);
            if ($days < 0 && ($curBal['net_balance'] + $days) < 0) {
                throw new ValidationException('Adjustment would result in negative available leave days.');
            }

            $movKey = sprintf('adjust:%d:%d:%s', $employeeId, $year, bin2hex(random_bytes(6)));
            $led = $this->pdo->prepare(
                'INSERT INTO tec_leave_ledger (employee_id, leave_year, movement, days, movement_key, reason, actor_user_id, channel, created_at)
                 VALUES (:e, :y, "adjustment", :d, :k, :r, :uid, "web", :now)'
            );
            $led->execute([
                ':e' => $employeeId,
                ':y' => $year,
                ':d' => $days,
                ':k' => $movKey,
                ':r' => $reason,
                ':uid' => $p->userId,
                ':now' => $now,
            ]);

            $this->syncLegacy($this->pdo, $employeeId);

            AuditLogger::log($p, 'leave.adjusted', 'employee', $employeeId, null, [
                'days' => $days,
                'year' => $year,
                'reason' => $reason,
            ]);

            $this->pdo->commit();

            return self::balance($this->pdo, $employeeId, $year);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function consume(\PDO $pdo, array $absence, Principal $actor): void
    {
        $employeeId = (int) $absence['employee_id'];
        $absenceId = (int) $absence['id'];

        $counter = new LeaveDayCounter();
        $cnt = $counter->count($absence['date_from'], $absence['date_to']);
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');

        foreach ($cnt['by_year'] as $year => $days) {
            $movKey = sprintf('consume:%d:%d', $absenceId, $year);
            $stmt = $pdo->prepare(
                'INSERT INTO tec_leave_ledger (employee_id, leave_year, movement, days, movement_key, reason, absence_id, actor_user_id, channel, created_at)
                 VALUES (:e, :y, "consumption", :d, :k, :r, :aid, :uid, "web", :now)
                 ON DUPLICATE KEY UPDATE days = VALUES(days)'
            );
            $stmt->execute([
                ':e' => $employeeId,
                ':y' => $year,
                ':d' => -$days,
                ':k' => $movKey,
                ':r' => "Absence approved #{$absenceId} ({$absence['date_from']} to {$absence['date_to']})",
                ':aid' => $absenceId,
                ':uid' => $actor->userId,
                ':now' => $now,
            ]);
        }

        $this->syncLegacy($pdo, $employeeId);
    }

    public function reverse(\PDO $pdo, array $absence, Principal $actor): void
    {
        $employeeId = (int) $absence['employee_id'];
        $absenceId = (int) $absence['id'];

        $counter = new LeaveDayCounter();
        $cnt = $counter->count($absence['date_from'], $absence['date_to']);
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');

        foreach ($cnt['by_year'] as $year => $days) {
            $movKey = sprintf('reverse:%d:%d', $absenceId, $year);
            $stmt = $pdo->prepare(
                'INSERT INTO tec_leave_ledger (employee_id, leave_year, movement, days, movement_key, reason, absence_id, actor_user_id, channel, created_at)
                 VALUES (:e, :y, "reversal", :d, :k, :r, :aid, :uid, "web", :now)
                 ON DUPLICATE KEY UPDATE days = VALUES(days)'
            );
            $stmt->execute([
                ':e' => $employeeId,
                ':y' => $year,
                ':d' => $days,
                ':k' => $movKey,
                ':r' => "Cancelled absence #{$absenceId} ({$absence['date_from']} to {$absence['date_to']})",
                ':aid' => $absenceId,
                ':uid' => $actor->userId,
                ':now' => $now,
            ]);
        }

        $this->syncLegacy($pdo, $employeeId);
    }

    public function ledger(Principal $p, int $employeeId, int $year): array
    {
        $isOwn = $p->employeeId !== null && $p->employeeId === $employeeId;
        if (!$isOwn && !$p->can('disponibilidad.absences.approve') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot view leave ledger.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT l.*, u.nombre as actor_nombre
             FROM tec_leave_ledger l
             LEFT JOIN tec_usuarios u ON u.id = l.actor_user_id
             WHERE l.employee_id = :e AND l.leave_year = :y
             ORDER BY l.id ASC'
        );
        $stmt->execute([':e' => $employeeId, ':y' => $year]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function syncLegacy(\PDO $pdo, int $employeeId): void
    {
        $currYear = (int) Clock::nowUtc()->format('Y');
        $bal = self::balance($pdo, $employeeId, $currYear);
        $pdo->prepare('UPDATE tec_employee SET dias_descanso = :d WHERE id = :id')
            ->execute([':d' => max(0, (int) $bal['net_balance']), ':id' => $employeeId]);
    }
}
