<?php
/**
 * PGS Centrum - Guardado de reportes con fotografia.
 * Compatible con inserta_optimizado.js (foto Base64) y subida binaria futura.
 * Requiere PHP >= 7.4.
 */
require '../../classes/Util.php';
require '../../classes/DbConection.php';
require '../../include/generic_validate_session.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
date_default_timezone_set('America/Bogota');

function pgsReply(bool $success, string $message, int $status = 200, ?int $id = null): void
{
    http_response_code($status);
    $result = ['success' => $success, 'messages' => $message];
    if ($id !== null) $result['id'] = $id;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    pgsReply(false, 'Method not allowed.', 405);
}

$userId = (int) ($_SESSION['session_user']['id'] ?? 0);
if ($userId <= 0) pgsReply(false, 'Your session has expired. Please sign in again.', 401);

// No se impone un token CSRF todavia: el formulario existente no lo envia.
// Al integrar el token al frontend, validar aqui antes de procesar el reporte.
$zone = trim((string) ($_POST['zone'] ?? ''));
$activities = trim((string) ($_POST['actividades'] ?? ''));
$observations = trim((string) ($_POST['observaciones'] ?? ''));
$propertyRaw = (string) ($_POST['tbl_requerimiento_id'] ?? '');
$takePicture = (string) ($_POST['take_picture'] ?? '0') === '1';

if ($zone === '' || $activities === '' || !ctype_digit($propertyRaw) || (int)$propertyRaw <= 0) {
    pgsReply(false, 'Select a property and complete Zone and Activities.', 422);
}
if (mb_strlen($zone, 'UTF-8') > 250 || mb_strlen($activities, 'UTF-8') > 10000 || mb_strlen($observations, 'UTF-8') > 10000) {
    pgsReply(false, 'Some text fields are too long.', 422);
}

// Compatibilidad con el cliente actual: hasta 8 MiB decodificados (aprox. 11 MiB en Base64).
$maxImageBytes = 8 * 1024 * 1024;
$maxPixels = 24 * 1000 * 1000;
$imageBinary = null;
$uploadedFile = $_FILES['foto_archivo'] ?? null;
if ($uploadedFile && isset($uploadedFile['error']) && (int)$uploadedFile['error'] === UPLOAD_ERR_OK) {
    $size = (int)($uploadedFile['size'] ?? 0);
    if ($size <= 0 || $size > $maxImageBytes || !is_uploaded_file((string)$uploadedFile['tmp_name'])) {
        pgsReply(false, 'Invalid photo or photo exceeds 8 MB.', 413);
    }
    $imageBinary = file_get_contents($uploadedFile['tmp_name']);
    $takePicture = true;
} elseif ($uploadedFile && (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    pgsReply(false, 'The photo upload failed.', 400);
} elseif ($takePicture) {
    $raw = $_POST['foto'] ?? '';
    if (!is_string($raw) || $raw === '') pgsReply(false, 'No photo received.', 422);
    if (strlen($raw) > (int)ceil($maxImageBytes * 4 / 3) + 100) {
        pgsReply(false, 'Photo exceeds the allowed size.', 413);
    }
    if (preg_match('~^data:image/(?:jpe?g|png|webp);base64,~i', $raw)) {
        $raw = substr($raw, strpos($raw, ',') + 1);
    } elseif (strpos($raw, 'data:') === 0) {
        pgsReply(false, 'Unsupported image format.', 415);
    }
    $imageBinary = base64_decode(str_replace(' ', '+', $raw), true);
    if ($imageBinary === false || $imageBinary === '' || strlen($imageBinary) > $maxImageBytes) {
        pgsReply(false, 'Invalid or oversized image.', 422);
    }
}

$imageName = 'no_image.png';
$imagePath = null;
if ($takePicture) {
    if (!is_string($imageBinary) || $imageBinary === '') pgsReply(false, 'No photo received.', 422);
    $info = @getimagesizefromstring($imageBinary);
    if (!$info || !in_array((int)$info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        pgsReply(false, 'Please select a valid JPEG, PNG or WebP photo.', 415);
    }
    if ((float)$info[0] * (float)$info[1] > $maxPixels) {
        pgsReply(false, 'Photo resolution is too large.', 413);
    }
    // Guardar en el formato real. No renombrar PNG/WebP como JPG (provoca fallos de visualizacion).
    $extension = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][(int)$info[2]];
    $photoDir = __DIR__ . DIRECTORY_SEPARATOR . 'foto';
    if (!is_dir($photoDir) && !@mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
        pgsReply(false, 'The photo storage folder is unavailable.', 500);
    }
    if (!is_writable($photoDir)) pgsReply(false, 'Photo storage is not writable.', 500);
    try {
        $imageName = 'f_' . date('Ymd_His') . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
    } catch (Throwable $e) {
        error_log('PGS photo: random name generation failed');
        pgsReply(false, 'Unable to prepare photo.', 500);
    }
    $imagePath = $photoDir . DIRECTORY_SEPARATOR . $imageName;
    if (file_put_contents($imagePath, $imageBinary, LOCK_EX) !== strlen($imageBinary)) {
        @unlink($imagePath);
        pgsReply(false, 'Unable to save photo.', 500);
    }
    @chmod($imagePath, 0644);
    unset($imageBinary);
}

try {
    $db = new DbConection();
    $pdo = $db->openConect();
    if (!$pdo instanceof PDO) throw new RuntimeException('Database connection failed');
    $unitResult = Util::getUnidadByUser($userId);
    $unitId = !empty($unitResult['output']['valid']) ? (int)($unitResult['output']['tbl_unidad_id'] ?? 0) : 0;
    // La autorizacion del inmueble seleccionado debe verificarse tambien en backend.
    // No se cambia el modelo de permisos sin disponer de las clases originales.
    $sql = 'INSERT INTO ' . $db->getTable('tbl_fotos') . '
        (zone, actividades, observaciones, estado, foto_antes, dtcreate, tbl_unidad_id, tbl_requerimiento_id, tbl_usuario_id)
        VALUES (:zone, :actividades, :observaciones, :estado, :foto_antes, :dtcreate, :tbl_unidad_id, :tbl_requerimiento_id, :tbl_usuario_id)';
    $stmt = $pdo->prepare($sql);
    $saved = $stmt->execute([
        ':zone' => $zone,
        ':actividades' => $activities,
        ':observaciones' => $observations,
        ':estado' => 'creado',
        ':foto_antes' => $imageName,
        ':dtcreate' => date('Y-m-d H:i:s'),
        ':tbl_unidad_id' => $unitId,
        ':tbl_requerimiento_id' => (int)$propertyRaw,
        ':tbl_usuario_id' => $userId,
    ]);
    if (!$saved) throw new RuntimeException('Insert failed');
    pgsReply(true, 'Information saved correctly', 200, (int)$pdo->lastInsertId());
} catch (Throwable $e) {
    if ($imagePath !== null && is_file($imagePath)) @unlink($imagePath);
    error_log('PGS report save failed: ' . $e->getMessage());
    pgsReply(false, 'Could not save the report. Please try again.', 500);
}
