<?php

declare(strict_types=1);

namespace Pgs\Integrations;

use Pgs\Core\Clock;

final class SyncEnqueuer
{
    public static function enqueue(\PDO $pdo, string $target, string $entityType, int $entityId, string $operation): void
    {
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO tec_sync_outbox (target, entity_type, entity_id, operation, status, attempts, next_attempt_at, created_at, updated_at)
             VALUES (:target, :entity_type, :entity_id, :operation, "pending", 0, :next_attempt, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE operation = VALUES(operation), status = "pending", attempts = 0, next_attempt_at = VALUES(next_attempt_at), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            ':target' => $target,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':operation' => $operation,
            ':next_attempt' => $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public static function task(\PDO $pdo, int $id, bool $hasDate = false): void
    {
        self::enqueue($pdo, 'sheets', 'task', $id, 'upsert');
        if ($hasDate) {
            self::enqueue($pdo, 'calendar', 'task', $id, 'upsert');
        }
    }

    public static function deleteTask(\PDO $pdo, int $id): void
    {
        self::enqueue($pdo, 'sheets', 'task', $id, 'delete');
        self::enqueue($pdo, 'calendar', 'task', $id, 'delete');
    }

    public static function expense(\PDO $pdo, int $id): void
    {
        self::enqueue($pdo, 'sheets', 'expense', $id, 'upsert');
    }

    public static function reservation(\PDO $pdo, int $id): void
    {
        self::enqueue($pdo, 'sheets', 'reservation', $id, 'upsert');
        self::enqueue($pdo, 'calendar', 'reservation', $id, 'upsert');
    }

    public static function absence(\PDO $pdo, int $id): void
    {
        self::enqueue($pdo, 'sheets', 'absence', $id, 'upsert');
        self::enqueue($pdo, 'calendar', 'absence', $id, 'upsert');
    }
}
