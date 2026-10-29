<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Ai\Attachments\AttachmentService;
use Pgs\Auth\PrincipalFactory;

$p = PrincipalFactory::fromSession('web');
if ($p->isAnonymous()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'error' => 'unauthorized', 'message' => 'Session expired or not authenticated.']]);
    exit;
}

$service = new AttachmentService();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $publicId = trim((string) ($_GET['id'] ?? ''));
    if ($publicId === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['output' => ['valid' => false, 'message' => 'Missing attachment ID.']]);
        exit;
    }
    $service->preview($p, $publicId);
    exit;
}

if ($method === 'POST') {
    $csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $csrfSession = $_SESSION['csrf_token'] ?? '';
    if (empty($csrfSession) || !hash_equals((string) $csrfSession, (string) $csrfSent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['output' => ['valid' => false, 'error' => 'csrf_invalid', 'message' => 'Invalid or missing CSRF token.']]);
        exit;
    }

    $publicId = trim((string) ($_POST['id'] ?? ''));
    $action = $_POST['action'] ?? 'delete';

    if ($action === 'delete') {
        try {
            $service->delete($p, $publicId);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['output' => ['valid' => true, 'response' => ['deleted' => true, 'id' => $publicId]]]);
        } catch (\Throwable $e) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['output' => ['valid' => false, 'message' => $e->getMessage()]]);
        }
        exit;
    }

    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'message' => 'Unknown action.']]);
    exit;
}

http_response_code(405);
