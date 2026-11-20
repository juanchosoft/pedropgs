<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use Pgs\Core\AppLog;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Integrations\WhatsApp\WhatsAppClient;
use Pgs\Integrations\WhatsApp\WhatsAppRetryableException;

final class OutboxWorker
{
    private const MAX_ATTEMPTS = 5;
    private const BACKOFF_MINUTES = [1, 5, 15, 60];
    private const LOCK_TIMEOUT_MINUTES = 15;

    private \PDO $pdo;
    private EmailChannel $email;
    /** @var callable|null */
    private $whatsappSender;

    /**
     * @param callable(string,string,string,array<int,string>):string|null $whatsappSender recipient, template, language, body params
     */
    public function __construct(?\PDO $pdo = null, ?EmailChannel $email = null, ?callable $whatsappSender = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->email = $email ?? new EmailChannel();
        $this->whatsappSender = $whatsappSender;
    }

    /**
     * @return array{sent:int,failed:int,skipped:int,retried:int}
     */
    public function process(int $limit = 50, ?\DateTimeImmutable $now = null): array
    {
        $now ??= Clock::nowUtc();
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'retried' => 0];

        $this->pdo->prepare(
            'UPDATE tec_outbox SET status = "pending", locked_at = NULL
             WHERE status = "sending" AND locked_at < :cut'
        )->execute([':cut' => $now->modify('-' . self::LOCK_TIMEOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s')]);

        foreach ($this->claim($limit, $now) as $row) {
            $this->deliver($row, $now, $stats);
        }

        return $stats;
    }

    /** @return list<array<string,mixed>> */
    private function claim(int $limit, \DateTimeImmutable $now): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tec_outbox WHERE status = "pending" AND next_attempt_at <= :now ORDER BY id ASC LIMIT ' . (int) $limit . ' FOR UPDATE'
            );
            $stmt->execute([':now' => $now->format('Y-m-d H:i:s')]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows !== []) {
                $ids = implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows));
                $this->pdo->exec('UPDATE tec_outbox SET status = "sending", locked_at = "' . $now->format('Y-m-d H:i:s') . '", attempts = attempts + 1 WHERE id IN (' . $ids . ')');
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array{sent:int,failed:int,skipped:int,retried:int} $stats
     */
    private function deliver(array $row, \DateTimeImmutable $now, array &$stats): void
    {
        $id = (int) $row['id'];
        $attempts = (int) $row['attempts'] + 1;
        $payload = json_decode((string) $row['payload'], true);
        $payload = is_array($payload) ? $payload : [];

        try {
            if ($row['channel'] === 'whatsapp') {
                if (!Config::bool('whatsapp', 'enabled', false)) {
                    $this->finish($id, 'skipped', null, 'WhatsApp is disabled', $now);
                    $stats['skipped']++;
                    return;
                }
                $sender = $this->whatsappSender ?? static fn (string $to, string $template, string $lang, array $params): string => (new WhatsAppClient())->sendTemplate($to, $template, $lang, $params);
                $messageId = $sender(
                    (string) $row['recipient'],
                    (string) $row['template'],
                    $row['lang'] === 'es' ? 'es' : 'en_US',
                    array_map('strval', (array) ($payload['params'] ?? []))
                );
            } else {
                $messageId = $this->email->send(
                    (string) $row['recipient'],
                    (string) ($payload['subject'] ?? 'PGS Centrum'),
                    (string) ($payload['html'] ?? ''),
                    (string) ($payload['text'] ?? '')
                );
            }
            $this->finish($id, 'sent', $messageId, null, $now);
            $stats['sent']++;
        } catch (\Throwable $e) {
            $retryable = !($e instanceof \InvalidArgumentException) && (!str_starts_with(get_class($e), 'Pgs\\Integrations\\WhatsApp\\') || $e instanceof WhatsAppRetryableException);
            if ($retryable && $attempts < self::MAX_ATTEMPTS) {
                $delay = self::BACKOFF_MINUTES[min($attempts - 1, count(self::BACKOFF_MINUTES) - 1)];
                $this->pdo->prepare(
                    'UPDATE tec_outbox SET status = "pending", locked_at = NULL, next_attempt_at = :next, last_error = :err WHERE id = :id'
                )->execute([':next' => $now->modify("+{$delay} minutes")->format('Y-m-d H:i:s'), ':err' => mb_substr($e->getMessage(), 0, 255), ':id' => $id]);
                $stats['retried']++;
                return;
            }
            AppLog::error('outbox', $e, ['outbox_id' => $id, 'channel' => $row['channel']]);
            $this->finish($id, 'failed', null, $e->getMessage(), $now);
            $stats['failed']++;
        }
    }

    private function finish(int $id, string $status, ?string $messageId, ?string $error, \DateTimeImmutable $now): void
    {
        $this->pdo->prepare(
            'UPDATE tec_outbox SET status = :status, locked_at = NULL, sent_at = :sent, provider_message_id = :mid, last_error = :err WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':sent' => $status === 'sent' ? $now->format('Y-m-d H:i:s') : null,
            ':mid' => $messageId !== null ? mb_substr($messageId, 0, 100) : null,
            ':err' => $error !== null ? mb_substr($error, 0, 255) : null,
            ':id' => $id,
        ]);
    }
}
