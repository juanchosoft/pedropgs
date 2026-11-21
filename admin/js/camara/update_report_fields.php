<?php
/** PGS Centrum - Edit activity/observations only. PHP 7.4+. */
require_once '../../classes/Util.php';
require_once '../../classes/DbConection.php';
require_once '../../include/generic_validate_session.php';
require_once '../../classes/SessionData.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function pgsEditResponse(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success'=>$success, 'messages'=>$message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {header('Allow: POST'); pgsEditResponse(false,'Method not allowed.',405);}
$userId = (int)($_SESSION['session_user']['id'] ?? 0);
if ($userId <= 0) pgsEditResponse(false,'Your session has expired. Please sign in again.',401);
if (!SessionData::getPermission(9)) pgsEditResponse(false,'You do not have permission to edit reports.',403);
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') pgsEditResponse(false,'Cross-site request rejected.',403);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $oh = parse_url($origin, PHP_URL_HOST);
    $host = parse_url('http://'.($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!is_string($oh) || !is_string($host) || strcasecmp($oh,$host) !== 0) pgsEditResponse(false,'Request origin not allowed.',403);
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$activity = $_POST['actividades'] ?? null;
$observations = $_POST['observaciones'] ?? '';
if (!$id || !is_string($activity) || !is_string($observations)) pgsEditResponse(false,'Invalid report data.',422);
$activity = trim($activity); $observations = trim($observations);
if ($activity === '') pgsEditResponse(false,'Activity description is required.',422);
if (strlen($activity) > 10000 || strlen($observations) > 10000) pgsEditResponse(false,'Text is too long.',422);
$db = null; $pdo = null;
try {
    $type = SessionData::getUserType();
    $admin = $type == Util::SuperAdmin();
    if (!$admin && $type != Util::Manager() && $type != Util::Staff()) pgsEditResponse(false,'Not authorized.',403);
    $units = array_values(array_unique(array_filter(array_map('intval',(array)SessionData::getUnidadesUser()), static function($v){return $v > 0;})));
    if (!$admin && !$units) pgsEditResponse(false,'No assigned properties.',403);
    $db = new DbConection(); $pdo = $db->openConect();
    if (!$pdo instanceof PDO) throw new RuntimeException('Database unavailable');
    $table = $db->getTable('tbl_fotos');
    $pdo->beginTransaction();
    $lookup = $pdo->prepare("SELECT id, estado, tbl_unidad_id FROM {$table} WHERE id=:id FOR UPDATE");
    $lookup->execute([':id'=>$id]);
    $row = $lookup->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $pdo->rollBack(); pgsEditResponse(false,'Report not found.',404); }
    if (!$admin && !in_array((int)$row['tbl_unidad_id'],$units,true)) { $pdo->rollBack(); pgsEditResponse(false,'You cannot edit a report from this property.',403); }
    if (!in_array((string)($row['estado'] ?? ''),['','creado','pendiente'],true)) { $pdo->rollBack(); pgsEditResponse(false,'This report is finalized and cannot be edited.',409); }
    // Do not overwrite dtcreate: it is the date when the original activity was registered.
    $stmt = $pdo->prepare("UPDATE {$table} SET actividades=:activity, observaciones=:observations, estado=:state, tbl_usuario_id_update=:uid WHERE id=:id");
    $stmt->execute([':activity'=>$activity, ':observations'=>$observations, ':state'=>'pendiente', ':uid'=>$userId, ':id'=>$id]);
    $pdo->commit();
    pgsEditResponse(true,'Report changes saved successfully.');
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('PGS edit report error: '.$e->getMessage());
    pgsEditResponse(false,'Unable to update this report. Check the server error log.',500);
} finally {if ($db) $db->closeConect();}
