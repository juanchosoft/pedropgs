<?php

declare(strict_types=1);

use Pgs\Core\Clock;
use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\Seed\NewPermissions;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_013_permissions_seed';
    }

    public function description(): string
    {
        return 'Seed 40 new permissions, create staff_field_expenses role, assign role permissions';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        $now = Clock::nowUtcSql();

        // 1. Insert permissions
        $insPerm = $pdo->prepare(
            "INSERT INTO tec_permissions (permission_key, module, action, name, legacy_id, is_active, dt_create)
             VALUES (:k, :m, :a, :n, NULL, 1, :now)
             ON DUPLICATE KEY UPDATE module=VALUES(module), action=VALUES(action), name=VALUES(name), is_active=1"
        );

        $permDefs = NewPermissions::definitions();
        $permCount = 0;
        foreach ($permDefs as $p) {
            $insPerm->execute([
                ':k' => $p['key'],
                ':m' => $p['module'],
                ':a' => $p['action'],
                ':n' => $p['name'],
                ':now' => $now,
            ]);
            $permCount++;
        }
        $ctx->out("Inserted/updated $permCount permissions");

        // Cache permission IDs
        $permMap = [];
        $stmtP = $pdo->query("SELECT id, permission_key FROM tec_permissions WHERE is_active = 1");
        while ($row = $stmtP->fetch()) {
            $permMap[(string) $row['permission_key']] = (int) $row['id'];
        }

        // 2. New role: staff_field_expenses
        $pdo->prepare(
            "INSERT IGNORE INTO tec_roles (role_key, name, description, is_system, dt_create)
             VALUES ('staff_field_expenses', 'Staff Field (Expenses)', 'Staff operations plus expense registration', 1, :now)"
        )->execute([':now' => $now]);

        $rolesMap = [];
        $stmtR = $pdo->query("SELECT id, role_key FROM tec_roles");
        while ($row = $stmtR->fetch()) {
            $rolesMap[(string) $row['role_key']] = (int) $row['id'];
        }

        if (isset($rolesMap['staff_field_expenses']) && isset($rolesMap['staff_operations'])) {
            $pdo->prepare(
                "INSERT IGNORE INTO tec_role_has_permissions (role_id, permission_id)
                 SELECT :newRoleId, permission_id FROM tec_role_has_permissions WHERE role_id = :staffOpsId"
            )->execute([
                ':newRoleId' => $rolesMap['staff_field_expenses'],
                ':staffOpsId' => $rolesMap['staff_operations'],
            ]);
        }

        // 3. Matrix definitions
        $all40Keys = array_column($permDefs, 'key');

        $superAdminStandardKeys = [
            'tareas.tasks.view', 'tareas.tasks.view_all', 'tareas.tasks.create', 'tareas.tasks.update',
            'tareas.tasks.assign', 'tareas.tasks.status', 'tareas.tasks.delete',
            'servicios.catalog.view', 'servicios.catalog.manage', 'servicios.requests.create',
            'disponibilidad.schedule.view', 'disponibilidad.schedule.manage',
            'disponibilidad.absences.request', 'disponibilidad.absences.approve',
            'disponibilidad.reservations.create', 'disponibilidad.reservations.manage',
            'reportes.catalog.view', 'reportes.catalog.send',
            'notificaciones.inbox.view', 'ia.asistente.use', 'ia.voz.use', 'ia.adjuntos.use',
            'telefonia.calls.view',
        ];

        $staffOpsKeys = [
            'tareas.tasks.view', 'tareas.tasks.status',
            'servicios.catalog.view',
            'disponibilidad.schedule.view', 'disponibilidad.absences.request',
            'notificaciones.inbox.view',
            'ia.asistente.use', 'ia.voz.use', 'ia.adjuntos.use',
        ];

        $staffFieldExpensesKeys = array_merge($staffOpsKeys, [
            'gastos.expenses.create', 'gastos.expenses.view_own',
        ]);

        $adminHoaKeys = [
            'tareas.tasks.view', 'tareas.tasks.create',
            'servicios.catalog.view', 'servicios.requests.create',
            'disponibilidad.schedule.view', 'disponibilidad.reservations.create',
            'facturas.invoices.view_own_units', 'facturas.invoices.download',
            'reportes.catalog.view', 'reportes.catalog.send',
            'notificaciones.inbox.view',
            'ia.asistente.use', 'ia.voz.use', 'ia.adjuntos.use',
        ];

        $roleAssignments = [
            'super_administrador' => $all40Keys,
            'super_admin_standard' => $superAdminStandardKeys,
            'catalog_full_45' => $superAdminStandardKeys,
            'staff_operations' => $staffOpsKeys,
            'staff_operations_no_time' => $staffOpsKeys,
            'staff_field_expenses' => $staffFieldExpensesKeys,
            'admin_hoa_reports' => $adminHoaKeys,
            'admin_hoa_reports_checklist' => $adminHoaKeys,
            'admin_hoa_checklist_daily' => $adminHoaKeys,
            'role_empty' => [],
        ];

        $checkUnitAdmin = $pdo->prepare(
            "SELECT COUNT(*) FROM tec_usuarios WHERE role_id = :r AND tipo = 'Administrador' AND is_internal_staff = 0"
        );
        $insLink = $pdo->prepare("INSERT IGNORE INTO tec_role_has_permissions (role_id, permission_id) VALUES (:r, :p)");

        foreach ($roleAssignments as $roleKey => $keys) {
            if (!isset($rolesMap[$roleKey])) {
                $ctx->warn("Role $roleKey not found in tec_roles");
                continue;
            }
            $roleId = $rolesMap[$roleKey];

            // Safeguard D-25
            $checkUnitAdmin->execute([':r' => $roleId]);
            $isUnitAdminRole = ((int) $checkUnitAdmin->fetchColumn()) > 0;

            $added = 0;
            foreach ($keys as $key) {
                if ($isUnitAdminRole && (str_starts_with($key, 'gastos.') || $key === 'facturas.invoices.manage')) {
                    $ctx->warn("role $roleKey has unit-admin users; $key skipped");
                    continue;
                }
                if (isset($permMap[$key])) {
                    $insLink->execute([':r' => $roleId, ':p' => $permMap[$key]]);
                    $added += $insLink->rowCount();
                }
            }
            $ctx->out("Role $roleKey ($roleId): +$added permissions linked");
        }

        // Print human inspection listing
        $stmtUsers = $pdo->query(
            "SELECT r.role_key, u.id, u.nickname, u.tipo, u.is_internal_staff
             FROM tec_usuarios u
             LEFT JOIN tec_roles r ON r.id = u.role_id
             ORDER BY r.role_key, u.id"
        );
        while ($u = $stmtUsers->fetch()) {
            $ctx->out(sprintf(
                "USER INSPECTION: role=%-25s id=%-4d tipo=%-18s internal=%d nick=%s",
                (string) ($u['role_key'] ?? 'NO_ROLE'),
                (int) $u['id'],
                (string) $u['tipo'],
                (int) $u['is_internal_staff'],
                (string) $u['nickname']
            ));
        }

        // 6. Bump perm_version
        $pdo->exec("UPDATE tec_usuarios SET perm_version = perm_version + 1");
        $ctx->out("Bumped perm_version for all users");
    }

    public function rollbackNotes(): string
    {
        return 'Manual removal of 40 keys from tec_role_has_permissions and update is_active=0.';
    }
};
