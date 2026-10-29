<?php

declare(strict_types=1);

namespace Pgs\Auth;

use Pgs\Core\Db;
use Pgs\Migrations\Seed\NewPermissions;

final class PrincipalFactory
{
    public static function fromSession(string $channel = 'web'): Principal
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionUser = $_SESSION['session_user'] ?? null;
        if (!is_array($sessionUser) || empty($sessionUser['id'])) {
            return new Principal(
                kind: 'anonymous',
                userId: null,
                userType: '',
                roleKey: null,
                permissionKeys: [],
                employeeId: null,
                unitIds: [],
                channel: $channel,
                verification: 'none',
                lang: 'en'
            );
        }

        $userId = (int) $sessionUser['id'];
        $now = time();
        $permLoadedAt = (int) ($sessionUser['perm_loaded_at'] ?? 0);

        $pdo = Db::pdo();
        if ($permLoadedAt === 0 || ($now - $permLoadedAt) > 300) {
            if (!class_exists(\Authorization::class, false)) {
                require_once dirname(__DIR__, 2) . '/Authorization.php';
            }
            if (!class_exists(\PermissionLegacyMap::class, false)) {
                require_once dirname(__DIR__, 2) . '/PermissionLegacyMap.php';
            }
            $legacyDb = new \DbConection();
            $legacyPdo = $legacyDb->openConect();
            try {
                $auth = \Authorization::loadUserAuth($legacyPdo, $legacyDb, $userId, (string) ($sessionUser['tipo'] ?? ''));
            } finally {
                $legacyDb->closeConect();
            }
            $_SESSION['session_user']['permission_keys'] = $auth['permission_keys'] ?? [];
            $_SESSION['session_user']['permisos'] = $auth['legacy_ids'] ?? ($sessionUser['permisos'] ?? []);
            $_SESSION['session_user']['role'] = $auth['role'] ?? null;
            $_SESSION['session_user']['perm_loaded_at'] = $now;
        }

        $info = PermissionResolver::forUser($pdo, $userId);
        $unitIds = UnitScope::forUser($pdo, $userId, $info['userType']);

        return new Principal(
            kind: 'user',
            userId: $userId,
            userType: $info['userType'],
            roleKey: $info['roleKey'],
            permissionKeys: $info['keys'],
            employeeId: $info['employeeId'],
            unitIds: $unitIds,
            channel: $channel,
            verification: 'session',
            lang: $info['lang'],
            isInternalStaff: $info['isInternalStaff']
        );
    }

    public static function fromUserId(
        int $userId,
        string $channel,
        string $verification,
        ?int $callSessionId = null,
        ?int $apiTokenId = null,
        array $scopes = []
    ): Principal {
        $pdo = Db::pdo();
        $info = PermissionResolver::forUser($pdo, $userId);
        $unitIds = UnitScope::forUser($pdo, $userId, $info['userType']);

        return new Principal(
            kind: $callSessionId !== null ? 'voice' : ($apiTokenId !== null ? 'api' : 'user'),
            userId: $userId,
            userType: $info['userType'],
            roleKey: $info['roleKey'],
            permissionKeys: $info['keys'],
            employeeId: $info['employeeId'],
            unitIds: $unitIds,
            channel: $channel,
            verification: $verification,
            lang: $info['lang'],
            callSessionId: $callSessionId,
            apiTokenId: $apiTokenId,
            tokenScopes: $scopes,
            isInternalStaff: $info['isInternalStaff']
        );
    }

    public static function anonymousVoice(int $callSessionId, int $apiTokenId, array $scopes): Principal
    {
        return new Principal(
            kind: 'anonymous',
            userId: null,
            userType: '',
            roleKey: null,
            permissionKeys: [],
            employeeId: null,
            unitIds: [],
            channel: 'voice',
            verification: 'none',
            lang: 'es',
            callSessionId: $callSessionId,
            apiTokenId: $apiTokenId,
            tokenScopes: $scopes,
            isInternalStaff: false
        );
    }

    public static function system(string $channel = 'cron'): Principal
    {
        $allKeys = array_column(NewPermissions::definitions(), 'key');
        if (class_exists(\PermissionCatalog::class)) {
            $allKeys = array_unique(array_merge($allKeys, \PermissionCatalog::allKeys()));
        }

        return new Principal(
            kind: 'system',
            userId: null,
            userType: \Util::SuperAdmin(),
            roleKey: 'super_administrador',
            permissionKeys: array_values($allKeys),
            employeeId: null,
            unitIds: null,
            channel: $channel,
            verification: 'session',
            lang: 'en',
            isInternalStaff: true
        );
    }

    public static function anonymous(string $channel = 'web'): Principal
    {
        return new Principal(
            kind: 'anonymous',
            userId: null,
            userType: '',
            roleKey: null,
            permissionKeys: [],
            employeeId: null,
            unitIds: [],
            channel: $channel,
            verification: 'none',
            lang: 'es',
            callSessionId: null,
            apiTokenId: null,
            tokenScopes: [],
            isInternalStaff: false
        );
    }
}
