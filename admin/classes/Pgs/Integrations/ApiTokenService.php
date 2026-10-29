<?php

declare(strict_types=1);

namespace Pgs\Integrations;

use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;

final class ApiTokenService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Lists all API tokens with status calculation.
     *
     * @return list<array<string, mixed>>
     */
    public function list(Principal $p): array
    {
        $p->require('integraciones.api_tokens.manage');

        $stmt = $this->pdo->query(
            'SELECT t.*,
                    TRIM(CONCAT(IFNULL(u.nombre, ""), " ", IFNULL(u.apellido, ""))) AS created_by_name
             FROM tec_api_tokens t
             LEFT JOIN tec_usuarios u ON u.id = t.created_by_user_id
             ORDER BY t.id DESC'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $now = Clock::nowUtc();
        $res = [];

        foreach ($rows as $r) {
            $status = 'active';
            if (!empty($r['revoked_at'])) {
                $status = 'revoked';
            } elseif (!empty($r['expires_at'])) {
                $exp = new \DateTimeImmutable((string) $r['expires_at'], new \DateTimeZone('UTC'));
                if ($now > $exp) {
                    $status = 'expired';
                }
            }

            $res[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'token_prefix' => (string) $r['token_prefix'],
                'scopes' => (string) $r['scopes'],
                'ip_allowlist' => $r['ip_allowlist'] !== null ? (string) $r['ip_allowlist'] : null,
                'rate_limit_per_minute' => $r['rate_limit_per_minute'] !== null ? (int) $r['rate_limit_per_minute'] : null,
                'created_at' => (string) $r['created_at'],
                'created_by_name' => !empty($r['created_by_name']) ? (string) $r['created_by_name'] : 'System',
                'expires_at' => $r['expires_at'] !== null ? (string) $r['expires_at'] : null,
                'last_used_at' => $r['last_used_at'] !== null ? (string) $r['last_used_at'] : null,
                'last_used_ip' => $r['last_used_ip'] !== null ? (string) $r['last_used_ip'] : null,
                'revoked_at' => $r['revoked_at'] !== null ? (string) $r['revoked_at'] : null,
                'status' => $status,
            ];
        }

        return $res;
    }

    /**
     * Creates a new API token and returns the plaintext token ONCE.
     *
     * @param array{name:string, scopes:string|list<string>, ip_allowlist?:string, expires_at?:string, rate_limit_per_minute?:int} $in
     * @return array{id:int, name:string, token_prefix:string, scopes:string, full_token:string}
     */
    public function create(Principal $p, array $in): array
    {
        $p->require('integraciones.api_tokens.manage');

        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationException(['name' => 'Name is required and must not exceed 100 characters']);
        }

        $scopesRaw = $in['scopes'] ?? '';
        if (is_array($scopesRaw)) {
            $scopes = implode(',', array_filter(array_map('trim', $scopesRaw)));
        } else {
            $scopes = trim((string) $scopesRaw);
        }

        if ($scopes === '') {
            throw new ValidationException(['scopes' => 'At least one scope must be selected']);
        }

        $ipAllowlist = isset($in['ip_allowlist']) ? trim((string) $in['ip_allowlist']) : null;
        if ($ipAllowlist === '') {
            $ipAllowlist = null;
        }

        $expiresAt = null;
        if (!empty($in['expires_at'])) {
            $expStr = trim((string) $in['expires_at']);
            // Allow Y-m-d or Y-m-d H:i:s
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expStr)) {
                $expiresAt = $expStr . ' 23:59:59';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expStr)) {
                $expiresAt = $expStr;
            } else {
                throw new ValidationException(['expires_at' => 'Invalid expiration date format (expected YYYY-MM-DD)']);
            }
        }

        $rateLimit = isset($in['rate_limit_per_minute']) && (int) $in['rate_limit_per_minute'] > 0
            ? (int) $in['rate_limit_per_minute']
            : null;

        // Generate token: pgs_<8 hex>_<40 hex>
        $prefixPart = bin2hex(random_bytes(4)); // 8 hex
        $secretPart = bin2hex(random_bytes(20)); // 40 hex
        $tokenPrefix = 'pgs_' . $prefixPart;
        $fullToken = $tokenPrefix . '_' . $secretPart;
        $tokenHash = hash('sha256', $fullToken);

        $now = Clock::nowUtcSql();

        $stmt = $this->pdo->prepare(
            'INSERT INTO tec_api_tokens (
                name, token_prefix, token_hash, scopes, ip_allowlist,
                rate_limit_per_minute, created_by_user_id, created_at, expires_at
             ) VALUES (
                :name, :prefix, :hash, :scopes, :ip,
                :rate_limit, :uid, :now, :expires_at
             )'
        );
        $stmt->execute([
            ':name' => $name,
            ':prefix' => $tokenPrefix,
            ':hash' => $tokenHash,
            ':scopes' => $scopes,
            ':ip' => $ipAllowlist,
            ':rate_limit' => $rateLimit,
            ':uid' => $p->id(),
            ':now' => $now,
            ':expires_at' => $expiresAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        AuditLogger::log($p, 'api_token.created', 'api_token', $id, [
            'name' => $name,
            'prefix' => $tokenPrefix,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'token_prefix' => $tokenPrefix,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
            'full_token' => $fullToken,
        ];
    }

    /**
     * Revokes an existing API token.
     */
    public function revoke(Principal $p, int $id): void
    {
        $p->require('integraciones.api_tokens.manage');

        $stmt = $this->pdo->prepare('SELECT id, name, token_prefix, revoked_at FROM tec_api_tokens WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException("API token #{$id} not found");
        }

        if (!empty($row['revoked_at'])) {
            return; // already revoked
        }

        $now = Clock::nowUtcSql();
        $upd = $this->pdo->prepare('UPDATE tec_api_tokens SET revoked_at = :now, revoked_by_user_id = :uid WHERE id = :id');
        $upd->execute([':now' => $now, ':uid' => $p->id(), ':id' => $id]);

        AuditLogger::log($p, 'api_token.revoked', 'api_token', $id, [
            'name' => $row['name'],
            'token_prefix' => $row['token_prefix'],
        ]);
    }
}
