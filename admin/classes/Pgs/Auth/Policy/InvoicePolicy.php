<?php

declare(strict_types=1);

namespace Pgs\Auth\Policy;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;

final class InvoicePolicy
{
    public static function canView(Principal $p, array $invoice): bool
    {
        if ($p->can('facturas.invoices.manage')) {
            return true;
        }
        if (!$p->can('facturas.invoices.view_own_units')) {
            return false;
        }
        $unitId = (int) ($invoice['unidad_id'] ?? 0);
        return $p->unitIds === null || in_array($unitId, $p->unitIds, true);
    }

    public static function canDownload(Principal $p, array $invoice): bool
    {
        if (!$p->can('facturas.invoices.download')) {
            return false;
        }
        return self::canView($p, $invoice);
    }

    public static function canManage(Principal $p): bool
    {
        if ($p->isUnitAdmin()) {
            return false;
        }
        return $p->can('facturas.invoices.manage');
    }
}
