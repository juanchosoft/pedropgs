<?php

declare(strict_types=1);

namespace Pgs\Auth\Policy;

use Pgs\Auth\Principal;

final class AvailabilityPolicy
{
    public static function canViewSchedule(Principal $p): bool
    {
        return $p->can('disponibilidad.schedule.view');
    }

    public static function canManageSchedule(Principal $p): bool
    {
        return $p->can('disponibilidad.schedule.manage');
    }

    public static function canRequestAbsence(Principal $p): bool
    {
        return $p->can('disponibilidad.absences.request');
    }

    public static function canApproveAbsence(Principal $p): bool
    {
        return $p->can('disponibilidad.absences.approve');
    }

    public static function canCreateReservation(Principal $p): bool
    {
        return $p->can('disponibilidad.reservations.create');
    }

    public static function canManageReservation(Principal $p): bool
    {
        return $p->can('disponibilidad.reservations.manage');
    }
}
