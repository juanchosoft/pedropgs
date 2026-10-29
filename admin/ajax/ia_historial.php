<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

$asistente = new AsistenteIA($p);
$accion = $_REQUEST['accion'] ?? 'listar';

if ($accion === 'cargar') {
    $convId = isset($_REQUEST['conversacion_id']) ? (int) $_REQUEST['conversacion_id'] : (isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0);
    if ($convId <= 0) {
        http_response_code(400);
        echo json_encode(['output' => ['valid' => false, 'message' => 'Invalid conversation ID.']]);
        exit;
    }

    try {
        $data = $asistente->cargarConversacion($convId);
        echo json_encode(['output' => ['valid' => true, 'response' => $data]], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        http_response_code(404);
        echo json_encode(['output' => ['valid' => false, 'message' => 'Conversation not found.']]);
    }
} elseif ($accion === 'borrar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['output' => ['valid' => false, 'message' => 'Method not allowed.']]);
        exit;
    }

    $csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $csrfSession = $_SESSION['csrf_token'] ?? '';
    if (empty($csrfSession) || !hash_equals((string) $csrfSession, (string) $csrfSent)) {
        http_response_code(403);
        echo json_encode(['output' => ['valid' => false, 'message' => 'Invalid CSRF token.']]);
        exit;
    }

    $convId = isset($_POST['conversacion_id']) ? (int) $_POST['conversacion_id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
    if ($convId <= 0) {
        http_response_code(400);
        echo json_encode(['output' => ['valid' => false, 'message' => 'Invalid conversation ID.']]);
        exit;
    }

    $asistente->borrarConversacion($convId);
    echo json_encode(['output' => ['valid' => true, 'response' => ['deleted' => true, 'id' => $convId]]], JSON_UNESCAPED_UNICODE);
} else {
    // Default: listar
    $limit = isset($_REQUEST['limit']) ? min((int) $_REQUEST['limit'], 50) : 20;
    $lista = $asistente->listarConversaciones($limit);
    echo json_encode(['output' => ['valid' => true, 'response' => ['conversaciones' => $lista]]], JSON_UNESCAPED_UNICODE);
}
