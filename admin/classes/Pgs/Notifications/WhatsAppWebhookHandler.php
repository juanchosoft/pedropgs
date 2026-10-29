<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\UnauthorizedException;
use Pgs\Core\Json;
use Pgs\Core\Phone;

final class WhatsAppWebhookHandler
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Handles GET /webhooks/whatsapp verification handshake from Meta.
     */
    public function handleVerify(Request $req): Response
    {
        $mode = (string) ($req->query('hub.mode') ?? $req->query('hub_mode') ?? '');
        $token = (string) ($req->query('hub.verify_token') ?? $req->query('hub_verify_token') ?? '');
        $challenge = (string) ($req->query('hub.challenge') ?? $req->query('hub_challenge') ?? '');

        $expectedToken = (string) Config::get('whatsapp', 'verify_token', '');

        if ($mode === 'subscribe' && $token !== '' && $expectedToken !== '' && hash_equals($expectedToken, $token)) {
            return Response::raw(200, $challenge, 'text/plain; charset=utf-8');
        }

        return Response::raw(403, 'Forbidden', 'text/plain; charset=utf-8');
    }

    /**
     * Handles POST /webhooks/whatsapp events (delivery statuses, incoming messages, opt-out).
     */
    public function handleWebhook(Request $req): Response
    {
        $appSecret = (string) Config::get('whatsapp', 'app_secret', '');
        if ($appSecret !== '') {
            $this->verifyMetaSignature($req->header('x-hub-signature-256') ?? '', $req->rawBody(), $appSecret);
        }

        $body = $req->json();
        if (!is_array($body) || empty($body['entry'])) {
            return Response::ok(['ok' => true]);
        }

        foreach ($body['entry'] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $val = $change['value'] ?? [];
                if (!is_array($val)) {
                    continue;
                }

                $this->processStatuses($val['statuses'] ?? []);
                $this->processMessages($val['messages'] ?? []);
            }
        }

        return Response::ok(['ok' => true]);
    }

    /**
     * Validates X-Hub-Signature-256 against raw body using HMAC-SHA256.
     */
    private function verifyMetaSignature(string $sigHeader, string $rawBody, string $secret): void
    {
        if ($sigHeader === '') {
            throw new UnauthorizedException('Missing X-Hub-Signature-256 header');
        }

        if (!preg_match('/^sha256=([a-fA-F0-9]+)$/i', $sigHeader, $m)) {
            throw new UnauthorizedException('Invalid X-Hub-Signature-256 format');
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $m[1])) {
            throw new UnauthorizedException('Signature verification failed');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $statuses
     */
    private function processStatuses(array $statuses): void
    {
        foreach ($statuses as $st) {
            $wamid = (string) ($st['id'] ?? '');
            $status = (string) ($st['status'] ?? '');
            $ts = (string) ($st['timestamp'] ?? '');
            $dedupeHash = hash('sha256', "{$wamid}|{$status}|{$ts}");

            $ins = $this->pdo->prepare(
                "INSERT IGNORE INTO tec_whatsapp_webhook_events
                 (dedupe_hash, wamid, event_kind, status, payload, received_at, processed_at)
                 VALUES (:hash, :wamid, 'status', :status, :payload, NOW(), NOW())"
            );
            $ins->execute([
                ':hash' => $dedupeHash,
                ':wamid' => $wamid ?: null,
                ':status' => $status ?: null,
                ':payload' => Json::encode($st),
            ]);

            if ($ins->rowCount() === 0) {
                // Duplicate event - skip processing
                continue;
            }

            if ($wamid === '') {
                continue;
            }

            if ($status === 'failed') {
                $errCode = $st['errors'][0]['code'] ?? '';
                $errTitle = $st['errors'][0]['title'] ?? ($st['errors'][0]['message'] ?? 'Delivery failed');
                $lastErr = mb_substr("[{$errCode}] {$errTitle}", 0, 500);

                $upd = $this->pdo->prepare(
                    "UPDATE tec_notification_outbox
                     SET status = 'failed',
                         last_error = :err,
                         updated_at = NOW()
                     WHERE provider_message_id = :wamid"
                );
                $upd->execute([
                    ':err' => $lastErr,
                    ':wamid' => $wamid,
                ]);
            } elseif (in_array($status, ['sent', 'delivered', 'read'], true)) {
                $upd = $this->pdo->prepare(
                    "UPDATE tec_notification_outbox
                     SET status = :status,
                         sent_at = COALESCE(sent_at, NOW()),
                         updated_at = NOW()
                     WHERE provider_message_id = :wamid
                       AND status != 'read'"
                );
                $upd->execute([
                    ':status' => $status,
                    ':wamid' => $wamid,
                ]);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function processMessages(array $messages): void
    {
        foreach ($messages as $msg) {
            $wamid = (string) ($msg['id'] ?? '');
            $from = (string) ($msg['from'] ?? '');
            $ts = (string) ($msg['timestamp'] ?? '');
            $dedupeHash = hash('sha256', "{$wamid}|message|{$ts}");

            $ins = $this->pdo->prepare(
                "INSERT IGNORE INTO tec_whatsapp_webhook_events
                 (dedupe_hash, wamid, event_kind, status, payload, received_at, processed_at)
                 VALUES (:hash, :wamid, 'message', NULL, :payload, NOW(), NOW())"
            );
            $ins->execute([
                ':hash' => $dedupeHash,
                ':wamid' => $wamid ?: null,
                ':payload' => Json::encode($msg),
            ]);

            if ($ins->rowCount() === 0) {
                // Duplicate message - skip processing
                continue;
            }

            $type = (string) ($msg['type'] ?? '');
            if ($type === 'text') {
                $bodyText = trim(strtoupper((string) ($msg['text']['body'] ?? '')));
                if (in_array($bodyText, ['STOP', 'BAJA', 'UNSUBSCRIBE', 'CANCEL'], true)) {
                    $phoneE164 = Phone::toE164($from) ?? (str_starts_with($from, '+') ? $from : "+{$from}");

                    $uStmt = $this->pdo->prepare("SELECT id FROM tec_usuarios WHERE phone_e164 = :phone LIMIT 1");
                    $uStmt->execute([':phone' => $phoneE164]);
                    $userId = $uStmt->fetchColumn() ?: null;

                    $upd = $this->pdo->prepare("UPDATE tec_usuarios SET whatsapp_opt_in = 0 WHERE phone_e164 = :phone");
                    $upd->execute([':phone' => $phoneE164]);

                    AuditLogger::log(
                        Principal::anonymous('webhook'),
                        'whatsapp.opt_out',
                        'user',
                        $userId ? (int) $userId : null,
                        null,
                        [
                            'phone' => $phoneE164,
                            'keyword' => $bodyText,
                            'wamid' => $wamid,
                        ]
                    );
                }
            }
        }
    }
}
