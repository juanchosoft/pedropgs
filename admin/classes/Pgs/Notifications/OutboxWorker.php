<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Json;
use Pgs\Integrations\WhatsApp\TemplateCatalog;
use Pgs\Integrations\WhatsApp\WhatsAppAuthException;
use Pgs\Integrations\WhatsApp\WhatsAppClient;
use Pgs\Integrations\WhatsApp\WhatsAppRecipientException;
use Pgs\Integrations\WhatsApp\WhatsAppRetryableException;
use Pgs\Integrations\WhatsApp\WhatsAppTemplateException;
use Pgs\Notifications\Channels\EmailChannel;

final class OutboxWorker
{
    private \PDO $pdo;
    private ?WhatsAppClient $waClient;
    private ?EmailChannel $emailChannel;

    private const BACKOFF_STEPS = [60, 300, 900, 3600, 21600];

    public function __construct(
        ?\PDO $pdo = null,
        ?WhatsAppClient $waClient = null,
        ?EmailChannel $emailChannel = null
    ) {
        $this->pdo = $pdo ?? Db::pdo();
        $this->waClient = $waClient;
        $this->emailChannel = $emailChannel;
    }

    /**
     * Processes pending/failed outbox rows up to $limit.
     *
     * @return array{processed: int, sent: int, failed: int, dead: int, skipped: int}
     */
    public static function run(int $limit = 50, ?\PDO $pdo = null): array
    {
        $worker = new self($pdo);
        return $worker->process($limit);
    }

