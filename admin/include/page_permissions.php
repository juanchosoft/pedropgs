<?php

require_once __DIR__ . '/../classes/SessionData.php';
require_once __DIR__ . '/../classes/Authorization.php';

/**
 * Page-level permission helpers.
 */
final class PagePermissions
{
    /** basename => view KEY or list of KEYs (OR) */
    private const PAGE_VIEW = [
        'roles_permisos.php' => ['configuracion.roles.view', 'configuracion.roles.manage'],
        'usuarios.php' => 'configuracion.usuarios.view',
        'empleados.php' => 'empleados.empleados.view',
        'clientes.php' => 'clientes.customers.view',
        'places_customers.php' => 'clientes.customers.view',
        'configuracion.php' => 'configuracion.sistema.view',
        'inventario.php' => 'inventario.inventario.view',
        'work_done.php' => 'operacion.work_done.view',
        'reloj.php' => 'tiempo.reloj.view',
        'informe_salidas.php' => 'empleados.salidas.view',
        'uniformes.php' => 'empleados.salidas.view',
        'productos.php' => 'reportes.activities.view',
        'report.php' => 'reportes.activities.view',
        'daily_report_group.php' => 'reportes.daily.view',
        'check_list.php' => 'checklist.checklist.view',
        'check_list_villasol.php' => 'checklist.checklist.view',
        'main.php' => 'dashboard.main.view',
    ];

    public static function guardCurrentRequest(): void
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script === '' || in_array($script, ['login.php', 'logout.php', 'index.php', 'permiso_denegado.php'], true)) {
            return;
        }
        if (!isset(self::PAGE_VIEW[$script])) {
            return; // other pages keep their own getPermission checks via bridge
        }
        $required = self::PAGE_VIEW[$script];
        if (is_array($required)) {
            if (!Authorization::canAny($required)) {
                require __DIR__ . '/../../permiso_denegado.php';
                exit;
            }
            return;
        }
        if (!Authorization::can($required)) {
            require __DIR__ . '/../../permiso_denegado.php';
            exit;
        }
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $key): void
    {
        if (!Authorization::can($key)) {
            require __DIR__ . '/../../permiso_denegado.php';
            exit;
        }
    }
}

if (!function_exists('requireAnyPermission')) {
    /** @param string[] $keys */
    function requireAnyPermission(array $keys): void
    {
        if (!Authorization::canAny($keys)) {
            require __DIR__ . '/../../permiso_denegado.php';
            exit;
        }
    }
}
