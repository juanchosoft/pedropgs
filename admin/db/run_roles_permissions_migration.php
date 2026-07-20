<?php
/**
 * Migración RBAC pgscentrum: tablas tec_* + seed + roles por huella + role_id usuarios.
 * Uso: php admin/db/run_roles_permissions_migration.php
 */
require_once __DIR__ . '/../classes/DbConection.php';
require_once __DIR__ . '/../classes/PermissionCatalog.php';

function out($msg) {
    echo $msg . PHP_EOL;
}

$db = new DbConection();
$pdo = $db->openConect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

out('=== RBAC migration start ===');

$pdo->exec("
CREATE TABLE IF NOT EXISTS pgscentrum.tec_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(120) NOT NULL,
  module VARCHAR(80) NOT NULL,
  action VARCHAR(40) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  legacy_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  dt_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tec_permissions_key (permission_key),
  KEY idx_tec_permissions_module (module),
  KEY idx_tec_permissions_legacy (legacy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS pgscentrum.tec_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  dt_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dt_update DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tec_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS pgscentrum.tec_role_has_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_trhp_role FOREIGN KEY (role_id) REFERENCES pgscentrum.tec_roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_trhp_permission FOREIGN KEY (permission_id) REFERENCES pgscentrum.tec_permissions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// role_id column
$col = $pdo->query("SHOW COLUMNS FROM pgscentrum.tec_usuarios LIKE 'role_id'")->fetch(PDO::FETCH_ASSOC);
if (!$col) {
    $pdo->exec("ALTER TABLE pgscentrum.tec_usuarios ADD COLUMN role_id INT UNSIGNED NULL AFTER tipo, ADD KEY idx_tec_usuarios_role_id (role_id)");
    out('Added tec_usuarios.role_id');
} else {
    out('tec_usuarios.role_id already exists');
}

// Seed permissions
$insPerm = $pdo->prepare("
INSERT INTO pgscentrum.tec_permissions (permission_key, module, action, name, legacy_id, is_active, dt_create)
VALUES (:k, :m, :a, :n, :l, 1, NOW())
ON DUPLICATE KEY UPDATE module=VALUES(module), action=VALUES(action), name=VALUES(name), legacy_id=VALUES(legacy_id), is_active=1
");
foreach (PermissionCatalog::definitions() as $d) {
    $insPerm->execute([
        ':k' => $d['key'],
        ':m' => $d['module'],
        ':a' => $d['action'],
        ':n' => $d['name'],
        ':l' => $d['legacy_id'],
    ]);
}
out('Seeded permissions: ' . count(PermissionCatalog::definitions()));

// Map legacy_id -> permission.id
$legacyToPermId = [];
foreach ($pdo->query("SELECT id, legacy_id FROM pgscentrum.tec_permissions WHERE legacy_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $legacyToPermId[(int) $r['legacy_id']] = (int) $r['id'];
}
$keyToPermId = [];
foreach ($pdo->query("SELECT id, permission_key FROM pgscentrum.tec_permissions")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $keyToPermId[$r['permission_key']] = (int) $r['id'];
}

// Seed fingerprint roles + permissions
$insRole = $pdo->prepare("
INSERT INTO pgscentrum.tec_roles (role_key, name, description, is_system, dt_create)
VALUES (:k, :n, :d, :s, NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), is_system=VALUES(is_system)
");
$delRp = $pdo->prepare("DELETE rp FROM pgscentrum.tec_role_has_permissions rp INNER JOIN pgscentrum.tec_roles r ON r.id = rp.role_id WHERE r.role_key = :k");
$insRp = $pdo->prepare("INSERT IGNORE INTO pgscentrum.tec_role_has_permissions (role_id, permission_id) VALUES (:r, :p)");

foreach (PermissionCatalog::fingerprintRoles() as $fp => $roleDef) {
    $insRole->execute([
        ':k' => $roleDef['role_key'],
        ':n' => $roleDef['name'],
        ':d' => $roleDef['description'],
        ':s' => (int) $roleDef['is_system'],
    ]);
    $roleId = (int) $pdo->query("SELECT id FROM pgscentrum.tec_roles WHERE role_key = " . $pdo->quote($roleDef['role_key']))->fetchColumn();
    $delRp->execute([':k' => $roleDef['role_key']]);
    foreach ($roleDef['legacy_ids'] as $legacyId) {
        if (!isset($legacyToPermId[$legacyId])) {
            out("WARN: missing permission for legacy_id=$legacyId (role {$roleDef['role_key']})");
            continue;
        }
        $insRp->execute([':r' => $roleId, ':p' => $legacyToPermId[$legacyId]]);
    }
    $extraKeys = isset($roleDef['extra_permission_keys']) ? $roleDef['extra_permission_keys'] : [];
    foreach ($extraKeys as $extraKey) {
        if (!isset($keyToPermId[$extraKey])) {
            out("WARN: missing permission for key=$extraKey (role {$roleDef['role_key']})");
            continue;
        }
        $insRp->execute([':r' => $roleId, ':p' => $keyToPermId[$extraKey]]);
    }
    out("Role {$roleDef['role_key']} id=$roleId legacy=" . count($roleDef['legacy_ids']) . " extra=" . count($extraKeys));
}

// Assign users by fingerprint
$users = $pdo->query("SELECT id, nickname, tipo FROM pgscentrum.tec_usuarios")->fetchAll(PDO::FETCH_ASSOC);
$getPerms = $pdo->prepare("SELECT tec_permiso_id FROM pgscentrum.tec_usuarios_has_tec_permisos WHERE tec_usuarios_id = :id ORDER BY tec_permiso_id");
$updUser = $pdo->prepare("UPDATE pgscentrum.tec_usuarios SET role_id = :roleId WHERE id = :id");
$roleIdByKey = [];
foreach ($pdo->query("SELECT id, role_key FROM pgscentrum.tec_roles")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $roleIdByKey[$r['role_key']] = (int) $r['id'];
}
$fpMap = PermissionCatalog::fingerprintRoles();
$assigned = 0;
$unknown = 0;
foreach ($users as $u) {
    $getPerms->execute([':id' => (int) $u['id']]);
    $ids = array_map('intval', $getPerms->fetchAll(PDO::FETCH_COLUMN) ?: []);
    sort($ids);
    $fp = implode(',', $ids);
    if (!isset($fpMap[$fp])) {
        out("UNKNOWN fingerprint user {$u['id']} {$u['nickname']}: [$fp] → role_empty");
        $roleKey = 'role_empty';
        $unknown++;
    } else {
        $roleKey = $fpMap[$fp]['role_key'];
    }
    // SuperAdmin always get super role if fingerprint is full; bypass covers them anyway
    if ($u['tipo'] === 'SuperAdministrador' && isset($roleIdByKey['super_administrador']) && $fp === '') {
        $roleKey = 'super_administrador';
    }
    if (!isset($roleIdByKey[$roleKey])) {
        out("ERROR missing role_key=$roleKey");
        continue;
    }
    $updUser->execute([':roleId' => $roleIdByKey[$roleKey], ':id' => (int) $u['id']]);
    $assigned++;
}

out("Users assigned role_id: $assigned (unknown fingerprints routed: $unknown)");
out('=== RBAC migration done ===');
