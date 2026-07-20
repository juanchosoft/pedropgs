<?php

require_once __DIR__ . '/PermissionCatalog.php';

final class PermissionLegacyMap
{
    /** @var array<int,string>|null */
    private static $idToKey = null;
    /** @var array<string,int>|null */
    private static $keyToId = null;

    private static function boot(): void
    {
        if (self::$idToKey !== null) {
            return;
        }
        self::$idToKey = PermissionCatalog::legacyIdToKey();
        self::$keyToId = PermissionCatalog::legacyKeyToId();
    }

    public static function idToKey(int $id): ?string
    {
        self::boot();
        return self::$idToKey[$id] ?? null;
    }

    public static function keyToId(string $key): ?int
    {
        self::boot();
        return self::$keyToId[$key] ?? null;
    }

    /** @param string[] $keys @return int[] */
    public static function keysToLegacyIds(array $keys): array
    {
        self::boot();
        $ids = [];
        foreach ($keys as $key) {
            if (isset(self::$keyToId[$key])) {
                $ids[] = self::$keyToId[$key];
            }
        }
        return array_values(array_unique($ids));
    }

    /** @param int[] $ids @return string[] */
    public static function legacyIdsToKeys(array $ids): array
    {
        self::boot();
        $keys = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (isset(self::$idToKey[$id])) {
                $keys[] = self::$idToKey[$id];
            }
        }
        return array_values(array_unique($keys));
    }
}
