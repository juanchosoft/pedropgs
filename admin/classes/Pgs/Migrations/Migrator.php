<?php

declare(strict_types=1);

namespace Pgs\Migrations;

use Pgs\Core\Clock;
use Pgs\Core\Db;

final class Migrator
{
    public function __construct(private ?string $dir = null, private ?\PDO $pdo = null)
    {
        $this->dir ??= dirname(__DIR__, 3) . '/db/migrations';
    }

    private function pdo(): \PDO
    {
        $pdo = $this->pdo ?? Db::pdo();
        $this->ensureTable($pdo);
        return $pdo;
    }

    private function ensureTable(\PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tec_migrations (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              migration VARCHAR(190) NOT NULL,
              checksum CHAR(64) NOT NULL,
              status ENUM('applied','failed','in_progress') NOT NULL DEFAULT 'applied',
              output MEDIUMTEXT NULL,
              duration_ms INT UNSIGNED NULL,
              applied_by_user_id INT UNSIGNED NULL,
              applied_via ENUM('web','cli') NOT NULL,
              applied_at DATETIME NOT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_tec_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"
        );
    }

    /**
     * @return array<string,string> id => ruta
     */
    private function files(): array
    {
        $files = glob($this->dir . '/2026_*.php') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $file) {
            $out[basename($file, '.php')] = $file;
        }
        return $out;
    }

    private function load(string $file): AbstractMigration
    {
        $migration = require $file;
        if (!$migration instanceof AbstractMigration) {
            throw new \RuntimeException('Invalid migration file ' . basename($file));
        }
        return $migration;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function status(): array
    {
        $pdo = $this->pdo();
        $rows = [];
        foreach ($pdo->query('SELECT * FROM tec_migrations') as $row) {
            $rows[(string) $row['migration']] = $row;
        }
        $result = [];
        foreach ($this->files() as $id => $file) {
            $migration = $this->load($file);
            $checksum = hash_file('sha256', $file);
            $row = $rows[$id] ?? null;
            $status = 'pending';
            if ($row !== null) {
                $status = (string) $row['status'];
                if ($status === 'applied' && $row['checksum'] !== $checksum) {
                    $status = 'checksum changed';
                }
            }
            $result[] = [
                'id' => $id,
                'description' => $migration->description(),
                'status' => $status,
                'applied_at' => $row['applied_at'] ?? null,
                'duration_ms' => $row['duration_ms'] ?? null,
                'applied_by_user_id' => $row['applied_by_user_id'] ?? null,
                'batched' => $migration->isBatched(),
            ];
        }
        return $result;
    }

    /**
     * @return array{id:string,status:string,output:string,has_more:bool}
     */
    public function runOne(string $id, string $via, ?int $userId, bool $echo = false): array
    {
        $files = $this->files();
        if (!isset($files[$id])) {
            throw new \InvalidArgumentException("Unknown migration $id");
        }
        $pdo = $this->pdo();
        $lock = (int) $pdo->query("SELECT GET_LOCK('pgs_migrate', 5)")->fetchColumn();
        if ($lock !== 1) {
            return ['id' => $id, 'status' => 'locked', 'output' => 'Another migration is running', 'has_more' => false];
        }
        $started = microtime(true);
        $ctx = new MigrationContext($userId, $echo);
        $checksum = (string) hash_file('sha256', $files[$id]);
        try {
            $existing = $pdo->prepare('SELECT status, checksum FROM tec_migrations WHERE migration = :m');
            $existing->execute([':m' => $id]);
            $row = $existing->fetch();
            if (is_array($row) && $row['status'] === 'applied') {
                $ctx->out("SKIP $id already applied");
                return ['id' => $id, 'status' => 'applied', 'output' => $ctx->output(), 'has_more' => false];
            }
            $migration = $this->load($files[$id]);
            $ctx->out("=== $id start ===");
            $this->record($pdo, $id, $checksum, 'in_progress', $ctx->output(), null, $userId, $via);
            try {
                $migration->up($pdo, $ctx);
                $status = $ctx->hasMore() ? 'in_progress' : 'applied';
                $ctx->out($ctx->hasMore() ? "=== $id partial (more batches pending) ===" : "=== $id done ===");
            } catch (\Throwable $e) {
                $status = 'failed';
                $ctx->out('FAIL ' . $e->getMessage());
            }
            $duration = (int) round((microtime(true) - $started) * 1000);
            $this->record($pdo, $id, $checksum, $status, $ctx->output(), $duration, $userId, $via);
            return ['id' => $id, 'status' => $status, 'output' => $ctx->output(), 'has_more' => $ctx->hasMore()];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('pgs_migrate')");
        }
    }

    /**
     * @return array{id:string,status:string,output:string,has_more:bool}|null
     */
    public function runNext(string $via, ?int $userId, bool $echo = false): ?array
    {
        foreach ($this->status() as $row) {
            if (in_array($row['status'], ['pending', 'failed', 'in_progress'], true)) {
                return $this->runOne((string) $row['id'], $via, $userId, $echo);
            }
        }
        return null;
    }

    private function record(\PDO $pdo, string $id, string $checksum, string $status, string $output, ?int $duration, ?int $userId, string $via): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO tec_migrations (migration, checksum, status, output, duration_ms, applied_by_user_id, applied_via, applied_at)
             VALUES (:m, :c, :s, :o, :d, :u, :v, :t)
             ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = VALUES(status), output = VALUES(output),
               duration_ms = VALUES(duration_ms), applied_by_user_id = VALUES(applied_by_user_id),
               applied_via = VALUES(applied_via), applied_at = VALUES(applied_at)'
        );
        $stmt->execute([
            ':m' => $id, ':c' => $checksum, ':s' => $status, ':o' => $output, ':d' => $duration,
            ':u' => $userId, ':v' => $via, ':t' => Clock::nowUtcSql(),
        ]);
    }
}
