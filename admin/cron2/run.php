<?php


/**
 * Scheduled jobs.
 * Usage: php admin/cron/run.php tick [--at=2026-01-01T12:00:00Z]
 */

// if (PHP_SAPI !== 'cli') {
//     http_response_code(404);
//     exit;
// }

require_once dirname(__DIR__) . '/bootstrap_pgs.php';

use Pgs\Core\AppLog;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Notifications\OutboxWorker;
use Pgs\Requests\EscalationService;
use Pgs\Responders\SettingsRepository;

$job = $argv[1] ?? '';
if ($job !== 'tick') {
    fwrite(STDERR, "Usage: php run.php tick [--at=ISO8601]\n");
    exit(1);
}

$now = Clock::nowUtc();
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--at=')) {
        if (Config::env() !== 'testing') {
            fwrite(STDERR, "--at is only available in the testing environment\n");
            exit(1);
        }
        $parsed = date_create_immutable(substr($arg, 5), new DateTimeZone('UTC'));
        if ($parsed === false) {
            fwrite(STDERR, "Invalid --at value\n");
            exit(1);
        }
        $now = $parsed->setTimezone(new DateTimeZone('UTC'));
    }
}

$pdo = Db::pdo();
$lock = $pdo->query("SELECT GET_LOCK('pgs_tick', 0)")->fetchColumn();
if ((int) $lock !== 1) {
    echo json_encode(['skipped' => 'another tick is running']) . "\n";
    exit(0);
}

try {
    $settings = new SettingsRepository($pdo);
    $settings->set('cron.last_tick', $now->format('Y-m-d H:i:s'));

    $expired = $pdo->prepare("UPDATE tec_calls SET state = 'expired' WHERE state = 'active' AND started_at < :cut");
    $expired->execute([':cut' => $now->modify('-2 hours')->format('Y-m-d H:i:s')]);

    $summary = [
        'at' => $now->format('Y-m-d\TH:i:s\Z'),
        'calls_expired' => $expired->rowCount(),
        'escalation' => (new EscalationService($pdo))->tick($now),
        'outbox' => (new OutboxWorker($pdo))->process(50, $now),
    ];

    $local = $now->setTimezone(Clock::businessTz());
    if ($local->format('G') === '3' && $settings->get('retention.last_run', '') !== $local->format('Y-m-d')) {
        $pdo->prepare('DELETE FROM tec_calls WHERE started_at < :cut')->execute([':cut' => $now->modify('-180 days')->format('Y-m-d H:i:s')]);
        $pdo->prepare("DELETE FROM tec_outbox WHERE status IN ('sent','failed','skipped') AND created_at < :cut")->execute([':cut' => $now->modify('-30 days')->format('Y-m-d H:i:s')]);
        $pdo->prepare('DELETE FROM tec_signed_links WHERE expires_at < :cut')->execute([':cut' => $now->modify('-7 days')->format('Y-m-d H:i:s')]);
        $pdo->prepare('DELETE FROM tec_rate_limits WHERE window_start < :cut')->execute([':cut' => $now->modify('-2 days')->format('Y-m-d H:i:s')]);
        $settings->set('retention.last_run', $local->format('Y-m-d'));
        $summary['retention'] = 'done';
    }

    echo json_encode($summary, JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    AppLog::error('cron', $e);
    fwrite(STDERR, 'tick failed: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('pgs_tick')");
}
