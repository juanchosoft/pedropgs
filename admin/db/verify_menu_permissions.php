<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Verifica qué ítems de menú vería cada rol (misma lógica que navbar/móvil).
 * Usage: php admin/db/verify_menu_permissions.php
 */
require __DIR__ . '/../classes/DbConection.php';

$db = new DbConection();
$pdo = $db->openConect();

function keysForRole(PDO $pdo, int $roleId): array
{
    $rows = $pdo->query(
        "SELECT p.legacy_id, p.permission_key
         FROM tec_role_has_permissions rp
         JOIN tec_permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = $roleId"
    )->fetchAll(PDO::FETCH_ASSOC);
    $legacy = [];
    $keys = [];
    foreach ($rows as $r) {
        $keys[] = $r['permission_key'];
        if ($r['legacy_id'] !== null) {
            $legacy[(int)$r['legacy_id']] = true;
        }
    }
    return [$legacy, $keys];
}

function hasLegacy(array $legacy, int $id): bool
{
    return isset($legacy[$id]);
}

function hasKey(array $keys, string $key): bool
{
    return in_array($key, $keys, true);
}

function menuFor(array $legacy, array $keys, bool $super = false, array $unidades = [], bool $isUnitAdmin = false): array
{
    $items = ['Home'];
    $canOperations = hasKey($keys, 'tareas.tasks.view')
        || hasKey($keys, 'tareas.tasks.view_all')
        || hasKey($keys, 'servicios.requests.create')
        || hasKey($keys, 'servicios.catalog.view')
        || hasKey($keys, 'servicios.catalog.manage')
        || $super;
    if ($canOperations) {
        if (hasKey($keys, 'tareas.tasks.view') || hasKey($keys, 'tareas.tasks.view_all') || $super) {
            $items[] = 'Tasks';
        }
        if (hasKey($keys, 'servicios.requests.create') || $super) {
            $items[] = 'Service Request';
        }
        if (hasKey($keys, 'servicios.catalog.view') || hasKey($keys, 'servicios.catalog.manage') || $super) {
            $items[] = 'Services Catalog';
        }
    }
    $canWorkforce = hasKey($keys, 'disponibilidad.schedule.view')
        || hasKey($keys, 'disponibilidad.schedule.manage')
        || hasKey($keys, 'disponibilidad.reservations.create')
        || hasKey($keys, 'disponibilidad.reservations.manage')
        || hasKey($keys, 'disponibilidad.absences.request')
        || hasKey($keys, 'disponibilidad.absences.approve')
        || $super;
    if ($canWorkforce) {
        if (hasKey($keys, 'disponibilidad.schedule.view') || hasKey($keys, 'disponibilidad.schedule.manage') || $super) {
            $items[] = 'Schedules';
        }
        if (hasKey($keys, 'disponibilidad.reservations.create') || hasKey($keys, 'disponibilidad.reservations.manage') || $super) {
            $items[] = 'Reservations';
        }
        if (hasKey($keys, 'disponibilidad.absences.request') || hasKey($keys, 'disponibilidad.absences.approve') || $super) {
            $items[] = 'Time Off & Leaves';
        }
    }
    $canExpenses = !$isUnitAdmin && (
        hasKey($keys, 'gastos.expenses.view_all')
        || hasKey($keys, 'gastos.expenses.view_own')
        || hasKey($keys, 'gastos.expenses.create')
        || $super
    );
    $canInvoices = hasKey($keys, 'facturas.invoices.view_own_units')
        || hasKey($keys, 'facturas.invoices.manage')
        || $super;
    if ($canExpenses) {
        $items[] = 'Expenses';
    }
    if ($canInvoices) {
        $items[] = 'Invoices';
    }
    $canReports = hasLegacy($legacy, 7) || hasLegacy($legacy, 9) || hasLegacy($legacy, 21) || hasLegacy($legacy, 22);
    if ($canReports) {
        if (hasLegacy($legacy, 7)) $items[] = 'Enter Report';
        if (hasLegacy($legacy, 9)) $items[] = 'Edit Report';
        if (hasLegacy($legacy, 21)) $items[] = 'Check List Report';
        if (($super || in_array(7, $unidades, true)) && hasLegacy($legacy, 21)) $items[] = 'Check List Villasol';
        if (($super || in_array(7, $unidades, true)) && hasLegacy($legacy, 22)) $items[] = 'Show Check Villasol';
        if (hasLegacy($legacy, 22)) {
            $items[] = 'Show Check List Report';
            $items[] = 'Calendar';
            $items[] = 'Report List Group';
        }
    }
    if (hasLegacy($legacy, 12)) $items[] = 'Customers';
    if (hasLegacy($legacy, 1)) $items[] = 'User';
    $canEmployees = hasLegacy($legacy, 27) || hasLegacy($legacy, 33) || hasLegacy($legacy, 45);
    if ($canEmployees) {
        if (hasLegacy($legacy, 27)) $items[] = 'View Employees';
        if (hasLegacy($legacy, 33)) $items[] = 'Check-in - Check-out';
        if (hasLegacy($legacy, 45)) {
            $items[] = 'Entry - Exit';
            $items[] = 'Uniforms';
        }
    }
    $canConfig = hasLegacy($legacy, 40)
        || hasKey($keys, 'configuracion.roles.view')
        || hasKey($keys, 'configuracion.roles.manage')
        || hasKey($keys, 'integraciones.api_tokens.manage')
        || hasKey($keys, 'integraciones.sync.manage')
        || hasKey($keys, 'integraciones.whatsapp.manage')
        || hasKey($keys, 'ia.logs.view')
        || hasKey($keys, 'telefonia.calls.view')
        || hasKey($keys, 'auditoria.logs.view')
        || hasKey($keys, 'sistema.migraciones.run')
        || $super;
    if ($canConfig) {
        if (hasLegacy($legacy, 40)) $items[] = 'Configuration';
        if (hasKey($keys, 'configuracion.roles.view') || hasKey($keys, 'configuracion.roles.manage') || $super) {
            $items[] = 'Roles & Permissions';
        }
        if (hasKey($keys, 'integraciones.api_tokens.manage') || $super) {
            $items[] = 'API Tokens';
        }
        if (hasKey($keys, 'integraciones.sync.manage') || hasKey($keys, 'integraciones.whatsapp.manage') || $super) {
            $items[] = 'Integrations & Sync';
        }
        if (hasKey($keys, 'ia.logs.view') || $super) {
            $items[] = 'AI Logs';
        }
        if (hasKey($keys, 'telefonia.calls.view') || $super) {
            $items[] = 'Phone Calls';
        }
        if (hasKey($keys, 'auditoria.logs.view') || $super) {
            $items[] = 'Audit Log';
        }
        if (hasKey($keys, 'sistema.migraciones.run') || $super) {
            $items[] = 'Migrations';
        }
    }
    return $items;
}

