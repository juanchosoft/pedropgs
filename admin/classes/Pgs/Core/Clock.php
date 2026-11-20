<?php

declare(strict_types=1);

namespace Pgs\Core;

final class Clock
{
    public static function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public static function nowUtcSql(): string
    {
        return self::nowUtc()->format('Y-m-d H:i:s');
    }

    public static function businessTz(): \DateTimeZone
    {
        return new \DateTimeZone((string) Config::get('app', 'business_timezone', 'America/New_York'));
    }

    public static function todayLocal(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('now', self::businessTz()))->setTime(0, 0, 0);
    }

    public static function toLocal(string $utcDatetime): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($utcDatetime, new \DateTimeZone('UTC')))->setTimezone(self::businessTz());
    }
}
