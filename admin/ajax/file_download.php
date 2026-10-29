<?php

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require_once __DIR__ . '/../include/generic_classes.php';
require_once __DIR__ . '/../bootstrap_pgs.php';

$p = \Pgs\Core\Web\PgsPage::principalOrNull();
if ($p === null) {
    http_response_code(401);
    echo 'Session expired.';
    exit;
}

$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

try {
    if ($type === 'task_attachment' && $id > 0) {
        (new \Pgs\Tasks\TaskAttachmentService())->stream($p, $id);
        exit;
    }

    if ($type === 'expense_receipt' && $id > 0) {
        (new \Pgs\Expenses\ExpenseService())->streamReceipt($p, $id);
        exit;
    }

    if ($type === 'invoice' && $id > 0) {
        (new \Pgs\Invoices\InvoiceService())->streamPdf($p, $id);
        exit;
    }

    if ($type === 'report') {
        $key = (string) ($_GET['key'] ?? '');
        $unitId = (int) ($_GET['unit_id'] ?? ($_GET['id'] ?? 0));
        $from = (string) ($_GET['from'] ?? '');
        $to = (string) ($_GET['to'] ?? '');
        (new \Pgs\Reports\ReportDeliveryService())->stream($p, $key, $unitId, $from, $to);
        exit;
    }

    http_response_code(400);
    echo 'Invalid download request.';
} catch (\Pgs\Core\Exceptions\PgsException $e) {
    http_response_code($e->httpStatus());
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} catch (\Throwable $e) {
    error_log('[file_download] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The file could not be delivered.';
}
