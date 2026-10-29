<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

set_time_limit(180);

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Ai\AsistenteIA;
use Pgs\Ai\ElevenLabsService;
use Pgs\Auth\PrincipalFactory;
use Pgs\Core\Config;
use Pgs\Core\Ulid;

header('Content-Type: application/json; charset=UTF-8');

$p = PrincipalFactory::fromSession('web');
if ($p->isAnonymous()) {
    http_response_code(401);
    echo json_encode(['output' => ['valid' => false, 'error' => 'unauthorized', 'message' => 'Session expired or not authenticated.']]);
    exit;
}

if (!$p->can('ia.voz.use') || !$p->can('ia.asistente.use')) {
    http_response_code(403);
    echo json_encode(['output' => ['valid' => false, 'error' => 'forbidden', 'message' => 'You do not have permission to use AI voice mode.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['output' => ['valid' => false, 'error' => 'method_not_allowed', 'message' => 'Method not allowed.']]);
    exit;
}

$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (empty($csrfSession) || !hash_equals((string) $csrfSession, (string) $csrfSent)) {
    http_response_code(403);
    echo json_encode(['output' => ['valid' => false, 'error' => 'csrf_invalid', 'message' => 'Invalid or missing CSRF token.']]);
    exit;
}

const MAX_AUDIO_BYTES = 25 * 1024 * 1024; // 25 MB
const ALLOWED_AUDIO_MIMES = ['audio/webm', 'video/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/x-m4a'];

$file = $_FILES['audio'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['output' => ['valid' => false, 'message' => 'No valid audio file received.']]);
    exit;
}

if ($file['size'] > MAX_AUDIO_BYTES) {
    http_response_code(413);
    echo json_encode(['output' => ['valid' => false, 'message' => 'Audio file exceeds 25 MB limit.']]);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_AUDIO_MIMES, true)) {
    http_response_code(415);
    echo json_encode(['output' => ['valid' => false, 'message' => "Unsupported audio format: {$mime}"]]);
    exit;
}

$privateDir = Config::privatePath();
$tmpDir = rtrim($privateDir, '/\\') . DIRECTORY_SEPARATOR . 'ia_tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0700, true);
}

$tmpPath = $tmpDir . DIRECTORY_SEPARATOR . Ulid::generate() . '.audio';
if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
    http_response_code(500);
    echo json_encode(['output' => ['valid' => false, 'message' => 'Failed to store audio file temporarily.']]);
    exit;
}

try {
    $lang = $p->lang ?: 'en';
    $transcription = ElevenLabsService::transcribir($tmpPath, $lang);
} catch (\Throwable $e) {
    @unlink($tmpPath);
    http_response_code(502);
    echo json_encode(['output' => ['valid' => false, 'error' => 'stt_error', 'message' => 'Speech-to-text failed: ' . $e->getMessage()]]);
    exit;
} finally {
    @unlink($tmpPath);
}

$convId = isset($_POST['conversacion_id']) && $_POST['conversacion_id'] !== '' ? (int) $_POST['conversacion_id'] : null;
$adjuntos = isset($_POST['adjuntos']) && is_array($_POST['adjuntos']) ? $_POST['adjuntos'] : [];

$asistente = new AsistenteIA($p);
$resultado = $asistente->enviarMensaje($convId, $transcription, 'voz', $adjuntos);
$resultado['transcripcion'] = $transcription;

if (!$resultado['valid']) {
    http_response_code(500);
}

echo json_encode(['output' => ['valid' => $resultado['valid'], 'response' => $resultado]], JSON_UNESCAPED_UNICODE);
