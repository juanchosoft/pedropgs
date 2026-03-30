<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Evitar que warnings/echo rompan el JSON
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

function normalizeKeyPart(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

function buildBaseUrl(): string
{
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $baseUrl   = preg_replace('#/admin/ajax$#', '', $scriptDir);
    return $baseUrl ?: '';
}

// ✅ Permisos
try {
    $view = SessionData::getPermission(21);
    if (!$view) {
        respond([
            'ok'  => false,
            'msg' => 'Sin permiso'
        ], 403);
    }
} catch (Throwable $e) {
    respond([
        'ok'  => false,
        'msg' => 'Error de sesión'
    ], 401);
}

// ✅ ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    respond([
        'ok'  => false,
        'msg' => 'ID inválido'
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
        'msg' => 'Error de conexión a base de datos'
    ], 500);
}

try {
    // Checklist principal
    $st = $pdo->prepare("
        SELECT
            id,
            title,
            unidad,
            estado,
            progress,
            general_comments,
            inspector_name,
            created_at,
            updated_at,
            created_by
        FROM tbl_checklists
        WHERE id = ?
        LIMIT 1
    ");
    $st->execute([$id]);
    $check = $st->fetch();

    if (!$check) {
        respond([
            'ok'  => false,
            'msg' => 'No existe'
        ], 404);
    }

    // Ítems del checklist
    $it = $pdo->prepare("
        SELECT
            id,
            checklist_id,
            section,
            item_label,
            status,
            comment,
            updated_at
        FROM tbl_checklist_items
        WHERE checklist_id = ?
        ORDER BY id ASC
    ");
    $it->execute([$id]);
    $items = $it->fetchAll();

    // Archivos relacionados
    $fs = $pdo->prepare("
        SELECT
            id,
            checklist_id,
            item_id,
            file_name,
            file_path,
            mime_type
        FROM tbl_checklist_files
        WHERE checklist_id = ?
        ORDER BY id ASC
    ");
    $fs->execute([$id]);
    $files = $fs->fetchAll();

    // Mapa por section||item_label
    $map = [];
    foreach ($items as &$row) {
        $section = normalizeKeyPart((string)($row['section'] ?? ''));
        $label   = normalizeKeyPart((string)($row['item_label'] ?? ''));
        $key     = $section . '||' . $label;

        $row['id']           = isset($row['id']) ? (int)$row['id'] : 0;
        $row['checklist_id'] = isset($row['checklist_id']) ? (int)$row['checklist_id'] : 0;
        $row['section']      = $section;
        $row['item_label']   = $label;
        $row['status']       = (string)($row['status'] ?? 'pendiente');
        $row['comment']      = (string)($row['comment'] ?? '');
        $row['files']        = [];

        $map[$key] = &$row;
    }
    unset($row);

    $baseUrl = buildBaseUrl();

    foreach ($files as $f) {
        $path = trim((string)($f['file_path'] ?? ''));
        $name = trim((string)($f['file_name'] ?? ''));
        $itemId = isset($f['item_id']) ? (int)$f['item_id'] : 0;

        if ($path === '') {
            continue;
        }

        $url = $baseUrl . '/' . ltrim(str_replace('\\', '/', $path), '/');

        $filePayload = [
            'id'        => isset($f['id']) ? (int)$f['id'] : 0,
            'url'       => $url,
            'name'      => $name,
            'path'      => $path,
            'mime_type' => (string)($f['mime_type'] ?? '')
        ];

        $attached = false;

        // 1) Si existe item_id y coincide con un item actual, asociarlo directamente
        if ($itemId > 0) {
            foreach ($items as &$itemRow) {
                if ((int)$itemRow['id'] === $itemId) {
                    $itemRow['files'][] = $filePayload;
                    $attached = true;
                    break;
                }
            }
            unset($itemRow);
        }

        if ($attached) {
            continue;
        }

        // 2) Intentar reconstruir por nombre del archivo: section__label__random.ext
        $foundKey = null;

        if ($name !== '' && strpos($name, '__') !== false) {
            $parts = explode('__', $name);

            if (count($parts) >= 3) {
                $sec = normalizeKeyPart(str_replace('_', ' ', (string)$parts[0]));
                $lab = normalizeKeyPart(str_replace('_', ' ', (string)$parts[1]));

                $candidate = $sec . '||' . $lab;
                if (isset($map[$candidate])) {
                    $foundKey = $candidate;
                }
            }
        }

        if ($foundKey !== null && isset($map[$foundKey])) {
            $map[$foundKey]['files'][] = $filePayload;
        }
    }

    respond([
        'ok' => true,
        'checklist' => [
            'id'               => (int)$check['id'],
            'title'            => (string)($check['title'] ?? ''),
            'unidad'           => (string)($check['unidad'] ?? ''),
            'estado'           => (string)($check['estado'] ?? 'borrador'),
            'progress'         => isset($check['progress']) ? (int)$check['progress'] : 0,
            'general_comments' => (string)($check['general_comments'] ?? ''),
            'inspector_name'   => (string)($check['inspector_name'] ?? ''),
            'created_at'       => (string)($check['created_at'] ?? ''),
            'updated_at'       => (string)($check['updated_at'] ?? ''),
            'created_by'       => isset($check['created_by']) ? (int)$check['created_by'] : 0
        ],
        'items' => array_values($items)
    ]);

} catch (Throwable $e) {
    respond([
        'ok'         => false,
        'msg'        => 'Error cargando',
        'error_real' => $e->getMessage()
    ], 500);
}