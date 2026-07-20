<?php
/**
 * Verify RBAC migration health.
 * Usage: php admin/db/verify_roles_permissions.php
 */
require_once __DIR__ . '/../classes/DbConection.php';
require_once __DIR__ . '/../classes/PermissionCatalog.php';

$db = new DbConection();
$pdo = $db->openConect();

$ok = true;
function check($label, $cond) {
    global $ok;
    echo ($cond ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$cond) $ok = false;
}

$perms = (int) $pdo->query('SELECT COUNT(*) FROM pgscentrum.tec_permissions')->fetchColumn();
$roles = (int) $pdo->query('SELECT COUNT(*) FROM pgscentrum.tec_roles')->fetchColumn();
$usersNoRole = (int) $pdo->query('SELECT COUNT(*) FROM pgscentrum.tec_usuarios WHERE role_id IS NULL')->fetchColumn();
$defs = count(PermissionCatalog::definitions());

check("permissions >= catalog defs ($perms >= $defs)", $perms >= $defs);
check("roles >= 9 fingerprints ($roles)", $roles >= 9);
check("users without role_id = 0 ($usersNoRole)", $usersNoRole === 0);

$mapFile = __DIR__ . '/../config/ajax_permissions_map.php';
check('ajax map exists', is_file($mapFile));
if (is_file($mapFile)) {
    $map = require $mapFile;
    check('ajax map has rolesave', isset($map['rolesave']));
}

echo $ok ? "VERIFY PASSED\n" : "VERIFY FAILED\n";
exit($ok ? 0 : 1);
