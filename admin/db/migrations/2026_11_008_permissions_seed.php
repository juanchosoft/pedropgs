<?php

declare(strict_types=1);

use Pgs\Core\Clock;
use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;

return new class extends AbstractMigration {
    private const DEFINITIONS = [
        ['solicitudes.requests.view', 'solicitudes', 'view', 'Requests - View'],
        ['solicitudes.requests.manage', 'solicitudes', 'manage', 'Requests - Manage'],
        ['solicitudes.responders.manage', 'solicitudes', 'responders_manage', 'Requests - Manage Responders'],
        ['solicitudes.settings.manage', 'solicitudes', 'settings_manage', 'Requests - Manage Voice Settings'],
    ];

    private const ROLE_GRANTS = [
        'super_admin_standard' => ['solicitudes.requests.view', 'solicitudes.requests.manage', 'solicitudes.responders.manage', 'solicitudes.settings.manage'],
        'catalog_full_45' => ['solicitudes.requests.view', 'solicitudes.requests.manage', 'solicitudes.responders.manage', 'solicitudes.settings.manage'],
        'admin_hoa_reports' => ['solicitudes.requests.view'],
        'admin_hoa_reports_checklist' => ['solicitudes.requests.view'],
    ];

    public function id(): string
    {
        return '2026_11_008_permissions_seed';
    }

    public function description(): string
    {
        return 'Seed request and voice settings permissions (incremental, no deletes)';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        $now = Clock::nowUtcSql();
        $insPerm = $pdo->prepare(
            'INSERT INTO tec_permissions (permission_key, module, action, name, legacy_id, is_active, dt_create)
             VALUES (:k, :m, :a, :n, NULL, 1, :now)
             ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1'
        );
        foreach (self::DEFINITIONS as [$key, $module, $action, $name]) {
            $insPerm->execute([':k' => $key, ':m' => $module, ':a' => $action, ':n' => $name, ':now' => $now]);
        }

        $ids = [];
        foreach ($pdo->query("SELECT id, permission_key FROM tec_permissions WHERE permission_key LIKE 'solicitudes.%'") as $row) {
            $ids[(string) $row['permission_key']] = (int) $row['id'];
        }

        $roleIds = [];
        foreach ($pdo->query('SELECT id, role_key FROM tec_roles') as $row) {
            $roleIds[(string) $row['role_key']] = (int) $row['id'];
        }

        $grant = $pdo->prepare('INSERT IGNORE INTO tec_role_has_permissions (role_id, permission_id) VALUES (:r, :p)');
        $granted = 0;
        foreach (self::ROLE_GRANTS as $roleKey => $keys) {
            if (!isset($roleIds[$roleKey])) {
                $ctx->warn("Role {$roleKey} not found; skipped");
                continue;
            }
            foreach ($keys as $key) {
                if (!isset($ids[$key])) {
                    continue;
                }
                $grant->execute([':r' => $roleIds[$roleKey], ':p' => $ids[$key]]);
                $granted += $grant->rowCount();
            }
        }
        $ctx->out("Permissions seeded; {$granted} role grants added");
    }
};
