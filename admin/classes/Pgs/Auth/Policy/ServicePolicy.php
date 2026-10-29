<?php

declare(strict_types=1);

namespace Pgs\Auth\Policy;

use Pgs\Auth\Principal;

final class ServicePolicy
{
    public static function canView(Principal $p): bool
    {
        return $p->can('servicios.catalog.view');
    }

    public static function canManage(Principal $p): bool
    {
        return $p->can('servicios.catalog.manage');
    }

    public static function canRequest(Principal $p): bool
    {
        return $p->can('servicios.requests.create');
    }
}
