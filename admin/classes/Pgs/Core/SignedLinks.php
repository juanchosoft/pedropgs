<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\GoneException;
use Pgs\Core\Exceptions\NotFoundException;

final class SignedLinks
{
    public static function issue(
        string $type,
        string $ref,
        string $filename,
        ?int $userId,
        string $channel,
        int $ttlSeconds = 259200,
        int $maxUses = 5
    ): string {
        $token = bin2hex(random_bytes(32));
        $now = Clock::nowUtc();
        $stmt = Db::pdo()->prepare(
            'INSERT INTO tec_signed_links
             (token_hash, resource_type, resource_ref, filename, issued_to_user_id, issued_channel, max_uses, use_count, expires_at, created_at)
             VALUES (:h, :t, :r, :f, :u, :c, :m, 0, :e, :n)'
        );
        $stmt->execute([
            ':h' => hash('sha256', $token),
            ':t' => $type,
            ':r' => $ref,
            ':f' => substr($filename, 0, 190),
            ':u' => $userId,
            ':c' => $channel,
            ':m' => $maxUses,
            ':e' => $now->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s'),
            ':n' => $now->format('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /**
     * @return array<string,mixed>
     */
    public static function consume(string $token): array
    {
        $pdo = Db::pdo();
        $hash = hash('sha256', $token);
        $now = Clock::nowUtcSql();
        $update = $pdo->prepare(
            'UPDATE tec_signed_links
             SET use_count = use_count + 1, last_used_at = :n, last_used_ip = :ip
             WHERE token_hash = :h AND use_count < max_uses AND expires_at > :n2'
        );
        $update->execute([':n' => $now, ':ip' => RequestContext::ip(), ':h' => $hash, ':n2' => $now]);
        $select = $pdo->prepare('SELECT * FROM tec_signed_links WHERE token_hash = :h');
        $select->execute([':h' => $hash]);
        $row = $select->fetch();
        if (!is_array($row)) {
            throw new NotFoundException('Link not found');
        }
        if ($update->rowCount() === 0) {
            throw new GoneException('Link expired or exhausted');
        }
        return $row;
    }

    /**
     * Validates a token without consuming a use.
     *
     * @return array<string,mixed>
     */
    public static function peek(string $token): array
    {
        $select = Db::pdo()->prepare('SELECT * FROM tec_signed_links WHERE token_hash = :h');
        $select->execute([':h' => hash('sha256', $token)]);
        $row = $select->fetch();
        if (!is_array($row)) {
            throw new NotFoundException('Link not found');
        }
        if ((int) $row['use_count'] >= (int) $row['max_uses'] || (string) $row['expires_at'] <= Clock::nowUtcSql()) {
            throw new GoneException('Link expired or exhausted');
        }
        return $row;
    }
}
