<?php

declare(strict_types=1);

namespace Pgs\Notifications;

final class Flusher
{
    private static bool $pending = false;

    public static function request(): void
    {
        self::$pending = true;
    }

    public static function isRequested(): bool
    {
        return self::$pending;
    }

    public static function reset(): void
    {
        self::$pending = false;
    }

    /**
     * Sends queued messages after the HTTP response has been delivered.
     */
    public static function flush(int $limit = 10): void
    {
        if (!self::$pending) {
            return;
        }
        self::$pending = false;
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        try {
            (new OutboxWorker())->process($limit);
        } catch (\Throwable $e) {
            \Pgs\Core\AppLog::error('flush', $e);
        }
    }
}