$roles = $pdo->query('SELECT id, role_key, name FROM tec_roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $role) {
    [$legacy, $keys] = keysForRole($pdo, (int)$role['id']);
    $isUnitAdmin = str_starts_with($role['role_key'], 'admin_hoa_');
    $items = menuFor($legacy, $keys, $role['role_key'] === 'super_administrador', [], $isUnitAdmin);
    echo "=== {$role['role_key']} ({$role['name']}) ===\n";
    echo '  ' . implode(' | ', $items) . "\n\n";
}

// User 244 specifically
$u = $pdo->query('SELECT id, nickname, tipo, role_id, is_internal_staff FROM tec_usuarios WHERE id=244')->fetch(PDO::FETCH_ASSOC);
if ($u) {
    [$legacy, $keys] = keysForRole($pdo, (int)$u['role_id']);
    $isUnitAdmin = ($u['tipo'] === 'Administrador' && (int)($u['is_internal_staff'] ?? 0) === 0);
    $items = menuFor($legacy, $keys, false, [], $isUnitAdmin);
    echo "=== USER 244 {$u['nickname']} tipo={$u['tipo']} ===\n";
    echo '  ' . implode(' | ', $items) . "\n";
    $must = ['Enter Report', 'Edit Report', 'Check List Report', 'Check-in - Check-out', 'Entry - Exit', 'Uniforms', 'Calendar'];
    $missing = array_filter($must, static function ($m) use ($items) {
        return !in_array($m, $items, true);
    });
    echo empty($missing) ? "VERIFY OK for staff_operations expected items\n" : ('MISSING: ' . implode(', ', $missing) . "\n");
}

// User 233 specifically (Internal Staff Administrador)
$u233 = $pdo->query('SELECT id, nickname, tipo, role_id, is_internal_staff FROM tec_usuarios WHERE id=233')->fetch(PDO::FETCH_ASSOC);
if ($u233) {
    [$legacy, $keys] = keysForRole($pdo, (int)$u233['role_id']);
    $isUnitAdmin = ($u233['tipo'] === 'Administrador' && (int)($u233['is_internal_staff'] ?? 0) === 0);
    $items = menuFor($legacy, $keys, false, [], $isUnitAdmin);
    echo "=== USER 233 {$u233['nickname']} tipo={$u233['tipo']} is_internal_staff={$u233['is_internal_staff']} ===\n";
    echo '  ' . implode(' | ', $items) . "\n";
}
