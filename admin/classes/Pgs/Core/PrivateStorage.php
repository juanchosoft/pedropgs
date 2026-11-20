<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\NotFoundException;

final class PrivateStorage
{
    public static function root(): string
    {
        $dir = (string) Config::get('app', 'private_dir', '');
        if ($dir === '') {
            throw new \RuntimeException('private_dir is not configured');
        }
        return rtrim(str_replace('\\', '/', $dir), '/');
    }

    public static function path(string $subdir = ''): string
    {
        $root = self::root();
        return $subdir !== '' ? $root . '/' . ltrim(str_replace('\\', '/', $subdir), '/') : $root;
    }

    public static function put(string $subdir, string $ext, string $sourcePath): string
    {
        if (!preg_match('/^[a-z0-9_]+(\/[a-z0-9_]+)*$/', $subdir) || !preg_match('/^[a-z0-9]{1,8}$/i', $ext)) {
            throw new \InvalidArgumentException('Invalid storage target');
        }
        if (!is_file($sourcePath)) {
            throw new NotFoundException('Source file not found');
        }
        $now = Clock::nowUtc();
        $relative = $subdir . '/' . $now->format('Y') . '/' . $now->format('m') . '/' . Ulid::generate() . '.' . strtolower($ext);
        $target = self::root() . '/' . $relative;
        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create storage directory');
        }
        if (!copy($sourcePath, $target)) {
            throw new \RuntimeException('Cannot store file');
        }
        chmod($target, 0600);
        return $relative;
    }

    public static function abs(string $relative): string
    {
        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, '\\') || $relative[0] === '/' || preg_match('/^[a-zA-Z]:/', $relative)) {
            throw new \InvalidArgumentException('Invalid storage path');
        }
        return self::root() . '/' . $relative;
    }

    public static function delete(string $relative): void
    {
        $path = self::abs($relative);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function ensureDirectory(string $absDir): void
    {
        $root = realpath(self::root());
        $norm = str_replace('\\', '/', $absDir);
        if ($root === false || strpos($norm, str_replace('\\', '/', $root)) !== 0) {
            throw new \InvalidArgumentException('Directory outside private storage');
        }
        if (!is_dir($absDir) && !mkdir($absDir, 0750, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Cannot create storage directory');
        }
    }
}
