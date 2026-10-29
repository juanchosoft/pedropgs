<?php

declare(strict_types=1);

namespace Pgs\Auth;

use Pgs\Core\Exceptions\ForbiddenException;

final class UnitScope
{
    /**
     * @return list<int>|null null means all units (SuperAdmin)
     */
    public static function forUser(\PDO $pdo, int $userId, string $userType): ?array
    {
        $superType = class_exists(\Util::class) ? \Util::SuperAdmin() : 'SuperAdministrador';
        if ($userType === $superType) {
            return null;
        }

        $units = [];
        // Check pivot
        $checkPivot = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tec_usuarios_has_tbl_unidades'")->fetchColumn();
        if ((int) $checkPivot > 0) {
            $stmt = $pdo->prepare("SELECT tbl_unidades_id FROM tec_usuarios_has_tbl_unidades WHERE tec_usuarios_id = :uid");
            $stmt->execute([':uid' => $userId]);
            while ($uid = $stmt->fetchColumn()) {
                $units[] = (int) $uid;
            }
        }

        if ($units === []) {
            $stmtSingle = $pdo->prepare("SELECT tbl_unidad_id FROM tec_usuarios WHERE id = :uid");
            $stmtSingle->execute([':uid' => $userId]);
            $single = $stmtSingle->fetchColumn();
            if ($single !== false && $single !== null && (int) $single > 0) {
                $units[] = (int) $single;
            }
        }

        return array_values(array_unique($units));
    }

    public static function assertUnit(Principal $p, int $unitId): void
    {
        if ($p->isSystemAdmin() || $p->unitIds === null) {
            return;
        }
        if (!in_array($unitId, $p->unitIds, true)) {
            throw new ForbiddenException("Access denied to unit #$unitId");
        }
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function sqlFilter(Principal $p, string $column, array &$params, string $prefix = 'us'): string
    {
        if ($p->isSystemAdmin() || $p->unitIds === null) {
            return '1=1';
        }
        if ($p->unitIds === []) {
            return '1=0';
        }

        $placeholders = [];
        foreach ($p->unitIds as $idx => $uid) {
            $paramName = ":{$prefix}{$idx}";
            $placeholders[] = $paramName;
            $params[$paramName] = $uid;
        }

        return "$column IN (" . implode(', ', $placeholders) . ")";
    }
    /**
     * Appends the unit restriction of the principal to a WHERE clause list.
     *
     * @param string[] $where
     * @param array<string,mixed> $params
     */
    public static function scopeSql(Principal $p, string $column, array &$where, array &$params, string $prefix = 'us'): void
    {
        $filter = self::sqlFilter($p, $column, $params, $prefix);
        if ($filter !== '1=1') {
            $where[] = $filter;
        }
    }

    /**
     * @return int[]
     */
    public static function accessibleUnitIds(Principal $p): array
    {
        if ($p->isSystemAdmin() || $p->unitIds === null) {
            return array_map('intval', \Pgs\Core\Db::pdo()->query('SELECT id FROM tbl_unidades')->fetchAll(\PDO::FETCH_COLUMN));
        }
        return array_map('intval', $p->unitIds);
    }
}
