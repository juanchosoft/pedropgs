<?php

declare(strict_types=1);

namespace Pgs\Invoices;

final class InvoiceStateMachine
{
    public const TRANSITIONS = [
        'pending' => ['partially_paid', 'paid', 'overdue', 'void'],
        'partially_paid' => ['paid', 'overdue', 'void'],
        'overdue' => ['partially_paid', 'paid', 'void'],
        'paid' => [],
        'void' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
