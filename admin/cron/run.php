<?php

declare(strict_types=1);

/**
 * PGS Centrum - Cron Job Runner
 *
 * Usage:
 *   php admin/cron/run.php <job>
 *
 * Supported jobs:
 *   outbox               - Process notification outbox (WhatsApp, Email, In-app)
 *   sync                 - Process Google Sheets/Calendar sync queue
 *   calls:expire         - Expire overdue voice call sessions and pending actions
 *   tasks:reminders      - Check tasks due soon and emit notifications
 *   invoices:overdue     - Mark overdue invoices and emit notifications
 *   units:contacts-sync  - Re-sync tbl_unidades contacts into tec_unit_contacts
 *   retention            - Purge expired idempotency, rate limits, signed links, report PDFs & voice data
 *   ia:attachments:cleanup - Cleanup expired/orphan AI attachments
 *   all                  - Run all cron jobs in sequence
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI execution only.\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\PrivateStorage;
use Pgs\Invoices\InvoiceService;
use Pgs\Notifications\NotificationService;
use Pgs\Notifications\OutboxWorker;
use Pgs\Integrations\SyncWorker;
use Pgs\Tasks\TaskService;
use Pgs\Units\UnitContactSync;

$jobsAvailable = [
    'outbox' => 'Process notification outbox (1-5 min)',
    'sync' => 'Process Google Sheets/Calendar sync queue (5 min)',
    'calls:expire' => 'Expire overdue voice call sessions and pending actions (5 min)',
    'tasks:reminders' => 'Send due-soon reminders for open tasks (60 min)',
    'invoices:overdue' => 'Mark overdue invoices and notify (daily 06:00)',
    'units:contacts-sync' => 'Re-sync tbl_unidades contacts into tec_unit_contacts (daily 03:15)',
    'retention' => 'Purge expired logs, links, PDFs and voice data (daily 03:30)',
    'ia:attachments:cleanup' => 'Cleanup expired AI chat attachments (daily 03:45)',
];

$requestedJob = $argv[1] ?? 'help';

if ($requestedJob === 'help' || $requestedJob === '--help' || $requestedJob === '-h') {
    echo "PGS Centrum Cron Runner\n";
    echo "Usage: php admin/cron/run.php <job>\n\n";
    echo "Available jobs:\n";
    foreach ($jobsAvailable as $name => $desc) {
        printf("  %-22s %s\n", $name, $desc);
    }
    printf("  %-22s %s\n", 'all', 'Run all available jobs sequentially');
    exit(0);
}

function cronLog(string $job, string $message, string $level = 'INFO'): void
{
    $now = Clock::nowUtc();
    $yearMonth = $now->format('Y-m');
    $logDir = PrivateStorage::path('logs');
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    $logFile = $logDir . DIRECTORY_SEPARATOR . "cron-{$yearMonth}.log";
    $timestamp = $now->format('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] [{$job}] {$message}\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

$pdo = Db::pdo();

/**
 * Executes a single job protected by MySQL GET_LOCK.
 */
