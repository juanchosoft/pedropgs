<?php
// Registro de errores PHP sin exponer rutas internas ni datos sensibles al usuario.
ini_set('log_errors', '1');
register_shutdown_function(static function () {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    error_log('PGS report-list fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="position:relative;z-index:2147483647;background:#fff;color:#182230;font:16px Arial,sans-serif;max-width:500px;margin:40px auto;padding:25px;border:1px solid #ddd;border-radius:16px">';
    echo '<strong>No fue posible cargar los reportes.</strong><p>Se detectó un error PHP. Revisa el registro de errores de tu servidor local.</p>';
    echo '</div>';
});
require './admin/include/generic_classes.php';
include './admin/classes/Report.php';
$view = SessionData::getPermission(7);
$create = SessionData::getPermission(8);
$edit = SessionData::getPermission(9);
$delete = SessionData::getPermission(10);
if (!$view && !$edit) { require 'permiso_denegado.php'; exit; }
$modulo = 'Activities report list - Edit Report';

// Saving activity descriptions is handled by PHP itself, independent of JavaScript.
// The existing camera, photo upload and report actions remain unchanged.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['pgs_edit_csrf'])) {
    $_SESSION['pgs_edit_csrf'] = bin2hex(random_bytes(32));
}
$pgsFinalizeError = '';
// Native finalize POST: independent of the AJAX router and page JavaScript.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['pgs_action'] ?? '') === 'finalize_report') {
    try {
        if (!$edit) throw new RuntimeException('You do not have permission to finalize reports.');
        if (!is_string($_POST['pgs_csrf'] ?? null) || !hash_equals($_SESSION['pgs_edit_csrf'], $_POST['pgs_csrf'])) {
            throw new RuntimeException('The form expired. Reload the page and try again.');
        }
        $rid = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$rid) throw new RuntimeException('Invalid report number.');
        $userId = (int)($_SESSION['session_user']['id'] ?? 0);
        if ($userId < 1) throw new RuntimeException('Your session has expired. Please sign in again.');
        $role = SessionData::getUserType();
        $isSuper = $role == Util::SuperAdmin();
        if (!$isSuper && $role != Util::Manager() && $role != Util::Staff()) throw new RuntimeException('Your account is not allowed to finalize reports.');
        $allowedUnits = array_map('intval', (array) SessionData::getUnidadesUser());
        $db = new DbConection();
        $pdo = $db->openConect();
        if (!$pdo instanceof PDO) throw new RuntimeException('Unable to connect to the database.');
        try {
            $table = $db->getTable('tbl_fotos');
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT id, estado, foto_despues, tbl_unidad_id FROM {$table} WHERE id = :id FOR UPDATE");
            $st->execute([':id' => $rid]);
            $report = $st->fetch(PDO::FETCH_ASSOC);
            if (!$report) throw new RuntimeException('Report not found.');
            if (!$isSuper && !in_array((int)$report['tbl_unidad_id'], $allowedUnits, true)) {
                throw new RuntimeException('You cannot finalize reports from this property.');
            }
            if (!Report::isEditable($report['estado'] ?? null)) {
                throw new RuntimeException('This report cannot be finalized.');
            }
            $photo = trim((string)($report['foto_despues'] ?? ''));
            if (!$photo || in_array(strtolower(basename(str_replace('\\', '/', $photo))), ['null', 'none', 'no_image.png'], true)) {
                throw new RuntimeException('Upload the after photo before finalizing this report.');
            }
            $update = $pdo->prepare("UPDATE {$table} SET estado = :state, tbl_usuario_id_update = :uid WHERE id = :id");
            if (!$update->execute([':state' => Report::ESTADO_FINALIZADO, ':uid' => $userId, ':id' => $rid])) {
                throw new RuntimeException('The database rejected the update.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } finally {
            $db->closeConect();
        }
        $params = $_GET;
        unset($params['pgs_open'], $params['pgs_report'], $params['edit_saved']);
        $params['finalized'] = '1';
        header('Location: report-list.php?' . http_build_query($params), true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('PGS finalize failed: ' . $e->getMessage());
        $safe = [
            'You do not have permission to finalize reports.', 'The form expired. Reload the page and try again.',
            'Invalid report number.', 'Your session has expired. Please sign in again.',
            'Your account is not allowed to finalize reports.', 'Report not found.',
            'You cannot finalize reports from this property.', 'This report cannot be finalized.',
            'Upload the after photo before finalizing this report.'
        ];
        $pgsFinalizeError = in_array($e->getMessage(), $safe, true) ? $e->getMessage() : 'Unable to finalize report. Check the server error log.';
    }
}
$pgsEditError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['pgs_action'] ?? '') === 'save_report_text') {
    $rid = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $activity = $_POST['actividades'] ?? null;
    $observations = $_POST['observaciones'] ?? null;
    try {
        if (!$edit) throw new RuntimeException('You do not have permission to edit reports.');
        if (!is_string($_POST['pgs_csrf'] ?? null) || !hash_equals($_SESSION['pgs_edit_csrf'], $_POST['pgs_csrf'])) {
            throw new RuntimeException('The form has expired. Refresh the page and try again.');
        }
        if (!$rid || !is_string($activity) || !is_string($observations)) throw new RuntimeException('Invalid report information.');
        $activity = trim($activity);
        $observations = trim($observations);
        if ($activity === '') throw new RuntimeException('Activity description is required.');
        if (strlen($activity)>10000 || strlen($observations)>10000) throw new RuntimeException('The text is too long.');
        $userId = (int)($_SESSION['session_user']['id'] ?? 0);
        if ($userId <= 0) throw new RuntimeException('Your session expired. Sign in again.');
        $role = SessionData::getUserType();
        $super = $role == Util::SuperAdmin();
        if (!$super && $role != Util::Manager() && $role != Util::Staff()) throw new RuntimeException('This account cannot edit reports.');
        $allowed = array_map('intval', (array)SessionData::getUnidadesUser());
        $db = new DbConection();
        $pdo = $db->openConect();
        if (!$pdo instanceof PDO) throw new RuntimeException('Database connection unavailable.');
        try {
            $table = $db->getTable('tbl_fotos');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id, estado, tbl_unidad_id FROM {$table} WHERE id = :id FOR UPDATE");
            if (!$stmt || !$stmt->execute([':id'=>$rid])) throw new RuntimeException('Could not retrieve the report.');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Report not found.');
            if (!$super && !in_array((int)$row['tbl_unidad_id'], $allowed, true)) throw new RuntimeException('You cannot edit this property.');
            if (!Report::isEditable($row['estado'] ?? null)) throw new RuntimeException('This report has already been finalized.');
            $update = $pdo->prepare("UPDATE {$table} SET actividades = :a, observaciones = :o, estado = :s, tbl_usuario_id_update = :u WHERE id = :id");
            if (!$update || !$update->execute([':a'=>$activity, ':o'=>$observations, ':s'=>Report::ESTADO_PENDIENTE, ':u'=>$userId, ':id'=>$rid])) {
                throw new RuntimeException('The database rejected the update.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } finally {
            $db->closeConect();
        }
        // Post/Redirect/Get prevents duplicate updates on refresh.
        $params = $_GET;
        unset($params['pgs_open'], $params['pgs_report']);
        $params['edit_saved'] = '1';
        header('Location: report-list.php?' . http_build_query($params), true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('PGS report edit: ' . $e->getMessage());
        // Show a readable reason without exposing SQL or server paths.
        $known = ['You do not have permission to edit reports.', 'The form has expired. Refresh the page and try again.', 'Invalid report information.', 'Activity description is required.', 'The text is too long.', 'Your session expired. Sign in again.', 'This account cannot edit reports.', 'Report not found.', 'You cannot edit this property.', 'This report has already been finalized.'];
        $pgsEditError = in_array($e->getMessage(), $known, true) ? $e->getMessage() : 'Unable to save changes. Check the PHP error log.';
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'open');
if (!in_array($status, ['all', 'open', 'finalizado'], true)) $status = 'open';
$mine = isset($_GET['mine']) ? ($_GET['mine'] === '1') : (SessionData::getUserType() == Util::Staff());
$page = max(1, (int)($_GET['page'] ?? 1));
$payload = ['output' => ['valid' => false, 'response' => [], 'total' => 0, 'page' => 1, 'pages' => 1]];
$pgsLoadError = '';
try {
    if (method_exists('Report', 'searchMobile')) {
        $payload = Report::searchMobile(['page'=>$page, 'limit'=>12, 'search'=>$search, 'status'=>$status, 'mine'=>$mine]);
    } else {
        // Compatibilidad temporal si Report.php todavía no se actualizó.
        $old = Report::getAll(null);
        $rows = !empty($old['output']['valid']) ? (array)($old['output']['response'] ?? []) : [];
        $allowed = array_map('intval', (array)SessionData::getUnidadesUser());
        $type = SessionData::getUserType();
        $uid = (int)($_SESSION['session_user']['id'] ?? 0);
        $rows = array_values(array_filter($rows, static function ($r) use ($allowed, $type, $uid, $mine, $status, $search) {
            if ($type != Util::SuperAdmin() && !in_array((int)($r['tbl_unidad_id'] ?? 0), $allowed, true)) return false;
            if ($mine && (int)($r['tbl_usuario_id'] ?? 0) !== $uid) return false;
            $state = (string)($r['estado'] ?? '');
            if ($status === 'finalizado' && $state !== 'finalizado') return false;
            if ($status === 'open' && !in_array($state, ['', 'creado', 'pendiente'], true)) return false;
            if ($search !== '') {
                $haystack = implode(' ', [(string)($r['id'] ?? ''), (string)($r['actividades'] ?? ''), (string)($r['zone'] ?? ''), (string)($r['observaciones'] ?? ''), (string)($r['unidad_nombre'] ?? ''), (string)($r['propiedad_nombre'] ?? '')]);
                if (stripos($haystack, $search) === false) return false;
            }
            return true;
        }));
        $count = count($rows);
        $pagesOld = max(1, (int)ceil($count / 12));
        $page = min($page, $pagesOld);
        $payload = ['output' => ['valid' => true, 'response' => array_slice($rows, ($page - 1) * 12, 12), 'total' => $count, 'page' => $page, 'pages' => $pagesOld]];
        $pgsLoadError = 'Temporary compatibility mode: to search all reports, install the updated Report.php.';
    }
} catch (Throwable $e) {
    error_log('PGS report-list load: ' . $e->getMessage());
    $pgsLoadError = 'Unable to load the reports right now. Check Report.php and the server error log.';
}
if (empty($payload['output']['valid']) && $pgsLoadError === '') {
    $pgsLoadError = 'No se pudieron consultar los reportes. Comprueba el registro de errores de PHP y la conexión con MySQL.';
}
$isvalid = !empty($payload['output']['valid']);
$arrReportData = $payload['output']['response'] ?? [];
$total = (int)($payload['output']['total'] ?? 0);
$page = (int)($payload['output']['page'] ?? 1);
$pages = (int)($payload['output']['pages'] ?? 1);

// Apertura por URL: funciona aunque se bloquee el JavaScript que controla los botones.
$pgsOpen = (string)($_GET['pgs_open'] ?? '');
$pgsOpenId = filter_var($_GET['pgs_report'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
if (!in_array($pgsOpen, ['after','edit','detail','finalize','delete'], true)) $pgsOpen = '';
$pgsSelected = null;
if ($pgsOpen && $pgsOpenId) {
    foreach ($arrReportData as $pgsRow) {
        if ((int)($pgsRow['id'] ?? 0) === $pgsOpenId) { $pgsSelected = $pgsRow; break; }
    }
}
if (!$pgsSelected) $pgsOpen = '';
$pgsEditable = $pgsSelected && Report::isEditable((string)($pgsSelected['estado'] ?? ''));
if (($pgsOpen === 'after' || $pgsOpen === 'edit' || $pgsOpen === 'finalize') && (!$edit || !$pgsEditable)) $pgsOpen = '';
if ($pgsOpen === 'delete' && !$delete) $pgsOpen = '';
if ($pgsOpen === 'finalize' && !pgsPhoto($pgsSelected['foto_despues'] ?? '')) $pgsOpen = '';
$pgsBaseQuery = $_GET;
unset($pgsBaseQuery['pgs_open'], $pgsBaseQuery['pgs_report']);
$pgsCleanUrl = 'report-list.php' . ($pgsBaseQuery ? ('?' . http_build_query($pgsBaseQuery)) : '');
function pgsActionUrl($action, $id) {
    $args = $_GET;
    $args['pgs_open'] = $action;
    $args['pgs_report'] = $id;
    return 'report-list.php?' . http_build_query($args);
}

function pgsPageUrl($page, $search, $status, $mine) {
    return '?' . http_build_query(['q'=>$search, 'status'=>$status, 'mine'=>$mine ? '1' : '0', 'page'=>$page]);
}
function pgsEsc($value) { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function pgsPhoto($filename) {
    if (!is_string($filename)) return '';
    $filename = trim($filename);
    if ($filename === '' || in_array(strtolower($filename), ['null','no_image.png','none'], true)) return '';
    // Permit filename, and older records containing a relative upload path.
    // Never permit remote URLs or path traversal from the database.
    $base = basename(str_replace('\\', '/', $filename));
    if (!preg_match('/^[a-zA-Z0-9_.-]+\.(jpe?g|png|webp)$/i', $base) || $base === 'no_image.png') return '';
    return 'admin/js/camara/foto/' . rawurlencode($base);
}
?>
<!doctype html><html lang="en"><head>
<?php include './admin/include/generic_head.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>
:root{--red:#d71e34;--dark:#111827;--muted:#667085;--border:#e5e7eb;--surface:#fff}
.content-body{background:#f4f6f9;min-height:100vh}.content-body .container-fluid{max-width:1250px;padding-top:15px;padding-bottom:35px}
.pgs-header{background:linear-gradient(125deg,#171e2a,#252d3b);color:#fff;padding:19px;border-radius:20px;margin-bottom:14px}
.pgs-header h1{font-size:1.3rem;font-weight:800;margin:0 0 5px}.pgs-header p{color:#e4e7ec;font-size:.88rem;margin:0}
.pgs-toolbar{background:#fff;border:1px solid var(--border);border-radius:18px;padding:12px;margin-bottom:14px;box-shadow:0 3px 15px #14192808}
.pgs-search{width:100%;min-height:48px;border:1px solid #d0d5dd;border-radius:12px;padding:10px 14px;font-size:16px;color:var(--dark)}
.pgs-search:focus{outline:3px solid #fbd6db;border-color:var(--red)}
.pgs-filters{display:flex;gap:7px;overflow-x:auto;padding:11px 0 2px;scrollbar-width:thin}
.pgs-filter{flex:0 0 auto;border-radius:30px;background:#f6f7f9;border:1px solid #e1e4e8;color:#344054;font-weight:700;padding:9px 13px;font-size:13px}
.pgs-filter:hover{text-decoration:none;color:#344054}.pgs-filter.active{background:var(--dark);border-color:var(--dark);color:#fff}
.pgs-summary{display:flex;justify-content:space-between;gap:10px;align-items:center;color:#475467;font-size:13px;margin:4px 2px 12px}
.pgs-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.pgs-report{background:#fff;border:1px solid var(--border);border-radius:18px;padding:15px;min-width:0;box-shadow:0 5px 18px #1018280b}
.pgs-report-head{display:flex;align-items:start;justify-content:space-between;gap:10px;margin-bottom:11px}
.pgs-report h2{margin:0;font-weight:800;font-size:1rem;color:#101828;line-height:1.32;overflow-wrap:anywhere}
.pgs-meta{font-size:.78rem;color:var(--muted);margin-top:5px}.pgs-id{font-weight:700}
.pgs-state{border-radius:999px;font-size:.73rem;padding:5px 8px;white-space:nowrap;font-weight:800;background:#fff4d6;color:#8b5b0b}
.pgs-state.finalizado{background:#dcfce7;color:#166534}.pgs-state.creado{background:#e9f3ff;color:#175cd3}
.pgs-photo-line{display:grid;grid-template-columns:80px minmax(0,1fr);gap:12px;align-items:start}
.pgs-thumb{height:86px;width:80px;border-radius:12px;object-fit:cover;background:#f1f3f6;border:1px solid #e2e5ea}
.pgs-thumb-missing{display:grid;place-items:center;text-align:center;font-size:.75rem;color:#667085;padding:5px}
.pgs-label{font-size:.69rem;text-transform:uppercase;letter-spacing:.04em;color:#667085;font-weight:800;margin:0 0 3px}
.pgs-activity{font-weight:700;color:#202939;line-height:1.4;margin:0;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.pgs-zone{font-size:.8rem;color:#475467;margin-top:8px;overflow-wrap:anywhere}.pgs-actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px;padding-top:12px;border-top:1px solid #edf0f3}
.pgs-action{border:1px solid #d0d5dd;background:#fff;border-radius:11px;padding:10px 12px;min-height:44px;color:#344054;font-weight:700;font-size:.82rem}
.pgs-primary{background:#ce1830;color:white;border-color:#ce1830;flex:1;min-width:170px}.pgs-primary:hover{background:#a91025;color:white}
.pgs-nav{display:flex;justify-content:center;align-items:center;gap:12px;margin:18px 0}.pgs-nav button{padding:10px 15px;border:1px solid #d0d5dd;background:#fff;border-radius:10px;font-weight:700}.pgs-nav button:disabled{opacity:.45}
.pgs-empty{background:#fff;border:1px dashed #d0d5dd;text-align:center;border-radius:16px;padding:30px 15px;color:#475467}
.pgs-modal-note{background:#f6f8fc;padding:11px;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:12px;font-size:.86rem;color:#344054}
#myModalAfterPhoto .modal-dialog,#myModal .modal-dialog{margin:8px auto;max-width:720px}
#myModal input.form-control,#myModal textarea.form-control{display:block;width:100%;height:48px!important;min-height:48px!important;max-height:130px;resize:vertical;box-sizing:border-box;font-size:16px!important}
#myModalAfterPhoto .modal-content,#myModal .modal-content{border-radius:17px;overflow:hidden}
#myModalAfterPhoto #video{width:100%;max-height:48vh;object-fit:contain;border-radius:12px;background:#101828}
#myModalAfterPhoto .defaultavatar{max-height:180px;object-fit:contain}
#myModalAfterPhoto .form-check{display:flex;align-items:center;gap:10px;margin:10px 0}
#myModalAfterPhoto .form-check-input{position:static;margin:0;transform:scale(1.25)}
#myModalAfterPhoto #btn_save{display:block;width:100%;min-height:49px;border-radius:12px;font-weight:800;font-size:16px}
#myModalAfterPhoto .modal-body{padding:15px}
.pgs-mobile-actions{position:sticky;bottom:0;padding:8px 0;background:linear-gradient(0deg,#fff 70%,#ffffff00);z-index:1}
@media(max-width:850px){.pgs-list{grid-template-columns:1fr}}
@media(max-width:600px){.content-body .container-fluid{padding:10px 10px 30px}.pgs-header{border-radius:15px;padding:15px}.pgs-report{padding:13px;border-radius:15px}.pgs-report h2{font-size:.95rem}.pgs-header h1{font-size:1.16rem}.pgs-toolbar{padding:10px}.modal-dialog{max-width:none!important;margin:6px!important}.modal-content{max-height:calc(100dvh - 12px);overflow-y:auto}.pgs-photo-line{grid-template-columns:68px minmax(0,1fr)}.pgs-thumb{width:68px;height:76px}.pgs-actions .pgs-action{min-height:45px}.pgs-primary{width:100%}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
/* PGS MOBILE FIX: plantilla Deznav conserva margen lateral de escritorio en vista móvil. */
@media (max-width: 991.98px) {
  html, body { width: 100% !important; max-width: 100% !important; overflow-x: hidden !important; }
  #main-wrapper { width: 100% !important; max-width: 100% !important; overflow-x: clip !important; }
  #main-wrapper > .content-body,
  #main-wrapper .content-body {
    margin-left: 0 !important;
    padding-left: 0 !important;
    left: 0 !important;
    right: auto !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    transform: none !important;
  }
  #main-wrapper .content-body > .container-fluid {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding-left: 12px !important;
    padding-right: 12px !important;
    box-sizing: border-box !important;
  }
  .pgs-header, .pgs-toolbar, .pgs-summary, .pgs-list, .pgs-report { width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; }
  .pgs-report-head { align-items: flex-start; }
  .pgs-report-head > div { min-width: 0; flex: 1; }
  .pgs-search { min-width: 0; flex: 1 1 auto; width: 1%; }
  .pgs-toolbar button[type=submit] { flex: 0 0 auto !important; min-width: 74px !important; }
  .pgs-report .pgs-state { flex: 0 0 auto; max-width: 45%; white-space: normal; text-align: center; }
  .pgs-report .pgs-actions { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); }
  .pgs-report .pgs-actions .pgs-action { min-width: 0; width: 100%; overflow-wrap: anywhere; }
  .pgs-report .pgs-actions .pgs-primary { grid-column: 1 / -1; }
  .pgs-photo-line > div { min-width:0; }
  #myModalAfterPhoto .modal-dialog, #myModal .modal-dialog, #myModalViewDetail .modal-dialog { width: calc(100% - 16px) !important; max-width: 720px !important; margin: 8px auto !important; }
}
@media (max-width: 420px) {
  #main-wrapper .content-body > .container-fluid { padding-left: 10px !important; padding-right: 10px !important; }
  .pgs-header { padding: 16px !important; }
  .pgs-report { padding: 13px !important; }
  .pgs-report-head { flex-wrap: wrap; }
  .pgs-report .pgs-state { max-width: 100%; }
  .pgs-summary { flex-wrap: wrap; }
}

/* Evitar que un preloader global o una animación del tema oculte la página. */
#preloader,.sk-three-bounce{display:none!important}
#main-wrapper,.content-body{opacity:1!important;visibility:visible!important}

/* PGS layout final: specificity against theme defaults */
.pgs-header{background:#202938!important;border:1px solid #343e50!important;box-shadow:0 6px 22px rgba(17,24,39,.11)}
.pgs-header h1,.pgs-header .pgs-header-title{color:#fff!important;opacity:1!important;visibility:visible!important;text-shadow:none!important;font-size:clamp(20px,2vw,25px)!important;line-height:1.26!important}
.pgs-header p{color:#e7eaf0!important;opacity:1!important;line-height:1.55!important;font-size:14px!important}
.pgs-search-row{display:grid!important;grid-template-columns:minmax(0,1fr) 92px!important;gap:10px!important;align-items:stretch!important;width:100%!important;min-width:0!important}
.pgs-search-row .pgs-search{box-sizing:border-box!important;display:block!important;width:100%!important;min-width:0!important;max-width:100%!important;min-height:48px!important;height:48px!important;padding:10px 14px!important;border:1px solid #cbd5e1!important;border-radius:12px!important;background-color:#fff!important;color:#101828!important;opacity:1!important;font-size:16px!important;line-height:24px!important;-webkit-appearance:none;appearance:none}
.pgs-search-row .pgs-search::placeholder{color:#667085!important;opacity:1!important}
.pgs-search-row .pgs-action{width:100%!important;min-width:0!important;max-width:100%!important;min-height:48px!important;margin:0!important;padding:10px!important;flex:none!important;display:flex!important;justify-content:center!important;align-items:center!important}
.pgs-search-row .pgs-search:focus{outline:3px solid rgba(225,29,46,.15)!important;outline-offset:0!important;border-color:#ce1830!important}
.pgs-toolbar,.pgs-report{max-width:100%!important}
.pgs-list{grid-template-columns:repeat(2,minmax(0,1fr))!important;align-items:stretch!important}
.pgs-report{display:flex;flex-direction:column;gap:0}
.pgs-report .pgs-actions{margin-top:auto;padding-top:13px!important;display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:9px!important}
.pgs-report .pgs-actions .pgs-action{width:100%!important;min-width:0!important;max-width:100%!important;white-space:normal!important;line-height:1.3!important;overflow-wrap:break-word!important;min-height:44px!important;text-align:center}
.pgs-report .pgs-actions .pgs-primary{grid-column:1/-1!important}
.pgs-report .pgs-report-head{min-height:50px;gap:8px}
.pgs-report .pgs-state{max-width:42%;white-space:normal;text-align:center;flex:none}
#myModal textarea.form-control{height:auto!important;min-height:85px!important;max-height:190px!important;resize:vertical!important;font-size:16px!important}
#myModal .modal-dialog,#myModalAfterPhoto .modal-dialog,#myModalViewDetail .modal-dialog{max-width:720px!important}
#myModal .modal-content,#myModalAfterPhoto .modal-content,#myModalViewDetail .modal-content{border-radius:16px!important}
@media(min-width:992px){
 #main-wrapper .content-body .container-fluid{width:100%!important;max-width:1450px!important;padding-left:25px!important;padding-right:25px!important;margin-left:auto!important;margin-right:auto!important}
 .pgs-report .pgs-actions{grid-template-columns:repeat(4,minmax(0,1fr))!important}
 .pgs-report .pgs-actions .pgs-primary{grid-column:1/-1!important}
}
@media(min-width:1500px){.pgs-list{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:991.98px){
 .pgs-list{grid-template-columns:1fr!important}
 #main-wrapper .content-body>.container-fluid{max-width:100%!important;width:100%!important;margin:0 auto!important}
 .pgs-header h1{font-size:21px!important}
}
@media(max-width:440px){
 .pgs-search-row{grid-template-columns:minmax(0,1fr) 70px!important;gap:8px!important}
 .pgs-search-row .pgs-action{font-size:13px!important}
 .pgs-toolbar{padding:12px!important}
 .pgs-report{padding:13px!important}
 .pgs-report .pgs-actions{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}


/* Final desktop + mobile layout: keep standard theme navigation intact. */
@media (min-width:992px){
  .content-body .container-fluid{max-width:none!important;width:100%!important;padding:16px 26px 38px!important}
  .pgs-list{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important}
  .pgs-report{padding:20px!important}
  .pgs-report .pgs-actions{grid-template-columns:repeat(2,minmax(0,1fr))!important}
  .pgs-report .pgs-actions .pgs-primary{grid-column:span 2!important}
}
.pgs-header h1{color:white!important}
.pgs-photo-line{grid-template-columns:120px minmax(0,1fr)!important;align-items:center!important;gap:14px!important}
.pgs-thumb{width:120px!important;height:110px!important;object-fit:cover!important}
.pgs-thumb[hidden]{display:none!important}
.pgs-report .pgs-actions .pgs-action{font-size:14px!important;white-space:normal!important}
.pgs-report .pgs-actions .pgs-primary{font-weight:800!important}
.pgs-report .pgs-actions button:focus-visible{outline:3px solid #de435c!important;outline-offset:2px}
.pgs-after-image{display:block;max-width:100%;max-height:340px;object-fit:contain;border-radius:12px;margin:0 auto}
#myModalViewDetail .modal-body img{max-width:100%;height:auto}
#myModal .form-control{font-size:16px!important;line-height:1.5!important}
@media(max-width:650px){
 .pgs-photo-line{grid-template-columns:94px minmax(0,1fr)!important;gap:10px!important}
 .pgs-thumb{width:94px!important;height:94px!important}
 .pgs-report .pgs-actions{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .pgs-report .pgs-actions .pgs-primary{grid-column:span 2!important}
 .pgs-report .pgs-actions .pgs-action{min-height:47px!important}
}

.pgs-actions a.pgs-action{text-decoration:none!important;display:flex!important;align-items:center;justify-content:center;cursor:pointer;position:relative;z-index:2}
.pgs-actions a.pgs-action:hover{text-decoration:none!important}
.pgs-fallback-modal{display:block!important;background:rgba(0,0,0,.5);overflow-y:auto;z-index:1090!important}
.pgs-fallback-modal .modal-dialog{position:relative;z-index:1100}
.pgs-server-action{background:#fff;border-radius:16px;padding:20px;width:min(92vw,480px);margin:14vh auto;box-shadow:0 12px 50px #0003}

/* Reliable fallback panels, file input and accessible feedback */
.pgs-fallback-modal{display:block!important;opacity:1!important;visibility:visible!important}
.pgs-fallback-modal .modal-dialog{transform:none!important;pointer-events:auto!important}
.pgs-close{display:inline-block;color:white!important;text-decoration:none;font-size:28px;line-height:1;padding:4px 10px}
.pgs-photo-options{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.pgs-picker{position:relative;min-height:64px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;text-align:center;padding:12px;color:#0f172a;font-weight:800;cursor:pointer;overflow:hidden}
.pgs-picker input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;font-size:0}
.pgs-picker:focus-within{outline:3px solid #f3b8c0}
#pgsNativePreview{width:100%;max-height:300px;object-fit:contain;border-radius:12px;background:#f3f4f6;display:block;margin:10px auto}
#pgsNativePreview[hidden]{display:none}
.pgs-upload-track{height:10px;background:#e2e8f0;overflow:hidden;border-radius:10px;margin:12px 0}
.pgs-upload-track[hidden]{display:none}#pgsUploadBar{height:100%;width:0;background:#c91e38;transition:width .2s}
.pgs-feedback{min-height:20px;font-size:14px;font-weight:700;color:#344054;margin:10px 0;white-space:normal}
.pgs-feedback.is-error{color:#b42318}
#myModal textarea.form-control{height:auto!important;min-height:95px!important;max-height:250px!important;resize:vertical}
#myModalAfterPhoto .pgs-native-photo #btn_save{width:100%;min-height:52px;border-radius:12px;font-weight:800}
.pgs-live-camera{width:100%;margin:12px 0;border-radius:14px;background:#101828;padding:10px}
.pgs-live-camera[hidden]{display:none!important}
.pgs-live-camera video{display:block;width:100%;max-height:55vh;object-fit:contain;background:#000;border-radius:9px}
.pgs-camera-buttons{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.pgs-camera-buttons button{flex:1 1 130px;min-height:48px;font-weight:750}
#pgsStartCamera{font:inherit;width:100%}
.pgs-open-live{display:block;width:100%;padding:10px;margin:10px 0;border:1px solid #cbd5e1;background:#fff;border-radius:12px;color:#1f2937;font-weight:700}
.pgs-take-native input{display:block!important;opacity:0;position:absolute;inset:0;width:100%;height:100%;cursor:pointer;z-index:1}
.pgs-swal-overlay{position:fixed;inset:0;background:rgba(12,20,35,.66);z-index:2147483646;display:flex;align-items:center;justify-content:center;padding:20px}
.pgs-swal-dialog{width:min(100%,380px);background:white;border-radius:20px;padding:26px 24px;text-align:center;box-shadow:0 20px 65px #0004;color:#152033;font-family:system-ui,Arial,sans-serif}
.pgs-swal-icon{border-radius:50%;border:3px solid #ef4444;color:#dc2626;width:66px;height:66px;display:grid;place-items:center;font-size:36px;font-weight:800;margin:0 auto 16px}
.pgs-swal-icon.ok{border-color:#22c55e;color:#16a34a}
.pgs-swal-dialog h3{font-size:20px;font-weight:800;margin:0 0 8px;color:#152033}
.pgs-swal-dialog p{font-size:14px;line-height:1.5;color:#475569;white-space:pre-wrap}
.pgs-swal-dialog button{background:#cb1631;color:#fff;border:0;border-radius:10px;min-height:44px;padding:10px 30px;margin-top:10px;font-weight:800}

@media(max-width:420px){.pgs-photo-options{grid-template-columns:1fr}.pgs-picker{min-height:56px}}

/* Evidence preview: two images side-by-side; no extra requests until cards scroll into view. */
.pgs-report .pgs-photo-line{display:block!important;min-width:0}
.pgs-evidence-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:12px 0}
.pgs-evidence-item{min-width:0}
.pgs-evidence-label{font-size:11px;letter-spacing:.06em;font-weight:850;color:#475467;margin-bottom:5px}
.pgs-evidence-img,.pgs-evidence-empty{width:100%;height:135px;border-radius:12px;border:1px solid #e2e8f0;background:#f1f5f9;box-sizing:border-box}
.pgs-evidence-img{display:block;object-fit:cover}
.pgs-evidence-img[hidden],.pgs-evidence-empty[hidden]{display:none!important}
.pgs-evidence-empty{display:flex;align-items:center;justify-content:center;text-align:center;color:#64748b;font-size:12px;padding:8px}
.pgs-evidence-description{min-width:0;margin-top:8px}
@media (max-width:600px){.pgs-evidence-img,.pgs-evidence-empty{height:120px}}
@media (max-width:370px){.pgs-evidence-grid{gap:7px}.pgs-evidence-img,.pgs-evidence-empty{height:100px}}
.pgs-finalize-disabled,.pgs-finalize-disabled:disabled{opacity:.52!important;cursor:not-allowed!important;background:#f0f2f5!important;border-color:#d9dde5!important;color:#667085!important;box-shadow:none!important}

/* Clean report detail, responsive evidence viewer */
#myModalViewDetail .pgs-detail-photos{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:16px}
#myModalViewDetail .pgs-detail-photo{padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;min-width:0}
#myModalViewDetail .pgs-detail-photo h5{color:#111827!important;font-size:14px;font-weight:800;margin:0 0 10px;text-align:left}
#myModalViewDetail .pgs-detail-photo img{display:block;width:100%;height:230px;max-height:45vh;object-fit:contain;background:#e9eef5;border-radius:12px}
#myModalViewDetail .pgs-detail-photo img[hidden],#myModalViewDetail .pgs-detail-photo .pgs-detail-empty[hidden]{display:none!important}
#myModalViewDetail .pgs-detail-empty{min-height:140px;display:grid;place-items:center;text-align:center;color:#667085;font-size:14px}
#myModalViewDetail .modal-body{padding:20px!important}
#myModalViewDetail .modal-body p{text-align:left;overflow-wrap:anywhere}
@media(max-width:580px){#myModalViewDetail .pgs-detail-photos{grid-template-columns:1fr;gap:10px}#myModalViewDetail .pgs-detail-photo img{height:200px}#myModalViewDetail .modal-body{padding:15px!important}}
/* Floating non-blocking notifications, never take space inside the list */
.pgs-toast-host{position:fixed;right:16px;top:74px;z-index:2147483635;max-width:min(420px,calc(100vw - 32px));pointer-events:none}
.pgs-toast{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:15px;border:1px solid #bbe8ca;background:#f0fff5;color:#155d35;box-shadow:0 16px 40px rgba(17,24,39,.20);font:700 14px/1.4 system-ui,Arial,sans-serif;animation:pgsToastIn .18s ease;pointer-events:auto}
.pgs-toast.error{background:#fff4f4;border-color:#f6c6c6;color:#9f2020}
.pgs-toast button{border:0;background:transparent;color:inherit;font-size:22px;line-height:1;cursor:pointer;margin-left:auto}
@keyframes pgsToastIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:600px){.pgs-toast-host{top:62px;right:10px;left:10px;max-width:none}}

/* Finalize confirmation: touch-friendly and readable on phones and desktops. */
.pgs-finalize-overlay{position:fixed;inset:0;z-index:12000;display:flex;justify-content:center;align-items:center;padding:18px;background:rgba(15,23,42,.62);backdrop-filter:blur(3px)}
.pgs-finalize-box{position:relative;width:min(100%,430px);background:#fff;border-radius:23px;padding:27px 24px 23px;box-shadow:0 25px 70px #0004;color:#182230;text-align:center;font-family:system-ui,-apple-system,Arial,sans-serif}
.pgs-finalize-icon{width:66px;height:66px;display:grid;place-items:center;margin:0 auto 14px;border:3px solid #f9c74f;border-radius:50%;color:#bd7210;font-size:36px;font-weight:700}
.pgs-finalize-box h3{margin:0 0 10px;font-size:23px!important;line-height:1.2;color:#101828!important;font-weight:800}
.pgs-finalize-box p{font-size:14px;line-height:1.55;color:#475467;margin:0 0 14px}
.pgs-finalize-report{padding:10px 12px;margin:10px 0 14px;background:#f4f6fa;border-radius:10px;font-weight:700;color:#344054;font-size:13px;overflow-wrap:anywhere}
.pgs-finalize-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:19px}
.pgs-finalize-actions .pgs-fin-btn{display:flex;align-items:center;justify-content:center;border-radius:12px;min-height:49px;width:100%;padding:10px;border:1px solid #cbd5e1;background:white;color:#344054;font-size:14px;font-weight:800;text-decoration:none;cursor:pointer}
.pgs-finalize-actions .pgs-fin-confirm{border-color:#ce1830;background:#ce1830;color:white}
.pgs-finalize-actions .pgs-fin-confirm:disabled{opacity:.6;cursor:wait}
.pgs-finalize-error{color:#b42318;background:#fff1f1;border:1px solid #fda29b;padding:11px;border-radius:10px;margin-bottom:12px;font-size:13px}
@media(max-width:420px){.pgs-finalize-box{padding:23px 17px}.pgs-finalize-box h3{font-size:21px!important}}

/* PGS final navigation repair: scoped to this page only. */
@media (min-width: 992px) {
  html body #main-wrapper { padding-top:0 !important; margin-top:0 !important; }
  html body #main-wrapper > .header { position:relative !important; top:auto !important; margin-top:0 !important; transform:none !important; }
  html body #main-wrapper > .content-body { margin-top:0 !important; padding-top:0 !important; }
  html body #main-wrapper > .nav-header { top:0 !important; margin-top:0 !important; }
}
/* An independent mobile button avoids theme's conflicting hamburger handlers. */
#pgsMobileMenuButton { display:none; }
@media (max-width:991.98px) {
  #pgsMobileMenuButton {
    position:fixed !important;display:flex !important;align-items:center;justify-content:center;
    left:clamp(105px, 34vw, 145px);top:10px;width:48px;height:46px;
    background:#101116;border:1px solid #55545c;border-radius:13px;
    color:#fff;z-index:2147482000;cursor:pointer;box-shadow:0 6px 16px #0003;
    -webkit-tap-highlight-color:transparent;
  }
  #pgsMobileMenuButton svg {display:block;width:24px;height:24px;pointer-events:none}
  /* Hide the theme trigger only on this page to prevent competing toggles. */
  #main-wrapper .nav-control, #main-wrapper .hamburger {visibility:hidden !important;pointer-events:none !important;}
  body.pgs-nav-open #main-wrapper .deznav {
    display:block !important; transform:translateX(0) !important;
    left:0 !important;opacity:1 !important;visibility:visible !important;
    pointer-events:auto !important;z-index:2147481000 !important;
  }
  #main-wrapper .deznav {z-index:2147481000 !important;}
  #pgsMobileBackdrop {display:none;position:fixed;inset:70px 0 0;background:rgba(3,9,20,.6);z-index:2147480999;}
  body.pgs-nav-open #pgsMobileBackdrop {display:block;}
  body.pgs-nav-open {overflow:hidden !important;}
}

/* Layout-only corrections: preserve all reporting, camera and save handlers. */
@media (min-width: 992px) {
  /* Place the dashboard header at the top instead of leaving an unused band. */
  #main-wrapper > .header, #main-wrapper .header { top: 0 !important; margin-top: 0 !important; }
  #main-wrapper > .content-body { padding-top: 0 !important; }
  #main-wrapper .content-body > .container-fluid { padding-top: 16px !important; }
}
@media (max-width: 991.98px) {
  /* The old overflow:clip on #main-wrapper hides the sliding navigation. */
  html, body { overflow-x: hidden !important; }
  #main-wrapper { overflow: visible !important; overflow-x: visible !important; }
  #main-wrapper .content-body { overflow: visible !important; }
  #main-wrapper .deznav {
    display:block !important;
    position:fixed !important;
    left:0 !important;
    top:70px !important;
    bottom:0 !important;
    width:min(300px, 86vw) !important;
    height:calc(100dvh - 70px) !important;
    max-height:calc(100dvh - 70px) !important;
    transform:translate3d(-110%,0,0) !important;
    transition:transform .24s ease !important;
    visibility:visible !important;
    opacity:1 !important;
    overflow-y:auto !important;
    z-index:1205 !important;
    box-shadow:10px 0 30px rgba(0,0,0,.18);
  }
  body.pgs-nav-open #main-wrapper .deznav {
    transform:translate3d(0,0,0) !important;
  }
  .pgs-nav-backdrop {
    display:none;
    position:fixed;
    inset:70px 0 0;
    background:rgba(10,17,29,.56);
    z-index:1204;
  }
  body.pgs-nav-open .pgs-nav-backdrop { display:block; }
  #main-wrapper .header { z-index:1206 !important; }
}
@media (min-width:992px) { .pgs-nav-backdrop{display:none !important;} }

/* PGS navigation root fix: nav-header was position:relative in generic_header.php.
   Its desktop height occupied normal document flow and pushed the header down. */
#main-wrapper { position:relative !important; }
#main-wrapper > .nav-header.nav-header--saas {
  position:fixed !important; top:0 !important; left:0 !important;
  margin:0 !important; transform:none !important;
  z-index:1300 !important;
}
@media(min-width:992px) {
  html body #main-wrapper > .header.header--saas {
    position:relative !important; top:auto !important;
    margin:0 0 0 auto !important; transform:none !important;
  }
  html body #main-wrapper > .content-body { margin-top:0 !important; padding-top:0 !important; }
  #pgsMobileMenuButton { display:none !important; }
}
@media(max-width:991.98px) {
  #main-wrapper > .nav-header.nav-header--saas { width:132px !important; height:70px !important; }
  #main-wrapper > .header.header--saas { position:relative !important; top:auto !important; margin-top:0 !important; }
  #main-wrapper .nav-control.nav-control--saas { display:none !important; }
  #pgsMobileMenuButton {
    display:flex !important; position:fixed !important;
    top:10px !important; left:139px !important;
    height:46px !important; width:47px !important;
    border-radius:12px !important;
    align-items:center !important; justify-content:center !important;
    border:1px solid #565663 !important; background:#15171d !important;
    color:white !important; z-index:2147483000 !important;
    cursor:pointer !important; touch-action:manipulation;
  }
  #pgsMobileMenuButton svg { width:24px;height:24px;pointer-events:none; }
  html body #main-wrapper .deznav#pgsMainNavigation {
    position:fixed !important; display:block !important;
    top:70px !important; bottom:0 !important; left:0 !important;
    width:min(310px,88vw) !important; height:calc(100dvh - 70px) !important;
    overflow-y:auto !important; visibility:visible !important; opacity:1 !important;
    transform:translateX(-110%) !important;
    transition:transform .2s ease !important;
    z-index:2147482000 !important; pointer-events:none !important;
  }
  html body.pgs-nav-open #main-wrapper .deznav#pgsMainNavigation {
    transform:translateX(0) !important;
    pointer-events:auto !important;
  }
  #pgsMobileBackdrop { position:fixed !important; inset:70px 0 0 !important;
    background:rgba(5,10,20,.66) !important; z-index:2147481500 !important;
    display:none !important; }
  html body.pgs-nav-open #pgsMobileBackdrop { display:block !important; }
}
</style></head><body>
<!-- Sin preloader bloqueante: se evita una pantalla blanca si falla el JavaScript del tema. -->
<div id="main-wrapper">
<?php include './admin/include/generic_header.php'; ?>
<div class="deznav"><div class="deznav-scroll"><?php include './admin/include/generic_navbar.php'; ?></div></div>
<button type="button" id="pgsMobileMenuButton" aria-label="Open navigation menu" aria-controls="pgsMainNavigation" aria-expanded="false">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
</button>
<div id="pgsMobileBackdrop" aria-hidden="true"></div>
<script>
/* Runs immediately after the menu exists, independently of footer scripts. */
(function(){
  var b=document.getElementById('pgsMobileMenuButton');
  var menu=document.querySelector('#main-wrapper .deznav');
  var bg=document.getElementById('pgsMobileBackdrop');
  if(!b||!menu||!bg)return;
  menu.id='pgsMainNavigation';
  function closeMenu(){
    document.body.classList.remove('pgs-nav-open');
    b.setAttribute('aria-expanded','false');
    b.setAttribute('aria-label','Open navigation menu');
  }
  b.addEventListener('click',function(e){
    e.preventDefault();e.stopPropagation();
    var open=!document.body.classList.contains('pgs-nav-open');
    document.body.classList.toggle('pgs-nav-open',open);
    b.setAttribute('aria-expanded',String(open));
    b.setAttribute('aria-label',open?'Close navigation menu':'Open navigation menu');
  });
  bg.addEventListener('click',closeMenu);
  menu.addEventListener('click',function(e){if(e.target.closest('a'))closeMenu()});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeMenu()});
  window.addEventListener('resize',function(){if(window.innerWidth>=992)closeMenu()});
})();
</script>
<div class="content-body"><div class="container-fluid">
<section class="pgs-header"><h1>Find your activity report</h1><p>Find the original activity, add the after photo, then complete the report.</p></section>
<form class="pgs-toolbar" method="get" action="report-list.php" role="search">
<label for="pgsSearch" class="sr-only">Search reports</label>
<div class="pgs-search-row"><input type="search" class="pgs-search" id="pgsSearch" name="q" value="<?=pgsEsc($search)?>" placeholder="Search property, zone, activity or #ID" autocomplete="off"><button class="pgs-action pgs-primary" type="submit">Find</button></div>
<div class="pgs-filters" aria-label="Filter reports">
<a class="pgs-filter <?= $status==='open' ? 'active':'' ?>" href="<?=pgsEsc(pgsPageUrl(1,$search,'open',$mine))?>">To complete</a>
<a class="pgs-filter <?= $status==='all' ? 'active':'' ?>" href="<?=pgsEsc(pgsPageUrl(1,$search,'all',$mine))?>">All</a>
<a class="pgs-filter <?= $status==='finalizado' ? 'active':'' ?>" href="<?=pgsEsc(pgsPageUrl(1,$search,'finalizado',$mine))?>">Finalized</a>
</div>
<div style="display:flex;align-items:center;gap:8px;margin-top:12px"><input type="hidden" name="mine" value="0"><input type="checkbox" id="pgsMine" name="mine" value="1" <?=$mine?'checked':''?> onchange="this.form.submit()"><label for="pgsMine" style="margin:0;color:#344054;font-weight:700">Only my reports</label><input type="hidden" name="status" value="<?=pgsEsc($status)?>"></div>
</form>
<div class="pgs-summary"><span id="pgsCount" aria-live="polite"><?= $total ?> matching reports</span><span>Page <?= $page ?> / <?= $pages ?></span></div>
<?php if ($pgsLoadError !== ''): ?><div class="pgs-empty" role="status"><?=pgsEsc($pgsLoadError)?></div><?php endif; ?>
<?php if (($_GET['edit_saved'] ?? '') === '1'): ?>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.PGSToast)window.PGSToast('Report changes saved successfully.','success',1500);});</script>
<?php endif; ?>
<div class="pgs-list" id="pgsList">
<?php foreach ($arrReportData as $r):
 $id=(int)($r['id']??0); $state=(string)($r['estado']??''); $editable=Report::isEditable($state);
 $unit=(string)($r['unidad_nombre']??$r['propiedad_nombre']??$r['unidad_usuario_nombre']??'Property not specified');
 $activity=(string)($r['actividades']??''); $zone=(string)($r['zone']??''); $date=(string)($r['dtcreate']??'');
 $before=pgsPhoto($r['foto_antes']??''); $after=pgsPhoto($r['foto_despues']??'');
 $canFinalize = $edit && $editable && $after !== '';
 $rowStatusLabel=Report::statusLabel($state);

?>
<article class="pgs-report" data-state="<?=pgsEsc($state)?>" data-id="<?=$id?>" data-property="<?=pgsEsc($unit)?>" data-activity="<?=pgsEsc($activity)?>" data-zone="<?=pgsEsc($zone)?>" data-before="<?=pgsEsc($before)?>" data-after="<?=pgsEsc($after)?>" data-observations="<?=pgsEsc($r['observaciones']??'')?>" data-date="<?=pgsEsc($date)?>">
<div class="pgs-report-head"><div><h2><?=pgsEsc($unit)?></h2><div class="pgs-meta"><span class="pgs-id">#<?=$id?></span> · <?=pgsEsc($date)?></div></div><span class="pgs-state <?=pgsEsc(preg_replace('/[^a-z0-9_-]/i','',$state))?>"><?=pgsEsc($rowStatusLabel)?></span></div>
<div class="pgs-photo-line">
<div class="pgs-evidence-grid">
  <div class="pgs-evidence-item">
    <div class="pgs-evidence-label">BEFORE</div>
    <?php if ($before): ?>
    <img class="pgs-evidence-img" src="<?=pgsEsc($before)?>" alt="Before photo, report #<?=$id?>" loading="lazy" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
    <div class="pgs-evidence-empty" hidden>Image unavailable</div>
    <?php else: ?><div class="pgs-evidence-empty">No photo</div><?php endif; ?>
  </div>
  <div class="pgs-evidence-item">
    <div class="pgs-evidence-label">AFTER</div>
    <?php if ($after): ?>
    <img class="pgs-evidence-img" src="<?=pgsEsc($after)?>" alt="After photo, report #<?=$id?>" loading="lazy" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
    <div class="pgs-evidence-empty" hidden>Image unavailable</div>
    <?php else: ?><div class="pgs-evidence-empty">Not added yet</div><?php endif; ?>
  </div>
</div>
<div class="pgs-evidence-description"><div class="pgs-label">Original activity</div><p class="pgs-activity"><?=pgsEsc($activity?:'No description')?></p><?php if ($zone!==''): ?><p class="pgs-zone"><strong>Zone:</strong> <?=pgsEsc($zone)?></p><?php endif; ?></div>
</div>
<div class="pgs-actions">
<?php if ($edit && $editable): ?>
<a class="pgs-action pgs-primary" data-action="after" data-report-id="<?=$id?>" href="<?=pgsEsc(pgsActionUrl('after',$id))?>">📷 Add after photo</a>
<a class="pgs-action" data-action="edit" data-report-id="<?=$id?>" href="<?=pgsEsc(pgsActionUrl('edit',$id))?>">Edit description</a>
<?php if ($canFinalize): ?>
<a class="pgs-action" data-action="finalize" data-report-id="<?=$id?>" href="<?=pgsEsc(pgsActionUrl('finalize',$id))?>">Finalize</a>
<?php else: ?>
<button type="button" class="pgs-action pgs-finalize-disabled" disabled aria-disabled="true" title="Upload the after photo before finalizing">Finalize <span aria-hidden="true">🔒</span></button>
<?php endif; ?>
<?php endif; ?>
<?php if ($view || $edit): ?><a class="pgs-action" data-action="detail" data-report-id="<?=$id?>" href="<?=pgsEsc(pgsActionUrl('detail',$id))?>">View details</a><?php endif; ?>
<?php if ($delete): ?><a class="pgs-action" data-action="delete" data-report-id="<?=$id?>" href="<?=pgsEsc(pgsActionUrl('delete',$id))?>">Delete</a><?php endif; ?>
</div></article>
<?php endforeach; ?>
</div>
<?php if ($isvalid && !$arrReportData): ?><div class="pgs-empty">No reports found. Try another word or choose All.</div><?php endif; ?>
<nav class="pgs-nav" aria-label="Report pages">
<?php if ($page>1): ?><a class="pgs-action" href="<?=pgsEsc(pgsPageUrl($page-1,$search,$status,$mine))?>">← Previous</a><?php endif; ?>
<span>Page <?= $page ?> of <?= $pages ?></span>
<?php if ($page<$pages): ?><a class="pgs-action" href="<?=pgsEsc(pgsPageUrl($page+1,$search,$status,$mine))?>">Next →</a><?php endif; ?>
</nav>
</div></div></div>
    <!-- Modal editar solo campos -->
    <div class="modal fade<?= $pgsOpen === 'edit' ? ' show pgs-fallback-modal' : '' ?>" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd"
        aria-hidden="<?= $pgsOpen === 'edit' || $pgsOpen === 'detail' || $pgsOpen === 'after' ? 'false' : 'true' ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header card-header card-header-danger">
<?php if ($pgsOpen): ?><a href="<?=pgsEsc($pgsCleanUrl)?>" style="color:white;margin-right:12px;font-weight:bold">← Back</a><?php endif; ?>
                    <h4 class="modal-title">Edit report</h4>
                    <a class="close pgs-close" href="<?=pgsEsc($pgsCleanUrl)?>" aria-label="Close">&times;</a>
                </div>

                <div class="modal-body">
                    <form id="formcreate" method="post" action="<?=pgsEsc($_SERVER['REQUEST_URI'] ?? 'report-list.php')?>" autocomplete="off">
                        <input type="hidden" name="pgs_action" value="save_report_text">
                        <input type="hidden" name="pgs_csrf" value="<?=pgsEsc($_SESSION['pgs_edit_csrf'])?>">
                        <input type="hidden" name="op" id="edit_report_op" />
                        <input type="hidden" name="id" id="edit_report_id" value="<?= $pgsOpen==='edit' ? (int)$pgsOpenId : 0 ?>" />
                        <?php if ($pgsEditError !== ''): ?><div role="alert" style="padding:12px;border:1px solid #f3b3b3;border-radius:12px;background:#fff3f3;color:#9c1f1f;margin:10px 0"><?=pgsEsc($pgsEditError)?></div><?php endif; ?>
                        <div class="row">

                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Activities<b class="errLbl">*</b></label>
                                    <textarea rows="3" id="actividades" name="actividades" class="form-control" placeholder="Detail activity" required><?= $pgsOpen==='edit' ? pgsEsc($pgsEditError !== '' ? ($_POST['actividades'] ?? '') : ($pgsSelected['actividades']??'')) : '' ?></textarea>
                                </div>
                            </div>

                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Observations</label>
                                    <textarea rows="3" class="form-control" id="observaciones" name="observaciones"
                                        placeholder="Write any additional comments"><?= $pgsOpen==='edit' ? pgsEsc($pgsEditError !== '' ? ($_POST['observaciones'] ?? '') : ($pgsSelected['observaciones']??'')) : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                    <a class="btn btn-outline-dark btn-rounded" href="<?=pgsEsc($pgsCleanUrl)?>">Cancel</a>
                    <button type="submit" form="formcreate" id="pgsUpdateFields"
                        class="btn btn-primary btn-rounded">Save changes</button>
                </div>

            </div>
        </div>
    </div>


    <!-- Modal ver detalle (reportes finalizados) -->
    <div class="modal fade<?= $pgsOpen === 'detail' ? ' show pgs-fallback-modal' : '' ?>" id="myModalViewDetail" tabindex="-1" role="dialog" aria-hidden="<?= $pgsOpen === 'edit' || $pgsOpen === 'detail' || $pgsOpen === 'after' ? 'false' : 'true' ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header card-header card-header-danger">
<?php if ($pgsOpen): ?><a href="<?=pgsEsc($pgsCleanUrl)?>" style="color:white;margin-right:12px;font-weight:bold">← Back</a><?php endif; ?>
                    <h4 class="modal-title">Report detail <span id="view_detail_item"><?= $pgsOpen === 'detail' ? '#'.(int)$pgsOpenId : '' ?></span></h4>
                    <a class="close pgs-close" href="<?=pgsEsc($pgsCleanUrl)?>" aria-label="Close">&times;</a>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Status</strong></p>
                            <p id="view_detail_estado" class="text-muted"><?= $pgsOpen === 'detail' ? pgsEsc($pgsSelected['estado'] ?? '—') : '—' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Date</strong></p>
                            <p id="view_detail_fecha" class="text-muted"><?= $pgsOpen === 'detail' ? pgsEsc($pgsSelected['dtcreate'] ?? '—') : '—' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Property / Unit</strong></p>
                            <p id="view_detail_unidad" class="text-muted"><?= $pgsOpen === 'detail' ? pgsEsc($pgsSelected['unidad_nombre'] ?? '—') : '—' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Zone</strong></p>
                            <p id="view_detail_zone" class="text-muted"><?= $pgsOpen === 'detail' ? pgsEsc($pgsSelected['zone'] ?? '—') : '—' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Activities</strong></p>
                            <p id="view_detail_actividades" class="text-muted"><?= $pgsOpen === 'detail' ? pgsEsc($pgsSelected['actividades'] ?? '—') : '—' ?></p>
                        </div>
                        <div class="col-md-12">
                            <p class="mb-1"><strong>Observations</strong></p>
                            <p id="view_detail_observaciones" class="text-muted"><?= $pgsOpen === 'detail' ? pgsEsc($pgsSelected['observaciones'] ?? '—') : '—' ?></p>
                        </div>
                    </div>
                    <div class="pgs-detail-photos">
                        <?php foreach ([['foto_antes','Before','view_detail_foto_antes','view_detail_foto_antes_empty'],['foto_despues','After','view_detail_foto_despues','view_detail_foto_despues_empty']] as $photoSpec):
                          $detailPhoto = ($pgsOpen === 'detail' && $pgsSelected) ? pgsPhoto($pgsSelected[$photoSpec[0]] ?? '') : '';
                        ?>
                        <section class="pgs-detail-photo">
                            <h5><?=pgsEsc($photoSpec[1])?> photo</h5>
                            <img id="<?=pgsEsc($photoSpec[2])?>" src="<?=pgsEsc($detailPhoto ?: 'assets/images/no-image.png')?>" alt="<?=pgsEsc($photoSpec[1])?> evidence" decoding="async" <?= $detailPhoto ? '' : 'hidden' ?> onerror="this.hidden=true;document.getElementById('<?=pgsEsc($photoSpec[3])?>').hidden=false">
                            <div class="pgs-detail-empty" id="<?=pgsEsc($photoSpec[3])?>" <?= $detailPhoto ? 'hidden' : '' ?>>No <?=strtolower(pgsEsc($photoSpec[1]))?> photo available</div>
                        </section>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-dark btn-rounded" href="<?=pgsEsc($pgsCleanUrl)?>">Close</a>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade<?= $pgsOpen === 'after' ? ' show pgs-fallback-modal' : '' ?>" id="myModalAfterPhoto" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd"
        aria-hidden="<?= $pgsOpen === 'edit' || $pgsOpen === 'detail' || $pgsOpen === 'after' ? 'false' : 'true' ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="frm_foto">
                    <div class="modal-header card-header card-header-danger">
<?php if ($pgsOpen): ?><a href="<?=pgsEsc($pgsCleanUrl)?>" style="color:white;margin-right:12px;font-weight:bold">← Back</a><?php endif; ?>
                        <h4 class="modal-title">After photo · Report <span id="item"><?= $pgsOpen === 'after' ? (int)$pgsOpenId : '' ?></span></h4>
                        <button type="button" class="close"
                            data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <div id="pgsAfterContext" class="pgs-modal-note" aria-live="polite"><?= $pgsOpen==='after' ? pgsEsc('Report #'.$pgsOpenId.' · '.($pgsSelected['unidad_nombre']??'').' · '.($pgsSelected['actividades']??'')) : 'Confirm this is the activity you completed, then take the after photo.' ?></div>
                        <p class="small text-muted">To explain the work performed, use <strong>Edit description</strong> on the report card. The photo and description are saved separately by the existing system.</p>
                        <div class="col-md-12">
                            <div class="card">

                                <div class="card-body table-responsive">
                                    <input type="hidden" name="op" id="op" />
                                    <input type="hidden" name="id" id="id" value="<?= $pgsOpen==='after' ? (int)$pgsOpenId : 0 ?>" />

                                    <div class="pgs-native-photo">
                                        <div class="pgs-photo-options">
                                            <button class="pgs-picker" id="pgsStartCamera" type="button" style="width:100%;font:inherit;">📷 Take photo</button>
                                            <label class="pgs-picker" for="pgsNativeGallery">▧ Choose from phone<input id="pgsNativeGallery" type="file" accept="image/*"></label>
                                        </div>
                                        <label for="pgsCameraDevices" style="display:block;font-weight:700;margin:10px 0 5px">Available camera</label><select id="pgsCameraDevices" class="form-control" style="width:100%;min-height:46px"><option value="">Automatic (prefer rear camera)</option></select><div id="pgsCameraStatus" role="status" aria-live="polite" style="font-size:13px;color:#475569;margin:8px 0">Tap Take photo to activate the camera.</div>
                                        <div id="pgsLiveCamera" class="pgs-live-camera" hidden>
                                          <video id="pgsCameraVideo" autoplay muted playsinline aria-label="Live camera preview"></video>
                                          <div class="pgs-camera-buttons">
                                            <button id="pgsCaptureCamera" type="button" class="btn btn-danger" disabled>📸 Capture photo</button>
                                            <button id="pgsStopCamera" type="button" class="btn btn-outline-secondary">Cancel camera</button>
                                          </div>
                                        </div>
                                        <p class="pgs-modal-note" style="margin:12px 0">Tap Take photo to activate the camera, then Capture photo. Or choose an image from your device. Images are compressed before upload.</p>
                                        <img id="pgsNativePreview" alt="After photo preview" hidden>
                                        <p id="pgsFileDescription" class="text-muted small" aria-live="polite">No photo selected yet.</p>
                                        <div class="pgs-upload-track" id="pgsUploadTrack" hidden><div id="pgsUploadBar"></div></div>
                                        <p id="pgsFormMessage" class="pgs-feedback" role="status" aria-live="polite"></p>
                                        <button class="btn btn-danger" type="submit" id="btn_save">Save after photo</button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="modal-footer">
                        <a class="btn btn-outline-dark btn-rounded" href="<?=pgsEsc($pgsCleanUrl)?>">Cancel</a>
                        <!-- <button type="submit" id="btn_save" class="btn btn-primary btn-rounded">Save</button> -->
                    </div>
                </form>
            </div>
        </div>
    </div>

 
<script>
/* Standalone notifications: independent of jQuery and Bootstrap. */
window.PGSToast = function(text,type,delay){
 var timeout=(typeof delay==='number')?delay:1500;
 if(window.Swal&&typeof window.Swal.fire==='function'){
  window.Swal.fire({toast:true,position:'top-end',icon:type==='error'?'error':'success',title:String(text),showConfirmButton:false,timer:timeout,timerProgressBar:true});return;
 }
 var host=document.getElementById('pgsToastHost');
 if(!host){host=document.createElement('div');host.id='pgsToastHost';host.className='pgs-toast-host';document.body.appendChild(host)}
 host.replaceChildren();var box=document.createElement('div');box.className='pgs-toast'+(type==='error'?' error':'');box.setAttribute('role','status');
 var label=document.createElement('span');label.textContent=(type==='error'?'⚠ ':'✓ ')+String(text);
 var close=document.createElement('button');close.type='button';close.setAttribute('aria-label','Dismiss notification');close.textContent='×';close.onclick=function(){box.remove()};box.append(label,close);host.appendChild(box);
 setTimeout(function(){if(box.isConnected)box.remove()},timeout);
};
</script>
<script>
/* PGS isolated camera: intentionally independent from jQuery, Bootstrap and report.js. */
(function () {
  'use strict';
  const byId = id => document.getElementById(id);
  const form = byId('frm_foto');
  if (!form || !form.querySelector('#pgsStartCamera')) return;
  const start = byId('pgsStartCamera'), list = byId('pgsCameraDevices'), status = byId('pgsCameraStatus');
  const live = byId('pgsLiveCamera'), video = byId('pgsCameraVideo'), capture = byId('pgsCaptureCamera');
  const stop = byId('pgsStopCamera'), gallery = byId('pgsNativeGallery'), preview = byId('pgsNativePreview');
  const filename = byId('pgsFileDescription'), save = byId('btn_save'), track = byId('pgsUploadTrack'), bar = byId('pgsUploadBar');
  const feedback = byId('pgsFormMessage');
  let stream = null, generation = 0, selected = null, objectUrl = null, saving = false;
  function message(text, isError) { if (status) {status.textContent=text;status.style.color=isError?'#b42318':'#344054';} }
  function notice(text, isError) {
    if (feedback) {feedback.textContent=isError?text:'';feedback.classList.toggle('is-error',Boolean(isError));} if(!isError && /saved successfully/i.test(text)){window.PGSToast?.(text,'success',1500);}
    if (isError) {
      if (window.Swal && typeof window.Swal.fire === 'function') window.Swal.fire({icon:'error',title:'Camera',text,confirmButtonColor:'#c91e38'});
      else if (typeof window.swal === 'function') window.swal('Camera',text,'error');
    }
  }
  function closeCamera() {
    generation++;
    if (stream) {stream.getTracks().forEach(t => t.stop());stream=null;}
    video.pause();video.srcObject=null;
    live.hidden=true;capture.disabled=true;start.disabled=false;
  }
  async function devices() {
    if (!navigator.mediaDevices?.enumerateDevices) return;
    try {
      const cams=(await navigator.mediaDevices.enumerateDevices()).filter(d=>d.kind==='videoinput');
      const chosen=list.value;
      list.replaceChildren(new Option('Automatic (prefer rear camera)',''));
      cams.forEach((d,i)=>list.add(new Option(d.label || 'Camera '+(i+1),d.deviceId)));
      if (cams.some(d=>d.deviceId===chosen)) list.value=chosen;
      if (!stream) message(cams.length+' camera(s) detected. Tap Take photo to request access.',false);
    } catch (err) {message('Could not list cameras: '+err.message,true);}
  }
  async function openCamera() {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      notice('Camera requires HTTPS or localhost and permission from the browser.',true);return;
    }
    closeCamera();const ticket=++generation;
    start.disabled=true;message('Requesting camera permission…',false);
    const desired=list.value ? {deviceId:{exact:list.value}} : {facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}};
    try {
      let s;
      try {s=await navigator.mediaDevices.getUserMedia({video:desired,audio:false});}
      catch (err) {if(list.value && ['OverconstrainedError','NotFoundError'].includes(err.name)) s=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});else throw err;}
      if(ticket!==generation){s.getTracks().forEach(t=>t.stop());return;}
      stream=s;video.srcObject=s;live.hidden=false;
      await video.play();capture.disabled=false;
      const current=s.getVideoTracks()[0];message('Camera active: '+(current?.label||'Camera')+' — ready to capture.',false);
      await devices();
    } catch (err) {
      if(ticket!==generation)return;
      closeCamera();
      let hint=err.name==='NotAllowedError'?'Allow camera access in browser permissions.':err.name==='NotReadableError'?'Camera is being used by another application.':err.message;
      message('Camera error: '+hint,true);notice('Could not activate the camera: '+hint,true);
    } finally {start.disabled=false;}
  }
  function selectPhoto(file) {
    if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}
    selected=null;preview.hidden=true;preview.removeAttribute('src');
    if(!file){filename.textContent='No photo selected.';return;}
    if(!file.type.startsWith('image/')){notice('Please select an image.',true);return;}
    if(file.size>30*1024*1024){notice('Image exceeds the 30 MB limit.',true);return;}
    selected=file;objectUrl=URL.createObjectURL(file);preview.src=objectUrl;preview.hidden=false;
    filename.textContent='Photo ready: '+(file.name||'camera-photo.jpg')+' ('+Math.round(file.size/1024)+' KB)';
    message('Photo captured. You can now save it.',false);
  }
  start.addEventListener('click', openCamera);
  list.addEventListener('change',()=>{if(stream)openCamera();});
  stop.addEventListener('click',()=>{closeCamera();message('Camera stopped.',false);});
  capture.addEventListener('click',()=>{
    if(!stream||!video.videoWidth){notice('Wait until the live camera is ready.',true);return;}
    let c=document.createElement('canvas'),factor=Math.min(1,1280/Math.max(video.videoWidth,video.videoHeight));
    c.width=Math.max(1,Math.round(video.videoWidth*factor));c.height=Math.max(1,Math.round(video.videoHeight*factor));
    c.getContext('2d').drawImage(video,0,0,c.width,c.height);
    c.toBlob(blob=>{if(!blob){notice('Could not capture image.',true);return;}
      selectPhoto(new File([blob],'after-photo.jpg',{type:'image/jpeg'}));closeCamera();
    },'image/jpeg',.7);
  });
  gallery.addEventListener('change',()=>{closeCamera();selectPhoto(gallery.files?.[0]);});
  document.addEventListener('visibilitychange',()=>{if(document.hidden)closeCamera();});
  window.addEventListener('pagehide',closeCamera);
  function resize(file){return new Promise((resolve,reject)=>{
    const u=URL.createObjectURL(file),image=new Image();
    image.onload=()=>{try{const ratio=Math.min(1,1280/Math.max(image.naturalWidth,image.naturalHeight)),c=document.createElement('canvas');
      c.width=Math.max(1,Math.round(image.naturalWidth*ratio));c.height=Math.max(1,Math.round(image.naturalHeight*ratio));
      const ctx=c.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(image,0,0,c.width,c.height);
      resolve(c.toDataURL('image/jpeg',.67));}catch(e){reject(e);}finally{URL.revokeObjectURL(u);}};
    image.onerror=()=>{URL.revokeObjectURL(u);reject(Error('Could not read this image.'))};image.src=u;
  });}
  form.addEventListener('submit',async e=>{
    e.preventDefault();e.stopImmediatePropagation();
    if(saving)return;
    const id=parseInt(form.querySelector('input[name="id"]')?.value||'0',10);
    if(!id){notice('Missing report ID.',true);return;}
    if(!selected){notice('Take a photo or select one from your gallery first.',true);return;}
    if(!navigator.onLine){notice('There is no internet connection.',true);return;}
    saving=true;save.disabled=true;save.textContent='Preparing image…';track.hidden=false;bar.style.width='5%';
    const reset=()=>{saving=false;save.disabled=false;save.textContent='Save after photo';};
    try{
      const data=await resize(selected);
      save.textContent='Uploading…';
      const xhr=new XMLHttpRequest();xhr.open('POST','admin/js/camara/update_photo_after.php',true);xhr.timeout=240000;
      xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');
      xhr.upload.onprogress=e=>{if(e.lengthComputable)bar.style.width=(10+85*e.loaded/e.total)+'%';};
      xhr.onload=()=>{try{const result=JSON.parse(xhr.responseText);if(xhr.status<200||xhr.status>=300||result.success!==true)throw Error(result.messages||'Server did not save image.');
        bar.style.width='100%';notice('Photo saved successfully.',false);
        
        setTimeout(()=>{const url=new URL(location.href);url.searchParams.delete('pgs_open');url.searchParams.delete('pgs_report');location.assign(url.toString())},1600);
      }catch(err){notice('Could not save: '+err.message,true);reset();}};
      xhr.onerror=xhr.ontimeout=()=>{notice('Connection interrupted. Check whether the photo was saved before retrying.',true);reset();};
      xhr.send(new URLSearchParams({id:String(id),foto:data}).toString());
    }catch(err){notice('Could not process image: '+err.message,true);reset();}
  },true);
  // Visible readiness proves this isolated controller ran even if another script fails.
  message('Camera controller ready. Detecting cameras…',false);
  devices();
})();
</script>



<?php if ($pgsOpen !== ''): ?>
<script>
// Apertura restaurable sin depender de eventos click.
window.PGS_SERVER_OPEN = <?=json_encode(['action'=>$pgsOpen,'id'=>$pgsOpenId,'row'=>[
 'estado'=>$pgsSelected['estado']??'', 'dtcreate'=>$pgsSelected['dtcreate']??'',
 'unidad'=>$pgsSelected['unidad_nombre']??$pgsSelected['propiedad_nombre']??'',
 'zone'=>$pgsSelected['zone']??'', 'actividades'=>$pgsSelected['actividades']??'',
 'observaciones'=>$pgsSelected['observaciones']??'',
 'before'=>pgsPhoto($pgsSelected['foto_antes']??''),
 'after'=>pgsPhoto($pgsSelected['foto_despues']??'')
]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
</script>
<?php endif; ?>


<?php if ($pgsOpen === 'finalize' && $pgsSelected): ?>
<div class="pgs-finalize-overlay" role="presentation">
  <section class="pgs-finalize-box" role="dialog" aria-modal="true" aria-labelledby="pgsFinTitle" aria-describedby="pgsFinDescription">
    <div class="pgs-finalize-icon" aria-hidden="true">!</div>
    <h3 id="pgsFinTitle">Finalize report?</h3>
    <div class="pgs-finalize-report">Report #<?= (int)$pgsOpenId ?> · <?= pgsEsc($pgsSelected['unidad_nombre'] ?? '') ?></div>
    <p id="pgsFinDescription">The report will be marked as finalized and can no longer be edited.</p>
    <?php if ($pgsFinalizeError !== ''): ?><div class="pgs-finalize-error" role="alert"><?= pgsEsc($pgsFinalizeError) ?></div><?php endif; ?>
    <form method="post" action="<?=pgsEsc($_SERVER['REQUEST_URI'] ?? 'report-list.php')?>" id="pgsFinalizeForm">
      <input type="hidden" name="pgs_action" value="finalize_report">
      <input type="hidden" name="pgs_csrf" value="<?=pgsEsc($_SESSION['pgs_edit_csrf'])?>">
      <input type="hidden" name="id" value="<?= (int)$pgsOpenId ?>">
      <div class="pgs-finalize-actions">
        <a class="pgs-fin-btn" href="<?=pgsEsc($pgsCleanUrl)?>">Cancel</a>
        <button class="pgs-fin-btn pgs-fin-confirm" type="submit" id="pgsFinalizeSubmit">Yes, finalize</button>
      </div>
    </form>
  </section>
</div>
<?php endif; ?>
<?php if ($pgsOpen === 'delete' && $pgsSelected): ?>
<div class="modal show pgs-fallback-modal" id="pgsConfirmFallback" role="dialog" aria-modal="true" aria-label="Confirm action">
<div class="pgs-server-action">
<h3>Delete report?</h3>
<p>Report #<?= (int)$pgsOpenId ?> · <?= pgsEsc($pgsSelected['unidad_nombre']??'') ?></p>
<p>This action cannot be undone.</p>
<div style="display:flex;gap:12px;justify-content:flex-end">
<a class="btn btn-outline-secondary" href="<?=pgsEsc($pgsCleanUrl)?>">Cancel</a>
<button type="button" class="btn btn-danger" id="pgsConfirmServerAction" data-action="delete" data-id="<?=$pgsOpenId?>">Confirm</button>
</div></div></div>
<?php endif; ?>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/generic_search.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>
<script>
(function(){
'use strict';
var opened = <?=json_encode($pgsOpen, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
var returnUrl = <?=json_encode($pgsCleanUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
function elem(id){return document.getElementById(id)}
function responseText(j){var v=j && j.output && j.output.response;return (v && typeof v.content==='string' && v.content) || (j && typeof j.messages==='string' && j.messages) || 'The server could not complete this operation.'}
function show(message,bad,done){
 var box=opened==='after'?elem('pgsFormMessage'):null;
 if(box){box.textContent=message;box.classList.toggle('is-error',!!bad)}
 if(done&&!bad){window.PGSToast?.(String(message),'success',1500);return;}
 if(bad){
   var icon='error';
   if(window.Swal&&typeof window.Swal.fire==='function'){
     window.Swal.fire({icon:icon,title:bad?'Something went wrong':'Saved successfully',text:String(message),confirmButtonColor:'#cb1631',confirmButtonText:'OK'});
   }else if(typeof window.swal==='function'){
     window.swal(bad?'Something went wrong':'Saved successfully',String(message),icon);
   }else{
     var prior=elem('pgsAlertOverlay');if(prior)prior.remove();
     var overlay=document.createElement('div');overlay.id='pgsAlertOverlay';overlay.className='pgs-swal-overlay';overlay.setAttribute('role','alertdialog');overlay.setAttribute('aria-modal','true');
     var dialog=document.createElement('div');dialog.className='pgs-swal-dialog';
     var symbol=document.createElement('div');symbol.className='pgs-swal-icon'+(bad?'':' ok');symbol.textContent=bad?'!':'✓';
     var heading=document.createElement('h3');heading.textContent=bad?'Something went wrong':'Saved successfully';
     var desc=document.createElement('p');desc.textContent=String(message);
     var ok=document.createElement('button');ok.type='button';ok.textContent='OK';ok.addEventListener('click',function(){overlay.remove()});
     dialog.append(symbol,heading,desc,ok);overlay.appendChild(dialog);document.body.appendChild(overlay);ok.focus();
   }
 }else if(!box){var target=elem('pgsGlobalStatus');if(!target){target=document.createElement('div');target.id='pgsGlobalStatus';target.setAttribute('role','status');target.style.cssText='position:fixed;z-index:999999;bottom:15px;left:15px;right:15px;max-width:650px;margin:auto;padding:14px;border-radius:12px;background:#fff;color:#111827;border:2px solid #d71e34;box-shadow:0 8px 30px #0005';document.body.appendChild(target)}target.textContent=message;}
}
function redirect(){window.location.assign(returnUrl)}
async function postAction(data,button){
 if(button.disabled)return;
 button.disabled=true;var old=button.textContent;button.textContent='Saving…';
 try{
  var r=await fetch('admin/ajax/rqst.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(data).toString(),cache:'no-store'});
  var json;try{json=await r.json()}catch(e){throw Error('Invalid server response (HTTP '+r.status+'). Check PHP logs.')}
  if(!r.ok||!json.output||json.output.valid!==true)throw Error(responseText(json));
  show('Changes saved successfully.',false,true);setTimeout(redirect,1600);
 }catch(e){show('Could not save: '+e.message,true);button.disabled=false;button.textContent=old}
}
// Finalize is a native form POST, never the AJAX router.
var finForm=elem('pgsFinalizeForm');
if(finForm){finForm.addEventListener('submit',function(){var submit=elem('pgsFinalizeSubmit');if(submit){submit.disabled=true;submit.textContent='Finalizing…';}})}
// Edit form uses native POST with CSRF validation; no click interception.
var confirm=elem('pgsConfirmServerAction');if(confirm){confirm.addEventListener('click',function(){postAction({op:confirm.dataset.action==='delete'?'delete_deport':'report_finalize',id:confirm.dataset.id},confirm)})}
// Use the same SweetAlert confirmation for Finalize and Delete.
// Keep the server-rendered dialog as a working fallback when no SweetAlert library is loaded.
if (opened === 'delete' && confirm) {
 var fallback = elem('pgsConfirmFallback');
 var isDelete = opened === 'delete';
 var heading = isDelete ? 'Delete report?' : 'Finalize report?';
 var explanation = isDelete ? 'This action cannot be undone.' : 'The report will no longer be editable.';
 var reportNumber = 'Report #' + String(confirm.dataset.id || '');
 var active = false;
 function approved() {
  if (active) return;
  active = true;
  confirm.click(); // Existing authorized endpoint and error handling remain unchanged.
 }
 function dismissed() { redirect(); }
 if (window.Swal && typeof window.Swal.fire === 'function') {
  if (fallback) fallback.style.display = 'none';
  window.Swal.fire({
   icon: isDelete ? 'warning' : 'question',
   title: heading,
   html: '<strong>' + reportNumber + '</strong><p style="margin-top:10px">' + explanation + '</p>',
   showCancelButton: true,
   confirmButtonText: isDelete ? 'Yes, delete' : 'Yes, finalize',
   cancelButtonText: 'Cancel',
   confirmButtonColor: '#d71e34',
   cancelButtonColor: '#667085',
   reverseButtons: true,
   allowOutsideClick: false,
   allowEscapeKey: true
  }).then(function(result) { if (result.isConfirmed) approved(); else dismissed(); });
 } else if (typeof window.swal === 'function') {
  // SweetAlert v1 is present in some versions of the project template.
  if (fallback) fallback.style.display = 'none';
  try {
   var result = window.swal({
    title: heading,
    text: reportNumber + '\n' + explanation,
    icon: isDelete ? 'warning' : 'info',
    type: isDelete ? 'warning' : 'info',
    buttons: ['Cancel', isDelete ? 'Yes, delete' : 'Yes, finalize'],
    dangerMode: isDelete,
    closeOnConfirm: true
   });
   if (result && typeof result.then === 'function') {
    result.then(function(ok) { if (ok) approved(); else dismissed(); });
   } else {
    // Older callback-style SweetAlert builds: use the HTML confirmation.
    if (fallback) fallback.style.display = '';
   }
  } catch (error) { if (fallback) fallback.style.display = ''; }
 }
}
// Saved status: auto-dismiss after 1.5 seconds; use SweetAlert toast where available.
if(new URLSearchParams(location.search).get('finalized')==='1'){
 window.PGSToast?.('Report finalized successfully.','success',1500);
 try{var finalUrl=new URL(location.href);finalUrl.searchParams.delete('finalized');history.replaceState(null,'',finalUrl.pathname+finalUrl.search+finalUrl.hash)}catch(e){}
}
if(new URLSearchParams(location.search).get('edit_saved')==='1'){try{var u=new URL(location.href);u.searchParams.delete('edit_saved');history.replaceState(null,'',u.pathname+u.search+u.hash)}catch(e){}}

// Populate details from server-rendered row without AJAX.
var data=window.PGS_SERVER_OPEN;if(data&&data.action==='detail'&&data.row){
 var row=data.row,columns={view_detail_item:'#'+data.id,view_detail_estado:row.estado,view_detail_fecha:row.dtcreate,view_detail_unidad:row.unidad,view_detail_zone:row.zone,view_detail_actividades:row.actividades,view_detail_observaciones:row.observaciones};
 Object.keys(columns).forEach(function(id){if(elem(id))elem(id).textContent=columns[id]||'—'});
}
// Camera and photo upload are managed by the isolated module above.

})();
</script>




</body></html>
