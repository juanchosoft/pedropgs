<?php

declare(strict_types=1);

namespace Pgs\Auth\Policy;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;

final class TaskPolicy
{
    public static function canView(Principal $p, array $task): bool
    {
        if ($p->isSystemAdmin() || $p->can('tareas.tasks.view_all')) {
            return true;
        }
        if (!$p->can('tareas.tasks.view')) {
            return false;
        }
        if ($p->employeeId !== null && isset($task['assignee_employee_id']) && (int) $task['assignee_employee_id'] === $p->employeeId) {
            return true;
        }
        $unitId = isset($task['unidad_id']) && $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null;
        if ($unitId !== null) {
            if ($p->unitIds !== null && !in_array($unitId, $p->unitIds, true)) {
                return false;
            }
            return true;
        }
        return false;
    }

    public static function canCreate(Principal $p, ?int $unitId): bool
    {
        if (!$p->can('tareas.tasks.create')) {
            return false;
        }
        if ($unitId !== null) {
            UnitScope::assertUnit($p, $unitId);
        }
        return true;
    }

    public static function canUpdate(Principal $p, array $task): bool
    {
        if (!$p->can('tareas.tasks.update')) {
            return false;
        }
        return self::canView($p, $task);
    }

    public static function canAssign(Principal $p): bool
    {
        return $p->can('tareas.tasks.assign');
    }

    public static function canChangeStatus(Principal $p, array $task): bool
    {
        if ($p->isSystemAdmin() || $p->can('tareas.tasks.update')) {
            return true;
        }
        if ($p->can('tareas.tasks.status')) {
            if ($p->employeeId !== null && isset($task['assignee_employee_id']) && (int) $task['assignee_employee_id'] === $p->employeeId) {
                return true;
            }
        }
        return false;
    }

    public static function canDelete(Principal $p): bool
    {
        return $p->can('tareas.tasks.delete');
    }

    public static function canSeeInternalComments(Principal $p): bool
    {
        return !$p->isUnitAdmin();
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function scopeSql(Principal $p, string $column, array &$params, string $prefix = 't'): string
    {
        if ($p->isSystemAdmin() || $p->can('tareas.tasks.view_all')) {
            return '1=1';
        }
        if ($p->isUnitAdmin()) {
            return UnitScope::sqlFilter($p, $column, $params);
        }
        if ($p->employeeId !== null) {
            $empParam = ':scope_emp_' . bin2hex(random_bytes(4));
            $params[$empParam] = $p->employeeId;
            $unitClause = UnitScope::sqlFilter($p, $column, $params);
            return "({$prefix}.assignee_employee_id = {$empParam} OR ({$unitClause}))";
        }
        if ($p->can('tareas.tasks.view')) {
            return UnitScope::sqlFilter($p, $column, $params);
        }
        return '1=0';
    }
}