    public function process(int $limit = 50): array
    {
        $now = Clock::nowUtcSql();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM tec_notification_outbox
             WHERE status IN ('pending', 'failed')
               AND (locked_until IS NULL OR locked_until < :now)
               AND next_attempt_at <= :now2
               AND attempts < max_attempts
             ORDER BY id ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':now', $now);
        $stmt->bindValue(':now2', $now);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => 0];

        if (empty($rows)) {
            return $stats;
        }

        $lockStmt = $this->pdo->prepare(
            "UPDATE tec_notification_outbox
             SET status = 'sending',
                 locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
                 updated_at = NOW()
             WHERE id = :id AND status IN ('pending', 'failed')"
        );

        $successStmt = $this->pdo->prepare(
            "UPDATE tec_notification_outbox
             SET status = 'sent',
                 sent_at = NOW(),
                 locked_until = NULL,
                 last_error = NULL,
                 provider_message_id = :msg_id,
                 updated_at = NOW()
             WHERE id = :id"
        );

        $failStmt = $this->pdo->prepare(
            "UPDATE tec_notification_outbox
             SET status = :status,
                 attempts = attempts + 1,
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL :backoff SECOND),
                 locked_until = NULL,
                 last_error = :err,
                 updated_at = NOW()
             WHERE id = :id"
        );

        $skipStmt = $this->pdo->prepare(
            "UPDATE tec_notification_outbox
             SET status = 'skipped',
                 locked_until = NULL,
                 last_error = :err,
                 updated_at = NOW()
             WHERE id = :id"
        );

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $lockStmt->execute([':id' => $id]);
            if ($lockStmt->rowCount() === 0) {
                continue; // Claimed by another worker
            }

            $stats['processed']++;
            $attempts = (int) $row['attempts'] + 1;
            $maxAttempts = (int) ($row['max_attempts'] ?? 6);

            try {
                $providerMsgId = $this->deliver($row);
                $successStmt->execute([
                    ':id' => $id,
                    ':msg_id' => $providerMsgId ?? ('sim_' . bin2hex(random_bytes(6))),
                ]);
                $stats['sent']++;
            } catch (WhatsAppRecipientException $e) {
                // Not on WhatsApp or outside 24h window -> mark skipped and fallback to email
                $skipStmt->execute([
                    ':id' => $id,
                    ':err' => mb_substr($e->getMessage(), 0, 500),
                ]);
                $stats['skipped']++;
                $this->fallbackToEmail($row);
            } catch (WhatsAppAuthException | WhatsAppTemplateException $e) {
                // Non-retryable configuration or authentication error -> mark dead immediately
                $failStmt->execute([
                    ':id' => $id,
                    ':status' => 'dead',
                    ':backoff' => 86400,
                    ':err' => mb_substr($e->getMessage(), 0, 500),
                ]);
                $stats['dead']++;
            } catch (\Throwable $e) {
                $isDead = ($attempts >= $maxAttempts);
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

    private function deliver(array $row): ?string
    {
        $channel = (string) $row['channel'];

        if ($channel === 'inapp') {
            return 'inapp_' . $row['id'];
        }

        if ($channel === 'whatsapp') {
            $recipient = (string) ($row['recipient_address'] ?? '');
            if ($recipient === '') {
                throw new WhatsAppRecipientException("Missing recipient phone number");
            }

            $waEnabled = (bool) Config::get('whatsapp', 'enabled', false);
            $token = (string) Config::get('whatsapp', 'access_token', '');
            $phoneId = (string) Config::get('whatsapp', 'phone_number_id', '');

            if ($waEnabled && $token !== '' && $phoneId !== '') {
                $client = $this->waClient ?? new WhatsAppClient();
                $payload = !empty($row['payload']) ? (array) json_decode((string) $row['payload'], true) : [];
                $lang = (string) ($row['lang'] ?? 'es');
                $eventType = (string) ($row['event_type'] ?? 'document.delivery');

                $mapped = TemplateCatalog::map($eventType, $payload, $lang);

                // Check for document header if attachment exists
                $docHeader = null;
                $attachPath = isset($payload['attachment_path']) ? (string) $payload['attachment_path'] : null;
                if ($mapped['has_document'] && $attachPath !== null && file_exists($attachPath)) {
                    $mime = mime_content_type($attachPath) ?: 'application/pdf';
                    $mediaId = $client->uploadMedia($attachPath, $mime);
                    $docHeader = ['id' => $mediaId, 'filename' => basename($attachPath)];
                }

                return $client->sendTemplate(
                    $recipient,
                    $mapped['template'],
                    $lang,
                    $mapped['body_params'],
                    $docHeader
                );
            }

            // Dev / simulated
            return 'wa_sim_' . bin2hex(random_bytes(6));
        }

        if ($channel === 'email') {
            $recipient = (string) ($row['recipient_address'] ?? '');
            if ($recipient === '') {
                throw new \InvalidArgumentException("No recipient email address");
            }

            $payload = !empty($row['payload']) ? (array) json_decode((string) $row['payload'], true) : [];
            $eventType = (string) ($row['event_type'] ?? 'system.notification');
            $subject = "PGS Centrum: " . ucfirst(str_replace('.', ' ', $eventType));

            $bodyHtml = "<p>Notificación de evento: <strong>{$eventType}</strong></p>";
            $bodyHtml .= "<p>Referencia: <code>{$row['entity_type']} #{$row['entity_id']}</code></p>";
            if (!empty($payload['title'])) {
                $bodyHtml .= "<p><strong>Asunto:</strong> " . htmlspecialchars((string) $payload['title']) . "</p>";
            }
            if (!empty($payload['body'])) {
                $bodyHtml .= "<p>" . nl2br(htmlspecialchars((string) $payload['body'])) . "</p>";
            }

            $attachPath = isset($payload['attachment_path']) ? (string) $payload['attachment_path'] : null;

            $emailChannel = $this->emailChannel ?? new EmailChannel(null, $this->pdo);
            return $emailChannel->send(
                $recipient,
                $subject,
                $bodyHtml,
                $attachPath
            );
        }

        return 'sim_' . bin2hex(random_bytes(6));
    }

    /**
     * Enqueues an email notification fallback when WhatsApp delivery is skipped.
     */
    private function fallbackToEmail(array $waRow): void
    {
        $userId = isset($waRow['recipient_user_id']) ? (int) $waRow['recipient_user_id'] : 0;
        if ($userId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT COALESCE(NULLIF(TRIM(notify_email), ''), nickname) AS notify_email FROM tec_usuarios WHERE id = :id AND habilitado IN ('yes', 'si') LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return;
        }

        $targetEmail = trim((string) ($user['notify_email'] ?? ''));
        if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $now = Clock::nowUtcSql();
        $ins = $this->pdo->prepare(
            "INSERT INTO tec_notification_outbox (
                recipient_user_id, channel, recipient_address, event_type, template_key,
                lang, payload, dedupe_key, status, attempts, max_attempts,
                next_attempt_at, created_at, updated_at
            ) VALUES (
                :uid, 'email', :rcpt, :event, :tmpl,
                :lang, :payload, :dedupe, 'pending', 0, 5,
                :now1, :now2, :now3
            )"
        );

        $dedupe = 'wa_fallback_' . $waRow['id'] . '_' . time();
        $ins->execute([
            ':uid' => $userId,
            ':rcpt' => $targetEmail,
            ':event' => $waRow['event_type'],
            ':tmpl' => $waRow['template_key'] ?? 'fallback_email',
            ':lang' => $waRow['lang'] ?? 'es',
            ':payload' => $waRow['payload'],
            ':dedupe' => $dedupe,
            ':now1' => $now,
            ':now2' => $now,
            ':now3' => $now,
        ]);
    }
}
