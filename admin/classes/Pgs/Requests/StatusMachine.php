<?php

declare(strict_types=1);

namespace Pgs\Requests;

final class StatusMachine
{
    public const STATUSES = ['received', 'assigned', 'in_progress', 'completed', 'cancelled'];
    public const PRIORITIES = ['urgent', 'high', 'normal', 'low'];

    private const TRANSITIONS = [
        'received' => ['assigned', 'cancelled'],
        'assigned' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => ['in_progress'],
        'cancelled' => [],
    ];

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public static function nextOptions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isOpen(string $status): bool
    {
        return in_array($status, ['received', 'assigned', 'in_progress'], true);
    }
}
