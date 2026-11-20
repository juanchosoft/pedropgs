<?php

declare(strict_types=1);

namespace Pgs\Core;

final class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        if (Config::env() === 'testing') {
            $c = Config::section('database_test');
            $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost') . ';dbname=' . ($c['name'] ?? 'pgscentrum_test') . ';charset=utf8mb4';
            $pdo = new \PDO($dsn, (string) ($c['user'] ?? 'root'), (string) ($c['pass'] ?? ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        } else {
            require_once dirname(__DIR__, 2) . '/DbConection.php';
            $pdo = (new \DbConection())->openConect();
        }
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET NAMES utf8mb4');
        return self::$pdo = $pdo;
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}
