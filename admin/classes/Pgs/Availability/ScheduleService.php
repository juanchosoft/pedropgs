<?php

declare(strict_types=1);

namespace Pgs\Availability;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;

final class ScheduleService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function listByUnit(Principal $p, int $unitId): array
    {
        if (!$p->can('disponibilidad.schedule.view') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot view worker schedules.');
        }
        UnitScope::assertUnit($p, $unitId);

        $stmt = $this->pdo->prepare(
            'SELECT s.*, e.nombre as employee_nombre
             FROM tec_worker_schedule s
             JOIN tec_employee e ON e.id = s.employee_id
             WHERE s.unidad_id = :u AND s.is_active = 1
             ORDER BY s.weekday ASC, e.nombre ASC'
        );
        $stmt->execute([':u' => $unitId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listByEmployee(Principal $p, int $employeeId): array
    {
        if (!$p->can('disponibilidad.schedule.view') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot view worker schedules.');
        }

        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.nombre as unidad_nombre
             FROM tec_worker_schedule s
             JOIN tbl_unidades u ON u.id = s.unidad_id
             WHERE s.employee_id = :e AND s.is_active = 1
             ORDER BY s.weekday ASC, u.nombre ASC'
        );
        $stmt->execute([':e' => $employeeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$p->isSystemAdmin()) {
            $allowed = array_map('intval', UnitScope::accessibleUnitIds($p));
            $rows = array_values(array_filter($rows, static fn (array $r) => in_array((int) $r['unidad_id'], $allowed, true)));
        }

        return $rows;
    }

    public function save(Principal $p, array $in): array
    {
        if (!$p->can('disponibilidad.schedule.manage') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot manage worker schedule.');
        }

        $empId = (int) ($in['employee_id'] ?? 0);
        $unitId = (int) ($in['unidad_id'] ?? 0);
        if ($empId <= 0 || $unitId <= 0) {
            throw new ValidationException('Employee and Property are required.');
        }
        UnitScope::assertUnit($p, $unitId);
        \Pgs\Workers\EmployeeScope::assertUsable($p, $empId, $unitId, $this->pdo);

        $weekdays = $in['weekdays'] ?? ($in['weekday'] ?? []);
        if (!is_array($weekdays)) {
            $weekdays = [(int) $weekdays];
        }
        $weekdays = array_filter(array_map('intval', $weekdays), static fn(int $w) => $w >= 1 && $w <= 7);
        if (empty($weekdays)) {
            throw new ValidationException('At least one day of the week (1-7) must be selected.');
        }

        $dayPart = (string) ($in['day_part'] ?? 'full');
        if (!in_array($dayPart, ['full', 'am', 'pm'], true)) {
            $dayPart = 'full';
        }

        $validFrom = !empty($in['valid_from']) ? (string) $in['valid_from'] : Clock::nowUtc()->format('Y-m-d');
        $validTo = !empty($in['valid_to']) ? (string) $in['valid_to'] : null;
        if ($validTo !== null && $validTo < $validFrom) {
            throw new ValidationException('Valid to date cannot be before valid from date.');
        }

        $notes = isset($in['notes']) ? trim((string) $in['notes']) : null;
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tec_worker_schedule (
                    employee_id, unidad_id, weekday, day_part, valid_from, valid_to, is_active, notes, created_by_user_id, created_at, updated_at
                ) VALUES (
                    :e, :u, :w, :part, :from, :to, 1, :notes, :uid, :now, :now
                )'
            );

            $ids = [];
            foreach ($weekdays as $w) {
                $stmt->execute([
                    ':e' => $empId,
                    ':u' => $unitId,
                    ':w' => $w,
                    ':part' => $dayPart,
                    ':from' => $validFrom,
                    ':to' => $validTo,
                    ':notes' => $notes,
                    ':uid' => $p->userId,
                    ':now' => $now,
                ]);
                $ids[] = (int) $this->pdo->lastInsertId();
            }

            AuditLogger::log($p, 'schedule.saved', 'employee', $empId, null, [
                'unidad_id' => $unitId,
                'weekdays' => $weekdays,
                'day_part' => $dayPart,
            ], $unitId);

            $this->pdo->commit();

            return ['saved_ids' => $ids, 'count' => count($ids)];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(Principal $p, int $id): void
    {
        if (!$p->can('disponibilidad.schedule.manage') && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot delete schedule.');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tec_worker_schedule WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Schedule entry not found.');
        }

        $this->pdo->prepare('DELETE FROM tec_worker_schedule WHERE id = :id')->execute([':id' => $id]);

        AuditLogger::log($p, 'schedule.deleted', 'employee', (int) $row['employee_id'], [
            'id' => $id,
            'weekday' => $row['weekday'],
            'unidad_id' => $row['unidad_id'],
        ], null, (int) $row['unidad_id']);
    }
}
