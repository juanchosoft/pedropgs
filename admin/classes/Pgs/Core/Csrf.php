<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\ForbiddenException;

final class Csrf
{
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        return (string) $_SESSION['csrf_token'];
    }

    public static function verify(?string $token): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($expected) || $expected === '' || $token === null || !hash_equals($expected, $token)) {
            throw new ForbiddenException('Invalid CSRF token', 'csrf_invalid');
        }
    }
}
