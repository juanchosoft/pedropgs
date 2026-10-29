<?php

declare(strict_types=1);

namespace Pgs\Auth\Policy;

use Pgs\Auth\Principal;

final class ReportPolicy
{
    public static function canView(Principal $p): bool
    {
        return $p->can('reportes.catalog.view');
    }

    public static function canSend(Principal $p): bool
    {
        return $p->can('reportes.catalog.send');
    }
}
