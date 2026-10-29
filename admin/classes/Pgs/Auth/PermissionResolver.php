<?php

declare(strict_types=1);

namespace Pgs\Auth;

final class PermissionResolver
{
    /**
     * @return array{keys:list<string>,roleKey:?string,userType:string,employeeId:?int,enabled:bool,lang:string,isInternalStaff:bool}
     */
    public static function forUser(\PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.tipo, u.role_id, u.employee_pk, u.lang, u.habilitado, u.is_internal_staff, r.role_key
             FROM tec_usuarios u
             LEFT JOIN tec_roles r ON r.id = u.role_id
             WHERE u.id = :id"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return [
                'keys' => [],
                'roleKey' => null,
                'userType' => '',
                'employeeId' => null,
                'enabled' => false,
                'lang' => 'en',
                'isInternalStaff' => false,
            ];
        }

        $roleId = $user['role_id'] !== null ? (int) $user['role_id'] : null;
        $keys = [];
        if ($roleId !== null) {
            $stmtPerms = $pdo->prepare(
                "SELECT p.permission_key FROM tec_permissions p
                 JOIN tec_role_has_permissions rp ON rp.permission_id = p.id
                 WHERE rp.role_id = :rid AND p.is_active = 1"
            );
            $stmtPerms->execute([':rid' => $roleId]);
            while ($k = $stmtPerms->fetchColumn()) {
                $keys[] = (string) $k;
            }
        }

        return [
            'keys' => $keys,
            'roleKey' => $user['role_key'] !== null ? (string) $user['role_key'] : null,
            'userType' => (string) ($user['tipo'] ?? ''),
            'employeeId' => $user['employee_pk'] !== null ? (int) $user['employee_pk'] : null,
            'enabled' => ((string) ($user['habilitado'] ?? '')) === '1',
            'lang' => in_array((string) ($user['lang'] ?? ''), ['es', 'en'], true) ? (string) $user['lang'] : 'en',
            'isInternalStaff' => ((int) ($user['is_internal_staff'] ?? 0)) === 1,
        ];
    }
}
