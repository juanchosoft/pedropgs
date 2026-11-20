<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\RateLimitedException;

final class RateLimiter
{
    public static function hit(string $bucket, int $limit, int $windowSeconds): void
    {
        [$start, $now] = self::window($windowSeconds);
        $hits = self::increment($bucket, $start);
        if ($hits > $limit) {
            throw new RateLimitedException(max(1, $start + $windowSeconds - $now));
        }
    }

    /**
     * Throws when the bucket has already reached its limit, without consuming a hit.
     */
    public static function assertAvailable(string $bucket, int $limit, int $windowSeconds): void
    {
        [$start, $now] = self::window($windowSeconds);
        $select = Db::pdo()->prepare('SELECT hits FROM tec_rate_limits WHERE bucket = :b AND window_start = :w');
        $select->execute([':b' => substr($bucket, 0, 150), ':w' => gmdate('Y-m-d H:i:s', $start)]);
        if ((int) $select->fetchColumn() >= $limit) {
            throw new RateLimitedException(max(1, $start + $windowSeconds - $now));
        }
    }

    /**
     * Registers a hit without enforcing the limit.
     */
    public static function record(string $bucket, int $windowSeconds): void
    {
        [$start] = self::window($windowSeconds);
        self::increment($bucket, $start);
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function window(int $windowSeconds): array
    {
        $now = Clock::nowUtc()->getTimestamp();
        return [$now - ($now % $windowSeconds), $now];
    }

    private static function increment(string $bucket, int $start): int
    {
        $pdo = Db::pdo();
        $windowStart = gmdate('Y-m-d H:i:s', $start);
        $stmt = $pdo->prepare(
            'INSERT INTO tec_rate_limits (bucket, window_start, hits) VALUES (:b, :w, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        );
        $stmt->execute([':b' => substr($bucket, 0, 150), ':w' => $windowStart]);
        $select = $pdo->prepare('SELECT hits FROM tec_rate_limits WHERE bucket = :b AND window_start = :w');
        $select->execute([':b' => substr($bucket, 0, 150), ':w' => $windowStart]);
        return (int) $select->fetchColumn();
    }
}
