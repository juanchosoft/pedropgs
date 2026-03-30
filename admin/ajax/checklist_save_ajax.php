<?php
declare(strict_types=1);

/**
 * admin/ajax/checklist_save_ajax.php
 * - SIEMPRE responde JSON
 * - Inserta/actualiza checklist + items
 * - Guarda dinámicamente nuevas secciones e ítems
 * - NO incluye vistas HTML (para no romper JSON)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Evitar que cualquier warning/echo rompa el JSON
while (ob_get_level()) {
    @ob_end_clean();
}
ob_start();

// Convertir warnings/notices en excepción controlable
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Cargar solo lo necesario
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

function cleanText($value): string
{
    return trim((string)$value);
}

function cleanLongText($value): string
{
    return trim((string)$value);
}

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

// ✅ Leer JSON del body
$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    respond([
        'ok'  => false,
        'msg' => 'Empty request body'
    ], 400);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    respond([
        'ok'  => false,
        'msg' => 'Invalid JSON'
    ], 400);
}

// ✅ Normalizar campos principales
$checklist_id     = isset($data['checklist_id']) ? (int)$data['checklist_id'] : 0;
$title            = cleanText($data['title'] ?? 'Checklist');
$unidad           = cleanText($data['unidad'] ?? '');
$estado           = cleanText($data['estado'] ?? 'borrador');
$general_comments = cleanLongText($data['general_comments'] ?? '');
$inspector_name   = cleanText($data['inspector_name'] ?? '');
$progress         = isset($data['progress']) ? (int)$data['progress'] : 0;

if ($title === '') {
    $title = 'Checklist';
}

if (!in_array($estado, ['borrador', 'finalizado'], true)) {
    $estado = 'borrador';
}

if ($progress < 0) {
    $progress = 0;
}
if ($progress > 100) {
    $progress = 100;
}

$sections = $data['sections'] ?? [];
if (!is_array($sections)) {
    $sections = [];
}

$created_by = 0;
try {
    $created_by = (int)SessionData::getUserId();
} catch (Throwable $e) {
    $created_by = 0;
}

date_default_timezone_set('America/Bogota');
$now = date('Y-m-d H:i:s');

// ✅ DB connect
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
    $pdo->beginTransaction();

    $is_new = false;

    // ✅ Si viene ID, validar que exista
    if ($checklist_id > 0) {
        $stmtCheck = $pdo->prepare("SELECT id FROM tbl_checklists WHERE id = ? LIMIT 1");
        $stmtCheck->execute([$checklist_id]);
        $existsChecklist = $stmtCheck->fetchColumn();

        if (!$existsChecklist) {
            $checklist_id = 0;
        }
    }

    // ✅ INSERT
    if ($checklist_id <= 0) {
        $is_new = true;

        $stmt = $pdo->prepare("
            INSERT INTO tbl_checklists
                (created_at, created_by, title, unidad, estado, progress, general_comments, inspector_name)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $now,
            $created_by,
            $title,
            $unidad,
            $estado,
            $progress,
            $general_comments,
            $inspector_name
        ]);

        $checklist_id = (int)$pdo->lastInsertId();

        if ($checklist_id <= 0) {
            throw new RuntimeException('Could not generate checklist ID');
        }

    // ✅ UPDATE
    } else {
        $stmt = $pdo->prepare("
            UPDATE tbl_checklists
            SET
                updated_at = ?,
                title = ?,
                unidad = ?,
                estado = ?,
                progress = ?,
                general_comments = ?,
                inspector_name = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $now,
            $title,
            $unidad,
            $estado,
            $progress,
            $general_comments,
            $inspector_name,
            $checklist_id
        ]);

        // Eliminar ítems anteriores para volver a insertar la nueva estructura
        $del = $pdo->prepare("DELETE FROM tbl_checklist_items WHERE checklist_id = ?");
        $del->execute([$checklist_id]);
    }

    // ✅ Insertar ítems dinámicamente
    $ins = $pdo->prepare("
        INSERT INTO tbl_checklist_items
            (checklist_id, section, item_label, status, comment, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    $items_count    = 0;
    $sections_count = 0;

    foreach ($sections as $sec) {
        if (!is_array($sec)) {
            continue;
        }

        $sectionName = cleanText($sec['section'] ?? '');
        if ($sectionName === '') {
            continue;
        }

        $items = $sec['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        $sectionHasValidItems = false;

        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }

            $label = cleanText($it['label'] ?? '');
            if ($label === '') {
                continue;
            }

            $status = cleanText($it['status'] ?? 'pendiente');
            if (!in_array($status, ['cumple', 'no_cumple', 'na', 'pendiente'], true)) {
                $status = 'pendiente';
            }

            $comment = cleanLongText($it['comment'] ?? '');

            $ins->execute([
                $checklist_id,
                $sectionName,
                $label,
                $status,
                $comment,
                $now
            ]);

            $items_count++;
            $sectionHasValidItems = true;
        }

        if ($sectionHasValidItems) {
            $sections_count++;
        }
    }

    $pdo->commit();

    respond([
        'ok'             => true,
        'checklist_id'   => $checklist_id,
        'is_new'         => $is_new,
        'items_saved'    => $items_count,
        'sections_saved' => $sections_count,
        'title'          => $title,
        'estado'         => $estado,
        'progress'       => $progress
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'ok'         => false,
        'msg'        => 'Save failed',
        'error_real' => $e->getMessage()
    ], 500);
}