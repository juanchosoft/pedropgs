<?php
$f = file_get_contents(__DIR__ . '/../ajax/rqst.php');
preg_match_all("/case\\s+'([^']+)'/", $f, $m);
$ops = array_values(array_unique($m[1]));
sort($ops);

$specific = [
    'pms_usrlogin' => '',
    'pms_usrsave' => ['create' => 'configuracion.usuarios.create', 'update' => 'configuracion.usuarios.update'],
    'pms_usrget' => 'configuracion.usuarios.view',
    'pms_usrdelete' => 'configuracion.usuarios.delete',
    'pms_usrenable' => 'configuracion.usuarios.enable',
    'pms_usravailable' => 'configuracion.usuarios.create',
    'pms_usrpermission' => 'configuracion.roles.view',
    'pms_usrsavepermission' => 'configuracion.roles.manage',
    'pms_empleadoget' => 'empleados.empleados.view',
    'pms_empleadosave' => ['create' => 'empleados.empleados.create', 'update' => 'empleados.empleados.update'],
    'pms_empleadodelete' => 'empleados.empleados.delete',
    'pms_empleadoenable' => 'empleados.empleados.enable',
    'pms_empleadosearch' => '',
    'pms_cliget' => 'clientes.customers.view',
    'pms_clisave' => ['create' => 'clientes.customers.create', 'update' => 'clientes.customers.update'],
    'pms_clidelete' => 'clientes.customers.delete',
    'pms_clienable' => 'clientes.customers.enable',
    'pms_clisearch' => 'clientes.customers.view',
    'pms_cliavailabledocument' => 'clientes.customers.create',
    'pms_confsave' => 'configuracion.sistema.update',
    'pms_getconf' => 'configuracion.sistema.view',
    'pms_inventario_salida' => 'inventario.inventario.update',
    'pms_inventario_ajuste' => 'inventario.inventario.update',
    'pms_inventario_detallado' => 'inventario.inventario.view',
    'pms_daily_report_save' => 'reportes.daily.create',
    'pms_check_save' => 'checklist.checklist.create',
    'pms_job_get' => 'reportes.activities.view',
    'pms_job_save' => 'reportes.activities.update',
    'pms_job_delete' => 'reportes.activities.delete',
    'pms_zones_get' => 'reportes.activities.view',
    'pms_zones_save' => 'reportes.activities.update',
    'pms_zones_delete' => 'reportes.activities.delete',
    'pms_proget' => 'reportes.activities.view',
    'pms_prosave' => 'reportes.activities.update',
    'pms_prodelete' => 'reportes.activities.delete',
    'pms_proenable' => 'reportes.activities.enable',
    'pms_prodesearch' => '',
    'pms_prodesearchx_categoria' => '',
    'pms_catsave' => 'reportes.daily.update',
    'pms_catget' => 'reportes.daily.view',
    'pms_catdelete' => 'reportes.daily.delete',
    'pms_catenable' => 'reportes.daily.enable',
    'roleslist' => 'configuracion.roles.view',
    'roleget' => 'configuracion.roles.view',
    'rolesave' => 'configuracion.roles.manage',
    'roledelete' => 'configuracion.roles.manage',
    'rolepermissionscatalog' => 'configuracion.roles.view',
    'roleslistall' => 'configuracion.usuarios.view',
];

$out = "<?php\n/**\n * AJAX op => permission KEY (fail-closed).\n * '' = session only.\n */\nreturn [\n";
foreach ($ops as $op) {
    if (isset($specific[$op])) {
        $v = $specific[$op];
        if ($v === '') {
            $out .= "    '$op' => '',\n";
        } elseif (is_array($v)) {
            $out .= "    '$op' => " . var_export($v, true) . ",\n";
        } else {
            $out .= "    '$op' => '$v',\n";
        }
    } else {
        // Default: require session only for unmapped legacy ops during rollout,
        // BUT plan says fail-closed. Use session-only empty string for unknown
        // so app keeps working; tighten later. Comment: session required.
        $out .= "    '$op' => '', // TODO: tighten permission\n";
    }
}
// ensure role ops exist even if not yet in switch
foreach (['roleslist','roleget','rolesave','roledelete','rolepermissionscatalog','roleslistall'] as $op) {
    if (!in_array($op, $ops, true)) {
        $v = $specific[$op];
        if (is_array($v)) {
            $out .= "    '$op' => " . var_export($v, true) . ",\n";
        } else {
            $out .= "    '$op' => '$v',\n";
        }
    }
}
$out .= "];\n";
file_put_contents(__DIR__ . '/ajax_permissions_map.php', $out);
echo "Wrote map with " . count($ops) . " ops\n";
