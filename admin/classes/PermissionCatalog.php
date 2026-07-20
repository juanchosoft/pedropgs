<?php
/**
 * Catálogo de permisos por KEY (fuente de verdad para seed).
 * legacy_id = ID histórico en tec_permisos / checks getPermission(N).
 */
final class PermissionCatalog
{
    /** @return array<int, array{key:string,module:string,action:string,name:string,legacy_id:?int}> */
    public static function definitions(): array
    {
        $items = [];
        $add = static function (
            string $key,
            string $module,
            string $action,
            string $name,
            ?int $legacyId = null
        ) use (&$items): void {
            $items[] = [
                'key' => $key,
                'module' => $module,
                'action' => $action,
                'name' => $name,
                'legacy_id' => $legacyId,
            ];
        };

        // Users (1-6)
        $add('configuracion.usuarios.view', 'configuracion', 'view', 'Users - See', 1);
        $add('configuracion.usuarios.create', 'configuracion', 'create', 'Users - Create', 2);
        $add('configuracion.usuarios.update', 'configuracion', 'update', 'Users - Edit', 3);
        $add('configuracion.usuarios.delete', 'configuracion', 'delete', 'Users - Delete', 4);
        $add('configuracion.usuarios.enable', 'configuracion', 'enable', 'Users - Enable', 5);
        $add('configuracion.usuarios.permissions', 'configuracion', 'manage', 'Users - Permissions', 6);

        // Roles (new)
        $add('configuracion.roles.view', 'configuracion', 'view', 'Roles - View');
        $add('configuracion.roles.manage', 'configuracion', 'manage', 'Roles - Manage');

        // Activities / Reports / Products shared (7-11)
        $add('reportes.activities.view', 'reportes', 'view', 'Activities Report - See', 7);
        $add('reportes.activities.create', 'reportes', 'create', 'Activities Report - Create', 8);
        $add('reportes.activities.update', 'reportes', 'update', 'Activities Report - Edit', 9);
        $add('reportes.activities.delete', 'reportes', 'delete', 'Activities Report - Delete', 10);
        $add('reportes.activities.enable', 'reportes', 'enable', 'Activities Report - Enable', 11);

        // Customers (12-16)
        $add('clientes.customers.view', 'clientes', 'view', 'Customers - See', 12);
        $add('clientes.customers.create', 'clientes', 'create', 'Customers - Create', 13);
        $add('clientes.customers.delete', 'clientes', 'delete', 'Customers - Delete', 14);
        $add('clientes.customers.update', 'clientes', 'update', 'Customers - Edit', 15);
        $add('clientes.customers.enable', 'clientes', 'enable', 'Customers - Enable', 16);

        // Checklist (17-21)
        $add('checklist.checklist.create', 'checklist', 'create', 'CheckList - Create', 17);
        $add('checklist.checklist.delete', 'checklist', 'delete', 'CheckList - Delete', 18);
        $add('checklist.checklist.enable', 'checklist', 'enable', 'CheckList - Enable', 19);
        $add('checklist.checklist.update', 'checklist', 'update', 'CheckList - Edit', 20);
        $add('checklist.checklist.view', 'checklist', 'view', 'CheckList - View', 21);

        // Daily report (22-26)
        $add('reportes.daily.view', 'reportes', 'view', 'DailyReport - See', 22);
        $add('reportes.daily.create', 'reportes', 'create', 'DailyReport - Create', 23);
        $add('reportes.daily.update', 'reportes', 'update', 'DailyReport - Edit', 24);
        $add('reportes.daily.delete', 'reportes', 'delete', 'DailyReport - Delete', 25);
        $add('reportes.daily.enable', 'reportes', 'enable', 'DailyReport - Enable', 26);

        // Employees (27-32)
        $add('empleados.empleados.view', 'empleados', 'view', 'Employees - View', 27);
        $add('empleados.empleados.create', 'empleados', 'create', 'Employees - Create', 28);
        $add('empleados.empleados.update', 'empleados', 'update', 'Employees - Edit', 29);
        $add('empleados.empleados.delete', 'empleados', 'delete', 'Employees - Delete', 30);
        $add('empleados.empleados.enable', 'empleados', 'enable', 'Employees - Enable', 31);
        $add('empleados.empleados.legacy32', 'empleados', 'manage', 'Employees - Legacy 32', 32);

        // Time (33-37)
        $add('tiempo.reloj.view', 'tiempo', 'view', 'Time - See', 33);
        $add('tiempo.reloj.create', 'tiempo', 'create', 'Time - Create', 34);
        $add('tiempo.reloj.update', 'tiempo', 'update', 'Time - Edit', 35);
        $add('tiempo.reloj.delete', 'tiempo', 'delete', 'Time - Delete', 36);
        $add('tiempo.reloj.enable', 'tiempo', 'enable', 'Time - Enable', 37);

        // Work done (38-39)
        $add('operacion.work_done.view', 'operacion', 'view', 'Work Done - Access', 38);
        $add('operacion.work_done.legacy39', 'operacion', 'manage', 'Work Done - Legacy 39', 39);

        // Configuration (40-44)
        $add('configuracion.sistema.view', 'configuracion', 'view', 'Configuration - See', 40);
        $add('configuracion.sistema.create', 'configuracion', 'create', 'Configuration - Create', 41);
        $add('configuracion.sistema.update', 'configuracion', 'update', 'Configuration - Edit', 42);
        $add('configuracion.sistema.delete', 'configuracion', 'delete', 'Configuration - Delete', 43);
        $add('configuracion.sistema.enable', 'configuracion', 'enable', 'Configuration - Enable', 44);

        // Salidas / Uniformes (45-49)
        $add('empleados.salidas.view', 'empleados', 'view', 'Employee Outings - View', 45);
        $add('empleados.salidas.create', 'empleados', 'create', 'Employee Outings - Create', 46);
        $add('empleados.salidas.update', 'empleados', 'update', 'Employee Outings - Edit', 47);
        $add('empleados.salidas.delete', 'empleados', 'delete', 'Employee Outings - Delete', 48);
        $add('empleados.salidas.enable', 'empleados', 'enable', 'Employee Outings - Enable', 49);

        // Inventory (50-54)
        $add('inventario.inventario.view', 'inventario', 'view', 'Inventory - View', 50);
        $add('inventario.inventario.create', 'inventario', 'create', 'Inventory - Create', 51);
        $add('inventario.inventario.update', 'inventario', 'update', 'Inventory - Edit', 52);
        $add('inventario.inventario.delete', 'inventario', 'delete', 'Inventory - Delete', 53);
        $add('inventario.inventario.enable', 'inventario', 'enable', 'Inventory - Enable', 54);

        // Dashboard (session pages that may gain KEY later)
        $add('dashboard.main.view', 'dashboard', 'view', 'Dashboard - View');

        return $items;
    }

