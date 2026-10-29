<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

set_time_limit(120);

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Ai\Attachments\AttachmentService;
use Pgs\Auth\PrincipalFactory;

header('Content-Type: application/json; charset=UTF-8');

$p = PrincipalFactory::fromSession('web');
if ($p->isAnonymous()) {
    http_response_code(401);
    echo json_encode(['output' => ['valid' => false, 'error' => 'unauthorized', 'message' => 'Session expired or not authenticated.']]);
    exit;
}

if (!$p->can('ia.adjuntos.use')) {
    http_response_code(403);
    echo json_encode(['output' => ['valid' => false, 'error' => 'forbidden', 'message' => 'You do not have permission to upload AI attachments.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['output' => ['valid' => false, 'message' => 'Method not allowed.']]);
    exit;
}

$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (empty($csrfSession) || !hash_equals((string) $csrfSession, (string) $csrfSent)) {
    http_response_code(403);
    echo json_encode(['output' => ['valid' => false, 'error' => 'csrf_invalid', 'message' => 'Invalid or missing CSRF token.']]);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['output' => ['valid' => false, 'message' => 'No file uploaded or upload error occurred.']]);
    exit;
}

try {
    $service = new AttachmentService();
    $descriptor = $service->upload($p, $file);
    echo json_encode(['output' => ['valid' => true, 'response' => $descriptor]], JSON_UNESCAPED_UNICODE);
} catch (\Pgs\Core\Exceptions\RateLimitException $e) {
    http_response_code(429);
    echo json_encode(['output' => ['valid' => false, 'error' => 'rate_limit', 'message' => $e->getMessage()]]);
} catch (\Pgs\Core\Exceptions\ValidationException $e) {
    http_response_code(422);
    echo json_encode(['output' => ['valid' => false, 'error' => 'validation_error', 'message' => $e->getMessage()]]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['output' => ['valid' => false, 'error' => 'internal_error', 'message' => $e->getMessage()]]);
}
