<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Evitar que warnings/echo rompan JSON
while (ob_get_level()) {
    @ob_end_clean();
}
ob_start();

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/DbConection.php';
require_once __DIR__ . '/../classes/SessionData.php';

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeSlug(string $str): string
{
    $str = trim($str);
    $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    if ($tmp !== false) {
        $str = $tmp;
    }
    $str = preg_replace('/[^a-zA-Z0-9]+/', '_', $str);
    $str = trim((string)$str, '_');

    return $str !== '' ? $str : 'item';
}

function buildBaseUrl(): string
{
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $baseUrl   = preg_replace('#/admin/ajax$#', '', $scriptDir);
    return $baseUrl ?: '';
}

date_default_timezone_set('America/Bogota');

// ✅ Permisos
try {
    $view = SessionData::getPermission(21);
    if (!$view) {
        respond([
            'ok'  => false,
            'msg' => 'No permission'
        ], 403);
    }
} catch (Throwable $e) {
    respond([
        'ok'  => false,
        'msg' => 'Session error'
    ], 401);
}

// ✅ Validar archivos
if (!isset($_FILES['files']) || !is_array($_FILES['files'])) {
    respond([
        'ok'  => false,
        'msg' => 'No files received'
    ], 400);
}

$checklist_id = isset($_POST['checklist_id']) ? (int)$_POST['checklist_id'] : 0;
$section      = trim((string)($_POST['section'] ?? ''));
$item_label   = trim((string)($_POST['item_label'] ?? ''));

if ($checklist_id <= 0 || $section === '' || $item_label === '') {
    respond([
        'ok'  => false,
        'msg' => 'Missing data'
    ], 400);
}

// ✅ DB
try {
    $db  = new DbConection();
    $pdo = $db->openConect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    respond([
        'ok'  => false,
        'msg' => 'Database connection failed'
    ], 500);
}

try {
    // Validar que exista checklist
    $stChecklist = $pdo->prepare("SELECT id FROM tbl_checklists WHERE id = ? LIMIT 1");
    $stChecklist->execute([$checklist_id]);
    $existsChecklist = $stChecklist->fetchColumn();

    if (!$existsChecklist) {
        respond([
            'ok'  => false,
            'msg' => 'Checklist not found'
        ], 404);
    }

    // Buscar item_id real si existe
    $item_id = null;
    $stItem = $pdo->prepare("
        SELECT id
        FROM tbl_checklist_items
        WHERE checklist_id = ? AND section = ? AND item_label = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stItem->execute([$checklist_id, $section, $item_label]);
    $foundItemId = $stItem->fetchColumn();

    if ($foundItemId) {
        $item_id = (int)$foundItemId;
    }

    $uploadDir = realpath(__DIR__ . '/../../');
    if ($uploadDir === false) {
        throw new RuntimeException('Could not resolve project root');
    }

    $uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'checklists' . DIRECTORY_SEPARATOR . $checklist_id . DIRECTORY_SEPARATOR;

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Could not create upload directory');
        }
    }

    $sectionSlug = safeSlug($section);
    $itemSlug    = safeSlug($item_label);

    $files = $_FILES['files'];

    if (
        !isset($files['name'], $files['tmp_name'], $files['error'], $files['size']) ||
        !is_array($files['name'])
    ) {
        respond([
            'ok'  => false,
            'msg' => 'Invalid upload structure'
        ], 400);
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMime = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    $count = count($files['name']);
    $savedFiles = [];
    $skipped = 0;

    $baseUrl = buildBaseUrl();
    $createdBy = 0;

    try {
        $createdBy = (int)SessionData::getUserId();
    } catch (Throwable $e) {
        $createdBy = isset($_SESSION['session_user']) ? (int)$_SESSION['session_user'] : 0;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO tbl_checklist_files
        (
            checklist_id,
            item_id,
            file_name,
            file_path,
            mime_type,
            file_size,
            created_at,
            created_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    for ($i = 0; $i < $count; $i++) {
        $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $skipped++;
            continue;
        }

        $originalName = (string)($files['name'][$i] ?? '');
        $tmpName      = (string)($files['tmp_name'][$i] ?? '');
        $size         = isset($files['size'][$i]) ? (int)$files['size'][$i] : 0;

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $skipped++;
            continue;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            $skipped++;
            continue;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($realMime === '' || !in_array($realMime, $allowedMime, true)) {
            $skipped++;
            continue;
        }

        // Limitar tamaño, ejemplo: 10MB
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
            $skipped++;
            continue;
        }

        $newName = $sectionSlug . '__' . $itemSlug . '__' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $uploadDir . $newName;

        if (!move_uploaded_file($tmpName, $destination)) {
            $skipped++;
            continue;
        }

        $relativePath = 'uploads/checklists/' . $checklist_id . '/' . $newName;
        $now = date('Y-m-d H:i:s');

        $stmtInsert->execute([
            $checklist_id,
            $item_id,
            $newName,
            $relativePath,
            $realMime,
            $size,
            $now,
            $createdBy
        ]);

        $savedFiles[] = [
            'id'        => (int)$pdo->lastInsertId(),
            'url'       => $baseUrl . '/' . ltrim($relativePath, '/'),
            'name'      => $newName,
            'path'      => $relativePath,
            'mime_type' => $realMime,
            'size'      => $size
        ];
    }

    if (!$savedFiles) {
        respond([
            'ok'      => false,
            'msg'     => 'No valid files could be uploaded',
            'files'   => [],
            'skipped' => $skipped
        ], 400);
    }

    respond([
        'ok'           => true,
        'msg'          => 'Files uploaded',
        'checklist_id' => $checklist_id,
        'item_id'      => $item_id,
        'files'        => $savedFiles,
        'uploaded'     => count($savedFiles),
        'skipped'      => $skipped
    ]);

} catch (Throwable $e) {
    respond([
        'ok'         => false,
        'msg'        => 'Upload failed',
        'error_real' => $e->getMessage()
    ], 500);
}