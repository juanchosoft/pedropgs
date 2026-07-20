<?php

require_once __DIR__ . '/DbConection.php';
require_once __DIR__ . '/Util.php';

/**
 * Roles CRUD + safe permission sync (anti-wipe on empty update).
 */
class Role
{
    public static function getAll($rqst = [])
    {
        $db = new DbConection();
        $pdo = $db->openConect();
        try {
            $sql = "SELECT r.*,
                    (SELECT COUNT(*) FROM " . $db->getTable('tec_role_has_permissions') . " rp WHERE rp.role_id = r.id) AS permisos_count,
                    (SELECT COUNT(*) FROM " . $db->getTable('tec_usuarios') . " u WHERE u.role_id = r.id) AS usuarios_count
                    FROM " . $db->getTable('tec_roles') . " r
                    ORDER BY r.is_system DESC, r.name ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return ['output' => ['valid' => true, 'response' => $rows]];
        } catch (Throwable $e) {
            return Util::error_general('Error listing roles: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    public static function getById($rqst)
    {
        $id = isset($rqst['id']) ? (int) $rqst['id'] : 0;
        if ($id <= 0) {
            return Util::error_missing_data();
        }
        $db = new DbConection();
        $pdo = $db->openConect();
        try {
            $stmt = $pdo->prepare("SELECT * FROM " . $db->getTable('tec_roles') . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$role) {
                return Util::error_no_result();
            }
            $stmtPerm = $pdo->prepare(
                "SELECT permission_id FROM " . $db->getTable('tec_role_has_permissions') . " WHERE role_id = :id"
            );
            $stmtPerm->execute([':id' => $id]);
            $role['permission_ids'] = array_map('intval', $stmtPerm->fetchAll(PDO::FETCH_COLUMN) ?: []);
            return ['output' => ['valid' => true, 'response' => $role]];
        } catch (Throwable $e) {
            return Util::error_general('Error loading role: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    public static function getPermissionsCatalog($rqst = [])
    {
        $db = new DbConection();
        $pdo = $db->openConect();
        try {
            $sql = "SELECT id, permission_key AS `key`, module, action, name
                    FROM " . $db->getTable('tec_permissions') . "
                    WHERE is_active = 1
                    ORDER BY module ASC, name ASC";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                return Util::error_general('Permission catalog is empty. Run the migration seed first.');
            }
            $grouped = [];
            foreach ($rows as $row) {
                $row['id'] = (int) $row['id'];
                if (empty($row['id'])) {
                    return Util::error_general('Permission catalog returned rows without id.');
                }
                $grouped[$row['module']][] = $row;
            }
            return ['output' => ['valid' => true, 'response' => $grouped]];
        } catch (Throwable $e) {
            return Util::error_general('Error loading permission catalog: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? (int) $rqst['id'] : 0;
        $name = trim($rqst['name'] ?? '');
        $description = trim($rqst['description'] ?? '');
        $permissionIds = self::parsePermissionIds($rqst['permission_ids'] ?? '');

        if ($name === '') {
            return Util::error_missing_data();
        }

        $db = new DbConection();
        $pdo = $db->openConect();
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT is_system, role_key FROM " . $db->getTable('tec_roles') . " WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    $pdo->rollBack();
                    return Util::error_no_result();
                }

                // Anti-wipe: empty IDs on update when role already has permissions
                if (empty($permissionIds)) {
                    $stmtCnt = $pdo->prepare(
                        "SELECT COUNT(*) FROM " . $db->getTable('tec_role_has_permissions') . " WHERE role_id = :roleId"
                    );
                    $stmtCnt->execute([':roleId' => $id]);
                    if ((int) $stmtCnt->fetchColumn() > 0) {
                        $pdo->rollBack();
                        return Util::error_general(
                            'No valid permission IDs were received. To avoid clearing the role, reload the catalog and try again.'
                        );
                    }
                }

                // role_key is immutable after creation (hidden from UI)
                $stmtUp = $pdo->prepare(
                    "UPDATE " . $db->getTable('tec_roles') . "
                     SET name = :name, description = :description, dt_update = NOW()
                     WHERE id = :id"
                );
                $stmtUp->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':id' => $id,
                ]);
                $roleId = $id;
            } else {
                $roleKey = self::generateUniqueRoleKey($pdo, $db, $name);
                $stmtIns = $pdo->prepare(
                    "INSERT INTO " . $db->getTable('tec_roles') . "
                    (role_key, name, description, is_system, dt_create)
                    VALUES (:role_key, :name, :description, 0, NOW())"
                );
                $stmtIns->execute([
                    ':role_key' => $roleKey,
                    ':name' => $name,
                    ':description' => $description,
                ]);
                $roleId = (int) $pdo->lastInsertId();
            }

            self::syncRolePermissions($pdo, $db, $roleId, $permissionIds);
            $pdo->commit();
            return ['output' => ['valid' => true, 'response' => ['id' => $roleId]]];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Util::error_general('Error saving role: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? (int) $rqst['id'] : 0;
        if ($id <= 0) {
            return Util::error_missing_data();
        }
        $db = new DbConection();
        $pdo = $db->openConect();
        try {
            $stmt = $pdo->prepare("SELECT is_system FROM " . $db->getTable('tec_roles') . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$role) {
                return Util::error_no_result();
            }
            if ((int) $role['is_system'] === 1) {
                return Util::error_general('System roles cannot be deleted.');
            }
            $stmtUsers = $pdo->prepare("SELECT COUNT(*) FROM " . $db->getTable('tec_usuarios') . " WHERE role_id = :id");
            $stmtUsers->execute([':id' => $id]);
            if ((int) $stmtUsers->fetchColumn() > 0) {
                return Util::error_general('Cannot delete a role assigned to users.');
            }
            $pdo->prepare("DELETE FROM " . $db->getTable('tec_roles') . " WHERE id = :id")->execute([':id' => $id]);
            return ['output' => ['valid' => true, 'response' => ['id' => $id]]];
        } catch (Throwable $e) {
            return Util::error_general('Error deleting role: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    /** @return int[] */
    private static function parsePermissionIds($raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[,\-\s]+/', (string) $raw) ?: [];
        }
        $ids = [];
        foreach ($parts as $p) {
            $n = (int) $p;
            if ($n > 0) {
                $ids[] = $n;
            }
        }
        return array_values(array_unique($ids));
    }

    private static function normalizeRoleKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? $key;
        $key = trim($key, '_');
        return $key !== '' ? $key : 'role';
    }

    /**
     * Autogenera role_key único a partir del nombre (solo en creación).
     */
    private static function generateUniqueRoleKey(PDO $pdo, DbConection $db, string $name): string
    {
        $base = self::normalizeRoleKey($name);
        $candidate = $base;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM " . $db->getTable('tec_roles') . " WHERE role_key = :k"
        );
        $n = 2;
        while (true) {
            $stmt->execute([':k' => $candidate]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }
            $candidate = $base . '_' . $n;
            $n++;
            if ($n > 9999) {
                return $base . '_' . bin2hex(random_bytes(3));
            }
        }
    }

    /** @param int[] $permissionIds */
    private static function syncRolePermissions(PDO $pdo, DbConection $db, int $roleId, array $permissionIds): void
    {
        $pdo->prepare("DELETE FROM " . $db->getTable('tec_role_has_permissions') . " WHERE role_id = :roleId")
            ->execute([':roleId' => $roleId]);
        if (empty($permissionIds)) {
            return;
        }
        $ins = $pdo->prepare(
            "INSERT INTO " . $db->getTable('tec_role_has_permissions') . " (role_id, permission_id) VALUES (:roleId, :permissionId)"
        );
        foreach ($permissionIds as $permissionId) {
            $ins->execute([':roleId' => $roleId, ':permissionId' => (int) $permissionId]);
        }
    }
}
