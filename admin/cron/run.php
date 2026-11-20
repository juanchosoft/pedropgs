<?php

declare(strict_types=1);

/**
 * Scheduled jobs.
 * Usage: php admin/cron/run.php tick [--at=2026-01-01T12:00:00Z]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap_pgs.php';

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Cron\TickJob;

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

try {
    $summary = TickJob::run($now);
    echo json_encode($summary ?? ['skipped' => 'another tick is running'], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'tick failed: ' . $e->getMessage() . "\n");
    exit(1);
}
