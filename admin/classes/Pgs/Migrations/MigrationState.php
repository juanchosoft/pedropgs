<?php

declare(strict_types=1);

namespace Pgs\Migrations;

use Pgs\Core\Db;

final class MigrationState
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function isApplied(string $id): bool
    {
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tec_migrations WHERE id = :id AND status = "applied"');
            $stmt->execute([':id' => $id]);
            $isApplied = ((int) $stmt->fetchColumn()) > 0;
            self::$cache[$id] = $isApplied;
            return $isApplied;
        } catch (\Throwable) {
            self::$cache[$id] = false;
            return false;
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
