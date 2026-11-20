<?php

declare(strict_types=1);

/**
 * HTTP trigger for the periodic job, for hosts whose scheduler can only request URLs.
 * Requires [cron] web_token in config.ini, sent as the X-Cron-Token header or the token query parameter.
 */

require_once __DIR__ . '/admin/bootstrap_pgs.php';

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Cron\TickJob;

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

$expected = (string) Config::get('cron', 'web_token', '');
$given = (string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? ($_GET['token'] ?? ''));

if (strlen($expected) < 24 || !hash_equals($expected, $given)) {
    http_response_code(404);
    echo '{"ok":false}';
    exit;
}

try {
    $summary = TickJob::run(Clock::nowUtc());
    echo json_encode(['ok' => true] + ($summary ?? ['skipped' => 'another tick is running']), JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo '{"ok":false,"error":"tick_failed"}';
}
