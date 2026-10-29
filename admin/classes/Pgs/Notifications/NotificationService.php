<?php

declare(strict_types=1);

namespace Pgs\Notifications;

use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Json;

final class NotificationService
{
    private \PDO $pdo;

    public const ALL_EVENTS = [
        'task.created',
        'task.assigned',
        'task.status_changed',
        'task.due_soon',
        'service_request.created',
        'reservation.confirmed',
        'reservation.cancelled',
        'absence.requested',
        'absence.decided',
        'invoice.issued',
        'invoice.overdue',
        'document.delivery',
        'phone.message',
        'voice.pin_locked',
        'expense.registered',
    ];

    public const CHANNELS = ['whatsapp', 'email', 'inapp'];

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Emits a domain event to recipients, checking user preferences and type defaults.
     *
     * @param \PDO $pdo
     * @param string $eventType
     * @param string $entityType
     * @param int $entityId
     * @param list<array{user_id?:int, contact_id?:int}> $recipients
     * @param array{title?:string, body?:string, link_url?:string, template_key?:string, lang?:string, payload?:array, attachment_path?:string} $data
     */
    public function emit(
        \PDO $pdo,
        string $eventType,
        string $entityType,
        int $entityId,
        array $recipients,
        array $data
    ): void {
        $now = Clock::nowUtc();
        $nowStr = $now->format('Y-m-d H:i:s');
        $bucket = $now->format('Y-m-d_H');
        if (!empty($data['force_channel'])) {
            $bucket .= '_' . bin2hex(random_bytes(3));
        }
        if (!empty($data['dedupe_suffix'])) {
            $bucket .= '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $data['dedupe_suffix']);
        }

        $title = (string) ($data['title'] ?? ucfirst(str_replace('.', ' ', $eventType)));
        $body = (string) ($data['body'] ?? "Notification for {$entityType} #{$entityId}");
        $linkUrl = isset($data['link_url']) ? (string) $data['link_url'] : null;
        $templateKey = (string) ($data['template_key'] ?? str_replace('.', '_', $eventType));
        $lang = isset($data['lang']) && in_array($data['lang'], ['es', 'en'], true) ? (string) $data['lang'] : 'en';
        $payloadData = $data['payload'] ?? ['entity_type' => $entityType, 'entity_id' => $entityId];
        $payloadData += ['title' => $title, 'body' => $body];
        if ($linkUrl !== null) {
            $payloadData += ['link_url' => $linkUrl];
        }
        $payloadJson = Json::encode($payloadData);
        $attachmentPath = isset($data['attachment_path']) ? (string) $data['attachment_path'] : null;

        // In-app insert prepared statement
        $inappStmt = $pdo->prepare(
            'INSERT INTO tec_notifications (
                user_id, event_type, title, body, link_url, entity_type, entity_id, read_at, created_at
            ) VALUES (
                :uid, :et, :title, :body, :link, :entype, :enid, NULL, :now
            )'
        );

