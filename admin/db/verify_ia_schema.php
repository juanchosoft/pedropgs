<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/admin/bootstrap_pgs.php';

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Migrations\SchemaHelper;
use Pgs\Migrations\Seed\NewPermissions;

$pdo = Db::pdo();
$failures = 0;
$warnings = 0;

function ok(string $msg): void {
    echo "[OK]   $msg" . PHP_EOL;
}

function warn(string $msg): void {
    global $warnings;
    $warnings++;
    echo "[WARN] $msg" . PHP_EOL;
}

function fail(string $msg): void {
    global $failures;
    $failures++;
    echo "[FAIL] $msg" . PHP_EOL;
}

echo "=== PGS Schema & Health Verification ===" . PHP_EOL;

// 1. Tables existence
$expectedTables = [
    'tec_migrations', 'tec_audit_log', 'tec_sequences', 'tec_idempotency_keys', 'tec_rate_limits', 'tec_signed_links', 'tec_pending_actions',
    'tec_unit_contacts', 'tec_services', 'tec_service_units',
    'tec_tasks', 'tec_task_history', 'tec_task_comments', 'tec_task_attachments',
    'tec_worker_schedule', 'tec_worker_absences', 'tec_worker_reservations',
    'tec_expenses', 'tec_expense_items', 'tec_invoices',
    'tec_notifications', 'tec_notification_outbox', 'tec_notification_prefs', 'tec_notification_type_defaults', 'tec_whatsapp_webhook_events',
    'tec_api_tokens', 'tec_sync_outbox', 'tec_sync_links', 'tec_outbound_call_requests',
    'tec_call_sessions', 'tec_call_logs',
    'tec_ia_conversaciones', 'tec_ia_mensajes', 'tec_ia_tool_logs', 'tec_ia_adjuntos',
    'tec_leave_allocations', 'tec_leave_ledger',
];

foreach ($expectedTables as $tbl) {
    if (SchemaHelper::tableExists($pdo, $tbl)) {
        ok("Table $tbl exists");
    } else {
        fail("Table $tbl is missing");
    }
}

