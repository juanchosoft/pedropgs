<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../classes/DbConection.php';
require_once __DIR__ . '/../classes/SessionData.php';

function respond(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $view = SessionData::getPermission(21);
  if (!$view) respond(['ok' => false, 'msg' => 'No permission'], 403);
} catch (Throwable $e) {
  respond(['ok' => false, 'msg' => 'Session error'], 500);
}

$unidad = '';
try { $unidad = (string)SessionData::getUnidadUser(); } catch(Throwable $e){ $unidad = ''; }
$unidadInt = (int)$unidad;

try {
  $db  = new DbConection();
  $pdo = $db->openConect();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
  respond(['ok' => false, 'msg' => 'DB connection failed'], 500);
}

/**
 * Helpers
 */
$fetchAll = function(string $sql, array $params = []) use ($pdo): array {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $r = $st->fetchAll(PDO::FETCH_ASSOC);
  return is_array($r) ? $r : [];
};

$tableExists = function(string $table) use ($pdo): bool {
  try {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  } catch(Throwable $e) {
    return false;
  }
};

$getColumns = function(string $table) use ($pdo): array {
  try {
    $st = $pdo->query("DESCRIBE `$table`");
    $cols = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      $cols[] = (string)$row['Field'];
    }
    return $cols;
  } catch(Throwable $e) {
    return [];
  }
};

$pickFirst = function(array $cols, array $candidates): ?string {
  foreach ($candidates as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
};

$formatDate = function($value): string {
  $v = (string)$value;
  if ($v === '') return '-';
  $ts = strtotime($v);
  if (!$ts) return $v;
  return date('Y-m-d H:i', $ts);
};

/**
 * 1) Detectar tabla real de checklist
 */
$candidateTables = [
  'tbl_checklists',
  'tbl_check_list',
  'tbl_checklist',
  'tbl_checklist_header',
];

$checkTable = null;
foreach ($candidateTables as $t) {
  if ($tableExists($t)) { $checkTable = $t; break; }
}

if (!$checkTable) {
  respond([
    'ok' => true,
    'source' => 'none',
    'count' => 0,
    'unidad' => $unidad,
    'rows' => [],
    'hint' => 'No checklist table found. Tried: ' . implode(', ', $candidateTables)
  ]);
}

$cols = $getColumns($checkTable);

/**
 * 2) Detectar columnas
 */
$idCol       = $pickFirst($cols, ['id','checklist_id']);
$titleCol    = $pickFirst($cols, ['title','titulo','nombre']);
$statusCol   = $pickFirst($cols, ['estado','status']);
$progressCol = $pickFirst($cols, ['progress','porcentaje','pct']);
$createdAt   = $pickFirst($cols, ['created_at','dtcreate','fecha','created','date_create']);
$updatedAt   = $pickFirst($cols, ['updated_at','dtupdate','updated','date_update']);
$userCol     = $pickFirst($cols, ['created_by','tbl_usuarios_id','usuario_id','user_id']);
$unidadCol   = $pickFirst($cols, ['unidad','unidad_id','tbl_unidad_id','tbl_unidades_id']);

if (!$idCol) {
  respond([
    'ok' => true,
    'source' => $checkTable,
    'count' => 0,
    'unidad' => $unidad,
    'rows' => [],
    'hint' => "Table $checkTable exists but no ID column found (id/checklist_id). Columns: " . implode(', ', $cols)
  ]);
}

/**
 * 3) Detect user table (optional)
 */
$userTable = null;
$userNameCol = null;

if ($tableExists('tbl_usuarios')) {
  $userTable = 'tbl_usuarios';
  $uCols = $getColumns($userTable);
  $userNameCol = $pickFirst($uCols, ['nombre','nombres','usuario','name','fullname']);
}

/**
 * 4) Armar SELECT dinámico
 */
$sel = [];
$sel[] = "c.`$idCol` AS checklist_id";
$sel[] = $titleCol ? "c.`$titleCol` AS title" : "'Checklist' AS title";
$sel[] = $statusCol ? "c.`$statusCol` AS estado" : "'borrador' AS estado";
$sel[] = $progressCol ? "c.`$progressCol` AS progress" : "0 AS progress";

$fechaExpr = null;
if ($updatedAt && $createdAt) $fechaExpr = "COALESCE(c.`$updatedAt`, c.`$createdAt`)";
elseif ($updatedAt) $fechaExpr = "c.`$updatedAt`";
elseif ($createdAt) $fechaExpr = "c.`$createdAt`";
else $fechaExpr = "NULL";

$sel[] = "$fechaExpr AS fecha";
$sel[] = $userCol ? "c.`$userCol` AS created_by" : "0 AS created_by";

if ($userTable && $userCol && $userNameCol) {
  $sel[] = "COALESCE(u.`$userNameCol`, CONCAT('User #', c.`$userCol`)) AS user_name";
} else {
  $sel[] = "'User' AS user_name";
}

$sql = "SELECT " . implode(",\n", $sel) . "\nFROM `$checkTable` c\n";
if ($userTable && $userCol && $userNameCol) {
  $sql .= "LEFT JOIN `$userTable` u ON u.id = c.`$userCol`\n";
}

$params = [];
$where = [];

if ($unidadCol && ($unidad !== '' || $unidadInt > 0)) {
  // filtro flexible: intenta por string y por int
  $where[] = "(c.`$unidadCol` = ? OR c.`$unidadCol` = ?)";
  $params[] = $unidad;
  $params[] = $unidadInt;
}

if ($where) $sql .= "WHERE " . implode(" AND ", $where) . "\n";
$sql .= "ORDER BY c.`$idCol` DESC\nLIMIT 500";

/**
 * 5) Ejecutar
 */
try {
  $rows = $fetchAll($sql, $params);
} catch(Throwable $e) {
  respond([
    'ok' => false,
    'msg' => 'Query failed',
    'error' => $e->getMessage(),
    'source' => $checkTable
  ], 500);
}

/**
 * 6) Normalizar salida
 */
$out = [];
foreach ($rows as $r) {
  $id = (int)($r['checklist_id'] ?? 0);
  if ($id <= 0) continue;

  $estadoRaw = strtolower((string)($r['estado'] ?? 'borrador'));
  $estado = ($estadoRaw === 'finalizado' || $estadoRaw === 'finalized') ? 'Finalized' : 'Draft';

  $out[] = [
    'checklist_id' => $id,
    'user_name'    => (string)($r['user_name'] ?? 'User'),
    'fecha'        => $formatDate($r['fecha'] ?? ''),
    'estado'       => $estado,
    'title'        => (string)($r['title'] ?? 'Checklist'),
    'progress'     => (int)($r['progress'] ?? 0),
  ];
}

respond([
  'ok' => true,
  'source' => $checkTable . ($unidadCol ? " (unidad:$unidadCol)" : " (no-unidad-col)"),
  'count' => count($out),
  'unidad' => $unidad,
  'rows' => $out,
  // debug útil para que sepamos por qué no muestra datos
  'debug' => [
    'table' => $checkTable,
    'idCol' => $idCol,
    'unidadCol' => $unidadCol,
    'userCol' => $userCol,
    'createdAt' => $createdAt,
    'updatedAt' => $updatedAt,
  ]
]);