        // Outbox insert prepared statement
        $outboxStmt = $pdo->prepare(
            'INSERT IGNORE INTO tec_notification_outbox (
                event_type, entity_type, entity_id, channel, recipient_user_id, recipient_address,
                template_key, lang, payload, attachment_path, dedupe_key, status, attempts,
                max_attempts, next_attempt_at, created_at, updated_at
            ) VALUES (
                :et, :entype, :enid, :channel, :uid, :address,
                :tmpl, :lang, :payload, :att, :dedupe, "pending", 0,
                6, :now, :now, :now
            )'
        );

        foreach ($recipients as $recipient) {
            $userId = isset($recipient['user_id']) ? (int) $recipient['user_id'] : null;
            $contactId = isset($recipient['contact_id']) ? (int) $recipient['contact_id'] : null;

            if ($userId !== null && $userId > 0) {
                // Fetch user info for preferences, email and phone
                $uStmt = $pdo->prepare('SELECT id, tipo, nickname, notify_email, phone_e164, whatsapp_opt_in, lang FROM tec_usuarios WHERE id = :uid');
                $uStmt->execute([':uid' => $userId]);
                $user = $uStmt->fetch(\PDO::FETCH_ASSOC);
                if (!$user) {
                    continue;
                }

                $userLang = in_array($user['lang'] ?? 'en', ['es', 'en'], true) ? $user['lang'] : $lang;
                $channelsEnabled = $this->resolveUserChannels($pdo, $userId, (string) $user['tipo'], $eventType);
                $forced = $data['force_channel'] ?? null;
                if ($forced === 'email' || $forced === 'whatsapp') {
                    $channelsEnabled = ['inapp' => false, 'email' => $forced === 'email', 'whatsapp' => $forced === 'whatsapp'];
                }
                if (!empty($data['always_email'])) {
                    $channelsEnabled['email'] = true;
                }

                if (!empty($channelsEnabled['inapp'])) {
                    $inappStmt->execute([
                        ':uid' => $userId,
                        ':et' => $eventType,
                        ':title' => mb_substr($title, 0, 190),
                        ':body' => mb_substr($body, 0, 1000),
                        ':link' => $linkUrl !== null ? mb_substr($linkUrl, 0, 255) : null,
                        ':entype' => $entityType,
                        ':enid' => $entityId,
                        ':now' => $nowStr,
                    ]);
                }

                $emailAddr = !empty($user['notify_email']) ? (string) $user['notify_email'] : (!empty($user['nickname']) && str_contains($user['nickname'], '@') ? (string) $user['nickname'] : null);
                if (!empty($channelsEnabled['email']) && !empty($emailAddr)) {
                    $dedupeKey = sprintf('%s:%s:%d:u%d:email:%s', $eventType, $entityType, $entityId, $userId, $bucket);
                    $outboxStmt->execute([
                        ':et' => $eventType,
                        ':entype' => $entityType,
                        ':enid' => $entityId,
                        ':channel' => 'email',
                        ':uid' => $userId,
                        ':address' => $emailAddr,
                        ':tmpl' => $templateKey,
                        ':lang' => $userLang,
                        ':payload' => $payloadJson,
                        ':att' => $attachmentPath,
                        ':dedupe' => $dedupeKey,
                        ':now' => $nowStr,
                    ]);
                }

                if (!empty($channelsEnabled['whatsapp']) && !empty($user['phone_e164']) && (int) ($user['whatsapp_opt_in'] ?? 0) === 1) {
                    $phone = (string) $user['phone_e164'];
                    $dedupeKey = sprintf('%s:%s:%d:u%d:wa:%s', $eventType, $entityType, $entityId, $userId, $bucket);
                    $outboxStmt->execute([
                        ':et' => $eventType,
                        ':entype' => $entityType,
                        ':enid' => $entityId,
                        ':channel' => 'whatsapp',
                        ':uid' => $userId,
                        ':address' => $phone,
                        ':tmpl' => $templateKey,
                        ':lang' => $userLang,
                        ':payload' => $payloadJson,
                        ':att' => $attachmentPath,
                        ':dedupe' => $dedupeKey,
                        ':now' => $nowStr,
                    ]);
                }
            } elseif ($contactId !== null && $contactId > 0) {
                // Fetch unit contact
                $cStmt = $pdo->prepare('SELECT id, name, email, phone_e164, receives_notifications FROM tec_unit_contacts WHERE id = :cid');
                $cStmt->execute([':cid' => $contactId]);
                $contact = $cStmt->fetch(\PDO::FETCH_ASSOC);

                if ($contact && (int) $contact['receives_notifications'] === 1) {
                    if (!empty($contact['email'])) {
                        $dedupeKey = sprintf('%s:%s:%d:c%d:email:%s', $eventType, $entityType, $entityId, $contactId, $bucket);
                        $outboxStmt->execute([
                            ':et' => $eventType,
                            ':entype' => $entityType,
                            ':enid' => $entityId,
                            ':channel' => 'email',
                            ':uid' => null,
                            ':address' => (string) $contact['email'],
                            ':tmpl' => $templateKey,
                            ':lang' => $lang,
                            ':payload' => $payloadJson,
                            ':att' => $attachmentPath,
                            ':dedupe' => $dedupeKey,
                            ':now' => $nowStr,
                        ]);
                    }

                    if (!empty($contact['phone_e164'])) {
                        $dedupeKey = sprintf('%s:%s:%d:c%d:wa:%s', $eventType, $entityType, $entityId, $contactId, $bucket);
                        $outboxStmt->execute([
                            ':et' => $eventType,
                            ':entype' => $entityType,
                            ':enid' => $entityId,
                            ':channel' => 'whatsapp',
                            ':uid' => null,
                            ':address' => (string) $contact['phone_e164'],
                            ':tmpl' => $templateKey,
                            ':lang' => $lang,
                            ':payload' => $payloadJson,
                            ':att' => $attachmentPath,
                            ':dedupe' => $dedupeKey,
                            ':now' => $nowStr,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Resolves active channels for a user and event by looking at tec_notification_prefs,
     * falling back to tec_notification_type_defaults.
     *
     * @return array{inapp:bool, email:bool, whatsapp:bool}
     */
    public function resolveUserChannels(\PDO $pdo, int $userId, string $userType, string $eventType): array
    {
        $prefsStmt = $pdo->prepare('SELECT channel, enabled FROM tec_notification_prefs WHERE user_id = :uid AND event_type = :et');
        $prefsStmt->execute([':uid' => $userId, ':et' => $eventType]);
        $customRows = $prefsStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $defaultsStmt = $pdo->prepare('SELECT channel, enabled FROM tec_notification_type_defaults WHERE user_type = :ut AND event_type = :et');
        $defaultsStmt->execute([':ut' => $userType, ':et' => $eventType]);
        $defaultRows = $defaultsStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $res = [];
        foreach (self::CHANNELS as $ch) {
            if (isset($customRows[$ch])) {
                $res[$ch] = ((int) $customRows[$ch]) === 1;
            } elseif (isset($defaultRows[$ch])) {
                $res[$ch] = ((int) $defaultRows[$ch]) === 1;
            } else {
                $res[$ch] = $ch === 'inapp'; // Fallback default
            }
        }

        return $res;
    }

    /**
     * Attempts immediate delivery of outbox IDs if configured, or leaves them pending for cron.
     *
     * @param list<int> $outboxIds
     */
    public function dispatchNow(array $outboxIds): void
    {
        // Standalone dispatch runner for cron or post-commit immediate delivery
        if (empty($outboxIds)) {
            return;
        }

        // Channels like inapp are processed directly; email/whatsapp are dispatched via OutboxWorker
    }

    public function unreadCount(Principal $p): int
    {
        if ($p->userId === null) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tec_notifications WHERE user_id = :uid AND read_at IS NULL');
        $stmt->execute([':uid' => $p->userId]);
        return (int) $stmt->fetchColumn();
    }

    public function list(Principal $p, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        if ($p->userId === null) {
            return ['items' => [], 'total' => 0, 'unread_count' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];
        }

        $where = ['user_id = :uid'];
        $params = [':uid' => $p->userId];

        if (!empty($filters['unread_only'])) {
            $where[] = 'read_at IS NULL';
        }

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :et';
            $params[':et'] = (string) $filters['event_type'];
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_notifications WHERE {$whereSql}");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT id, event_type, title, body, link_url, entity_type, entity_id, read_at, created_at
                FROM tec_notifications
                WHERE {$whereSql}
                ORDER BY created_at DESC, id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $unreadCount = $this->unreadCount($p);

        return [
            'items' => $items,
            'total' => $total,
            'unread_count' => $unreadCount,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    public function markRead(Principal $p, int $notificationId): void
    {
        if ($p->userId === null) {
            throw new ForbiddenException('User session required.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE tec_notifications SET read_at = :now WHERE id = :id AND user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute([
            ':now' => $now,
            ':id' => $notificationId,
            ':uid' => $p->userId,
        ]);
    }

    public function markAllRead(Principal $p): int
    {
        if ($p->userId === null) {
            throw new ForbiddenException('User session required.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE tec_notifications SET read_at = :now WHERE user_id = :uid AND read_at IS NULL'
        );
        $stmt->execute([
            ':now' => $now,
            ':uid' => $p->userId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Gets user preferences matrix merged with user type defaults.
     *
     * @return array<string, array<string, bool>> eventType => [channel => bool]
     */
    public function getPreferences(Principal $p): array
    {
        if ($p->userId === null) {
            throw new ForbiddenException('User session required.');
        }

        // Fetch user custom preferences
        $pStmt = $this->pdo->prepare('SELECT event_type, channel, enabled FROM tec_notification_prefs WHERE user_id = :uid');
        $pStmt->execute([':uid' => $p->userId]);
        $custom = [];
        while ($row = $pStmt->fetch(\PDO::FETCH_ASSOC)) {
            $custom[$row['event_type']][$row['channel']] = ((int) $row['enabled']) === 1;
        }

        // Fetch type defaults
        $dStmt = $this->pdo->prepare('SELECT event_type, channel, enabled FROM tec_notification_type_defaults WHERE user_type = :ut');
        $dStmt->execute([':ut' => $p->userType]);
        $defaults = [];
        while ($row = $dStmt->fetch(\PDO::FETCH_ASSOC)) {
            $defaults[$row['event_type']][$row['channel']] = ((int) $row['enabled']) === 1;
        }

        $matrix = [];
        foreach (self::ALL_EVENTS as $evt) {
            $matrix[$evt] = [];
            foreach (self::CHANNELS as $ch) {
                if (isset($custom[$evt][$ch])) {
                    $matrix[$evt][$ch] = $custom[$evt][$ch];
                } elseif (isset($defaults[$evt][$ch])) {
                    $matrix[$evt][$ch] = $defaults[$evt][$ch];
                } else {
                    $matrix[$evt][$ch] = $ch === 'inapp';
                }
            }
        }

        return $matrix;
    }

    /**
     * Saves user preferences matrix.
     *
     * @param array<string, array<string, bool|int>> $prefs
     */
    public function savePreferences(Principal $p, array $prefs): void
    {
        if ($p->userId === null) {
            throw new ForbiddenException('User session required.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tec_notification_prefs (user_id, event_type, channel, enabled, updated_at)
             VALUES (:uid, :et, :ch, :en, :now)
             ON DUPLICATE KEY UPDATE enabled = :en2, updated_at = :now2'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($prefs as $eventType => $channels) {
                if (!in_array($eventType, self::ALL_EVENTS, true) || !is_array($channels)) {
                    continue;
                }
                foreach ($channels as $channel => $val) {
                    if (!in_array($channel, self::CHANNELS, true)) {
                        continue;
                    }
                    $enabled = !empty($val) ? 1 : 0;
                    $stmt->execute([
                        ':uid' => $p->userId,
                        ':et' => $eventType,
                        ':ch' => $channel,
                        ':en' => $enabled,
                        ':now' => $now,
                        ':en2' => $enabled,
                        ':now2' => $now,
                    ]);
                }
            }

            AuditLogger::log($p, 'notification_prefs.updated', 'user', $p->userId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieves global defaults matrix across all user types.
     * Requires notificaciones.prefs.manage.
     *
     * @return array<string, array<string, array<string, bool>>> userType => [eventType => [channel => bool]]
     */
    public function getDefaults(Principal $p): array
    {
        if (!$p->isSystemAdmin() && !$p->can('notificaciones.prefs.manage')) {
            throw new ForbiddenException('Cannot manage notification defaults.');
        }

        $stmt = $this->pdo->query('SELECT user_type, event_type, channel, enabled FROM tec_notification_type_defaults ORDER BY user_type, event_type, channel');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $types = ['SuperAdministrador', 'Administrador', 'Staff'];
        $matrix = [];
        foreach ($types as $ut) {
            $matrix[$ut] = [];
            foreach (self::ALL_EVENTS as $evt) {
                $matrix[$ut][$evt] = [];
                foreach (self::CHANNELS as $ch) {
                    $matrix[$ut][$evt][$ch] = false;
                }
            }
        }

        foreach ($rows as $r) {
            $ut = $r['user_type'];
            $et = $r['event_type'];
            $ch = $r['channel'];
            if (isset($matrix[$ut][$et][$ch])) {
                $matrix[$ut][$et][$ch] = ((int) $r['enabled']) === 1;
            }
        }

        return $matrix;
    }

    /**
     * Saves global defaults matrix.
     * Requires notificaciones.prefs.manage.
     *
     * @param array<string, array<string, array<string, bool|int>>> $matrix
     */
    public function saveDefaults(Principal $p, array $matrix): void
    {
        if (!$p->isSystemAdmin() && !$p->can('notificaciones.prefs.manage')) {
            throw new ForbiddenException('Cannot manage notification defaults.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tec_notification_type_defaults (user_type, event_type, channel, enabled)
             VALUES (:ut, :et, :ch, :en)
             ON DUPLICATE KEY UPDATE enabled = :en2'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($matrix as $userType => $events) {
                if (!is_array($events)) {
                    continue;
                }
                foreach ($events as $eventType => $channels) {
                    if (!in_array($eventType, self::ALL_EVENTS, true) || !is_array($channels)) {
                        continue;
                    }
                    foreach ($channels as $channel => $val) {
                        if (!in_array($channel, self::CHANNELS, true)) {
                            continue;
                        }
                        $enabled = !empty($val) ? 1 : 0;
                        $stmt->execute([
                            ':ut' => $userType,
                            ':et' => $eventType,
                            ':ch' => $channel,
                            ':en' => $enabled,
                            ':en2' => $enabled,
                        ]);
                    }
                }
            }

            AuditLogger::log($p, 'notification_defaults.updated', 'system', null);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