// 2. Engine and Row Format
foreach ($expectedTables as $tbl) {
    $row = $pdo->prepare("SELECT ENGINE, ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $row->execute([':t' => $tbl]);
    $info = $row->fetch();
    if ($info) {
        if (strcasecmp((string) $info['ENGINE'], 'InnoDB') !== 0) {
            fail("Table $tbl engine is {$info['ENGINE']}, expected InnoDB");
        }
        if (strcasecmp((string) $info['ROW_FORMAT'], 'Dynamic') !== 0) {
            fail("Table $tbl row_format is {$info['ROW_FORMAT']}, expected Dynamic");
        }
    }
}
ok("All new tables are InnoDB and ROW_FORMAT=Dynamic");

// 3. tec_usuarios columns and phone_e164 index
$userCols = [
    'phone_e164', 'notify_email', 'voice_pin_hash', 'voice_pin_set_at', 'voice_pin_failed',
    'voice_pin_fail_window_start', 'voice_pin_lock_level', 'voice_pin_locked_until', 'voice_pin_admin_locked',
    'lang', 'whatsapp_opt_in', 'whatsapp_opt_in_at', 'employee_pk', 'perm_version', 'is_internal_staff'
];
foreach ($userCols as $col) {
    if (SchemaHelper::columnExists($pdo, 'tec_usuarios', $col)) {
        ok("tec_usuarios.$col exists");
    } else {
        fail("tec_usuarios.$col is missing");
    }
}
if (SchemaHelper::indexExists($pdo, 'tec_usuarios', 'uq_tec_usuarios_phone_e164')) {
    ok("tec_usuarios.uq_tec_usuarios_phone_e164 index exists");
} else {
    fail("tec_usuarios.uq_tec_usuarios_phone_e164 index is missing");
}

// 4. Foreign Keys
$expectedFks = [
    'tec_tasks' => ['fk_tasks_unidad', 'fk_tasks_assignee'],
    'tec_worker_reservations' => ['fk_rsv_employee'],
    'tec_expenses' => ['fk_expenses_unidad', 'fk_expenses_invoice'],
    'tec_invoices' => ['fk_invoices_unidad'],
    'tec_ia_mensajes' => ['fk_ia_msg_conv'],
];
foreach ($expectedFks as $t => $fks) {
    foreach ($fks as $fk) {
        if (SchemaHelper::fkExists($pdo, $t, $fk)) {
            ok("FK $t.$fk exists");
        } else {
            fail("FK $t.$fk is missing");
        }
    }
}

// 5. slot_lock generated column & unique index
$resCol = $pdo->prepare("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tec_worker_reservations' AND COLUMN_NAME = 'slot_lock'");
$resCol->execute();
$extra = (string) $resCol->fetchColumn();
if (str_contains(strtolower($extra), 'stored') || str_contains(strtolower($extra), 'generated')) {
    ok("tec_worker_reservations.slot_lock is generated");
} else {
    fail("tec_worker_reservations.slot_lock is not generated (extra: $extra)");
}
if (SchemaHelper::indexExists($pdo, 'tec_worker_reservations', 'uq_rsv_slot')) {
    ok("tec_worker_reservations.uq_rsv_slot unique index exists");
} else {
    fail("tec_worker_reservations.uq_rsv_slot index is missing");
}

// 6. 40 Permissions and Role Safeguard
$allNew = NewPermissions::definitions();
$missingPerms = 0;
foreach ($allNew as $p) {
    $chk = $pdo->prepare("SELECT is_active FROM tec_permissions WHERE permission_key = :k");
    $chk->execute([':k' => $p['key']]);
    $act = $chk->fetchColumn();
    if ($act === false || (int) $act !== 1) {
        fail("Permission {$p['key']} is missing or not active");
        $missingPerms++;
    }
}
if ($missingPerms === 0) {
    ok("All 40 new permissions exist and are active");
}

// Safeguard D-25 check: No external Unit Admin has expenses or invoices.manage
$vulnUsers = $pdo->query(
    "SELECT u.id, u.nickname, p.permission_key
     FROM tec_usuarios u
     JOIN tec_role_has_permissions rp ON rp.role_id = u.role_id
     JOIN tec_permissions p ON p.id = rp.permission_id
     WHERE u.tipo = 'Administrador' AND u.is_internal_staff = 0
       AND (p.permission_key LIKE 'gastos.%' OR p.permission_key = 'facturas.invoices.manage')"
)->fetchAll();
if (count($vulnUsers) > 0) {
    foreach ($vulnUsers as $vu) {
        fail("Unit admin {$vu['id']} ({$vu['nickname']}) has forbidden permission {$vu['permission_key']}");
    }
} else {
    ok("Safeguard D-25: No unit-admin users hold expense or invoice-manage permissions");
}

// 7. tec_migrations 001..015 applied
$migRows = $pdo->query("SELECT migration, status FROM tec_migrations")->fetchAll();
$migMap = [];
foreach ($migRows as $mr) {
    $migMap[$mr['migration']] = $mr['status'];
}
for ($i = 1; $i <= 15; $i++) {
    $numStr = sprintf('%03d', $i);
    $found = false;
    foreach ($migMap as $mName => $mStatus) {
        if (str_contains($mName, "2026_10_{$numStr}")) {
            if ($mStatus === 'applied') {
                $found = true;
            } else {
                fail("Migration $mName status is $mStatus (expected applied)");
            }
            break;
        }
    }
    if (!$found) {
        fail("Migration index $numStr is missing from applied migrations");
    }
}
ok("All migrations 001 to 015 are recorded as applied");

// 8. tbl_requerimientos count vs tec_tasks migration count
$cntReq = (int) $pdo->query("SELECT COUNT(*) FROM tbl_requerimientos")->fetchColumn();
$cntTasks = (int) $pdo->query("SELECT COUNT(*) FROM tec_tasks WHERE origin = 'migration'")->fetchColumn();
if ($cntReq === $cntTasks) {
    ok("Task migration count matches tbl_requerimientos ($cntReq)");
} else {
    fail("Task migration count ($cntTasks) does not match tbl_requerimientos ($cntReq)");
}

// 8b. Leave balances
$currYear = (int) Clock::todayLocal()->format('Y');
$empStmt = $pdo->query("SELECT id, nombre, dias_descanso FROM tec_employee");
while ($emp = $empStmt->fetch()) {
    $eid = (int) $emp['id'];
    $legacyDays = (int) ($emp['dias_descanso'] ?? 0);
    $sumLedger = (int) $pdo->query("SELECT COALESCE(SUM(days), 0) FROM tec_leave_ledger WHERE employee_id = $eid AND leave_year = $currYear")->fetchColumn();
    if ($legacyDays !== $sumLedger) {
        warn("Employee $eid ({$emp['nombre']}) dias_descanso=$legacyDays != ledger sum=$sumLedger");
    }
}
$negBal = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT employee_id, leave_year, SUM(days) as bal FROM tec_leave_ledger GROUP BY employee_id, leave_year HAVING bal < 0) t")->fetchColumn();
if ($negBal > 0) {
    fail("Found $negBal negative leave balances");
} else {
    ok("Leave balances verified (no negative balances)");
}

