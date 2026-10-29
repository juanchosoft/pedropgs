<?php

declare(strict_types=1);

namespace Pgs\Integrations;

use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Phone;
use Pgs\Notifications\NotificationService;

final class IntegrationStatusService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Returns operational status for WhatsApp, Google, and outboxes.
     *
     * @return array<string, mixed>
     */
    public function status(Principal $p): array
    {
        $p->requireAny(['integraciones.sync.manage', 'integraciones.whatsapp.manage']);

        // WhatsApp config & outbox counts
        $waEnabled = (bool) Config::get('whatsapp', 'enabled', false);
        $phoneId = (string) Config::get('whatsapp', 'phone_number_id', '');
        $phoneIdMasked = $phoneId !== '' ? '***' . substr($phoneId, -4) : 'Not configured';

        $waCountsStmt = $this->pdo->query(
            "SELECT status, COUNT(*) as cnt
             FROM tec_notification_outbox
             WHERE channel = 'whatsapp'
             GROUP BY status"
        );
        $waCounts = [];
        while ($row = $waCountsStmt->fetch(\PDO::FETCH_ASSOC)) {
            $waCounts[(string) $row['status']] = (int) $row['cnt'];
        }

        // Google config & links counts
        $gEnabled = (bool) Config::get('google', 'enabled', false);
        $serviceAccount = (string) Config::get('google', 'service_account_email', '');
        $saParts = explode('@', $serviceAccount);
        $saMasked = count($saParts) === 2 ? substr($saParts[0], 0, 3) . '***@' . $saParts[1] : 'Not configured';

        $gSheetsCount = (int) $this->pdo->query("SELECT COUNT(*) FROM tec_sync_links WHERE target = 'sheets'")->fetchColumn();
        $gCalendarCount = (int) $this->pdo->query("SELECT COUNT(*) FROM tec_sync_links WHERE target = 'calendar'")->fetchColumn();

        // Notification Outbox total breakdown
        $ntfOutboxStmt = $this->pdo->query('SELECT status, COUNT(*) as cnt FROM tec_notification_outbox GROUP BY status');
        $ntfOutboxCounts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0];
        while ($r = $ntfOutboxStmt->fetch(\PDO::FETCH_ASSOC)) {
            $ntfOutboxCounts[(string) $r['status']] = (int) $r['cnt'];
        }

        // Sync Outbox breakdown
        $syncOutboxStmt = $this->pdo->query('SELECT status, COUNT(*) as cnt FROM tec_sync_outbox GROUP BY status');
        $syncOutboxCounts = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'dead' => 0];
        while ($r = $syncOutboxStmt->fetch(\PDO::FETCH_ASSOC)) {
            $syncOutboxCounts[(string) $r['status']] = (int) $r['cnt'];
        }

        return [
            'whatsapp' => [
                'enabled' => $waEnabled,
                'phone_number_id_masked' => $phoneIdMasked,
                'pending' => $waCounts['pending'] ?? 0,
                'failed' => $waCounts['failed'] ?? 0,
                'dead' => $waCounts['dead'] ?? 0,
            ],
            'google' => [
                'enabled' => $gEnabled,
                'service_account_masked' => $saMasked,
                'sheets_synced_records' => $gSheetsCount,
                'calendar_synced_records' => $gCalendarCount,
            ],
            'notification_outbox' => $ntfOutboxCounts,
            'sync_outbox' => $syncOutboxCounts,
        ];
    }

    /**
     * Lists failed and dead items from either notification or sync outbox.
     *
     * @param string $table 'notification' | 'sync'
     * @return list<array<string, mixed>>
     */
    public function outboxList(Principal $p, string $table = 'notification', string $statusFilter = 'all'): array
    {
        $p->requireAny(['integraciones.sync.manage', 'integraciones.whatsapp.manage']);

        $tblName = $table === 'sync' ? 'tec_sync_outbox' : 'tec_notification_outbox';
        $params = [];
        $where = [];

        if ($statusFilter === 'failed') {
            $where[] = "status = 'failed'";
        } elseif ($statusFilter === 'dead') {
            $where[] = "status = 'dead'";
        } else {
            $where[] = "status IN ('failed', 'dead')";
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM {$tblName} {$whereSql} ORDER BY id DESC LIMIT 100";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retries a single outbox row.
     */
    public function retry(Principal $p, string $table, int $id): void
    {
        $p->requireAny(['integraciones.sync.manage', 'integraciones.whatsapp.manage']);

        $tblName = $table === 'sync' ? 'tec_sync_outbox' : 'tec_notification_outbox';
        $now = Clock::nowUtcSql();

        $stmt = $this->pdo->prepare(
            "UPDATE {$tblName}
             SET status = 'pending',
                 attempts = 0,
                 next_attempt_at = :now,
                 last_error = NULL
             WHERE id = :id"
        );
        $stmt->execute([':now' => $now, ':id' => $id]);

        AuditLogger::log($p, 'integration.outbox_retry', 'outbox', $id, [
            'table' => $table,
        ]);
    }

    /**
     * Retries all dead outbox rows.
     */
    public function retryAllDead(Principal $p, string $table): int
    {
        $p->requireAny(['integraciones.sync.manage', 'integraciones.whatsapp.manage']);

        $tblName = $table === 'sync' ? 'tec_sync_outbox' : 'tec_notification_outbox';
        $now = Clock::nowUtcSql();

        $stmt = $this->pdo->prepare(
            "UPDATE {$tblName}
             SET status = 'pending',
                 attempts = 0,
                 next_attempt_at = :now,
                 last_error = NULL
             WHERE status = 'dead'"
        );
        $stmt->execute([':now' => $now]);
        $count = $stmt->rowCount();

        AuditLogger::log($p, 'integration.outbox_retry_all_dead', 'outbox', null, [
            'table' => $table,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Enqueues entities to sync_outbox.
     */
    public function syncAll(Principal $p, bool $includeMigrated = false): array
    {
        $p->require('integraciones.sync.manage');

        $now = Clock::nowUtcSql();
        $enqueued = 0;

        // Enqueue tasks
        $taskSql = 'SELECT id FROM tec_tasks WHERE deleted_at IS NULL';
        if (!$includeMigrated) {
            $taskSql .= " AND origin != 'migration'";
        }
        $tasksStmt = $this->pdo->query($taskSql);
        while ($tId = $tasksStmt->fetchColumn()) {
            SyncEnqueuer::task($this->pdo, (int) $tId, true);
            $enqueued++;
        }

        // Enqueue expenses
        $expensesStmt = $this->pdo->query("SELECT id FROM tec_expenses WHERE billing_status != 'void'");
        while ($eId = $expensesStmt->fetchColumn()) {
            SyncEnqueuer::expense($this->pdo, (int) $eId);
            $enqueued++;
        }

        AuditLogger::log($p, 'integration.sync_all_enqueued', 'sync', null, [
            'enqueued_count' => $enqueued,
            'include_migrated' => $includeMigrated,
        ]);

        return ['enqueued' => $enqueued];
    }

    /**
     * Sends a test WhatsApp notification to the Principal's registered phone.
     */
    public function sendTestWhatsApp(Principal $p): array
    {
        $p->require('integraciones.whatsapp.manage');

        $phone = $p->phone();
        if (!$phone) {
            throw new ValidationException(['phone' => 'Your user profile does not have a registered phone number.']);
        }

        (new NotificationService($this->pdo))->emit(
            $this->pdo,
            'phone.message',
            'user',
            (int) $p->id(),
            [['user_id' => $p->id()]],
            [
                'title' => 'Test Notification',
                'body' => 'This is a test notification from PGS Centrum Integration settings.',
                'lang' => $p->language() ?? 'en',
            ]
        );

        AuditLogger::log($p, 'integration.whatsapp_test_sent', 'user', $p->id(), [
            'destination_masked' => Phone::mask($phone),
        ]);

        return [
            'sent' => true,
            'destination_masked' => Phone::mask($phone),
        ];
    }
}
