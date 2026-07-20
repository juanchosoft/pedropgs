<?php
require_once __DIR__ . '/../classes/DbConection.php';
$db = new DbConection();
$pdo = $db->openConect();
$keys = ['configuracion.roles.view', 'configuracion.roles.manage'];
$roleKeys = ['super_administrador', 'super_admin_standard', 'catalog_full_45'];
foreach ($keys as $k) {
    $pid = (int) $pdo->query('SELECT id FROM pgscentrum.tec_permissions WHERE permission_key=' . $pdo->quote($k))->fetchColumn();
    echo "perm $k = $pid\n";
    foreach ($roleKeys as $rk) {
        $rid = (int) $pdo->query('SELECT id FROM pgscentrum.tec_roles WHERE role_key=' . $pdo->quote($rk))->fetchColumn();
        $pdo->exec("INSERT IGNORE INTO pgscentrum.tec_role_has_permissions (role_id, permission_id) VALUES ($rid, $pid)");
        echo "  linked role $rk\n";
    }
}
echo "done\n";
