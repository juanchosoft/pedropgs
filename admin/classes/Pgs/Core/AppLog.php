<?php

declare(strict_types=1);

namespace Pgs\Core;

final class AppLog
{
    public static function error(string $channel, \Throwable $e, array $context = []): void
    {
        self::write('error', $channel, $e->getMessage(), $context + [
            'exception' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ]);
    }

    public static function write(string $level, string $channel, string $message, array $context = []): void
    {
        try {
            $dir = PrivateStorage::path('logs');
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                return;
            }
            $line = json_encode([
                'ts' => Clock::nowUtc()->format('Y-m-d\TH:i:s\Z'),
                'level' => $level,
                'channel' => $channel,
                'message' => mb_substr($message, 0, 500),
                'request_id' => RequestContext::id(),
                'context' => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($line !== false) {
                @file_put_contents($dir . '/app-' . Clock::nowUtc()->format('Y-m') . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $ignored) {
        }
    }
}
