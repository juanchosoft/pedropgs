<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\ConflictException;

final class Idempotency
{
    private const TTL_SECONDS = 86400;

    /**
     * @return array{code:int,body:string}|null
     */
    public static function begin(string $scope, string $key, string $requestHash): ?array
    {
        $pdo = Db::pdo();
        $now = Clock::nowUtc();
        $pdo->prepare('DELETE FROM tec_idempotency_keys WHERE scope = :s AND idem_key = :k AND expires_at < :n')
            ->execute([':s' => $scope, ':k' => $key, ':n' => $now->format('Y-m-d H:i:s')]);
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO tec_idempotency_keys (scope, idem_key, request_hash, status, created_at, expires_at)
             VALUES (:s, :k, :h, \'processing\', :c, :e)'
        );
        $insert->execute([
            ':s' => $scope,
            ':k' => $key,
            ':h' => $requestHash,
            ':c' => $now->format('Y-m-d H:i:s'),
            ':e' => $now->modify('+' . self::TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s'),
        ]);
        if ($insert->rowCount() === 1) {
            return null;
        }
        $select = $pdo->prepare('SELECT request_hash, status, response_code, response_body FROM tec_idempotency_keys WHERE scope = :s AND idem_key = :k');
        $select->execute([':s' => $scope, ':k' => $key]);
        $row = $select->fetch();
        if (!is_array($row)) {
            return null;
        }
        if (!hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new ConflictException('Idempotency key reused with a different request', 'idempotency_mismatch');
        }
        if ($row['status'] !== 'done') {
            throw new ConflictException('Request is still being processed', 'idempotency_in_progress');
        }
        return ['code' => (int) $row['response_code'], 'body' => (string) $row['response_body']];
    }

    public static function finish(string $scope, string $key, int $code, string $body): void
    {
        Db::pdo()->prepare(
            'UPDATE tec_idempotency_keys SET status = \'done\', response_code = :c, response_body = :b WHERE scope = :s AND idem_key = :k'
        )->execute([':c' => $code, ':b' => $body, ':s' => $scope, ':k' => $key]);
    }
}
