<?php

declare(strict_types=1);

namespace Pgs\Expenses;

final class ExpenseStateMachine
{
    public const TRANSITIONS = [
        'pending' => ['invoiced', 'void'],
        'invoiced' => ['pending'],
        'void' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
