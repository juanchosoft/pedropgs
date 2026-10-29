<?php

declare(strict_types=1);

namespace Pgs\Integrations;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Integrations\Google\CalendarSyncHandler;
use Pgs\Integrations\Google\SheetsSyncHandler;

final class SyncWorker
{
    private \PDO $pdo;
    private ?SheetsSyncHandler $sheetsHandler;
    private ?CalendarSyncHandler $calHandler;

    private const BACKOFF_STEPS = [60, 300, 900, 3600, 21600];

    public function __construct(
        ?\PDO $pdo = null,
        ?SheetsSyncHandler $sheetsHandler = null,
        ?CalendarSyncHandler $calHandler = null
    ) {
        $this->pdo = $pdo ?? Db::pdo();
        $this->sheetsHandler = $sheetsHandler;
        $this->calHandler = $calHandler;
    }

    /**
     * Runs sync queue processor up to $limit.
     *
     * @return array{processed: int, synced: int, failed: int, dead: int, skipped: bool}
     */
    public static function run(int $limit = 50, ?\PDO $pdo = null): array
    {
        $worker = new self($pdo);
        return $worker->process($limit);
    }

    public function process(int $limit = 50): array
    {
        $googleEnabled = (bool) Config::get('google', 'enabled', false);
        if (!$googleEnabled) {
            // Rule 5: If [google] enabled = 0, the worker does nothing and leaves rows in pending.
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => true];
        }

        $now = Clock::nowUtcSql();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM tec_sync_outbox
             WHERE status IN ('pending', 'failed')
               AND (locked_until IS NULL OR locked_until < :now)
               AND next_attempt_at <= :now2
               AND attempts < 6
             ORDER BY id ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':now2', $now);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stats = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => false];

        if (empty($rows)) {
            return $stats;
        }

        $lockStmt = $this->pdo->prepare(
            "UPDATE tec_sync_outbox
             SET status = 'processing',
                 locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                 updated_at = NOW()
             WHERE id = :id AND status IN ('pending', 'failed')"
        );

        $successStmt = $this->pdo->prepare(
            "UPDATE tec_sync_outbox
             SET status = 'done',
                 locked_until = NULL,
                 last_error = NULL,
                 updated_at = NOW()
             WHERE id = :id"
        );

        $failStmt = $this->pdo->prepare(
            "UPDATE tec_sync_outbox
             SET status = :status,
                 attempts = attempts + 1,
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL :backoff SECOND),
                 locked_until = NULL,
                 last_error = :err,
                 updated_at = NOW()
             WHERE id = :id"
        );

        $sheets = $this->sheetsHandler ?? new SheetsSyncHandler(null, null, $this->pdo);
        $calendar = $this->calHandler ?? new CalendarSyncHandler(null, null, $this->pdo);

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $lockStmt->execute([':id' => $id]);
            if ($lockStmt->rowCount() === 0) {
                continue;
            }

            $stats['processed']++;
            $attempts = (int) $row['attempts'] + 1;
            $entityType = (string) $row['entity_type'];
            $entityId = (int) $row['entity_id'];

            try {
                // Dispatch to handlers
                if ($entityType === 'task') {
                    $sheets->syncTask($entityId);
                    $calendar->syncTask($entityId);
                } elseif ($entityType === 'expense') {
                    $sheets->syncExpense($entityId);
                } elseif ($entityType === 'reservation') {
                    $calendar->syncReservation($entityId);
                } elseif ($entityType === 'absence') {
                    $calendar->syncAbsence($entityId);
                }

                $successStmt->execute([':id' => $id]);
                $stats['synced']++;
            } catch (\Throwable $e) {
                $statusCode = 0;
                if ($e instanceof ClientException || $e instanceof ServerException) {
                    $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
                }

                $isRetryable = ($statusCode === 429 || $statusCode >= 500 || $statusCode === 0);
                $isDead = (!$isRetryable || $attempts >= 6);
                $status = $isDead ? 'dead' : 'failed';
                $backoffSecs = self::BACKOFF_STEPS[min($attempts - 1, count(self::BACKOFF_STEPS) - 1)];

                $failStmt->execute([
                    ':id' => $id,
                    ':status' => $status,
                    ':backoff' => $backoffSecs,
                    ':err' => mb_substr($e->getMessage(), 0, 500),
                ]);

                if ($isDead) {
                    $stats['dead']++;
                } else {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }
}
