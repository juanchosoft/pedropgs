<?php

declare(strict_types=1);

namespace Pgs\Responders;

use Pgs\Core\Clock;
use Pgs\Core\Db;

final class SettingsRepository
{
    private \PDO $pdo;

    /** @var array<string,string>|null */
    private ?array $cache = null;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /** @return array<string,string> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->pdo->query('SELECT setting_key, setting_value FROM tec_settings') as $row) {
                $this->cache[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        }

        return $this->cache;
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->all()[$key] ?? $default;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->get($key, '');

        return $value !== '' && ctype_digit($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, '');

        return $value === '' ? $default : $value === '1';
    }

    /** @return list<int> */
    public function intList(string $key): array
    {
        $out = [];
        foreach (explode(',', $this->get($key, '')) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $out[] = (int) $part;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    public function emailList(string $key): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $this->get($key, '')) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $out[] = strtolower($part);
            }
        }

        return array_values(array_unique($out));
    }

    public function set(string $key, string $value, ?int $userId = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO tec_settings (setting_key, setting_value, updated_by, updated_at)
             VALUES (:k, :v, :u, :now)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        )->execute([':k' => $key, ':v' => $value, ':u' => $userId, ':now' => Clock::nowUtcSql()]);
        $this->cache = null;
    }
}
