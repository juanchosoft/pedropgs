<?php

declare(strict_types=1);

namespace Pgs\Core;

final class Sequence
{
    public static function next(\PDO $pdo, string $name, int $year): int
    {
        $pdo->prepare(
            'INSERT INTO tec_sequences (seq_name, seq_year, last_value) VALUES (:n, :y, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)'
        )->execute([':n' => $name, ':y' => $year]);
        return (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    }

    public static function code(\PDO $pdo, string $prefix, \DateTimeImmutable $at): string
    {
        $year = (int) $at->format('Y');
        return sprintf('%s-%d-%06d', $prefix, $year, self::next($pdo, $prefix, $year));
    }
}
