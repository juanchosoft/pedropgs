<?php

declare(strict_types=1);

namespace Pgs\Core;

final class Config
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $data = null;
    private static ?string $resolved = null;

    public static function path(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }
        $root = defined('APP_ROOT') ? APP_ROOT : str_replace('\\', '/', dirname(__DIR__, 4));
        $candidates = [];
        $env = getenv('PGS_CONFIG_FILE');
        if (is_string($env) && $env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = dirname($root) . '/pgscentrum_private/config.ini';
        $candidates[] = dirname($root, 2) . '/pgscentrum_private/config.ini';

        foreach ($candidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $real = realpath($candidate);
            $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
            if ($real !== false && $docRoot !== false && $docRoot !== '') {
                $normReal = strtolower(str_replace('\\', '/', $real));
                $normDoc = rtrim(strtolower(str_replace('\\', '/', $docRoot)), '/') . '/';
                if (str_starts_with($normReal, $normDoc)) {
                    throw new \RuntimeException('Configuration file must be outside the web root');
                }
            }
            return self::$resolved = $candidate;
        }
        throw new \RuntimeException('Configuration file not found');
    }

    public static function privatePath(): string
    {
        return PrivateStorage::root();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function load(): array
    {
        if (self::$data === null) {
            $parsed = parse_ini_file(self::path(), true, INI_SCANNER_TYPED);
            if ($parsed === false) {
                throw new \RuntimeException('Configuration file is not valid');
            }
            self::$data = $parsed;
        }
        return self::$data;
    }

    public static function get(string $section, string $key, mixed $default = null): mixed
    {
        $data = self::load();
        return $data[$section][$key] ?? $default;
    }

    public static function set(string $section, string $key, mixed $value): void
    {
        if (self::$data === null) {
            self::load();
        }
        self::$data[$section][$key] = $value;
    }

    /**
     * @return array<string,mixed>
     */
    public static function section(string $section): array
    {
        $data = self::load();
        return is_array($data[$section] ?? null) ? $data[$section] : [];
    }

    public static function bool(string $section, string $key, bool $default = false): bool
    {
        $value = self::get($section, $key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function env(): string
    {
        $env = getenv('APP_ENV');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return (string) self::get('app', 'env', 'production');
    }

    public static function reset(): void
    {
        self::$data = null;
        self::$resolved = null;
    }
}
