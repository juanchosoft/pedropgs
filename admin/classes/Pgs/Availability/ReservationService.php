<?php

declare(strict_types=1);

namespace Pgs\Availability;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Sequence;
use Pgs\Integrations\SyncEnqueuer;

final class ReservationService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function createBatch(
        Principal $p,
        int $employeeId,
        int $unitId,
        array $dates,
        string $part = 'full',
        ?string $notes = null,
        ?int $taskId = null
    ): array {
        return $this->reserve($p, $employeeId, $unitId, $dates, $part, $notes, $taskId);
    }

    public function reserve(
        Principal $p,
        int $employeeId,
        int $unitId,
        array $dates,
        string $part = 'full',
        ?string $notes = null,
        ?int $taskId = null
    ): array {
        if (!$p->can('disponibilidad.reservations.create') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot create reservations.');
        }

        // Policy: pure employees cannot reserve
        if ($p->employeeId !== null && !$p->isSystemAdmin() && !$p->isUnitAdmin()) {
            throw new ForbiddenException('Employees cannot reserve worker time.');
        }

        if ($employeeId <= 0) {
            throw new ValidationException('Employee is required.');
        }

        if ($unitId <= 0) {
            throw new ValidationException('Property (unit) is required.');
        }

        UnitScope::assertUnit($p, $unitId);

        if (!in_array($part, ['full', 'am', 'pm'], true)) {
            $part = 'full';
        }

        // Clean & validate dates
        $cleanedDates = [];
        foreach ($dates as $d) {
            $dStr = trim((string) $d);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dStr)) {
                throw new ValidationException("Invalid date format: {$dStr}");
            }
            $cleanedDates[] = $dStr;
        }
        $cleanedDates = array_values(array_unique($cleanedDates));
        sort($cleanedDates);

        if (empty($cleanedDates)) {
            throw new ValidationException('At least one date is required to make a reservation.');
        }

        $now = Clock::nowUtc();
        $nowStr = $now->format('Y-m-d H:i:s');

        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            // Lock technician
            $empStmt = $this->pdo->prepare('SELECT id, enable FROM tec_employee WHERE id = :id FOR UPDATE');
            $empStmt->execute([':id' => $employeeId]);
            $emp = $empStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$emp || !in_array(strtolower((string) $emp['enable']), ['si', 'yes'], true)) {
                throw new ValidationException('Worker not found or inactive.');
            }

            // Check that worker has active schedule in this unit
            $schedStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tec_worker_schedule
                 WHERE employee_id = :e AND unidad_id = :u AND is_active = 1'
            );
            $schedStmt->execute([':e' => $employeeId, ':u' => $unitId]);
            if ((int) $schedStmt->fetchColumn() === 0) {
                throw new ValidationException('Worker does not have an active schedule for this property.');
            }

            // Validate isFree for all dates
            $unavailable = [];
            foreach ($cleanedDates as $dStr) {
                $check = AvailabilityService::isFree($this->pdo, $employeeId, $unitId, $dStr, $part);
                if (!$check['free']) {
                    $unavailable[] = [
                        'date' => $dStr,
                        'reason' => $check['reason'],
                    ];
                }
            }

            if (!empty($unavailable)) {
                $alternatives = AvailabilityService::alternatives($this->pdo, $employeeId, $unitId, $cleanedDates[0], $part, 5);
                throw new ConflictException(
                    'One or more selected dates are unavailable.',
                    'slot_unavailable',
                    [
                        'unavailable_dates' => $unavailable,
                        'alternatives' => $alternatives,
                    ]
                );
            }

            // If task_id provided, verify task exists
            if ($taskId !== null) {
                $tStmt = $this->pdo->prepare('SELECT id FROM tec_tasks WHERE id = :t');
                $tStmt->execute([':t' => $taskId]);
                if (!$tStmt->fetch()) {
                    $taskId = null;
                }
            }

            $stmtIns = $this->pdo->prepare(
                'INSERT INTO tec_worker_reservations (
                    code, employee_id, unidad_id, work_date, day_part,
                    status, notes, requested_by_user_id, channel, task_id,
                    confirmed_at, created_at, updated_at
                ) VALUES (
                    :code, :e, :u, :date, :part,
                    "confirmed", :notes, :uid, "web", :task_id,
                    :now, :now, :now
                )'
            );

            $created = [];
            foreach ($cleanedDates as $dStr) {
                $code = Sequence::code($this->pdo, 'RSV', $now);

                try {
                    $stmtIns->execute([
                        ':code' => $code,
                        ':e' => $employeeId,
                        ':u' => $unitId,
                        ':date' => $dStr,
                        ':part' => $part,
                        ':notes' => $notes,
                        ':uid' => $p->userId,
                        ':task_id' => $taskId,
                        ':now' => $nowStr,
                    ]);
                } catch (\PDOException $pe) {
                    if ((int) $pe->getCode() === 23000 || str_contains($pe->getMessage(), 'uq_rsv_slot')) {
                        throw new ConflictException('Worker is already booked for this slot (concurrency conflict).', 'slot_booked');
                    }
                    throw $pe;
                }

                $rsvId = (int) $this->pdo->lastInsertId();

                SyncEnqueuer::reservation($this->pdo, $rsvId);

                AuditLogger::log($p, 'reservation.created', 'reservation', $rsvId, null, [
                    'code' => $code,
                    'employee_id' => $employeeId,
                    'work_date' => $dStr,
                    'day_part' => $part,
                    'task_id' => $taskId,
                ], $unitId);

                $created[] = [
                    'id' => $rsvId,
                    'code' => $code,
                    'work_date' => $dStr,
                    'day_part' => $part,
                    'status' => 'confirmed',
                ];
            }

            if ($ownsTx) {
                $this->pdo->commit();
            }

            return [
                'codes' => array_column($created, 'code'),
                'dates' => array_column($created, 'work_date'),
                'reservations' => $created,
                'count' => count($created),
            ];
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancel(Principal $p, int $id, string $reason): void
    {
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_worker_reservations WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $rsv = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$rsv) {
                throw new NotFoundException('Reservation not found.');
            }

            if (!in_array($rsv['status'], ['pending', 'confirmed'], true)) {
                throw new ConflictException("Cannot cancel reservation with status '{$rsv['status']}'.");
            }

            $unitId = (int) $rsv['unidad_id'];
            if (!$p->can('disponibilidad.reservations.manage') && !$p->isSystemAdmin()) {
                if (!$p->can('disponibilidad.reservations.create')) {
                    throw new ForbiddenException('Cannot cancel reservation.');
                }
                UnitScope::assertUnit($p, $unitId);
            }

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_worker_reservations SET
                    status = "cancelled",
                    cancel_reason = :reason,
                    updated_at = :now
                 WHERE id = :id'
            );
            $stmtUpd->execute([
                ':reason' => trim($reason) ?: 'Cancelled by user',
                ':now' => $now,
                ':id' => $id,
            ]);

            SyncEnqueuer::reservation($this->pdo, $id);

            AuditLogger::log($p, 'reservation.cancelled', 'reservation', $id, ['status' => $rsv['status']], [
                'status' => 'cancelled',
                'reason' => $reason,
            ], $unitId);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function fulfill(Principal $p, int $id): void
    {
        if (!$p->can('disponibilidad.reservations.manage') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot mark reservation as fulfilled.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tec_worker_reservations WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $rsv = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$rsv) {
                throw new NotFoundException('Reservation not found.');
            }

            if ($rsv['status'] !== 'confirmed') {
                throw new ConflictException("Cannot fulfill reservation with status '{$rsv['status']}'.");
            }

            $unitId = (int) $rsv['unidad_id'];

            $stmtUpd = $this->pdo->prepare(
                'UPDATE tec_worker_reservations SET status = "fulfilled", updated_at = :now WHERE id = :id'
            );
            $stmtUpd->execute([':now' => $now, ':id' => $id]);

            SyncEnqueuer::reservation($this->pdo, $id);

            AuditLogger::log($p, 'reservation.fulfilled', 'reservation', $id, ['status' => 'confirmed'], [
                'status' => 'fulfilled',
            ], $unitId);

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
        $canCreate = $p->can('disponibilidad.reservations.create');
        $canManage = $p->can('disponibilidad.reservations.manage');

        if (!$canCreate && !$canManage && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot view reservations.');
        }

        $where = ['1=1'];
        $params = [];

        UnitScope::scopeSql($p, 'r.unidad_id', $where, $params);

        if (!empty($f['unidad_id'])) {
            $uId = (int) $f['unidad_id'];
            UnitScope::assertUnit($p, $uId);
            $where[] = 'r.unidad_id = :u';
            $params[':u'] = $uId;
        }

        if (!empty($f['employee_id'])) {
            $where[] = 'r.employee_id = :e';
            $params[':e'] = (int) $f['employee_id'];
        }

        if (!empty($f['status'])) {
            $where[] = 'r.status = :status';
            $params[':status'] = (string) $f['status'];
        }

        if (!empty($f['from_date'])) {
            $where[] = 'r.work_date >= :df';
            $params[':df'] = (string) $f['from_date'];
        }

        if (!empty($f['to_date'])) {
            $where[] = 'r.work_date <= :dt';
            $params[':dt'] = (string) $f['to_date'];
        }

        if (!empty($f['task_id'])) {
            $where[] = 'r.task_id = :tid';
            $params[':tid'] = (int) $f['task_id'];
        }

        $limit = isset($f['limit']) ? max(1, min(200, (int) $f['limit'])) : 100;
        $offset = isset($f['offset']) ? max(0, (int) $f['offset']) : 0;

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT r.*,
                       u.nombre as unidad_nombre,
                       e.nombre as employee_nombre,
                       t.code as task_code,
                       t.title as task_title,
                       req.nombre as requested_by_nombre
                FROM tec_worker_reservations r
                JOIN tbl_unidades u ON u.id = r.unidad_id
                JOIN tec_employee e ON e.id = r.employee_id
                LEFT JOIN tec_tasks t ON t.id = r.task_id
                LEFT JOIN tec_usuarios req ON req.id = r.requested_by_user_id
                WHERE {$whereSql}
                ORDER BY r.work_date DESC, r.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
