<?php

declare(strict_types=1);

namespace Pgs\Auth\Policy;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;

final class ExpensePolicy
{
    public static function canCreate(Principal $p, int $unitId): bool
    {
        if ($p->isUnitAdmin()) {
            return false;
        }
        if (!$p->can('gastos.expenses.create')) {
            return false;
        }
        UnitScope::assertUnit($p, $unitId);
        return true;
    }

    public static function canView(Principal $p, array $expense): bool
    {
        if ($p->isUnitAdmin()) {
            return false;
        }
        if ($p->isSystemAdmin() || $p->can('gastos.expenses.view_all')) {
            return true;
        }
        if ($p->can('gastos.expenses.view_own') || $p->can('gastos.expenses.view')) {
            return (int) ($expense['created_by_user_id'] ?? 0) === $p->userId;
        }
        return false;
    }

    public static function canUpdate(Principal $p, array $expense): bool
    {
        if ($p->isUnitAdmin()) {
            return false;
        }
        if (!$p->can('gastos.expenses.update')) {
            return false;
        }
        if ($p->isSystemAdmin()) {
            return true;
        }
        // Staff can only update their own expenses
        return (int) ($expense['created_by_user_id'] ?? 0) === $p->userId;
    }

    public static function canVoid(Principal $p): bool
    {
        if ($p->isUnitAdmin()) {
            return false;
        }
        return $p->can('gastos.expenses.void');
    }

    public static function canMarkInvoiced(Principal $p): bool
    {
        if ($p->isUnitAdmin()) {
            return false;
        }
        return $p->can('gastos.expenses.mark_invoiced');
    }
}
