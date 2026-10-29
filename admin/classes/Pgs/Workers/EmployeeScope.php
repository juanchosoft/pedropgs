<?php

declare(strict_types=1);

namespace Pgs\Workers;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;

final class EmployeeScope
{
    public static function isRestricted(Principal $p): bool
    {
        return !$p->isSystemAdmin() && $p->isUnitAdmin();
    }

    /**
     * Active employees the principal may use, optionally narrowed to one property.
     * Each row carries `assigned` = 1 when the employee belongs to the property (home property or active schedule).
     *
     * @return array<int, array{id:int|string, nombre:string, assigned:int}>
     */
    public static function listFor(Principal $p, ?int $unitId = null, ?\PDO $pdo = null): array
    {
        $pdo = $pdo ?? Db::pdo();
        $today = Clock::nowUtc()->format('Y-m-d');
        $restricted = self::isRestricted($p);

        if ($restricted) {
            $units = $unitId !== null ? self::allowedUnits($p, [$unitId]) : self::allowedUnits($p, null);
            if ($units === []) {
                return [];
            }
        } else {
            $units = $unitId !== null ? [$unitId] : [];
        }

        $assignedSql = '0';
        $params = [];
        if ($units !== []) {
            $in = implode(',', array_fill(0, count($units), '?'));
            $assignedSql = "(e.tbl_unidad_id IN ({$in}) OR EXISTS (
                SELECT 1 FROM tec_worker_schedule s
                WHERE s.employee_id = e.id AND s.unidad_id IN ({$in}) AND s.is_active = 1
                  AND (s.valid_to IS NULL OR s.valid_to >= ?)))";
            $params = [...$units, ...$units, $today];
        }

        $sql = "SELECT e.id, e.nombre, {$assignedSql} AS assigned
                FROM tec_employee e
                WHERE e.enable IN ('si','yes')";
        if ($restricted) {
            $sql .= " AND {$assignedSql}";
            $params = [...$params, ...$params];
        }
        $sql .= ' ORDER BY assigned DESC, e.nombre ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['assigned'] = (int) $row['assigned'];
        }
        unset($row);

        return $rows;
    }

    public static function canUse(Principal $p, int $employeeId, ?int $unitId = null, ?\PDO $pdo = null): bool
    {
        foreach (self::listFor($p, $unitId, $pdo) as $row) {
            if ((int) $row['id'] === $employeeId) {
                return true;
            }
        }

        return false;
    }

    public static function assertUsable(Principal $p, int $employeeId, ?int $unitId = null, ?\PDO $pdo = null): void
    {
        if (!self::canUse($p, $employeeId, $unitId, $pdo)) {
            throw new ForbiddenException('That worker is not assigned to your property.');
        }
    }

    /**
     * @param int[]|null $requested
     * @return int[]
     */
    private static function allowedUnits(Principal $p, ?array $requested): array
    {
        $accessible = array_map('intval', UnitScope::accessibleUnitIds($p));
        if ($requested === null) {
            return $accessible;
        }

        return array_values(array_intersect(array_map('intval', $requested), $accessible));
    }
}