    /** @return array<int,string> */
    public static function legacyIdToKey(): array
    {
        $map = [];
        foreach (self::definitions() as $row) {
            if ($row['legacy_id'] !== null) {
                $map[(int) $row['legacy_id']] = $row['key'];
            }
        }
        return $map;
    }

    /** @return array<string,int> */
    public static function legacyKeyToId(): array
    {
        $map = [];
        foreach (self::definitions() as $row) {
            if ($row['legacy_id'] !== null) {
                $map[$row['key']] = (int) $row['legacy_id'];
            }
        }
        return $map;
    }

    /** @return string[] */
    public static function allKeys(): array
    {
        return array_values(array_map(static function ($r) {
            return $r['key'];
        }, self::definitions()));
    }

    /**
     * Roles por huella (fingerprint de IDs legacy ordenados).
     * @return array<string, array{role_key:string,name:string,description:string,is_system:int,legacy_ids:int[],extra_permission_keys?:string[]}>
     */
    public static function fingerprintRoles(): array
    {
        $rolesManage = ['configuracion.roles.view', 'configuracion.roles.manage'];

        return [
            '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54' => [
                'role_key' => 'super_administrador',
                'name' => 'Super Administrator',
                'description' => 'Full legacy set including orphan IDs (inventory, work done, etc.)',
                'is_system' => 1,
                'legacy_ids' => range(1, 54),
                'extra_permission_keys' => $rolesManage,
            ],
            '1,2,3,4,5,6,7,8,9,10,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,33,34,35,36,37,40,41,42,43,44' => [
                'role_key' => 'super_admin_standard',
                'name' => 'Super Admin Standard',
                'description' => 'Broad admin without outings/orphan inventory IDs',
                'is_system' => 1,
                'legacy_ids' => [1,2,3,4,5,6,7,8,9,10,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,33,34,35,36,37,40,41,42,43,44],
                'extra_permission_keys' => $rolesManage,
            ],
            '1,2,3,4,5,6,7,8,9,10,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,33,34,35,36,37,40,41,42,43,44,45,46,47,48,49' => [
                'role_key' => 'catalog_full_45',
                'name' => 'Full Catalog (no orphans)',
                'description' => 'Near-full catalog without orphan IDs 11/32/38/39/50-54',
                'is_system' => 1,
                'legacy_ids' => [1,2,3,4,5,6,7,8,9,10,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,33,34,35,36,37,40,41,42,43,44,45,46,47,48,49],
                'extra_permission_keys' => $rolesManage,
            ],
            '7,8,9,10,17,18,19,20,21,22,23,24,25,26,33,34,35,36,37,45,46,47,48,49' => [
                'role_key' => 'staff_operations',
                'name' => 'Staff Operations',
                'description' => 'Reports, checklist, daily, time, outings',
                'is_system' => 1,
                'legacy_ids' => [7,8,9,10,17,18,19,20,21,22,23,24,25,26,33,34,35,36,37,45,46,47,48,49],
            ],
            '7,8,9,10,17,18,19,20,21,22,23,24,25,26,45,46,47,48,49' => [
                'role_key' => 'staff_operations_no_time',
                'name' => 'Staff Operations (No Time)',
                'description' => 'Staff ops without Time module',
                'is_system' => 1,
                'legacy_ids' => [7,8,9,10,17,18,19,20,21,22,23,24,25,26,45,46,47,48,49],
            ],
            '7,21,22' => [
                'role_key' => 'admin_hoa_reports',
                'name' => 'HOA Administrator (Reports)',
                'description' => 'Activities see + Checklist view + Daily see',
                'is_system' => 1,
                'legacy_ids' => [7, 21, 22],
            ],
            '7,21' => [
                'role_key' => 'admin_hoa_reports_checklist',
                'name' => 'HOA Administrator (Reports + Checklist)',
                'description' => 'Activities see + Checklist view',
                'is_system' => 1,
                'legacy_ids' => [7, 21],
            ],
            '21,22' => [
                'role_key' => 'admin_hoa_checklist_daily',
                'name' => 'HOA Administrator (Checklist + Daily)',
                'description' => 'Checklist view + Daily see',
                'is_system' => 1,
                'legacy_ids' => [21, 22],
            ],
            '' => [
                'role_key' => 'role_empty',
                'name' => 'No Permissions',
                'description' => 'Users with empty permission set',
                'is_system' => 1,
                'legacy_ids' => [],
            ],
        ];
    }
}
