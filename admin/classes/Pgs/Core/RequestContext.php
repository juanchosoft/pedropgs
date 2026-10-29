<?php

declare(strict_types=1);

namespace Pgs\Core;

final class RequestContext
{
    private static ?string $id = null;
    private static ?string $lang = null;

    public static function id(): string
    {
        return self::$id ??= Ulid::generate();
    }

    public static function set(string $id): void
    {
        self::$id = $id;
    }

    public static function setLang(?string $lang): void
    {
        self::$lang = $lang;
    }

    public static function lang(): ?string
    {
        return self::$lang;
    }

    public static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? substr($ip, 0, 45) : null;
    }

    public static function userAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($ua) ? substr($ua, 0, 255) : null;
    }
}
