<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Json;

final class OutboxRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Queues one message. Returns the new id, or null when the dedupe key already exists.
     *
     * @param array<string,mixed> $payload
     */
    public function enqueue(
        string $channel,
        string $recipient,
        string $template,
        string $lang,
        array $payload,
        ?int $requestId,
        string $dedupeKey,
        ?\DateTimeImmutable $at = null
    ): ?int {
        $now = ($at ?? Clock::nowUtc())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO tec_outbox
             (channel, recipient, template, lang, payload, request_id, dedupe_key, status, attempts, next_attempt_at, created_at)
             VALUES (:channel, :recipient, :template, :lang, :payload, :request, :dedupe, "pending", 0, :now, :now)'
        );
        $stmt->execute([
            ':channel' => $channel,
            ':recipient' => mb_substr($recipient, 0, 190),
            ':template' => mb_substr($template, 0, 60),
            ':lang' => $lang === 'es' ? 'es' : 'en',
            ':payload' => Json::encode($payload),
            ':request' => $requestId,
            ':dedupe' => mb_substr($dedupeKey, 0, 120),
            ':now' => $now,
        ]);

        return $stmt->rowCount() === 1 ? (int) $this->pdo->lastInsertId() : null;
    }

    /** @return list<array<string,mixed>> */
    public function forRequest(int $requestId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, channel, recipient, template, status, attempts, sent_at, last_error, created_at FROM tec_outbox WHERE request_id = :r ORDER BY id');
        $stmt->execute([':r' => $requestId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