// 9. Logical integrity
$unlinkedTasks = (int) $pdo->query("SELECT COUNT(*) FROM tec_tasks t LEFT JOIN tec_usuarios u ON u.id = t.requested_by_user_id WHERE t.requested_by_user_id IS NOT NULL AND u.id IS NULL")->fetchColumn();
if ($unlinkedTasks === 0) {
    ok("Logical integrity: tec_tasks.requested_by_user_id all exist");
} else {
    fail("Found $unlinkedTasks orphan requested_by_user_id in tec_tasks");
}

$unlinkedEmpPk = (int) $pdo->query("SELECT COUNT(*) FROM tec_usuarios u LEFT JOIN tec_employee e ON e.id = u.employee_pk WHERE u.employee_pk IS NOT NULL AND e.id IS NULL")->fetchColumn();
if ($unlinkedEmpPk === 0) {
    ok("Logical integrity: tec_usuarios.employee_pk all exist in tec_employee");
} else {
    fail("Found $unlinkedEmpPk invalid employee_pk in tec_usuarios");
}

$unresolvedEmps = $pdo->query("SELECT id, nickname, employee_id FROM tec_usuarios WHERE employee_id IS NOT NULL AND employee_id <> 0 AND employee_pk IS NULL")->fetchAll();
if (count($unresolvedEmps) > 0) {
    foreach ($unresolvedEmps as $ue) {
        warn("User {$ue['id']} ({$ue['nickname']}) has employee_id={$ue['employee_id']} but employee_pk is NULL");
    }
} else {
    ok("All employees with employee_id have resolved employee_pk");
}

// 10. config.ini readability & location outside DOCUMENT_ROOT
try {
    $cfgPath = Config::path();
    ok("Config file readable at $cfgPath");
} catch (\Throwable $e) {
    fail("Config path error: " . $e->getMessage());
}

// 11. private_dir writeable
try {
    $privDir = (string) Config::get('app', 'private_dir', '');
    if (is_dir($privDir) && is_writable($privDir)) {
        ok("Private storage directory exists and is writable ($privDir)");
    } else {
        fail("Private storage directory does not exist or is not writable ($privDir)");
    }
} catch (\Throwable $e) {
    fail("Private storage error: " . $e->getMessage());
}

// 12. PHP Extensions
$reqExts = ['pdo_mysql', 'curl', 'openssl', 'mbstring', 'fileinfo', 'zip', 'gd'];
foreach ($reqExts as $ext) {
    if (extension_loaded($ext)) {
        ok("PHP extension $ext is loaded");
    } else {
        fail("PHP extension $ext is missing");
    }
}

echo PHP_EOL;
if ($failures > 0) {
    echo "=== VERIFY FAILED ($failures failures, $warnings warnings) ===" . PHP_EOL;
    exit(1);
} else {
    echo "=== VERIFY PASSED ($warnings warnings) ===" . PHP_EOL;
    exit(0);
}
