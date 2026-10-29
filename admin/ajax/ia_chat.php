<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

set_time_limit(180);

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Ai\AsistenteIA;
use Pgs\Auth\PrincipalFactory;

header('Content-Type: application/json; charset=UTF-8');

$p = PrincipalFactory::fromSession('web');
if ($p->isAnonymous()) {
    http_response_code(401);
    echo json_encode([
        'output' => [
            'valid' => false,
            'error' => 'unauthorized',
            'message' => 'Session expired or not authenticated.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$p->can('ia.asistente.use')) {
    http_response_code(403);
    echo json_encode([
        'output' => [
            'valid' => false,
            'error' => 'forbidden',
            'message' => 'You do not have permission to use the AI assistant.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'output' => [
            'valid' => false,
            'error' => 'method_not_allowed',
            'message' => 'Method not allowed.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verify CSRF
$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (empty($csrfSession) || !hash_equals((string) $csrfSession, (string) $csrfSent)) {
    http_response_code(403);
    echo json_encode([
        'output' => [
            'valid' => false,
            'error' => 'csrf_invalid',
            'message' => 'Invalid or missing CSRF token.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$mensaje = trim((string) ($_POST['mensaje'] ?? ''));
$convId = isset($_POST['conversacion_id']) && $_POST['conversacion_id'] !== '' ? (int) $_POST['conversacion_id'] : null;
$adjuntos = isset($_POST['adjuntos']) && is_array($_POST['adjuntos']) ? $_POST['adjuntos'] : [];

$asistente = new AsistenteIA($p);
$resultado = $asistente->enviarMensaje($convId, $mensaje, 'texto', $adjuntos);

if (!$resultado['valid']) {
    $status = match ($resultado['error'] ?? '') {
        'rate_limit_exceeded' => 429,
        'empty_message', 'message_too_long', 'too_many_attachments' => 400,
        default => 500,
    };
    http_response_code($status);
}

echo json_encode(['output' => ['valid' => $resultado['valid'], 'response' => $resultado]], JSON_UNESCAPED_UNICODE);