function executeJob(\PDO $pdo, string $job): int
{
    $cleanLock = 'pgs_cron_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $job);

    $lockStmt = $pdo->prepare("SELECT GET_LOCK(:name, 0)");
    $lockStmt->execute([':name' => $cleanLock]);
    $acquired = (int) $lockStmt->fetchColumn();

    if ($acquired !== 1) {
        cronLog($job, "Could not acquire lock '{$cleanLock}', job already running. Skipping.", 'WARN');
        return 0;
    }

    try {
        cronLog($job, "Started job execution.");
        $start = microtime(true);

        switch ($job) {
            case 'outbox':
                $stats = OutboxWorker::run(50, $pdo);
                cronLog($job, sprintf(
                    "Outbox completed: %d processed, %d sent, %d failed, %d dead.",
                    $stats['processed'],
                    $stats['sent'],
                    $stats['failed'],
                    $stats['dead']
                ));
                break;

            case 'sync':
                $stats = SyncWorker::run(50, $pdo);
                if ($stats['skipped']) {
                    cronLog($job, "Sync skipped: [google] enabled is 0.");
                } else {
                    cronLog($job, sprintf(
                        "Sync completed: %d processed, %d synced, %d failed, %d dead.",
                        $stats['processed'],
                        $stats['synced'],
                        $stats['failed'],
                        $stats['dead']
                    ));
                }
                break;

            case 'calls:expire':
                $now = Clock::nowUtcSql();
                // 1. Expire stale call sessions
                $stmtCalls = $pdo->prepare(
                    "UPDATE tec_call_sessions
                     SET status = 'expired',
                         ended_at = COALESCE(ended_at, :now)
                     WHERE status = 'active'
                       AND expires_at IS NOT NULL
                       AND expires_at < :now2"
                );
                $stmtCalls->execute([':now' => $now, ':now2' => $now]);
                $expiredCalls = $stmtCalls->rowCount();

                // 2. Expire pending actions if table exists
                $expiredActions = 0;
                $chkActions = $pdo->query("SHOW TABLES LIKE 'tec_pending_actions'")->fetchColumn();
                if ($chkActions) {
                    $stmtAct = $pdo->prepare(
                        "UPDATE tec_pending_actions
                         SET status = 'expired'
                         WHERE status = 'pending'
                           AND expires_at IS NOT NULL
                           AND expires_at < :now"
                    );
                    $stmtAct->execute([':now' => $now]);
                    $expiredActions = $stmtAct->rowCount();
                }

                cronLog($job, sprintf(
                    "Calls & actions expired: %d call sessions, %d pending actions.",
                    $expiredCalls,
                    $expiredActions
                ));
                break;

            case 'tasks:reminders':
                $taskSvc = new TaskService($pdo);
                $notifSvc = new NotificationService($pdo);
                $hours = (int) Config::get('notifications', 'due_soon_hours', 24);
                $nowUtc = Clock::nowUtc();
                $dueTasks = $taskSvc->dueSoon($nowUtc, $hours);
                $remindedCount = 0;

                $updateTaskStmt = $pdo->prepare(
                    "UPDATE tec_tasks SET due_soon_notified_at = NOW() WHERE id = :id"
                );

                foreach ($dueTasks as $t) {
                    $taskId = (int) $t['id'];
                    $recipients = [];

                    if (!empty($t['assigned_employee_id'])) {
                        $uStmt = $pdo->prepare(
                            "SELECT id FROM tec_usuarios
                             WHERE (employee_pk = :emp OR employee_id = :emp2)
                               AND habilitado = 'si' LIMIT 1"
                        );
                        $uStmt->execute([':emp' => $t['assigned_employee_id'], ':emp2' => $t['assigned_employee_id']]);
                        $assignedUid = $uStmt->fetchColumn();
                        if ($assignedUid) {
                            $recipients[] = ['user_id' => (int) $assignedUid];
                        }
                    }

                    // Also notify unit admin
                    $adminStmt = $pdo->prepare(
                        "SELECT id FROM tec_usuarios
                         WHERE tbl_unidad_id = :uid AND tipo = 'Administrador' AND habilitado = 'si' LIMIT 1"
                    );
                    $adminStmt->execute([':uid' => $t['unidad_id']]);
                    $adminUid = $adminStmt->fetchColumn();
                    if ($adminUid) {
                        $recipients[] = ['user_id' => (int) $adminUid];
                    }

                    if (!empty($recipients)) {
                        $notifSvc->emit($pdo, 'task.due_soon', 'task', $taskId, $recipients, [
                            'title' => "Task #{$t['code']} due soon",
                            'body' => "Task '{$t['title']}' is due on {$t['due_date']}",
                            'link_url' => "tasks.php?id={$taskId}",
                            'payload' => ['task_id' => $taskId, 'code' => $t['code']],
                        ]);
                    }

                    $updateTaskStmt->execute([':id' => $taskId]);
                    $remindedCount++;
                }

                cronLog($job, sprintf("Task reminders sent: %d tasks notified.", $remindedCount));
                break;

            case 'invoices:overdue':
                $invoiceSvc = new InvoiceService($pdo);
                $notifSvc = new NotificationService($pdo);
                $today = Clock::nowUtc();
                $marked = $invoiceSvc->markOverdue($today);

                // Send notification for overdue invoices that haven't been notified yet
                $stmtOverdue = $pdo->query(
                    "SELECT i.id, i.invoice_number, i.unidad_id, i.amount, i.currency
                     FROM tec_invoices i
                     WHERE i.payment_status = 'overdue'
                       AND NOT EXISTS (
                           SELECT 1 FROM tec_notification_outbox o
                           WHERE o.event_type = 'invoice.overdue'
                             AND o.entity_type = 'invoice'
                             AND o.entity_id = i.id
                       )
                     LIMIT 20"
                );
                $newlyOverdue = $stmtOverdue->fetchAll(\PDO::FETCH_ASSOC);
                $notifiedInvoices = 0;

                foreach ($newlyOverdue as $inv) {
                    $invId = (int) $inv['id'];
                    $adminStmt = $pdo->prepare(
                        "SELECT id FROM tec_usuarios
                         WHERE tbl_unidad_id = :uid AND tipo = 'Administrador' AND habilitado = 'si' LIMIT 1"
                    );
                    $adminStmt->execute([':uid' => $inv['unidad_id']]);
                    $adminUid = $adminStmt->fetchColumn();

                    $recipients = [];
                    if ($adminUid) {
                        $recipients[] = ['user_id' => (int) $adminUid];
                    }

                    $notifSvc->emit($pdo, 'invoice.overdue', 'invoice', $invId, $recipients, [
                        'title' => "Invoice #{$inv['invoice_number']} overdue",
                        'body' => "Invoice #{$inv['invoice_number']} ({$inv['currency']} {$inv['total']}) is past its due date.",
                        'link_url' => "facturas.php?id={$invId}",
                        'payload' => ['invoice_id' => $invId, 'invoice_number' => $inv['invoice_number']],
                    ]);
                    $notifiedInvoices++;
                }

                cronLog($job, sprintf(
                    "Invoices overdue: %d marked overdue, %d notifications queued.",
                    $marked,
                    $notifiedInvoices
                ));
                break;

            case 'units:contacts-sync':
                $count = UnitContactSync::syncAll($pdo);
                cronLog($job, sprintf("Unit contacts sync completed: %d units synchronized.", $count));
                break;

            case 'retention':
                $deletedIdem = $pdo->exec(
                    "DELETE FROM tec_idempotency_keys
                     WHERE expires_at < NOW()
                        OR created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
                );

                $deletedRate = $pdo->exec(
                    "DELETE FROM tec_rate_limits
                     WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 DAY)"
                );

                $deletedLinks = $pdo->exec(
                    "DELETE FROM tec_signed_links
                     WHERE expires_at < NOW()"
                );

                // Report PDFs older than 7 days
                $reportDir = PrivateStorage::path('reports');
                $deletedPdfs = 0;
                if (is_dir($reportDir)) {
                    $sevenDaysAgo = time() - (7 * 86400);
                    $files = scandir($reportDir);
                    if ($files !== false) {
                        foreach ($files as $file) {
                            if ($file === '.' || $file === '..' || !str_ends_with($file, '.pdf')) {
                                continue;
                            }
                            $filePath = $reportDir . DIRECTORY_SEPARATOR . $file;
                            if (is_file($filePath) && filemtime($filePath) < $sevenDaysAgo) {
                                if (@unlink($filePath)) {
                                    $deletedPdfs++;
                                }
                            }
                        }
                    }
                }

                // Voice retention: mask logs and anonymize caller sessions older than retention_days
                $voiceDays = (int) Config::get('voice', 'retention_days', 180);
                $stmtVoiceLogs = $pdo->prepare(
                    "UPDATE tec_call_logs
                     SET input_redacted = '[REDACTED_RETENTION]'
                     WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                       AND input_redacted != '[REDACTED_RETENTION]'"
                );
                $stmtVoiceLogs->execute([':days' => $voiceDays]);
                $redactedLogs = $stmtVoiceLogs->rowCount();

                $stmtVoiceSessions = $pdo->prepare(
                    "UPDATE tec_call_sessions
                     SET caller_e164 = '[ANONYMIZED]',
                         caller_raw = NULL,
                         summary = NULL
                     WHERE started_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                       AND (caller_e164 IS NULL OR caller_e164 != '[ANONYMIZED]' OR caller_raw IS NOT NULL OR summary IS NOT NULL)"
                );
                $stmtVoiceSessions->execute([':days' => $voiceDays]);
                $anonymizedSessions = $stmtVoiceSessions->rowCount();

                // Check dead outbox messages
                $deadCount = (int) $pdo->query(
                    "SELECT COUNT(*) FROM tec_notification_outbox WHERE status = 'dead'"
                )->fetchColumn();

                cronLog($job, sprintf(
                    "Retention completed: %d idempotency keys, %d rate limits, %d signed links, %d PDFs purged; %d voice logs redacted, %d voice sessions anonymized. Dead outbox messages currently: %d.",
                    (int) $deletedIdem,
                    (int) $deletedRate,
                    (int) $deletedLinks,
                    $deletedPdfs,
                    $redactedLogs,
                    $anonymizedSessions,
                    $deadCount
                ));
                break;

            case 'ia:attachments:cleanup':
                $cleanedCount = 0;
                $chkAdjuntos = $pdo->query("SHOW TABLES LIKE 'tec_ia_adjuntos'")->fetchColumn();
                if ($chkAdjuntos) {
                    $stmtAdj = $pdo->query(
                        "SELECT id, storage_path, derived_path
                         FROM tec_ia_adjuntos
                         WHERE deleted_at IS NULL
                           AND expires_at IS NOT NULL
                           AND expires_at < NOW()
                         LIMIT 100"
                    );
                    $expired = $stmtAdj->fetchAll(\PDO::FETCH_ASSOC);
                    $markDelStmt = $pdo->prepare(
                        "UPDATE tec_ia_adjuntos SET deleted_at = NOW() WHERE id = :id"
                    );

                    foreach ($expired as $item) {
                        $id = (int) $item['id'];
                        if (!empty($item['storage_path']) && is_file($item['storage_path'])) {
                            @unlink($item['storage_path']);
                        }
                        if (!empty($item['derived_path']) && is_file($item['derived_path'])) {
                            @unlink($item['derived_path']);
                        }
                        $markDelStmt->execute([':id' => $id]);
                        $cleanedCount++;
                    }
                }

                cronLog($job, sprintf("AI attachments cleanup completed: %d items purged.", $cleanedCount));
                break;

            default:
                cronLog($job, "Unknown job '{$job}'.", 'ERROR');
                return 1;
        }

        $duration = round(microtime(true) - $start, 3);
        cronLog($job, "Job completed in {$duration}s.");
        return 0;
    } catch (\Throwable $e) {
        cronLog($job, "Unhandled exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'ERROR');
        return 1;
    } finally {
        $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(:name)");
        $unlockStmt->execute([':name' => $cleanLock]);
    }
}

if ($requestedJob === 'all') {
    cronLog('all', "Executing all registered cron jobs...");
    $allExit = 0;
    foreach (array_keys($jobsAvailable) as $singleJob) {
        $code = executeJob($pdo, $singleJob);
        if ($code !== 0) {
            $allExit = $code;
        }
    }
    cronLog('all', "All cron jobs finished.");
    exit($allExit);
}

if (!array_key_exists($requestedJob, $jobsAvailable)) {
    cronLog($requestedJob, "Unknown job requested: {$requestedJob}", 'ERROR');
    exit(1);
}

$exitCode = executeJob($pdo, $requestedJob);
exit($exitCode);
