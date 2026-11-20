<?php

declare(strict_types=1);

namespace Pgs\Migrations;

final class SchemaHelper
{
    private const TABLE_OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC';

    private static function scalar(\PDO $pdo, string $sql, array $params): mixed
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function tableExists(\PDO $pdo, string $t): bool
    {
        return (int) self::scalar(
            $pdo,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            [':t' => $t]
        ) > 0;
    }

    public static function columnExists(\PDO $pdo, string $t, string $c): bool
    {
        return (int) self::scalar(
            $pdo,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c',
            [':t' => $t, ':c' => $c]
        ) > 0;
    }

    public static function indexExists(\PDO $pdo, string $t, string $i): bool
    {
        return (int) self::scalar(
            $pdo,
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i',
            [':t' => $t, ':i' => $i]
        ) > 0;
    }

    public static function fkExists(\PDO $pdo, string $t, string $fk): bool
    {
        return (int) self::scalar(
            $pdo,
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [':t' => $t, ':c' => $fk]
        ) > 0;
    }

    public static function engineOf(\PDO $pdo, string $t): ?string
    {
        $v = self::scalar(
            $pdo,
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            [':t' => $t]
        );
        return $v === false || $v === null ? null : (string) $v;
    }

    public static function assertInnoDb(\PDO $pdo, string $t): void
    {
        $row = $pdo->prepare(
            'SELECT ENGINE, ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $row->execute([':t' => $t]);
        $info = $row->fetch();
        if (!is_array($info) || strcasecmp((string) $info['ENGINE'], 'InnoDB') !== 0) {
            throw new \RuntimeException("Table $t is not InnoDB");
        }
        if (strcasecmp((string) $info['ROW_FORMAT'], 'Dynamic') !== 0) {
            throw new \RuntimeException("Table $t is not ROW_FORMAT=DYNAMIC");
        }
    }

    /**
     * @param array<string,string> $fks nombre => definición "FOREIGN KEY (...) REFERENCES ..."
     */
    public static function createTable(\PDO $pdo, string $t, string $ddl, MigrationContext $ctx, array $fks = []): void
    {
        $existed = self::tableExists($pdo, $t);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `' . $t . '` ' . $ddl . ' ' . self::TABLE_OPTIONS);
        self::assertInnoDb($pdo, $t);
        $ctx->out(($existed ? 'SKIP ' : 'OK CREATE ') . $t);
        foreach ($fks as $name => $definition) {
            self::addFk($pdo, $t, $name, $definition, $ctx);
        }
    }

    public static function addColumn(\PDO $pdo, string $t, string $c, string $definition, MigrationContext $ctx): void
    {
        if (self::columnExists($pdo, $t, $c)) {
            $ctx->out("SKIP column $t.$c");
            return;
        }
        $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $definition");
        $ctx->out("OK ADD COLUMN $t.$c");
    }

    public static function addIndex(\PDO $pdo, string $t, string $i, string $definition, MigrationContext $ctx): void
    {
        if (self::indexExists($pdo, $t, $i)) {
            $ctx->out("SKIP index $t.$i");
            return;
        }
        $pdo->exec("ALTER TABLE `$t` ADD $definition");
        $ctx->out("OK ADD INDEX $t.$i");
    }

    public static function addFk(\PDO $pdo, string $t, string $fk, string $definition, MigrationContext $ctx): void
    {
        if (self::fkExists($pdo, $t, $fk)) {
            $ctx->out("SKIP fk $t.$fk");
            return;
        }
        if (preg_match('/REFERENCES\s+`?(\w+)`?/i', $definition, $m) === 1) {
            $engine = self::engineOf($pdo, $m[1]);
            if ($engine !== null && strcasecmp($engine, 'InnoDB') !== 0) {
                $ctx->warn("fk skipped ($m[1] engine=$engine) $t.$fk");
                return;
            }
        }
        $pdo->exec("ALTER TABLE `$t` ADD CONSTRAINT `$fk` $definition");
        $ctx->out("OK ADD FK $t.$fk");
    }
}
