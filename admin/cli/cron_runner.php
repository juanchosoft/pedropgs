<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Integrations\SyncWorker;
use Pgs\Notifications\NotificationService;
use Pgs\Notifications\OutboxWorker;

$start = microtime(true);
$pdo = Db::pdo();
$now = Clock::nowUtcSql();

// 1. Process Notification Outbox
$outboxStats = OutboxWorker::run(50, $pdo);

// 2. Process Google Sync Outbox
$syncStats = SyncWorker::run(50, $pdo);

// 3. Check Overdue Invoices (INV-03)
$overdueCount = 0;
try {
    $selStmt = $pdo->query(
        "SELECT id, invoice_number, unidad_id, amount, due_date 
         FROM tec_invoices 
         WHERE payment_status = 'pending' AND due_date < CURDATE()"
    );
    $overdueInvoices = $selStmt->fetchAll(\PDO::FETCH_ASSOC);

    if (!empty($overdueInvoices)) {
        $updStmt = $pdo->prepare(
            "UPDATE tec_invoices 
             SET payment_status = 'overdue', updated_at = NOW() 
             WHERE id = :id AND payment_status = 'pending'"
        );
        $notif = new NotificationService($pdo);

        foreach ($overdueInvoices as $inv) {
            $updStmt->execute([':id' => $inv['id']]);
            if ($updStmt->rowCount() > 0) {
                $overdueCount++;

                // Emit invoice.overdue event
                $recipients = [];
                // Find unit contacts that receive notifications
                $cStmt = $pdo->prepare(
                    'SELECT id, email, phone FROM tec_unit_contacts 
                     WHERE unit_id = :uid AND is_active = 1 AND receives_notifications = 1'
                );
                $cStmt->execute([':uid' => $inv['unidad_id']]);
                while ($c = $cStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $recipients[] = ['contact_id' => (int) $c['id']];
                }

                $notif->emit(
                    $pdo,
                    'invoice.overdue',
                    'invoice',
                    (int) $inv['id'],
                    $recipients,
                    [
                        'title' => 'Invoice Overdue',
                        'body' => "Invoice #{$inv['invoice_number']} is past due since {$inv['due_date']}.",
                        'payload' => [
                            'invoice_number' => $inv['invoice_number'],
                            'total_formatted' => '$' . number_format((float) $inv['amount'], 2),
                            'due_date' => $inv['due_date'],
                        ],
                    ]
                );
            }
        }
    }
} catch (\Throwable $e) {
    echo "Warning: Error processing overdue invoices: " . $e->getMessage() . "\n";
}

// 4. Clean expired signed links
$deletedLinks = 0;
try {
    $delStmt = $pdo->prepare('DELETE FROM tec_signed_links WHERE expires_at < NOW()');
    $delStmt->execute();
    $deletedLinks = $delStmt->rowCount();
} catch (\Throwable) {
    // Non-critical cleanup
}

$elapsed = round((microtime(true) - $start) * 1000, 2);

$report = [
    'timestamp' => $now,
    'elapsed_ms' => $elapsed,
    'outbox' => $outboxStats,
    'sync' => $syncStats,
    'invoices_marked_overdue' => $overdueCount,
    'expired_links_purged' => $deletedLinks,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
