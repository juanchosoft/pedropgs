<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

set_time_limit(60);

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Ai\AsistenteIA;
use Pgs\Ai\ElevenLabsService;
use Pgs\Auth\PrincipalFactory;

$p = PrincipalFactory::fromSession('web');
if ($p->isAnonymous()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'error' => 'unauthorized', 'message' => 'Session expired or not authenticated.']]);
    exit;
}

if (!$p->can('ia.voz.use')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'error' => 'forbidden', 'message' => 'You do not have permission to use AI voice features.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'message' => 'Method not allowed.']]);
    exit;
}

$mensajeId = isset($_POST['mensaje_id']) ? (int) $_POST['mensaje_id'] : 0;
if ($mensajeId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'message' => 'Invalid mensaje_id.']]);
    exit;
}

$asistente = new AsistenteIA($p);
$texto = $asistente->obtenerTextoMensajeAsistente($mensajeId);
if ($texto === null || trim($texto) === '') {
    http_response_code(404);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'message' => 'Message not found or empty.']]);
    exit;
}

try {
    $limpio = ElevenLabsService::limpiarMarkdown($texto);
    if (mb_strlen($limpio) > 2500) {
        $limpio = mb_substr($limpio, 0, 2500);
    }
    $mp3 = ElevenLabsService::sintetizar($limpio);
} catch (\Throwable $e) {
    http_response_code(502);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['output' => ['valid' => false, 'error' => 'tts_error', 'message' => 'TTS synthesis failed: ' . $e->getMessage()]]);
    exit;
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($mp3));
header('Cache-Control: private, max-age=3600');
echo $mp3;
