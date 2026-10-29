<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_002_users_voice_columns';
    }

    public function description(): string
    {
        return 'User voice columns, employee_pk linkage, and is_internal_staff flag';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'phone_e164', "VARCHAR(20) CHARACTER SET ascii COLLATE ascii_general_ci NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'notify_email', "VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_hash', "VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_set_at', "DATETIME NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_failed', "TINYINT UNSIGNED NOT NULL DEFAULT 0", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_fail_window_start', "DATETIME NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_lock_level', "TINYINT UNSIGNED NOT NULL DEFAULT 0", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_locked_until', "DATETIME NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'voice_pin_admin_locked', "TINYINT(1) NOT NULL DEFAULT 0", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'lang', "ENUM('es','en') CHARACTER SET ascii NOT NULL DEFAULT 'en'", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'whatsapp_opt_in', "TINYINT(1) NOT NULL DEFAULT 0", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'whatsapp_opt_in_at', "DATETIME NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'employee_pk', "INT NULL", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'perm_version', "INT UNSIGNED NOT NULL DEFAULT 0", $ctx);
        SchemaHelper::addColumn($pdo, 'tec_usuarios', 'is_internal_staff', "TINYINT(1) NOT NULL DEFAULT 0", $ctx);

        SchemaHelper::addIndex($pdo, 'tec_usuarios', 'uq_tec_usuarios_phone_e164', "UNIQUE KEY uq_tec_usuarios_phone_e164 (phone_e164)", $ctx);
        SchemaHelper::addIndex($pdo, 'tec_usuarios', 'idx_tec_usuarios_employee_pk', "KEY idx_tec_usuarios_employee_pk (employee_pk)", $ctx);

        // 1. Backfill notify_email
        $updatedEmail = $pdo->exec(
            "UPDATE tec_usuarios SET notify_email = nickname WHERE notify_email IS NULL AND nickname REGEXP '^[^@ ]+@[^@ ]+\\.[^@ ]+$'"
        );
        $ctx->out("Backfill notify_email updated: " . ($updatedEmail !== false ? $updatedEmail : 0));

        // 2. Backfill employee_pk
        $stmtUsers = $pdo->query("SELECT id, employee_id FROM tec_usuarios WHERE employee_id IS NOT NULL AND employee_id <> 0");
        $empCheckStmt = $pdo->prepare("SELECT id FROM tec_employee WHERE cc = CAST(:eid AS CHAR)");
        $updEmpStmt = $pdo->prepare("UPDATE tec_usuarios SET employee_pk = :pk WHERE id = :uid AND employee_pk IS NULL");

        $linked = 0;
        $unmatched = 0;
        $ambiguous = 0;

        while ($u = $stmtUsers->fetch()) {
            $eid = (string) $u['employee_id'];
            $uid = (int) $u['id'];
            $empCheckStmt->execute([':eid' => $eid]);
            $matches = $empCheckStmt->fetchAll();
            $cnt = count($matches);
            if ($cnt === 1) {
                $updEmpStmt->execute([':pk' => (int) $matches[0]['id'], ':uid' => $uid]);
                $linked++;
            } elseif ($cnt === 0) {
                $ctx->warn("user $uid employee cc $eid -> 0 matches");
                $unmatched++;
            } else {
                $ctx->warn("user $uid employee cc $eid -> $cnt matches");
                $ambiguous++;
            }
        }
        $ctx->out("Backfill employee_pk: linked=$linked, unmatched=$unmatched, ambiguous=$ambiguous");

        // 3. Backfill is_internal_staff
        $updSuperStaff = $pdo->exec(
            "UPDATE tec_usuarios SET is_internal_staff = 1 WHERE is_internal_staff = 0 AND tipo IN ('SuperAdministrador','Staff')"
        );
        $ctx->out("Set is_internal_staff for SuperAdministrador and Staff: " . ($updSuperStaff !== false ? $updSuperStaff : 0));

        $adminCheck = $pdo->query(
            "SELECT u.id, u.nickname FROM tec_usuarios u
             JOIN tec_roles r ON r.id = u.role_id
             WHERE u.tipo = 'Administrador' AND r.role_key = 'catalog_full_45'"
        )->fetchAll();

        foreach ($adminCheck as $adm) {
            $ctx->out("INFO internal Administrador users: {$adm['id']} {$adm['nickname']}");
        }

        $updAdmin = $pdo->exec(
            "UPDATE tec_usuarios u JOIN tec_roles r ON r.id = u.role_id
             SET u.is_internal_staff = 1
             WHERE u.is_internal_staff = 0 AND u.tipo = 'Administrador' AND r.role_key = 'catalog_full_45'"
        );
        $ctx->out("Set is_internal_staff for catalog_full_45 Administrador: " . ($updAdmin !== false ? $updAdmin : 0));

        $totalInternal = (int) $pdo->query("SELECT COUNT(*) FROM tec_usuarios WHERE is_internal_staff = 1")->fetchColumn();
        $ctx->out("Total is_internal_staff users: $totalInternal");
    }

    public function rollbackNotes(): string
    {
        return 'Manual ALTER TABLE tec_usuarios DROP COLUMN for added columns.';
    }
};
