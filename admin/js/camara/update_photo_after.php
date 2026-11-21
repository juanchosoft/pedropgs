<?php
/**
 * PGS Centrum - actualizar evidencia fotografica "despues".
 * Compatible con admin/js/camara/update_optimizado.js: POST id + foto (JPEG Base64).
 * PHP >= 7.4.
 */
require '../../classes/Util.php';
require '../../classes/DbConection.php';
require '../../include/generic_validate_session.php';
// El guardado se ejecuta fuera del contexto de report-list.php.
// Cargar explícitamente la clase de permisos, igual que el resto del sistema.
require_once '../../classes/SessionData.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function afterReply(bool $ok, string $message, int $http = 200): void
{
    http_response_code($http);
    echo json_encode(['success' => $ok, 'messages' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    afterReply(false, 'Method not allowed.', 405);
}

$userId = (int)($_SESSION['session_user']['id'] ?? 0);
if ($userId <= 0) {
    afterReply(false, 'Your session has expired. Please sign in again.', 401);
}
// El endpoint se usa para editar reportes. Denegar si el permiso no se puede verificar.
if (!SessionData::getPermission(9)) {
    error_log('PGS after photo denied: missing permission 9 for user ' . $userId);
    afterReply(false, 'Your account does not have the Edit Report permission (9). Ask the administrator to enable it.', 403);
}

// Defense-in-depth contra peticiones cross-site sin exigir cambios al cliente actual.
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    afterReply(false, 'Cross-site request rejected.', 403);
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!is_string($originHost) || !is_string($host) || strcasecmp($originHost, $host) !== 0) {
        afterReply(false, 'Request origin is not allowed.', 403);
    }
}

$idInput = $_POST['id'] ?? null;
$id = filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) afterReply(false, 'Invalid report identifier.', 422);

$raw = $_POST['foto'] ?? null;
$maxBytes = 8 * 1024 * 1024;
if (!is_string($raw) || $raw === '') {
    afterReply(false, 'No photo received.', 422);
}
if (strlen($raw) > (int)ceil($maxBytes * 4 / 3) + 100) {
    afterReply(false, 'Photo exceeds 8 MB.', 413);
}
// Las versiones actuales de update.js envian JPEG; se exige JPEG real, no solo prefijo Base64.
if (preg_match('~^data:image/jpeg;base64,~i', $raw)) {
    $raw = substr($raw, strpos($raw, ',') + 1);
} elseif (strpos($raw, 'data:') === 0) {
    afterReply(false, 'Only JPEG photos are supported.', 422);
}
$binary = base64_decode(str_replace(' ', '+', $raw), true);
if ($binary === false || $binary === '' || strlen($binary) > $maxBytes) {
    afterReply(false, 'Invalid photo or photo exceeds 8 MB.', 422);
}
$image = @getimagesizefromstring($binary);
if (!$image || (int)$image[2] !== IMAGETYPE_JPEG) {
    afterReply(false, 'Invalid JPEG image.', 422);
}
$width = (int)$image[0];
$height = (int)$image[1];
if ($width < 1 || $height < 1 || $width * $height > 24000000) {
    afterReply(false, 'Image dimensions are not supported.', 422);
}

$db = null;
$pdo = null;
$filePath = null;
$committed = false;
try {
    $db = new DbConection();
    $pdo = $db->openConect();
    if (!$pdo instanceof PDO) throw new RuntimeException('Database unavailable');
    $table = $db->getTable('tbl_fotos');

    $type = SessionData::getUserType();
    $isAdmin = $type == Util::SuperAdmin();
    if (!$isAdmin && $type != Util::Manager() && $type != Util::Staff()) {
        afterReply(false, 'Not authorized to edit this report.', 403);
    }
    $unitIds = array_values(array_unique(array_filter(array_map('intval', (array)SessionData::getUnidadesUser()), static function ($v) { return $v > 0; })));
    if (!$isAdmin && !$unitIds) afterReply(false, 'No assigned property access.', 403);

    // Bloquea el registro durante la validacion y actualizacion: evita cambios de estado simultaneos.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id, estado, tbl_unidad_id FROM {$table} WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        $pdo->rollBack();
        afterReply(false, 'Report not found.', 404);
    }
    if (!$isAdmin && !in_array((int)$report['tbl_unidad_id'], $unitIds, true)) {
        $pdo->rollBack();
        afterReply(false, 'You cannot edit this property report.', 403);
    }
    if (!in_array((string)($report['estado'] ?? ''), ['', 'creado', 'pendiente'], true)) {
        $pdo->rollBack();
        afterReply(false, 'This report is finalized and cannot be edited.', 409);
    }

    $directory = __DIR__ . '/foto';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Photo directory unavailable');
    }
    $name = 'after_' . bin2hex(random_bytes(16)) . '.jpg';
    $filePath = $directory . '/' . $name;
    // Escritura exclusiva para no sobrescribir evidencias existentes.
    $handle = @fopen($filePath, 'xb');
    if ($handle === false) throw new RuntimeException('Unable to create photo file');
    $length = strlen($binary);
    $written = 0;
    try {
        while ($written < $length) {
            $chunk = fwrite($handle, substr($binary, $written, min(1048576, $length - $written)));
            if ($chunk === false || $chunk === 0) throw new RuntimeException('Unable to write photo file');
            $written += $chunk;
        }
    } finally {
        fclose($handle);
    }
    @chmod($filePath, 0644);

    // No modificar dtcreate: es la fecha ORIGINAL del informe, importante para buscarlo.
    $update = $pdo->prepare("UPDATE {$table} SET foto_despues = :photo, estado = :state WHERE id = :id");
    $update->execute([':photo' => $name, ':state' => 'pendiente', ':id' => $id]);
    $pdo->commit();
    $committed = true;
    afterReply(true, 'After photo saved successfully.');
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if (!$committed && $filePath !== null && is_file($filePath)) @unlink($filePath);
    error_log('PGS after photo error: ' . $exception->getMessage());
    afterReply(false, 'Could not save the photo. Please try again or contact support.', 500);
} finally {
    if ($db instanceof DbConection) $db->closeConect();
}
