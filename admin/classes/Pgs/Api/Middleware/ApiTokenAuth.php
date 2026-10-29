<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\Db;
use Pgs\Core\Json;

final class ApiTokenAuth
{
    /** @var list<string> */
    private array $requiredScopes;

    /**
     * @param list<string> $requiredScopes
     */
    public function __construct(array $requiredScopes = [])
    {
        $this->requiredScopes = $requiredScopes;
    }

    public function __invoke(Request $req, callable $next): Response
    {
        $authHeader = $req->header('Authorization');
        if ($authHeader === null || !preg_match('/^Bearer\s+(pgs_[a-zA-Z0-9_]{8}_[a-zA-Z0-9]{40})$/i', trim($authHeader), $matches)) {
            return Response::error(401, 'unauthorized', 'Missing or malformed Authorization Bearer token');
        }

        $rawToken = $matches[1];
        $prefix12 = substr($rawToken, 0, 12);
        $prefix8 = substr($rawToken, 4, 8);
        $tokenHash = hash('sha256', $rawToken);

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM tec_api_tokens WHERE (token_prefix = :p12 OR token_prefix = :p8) LIMIT 1'
        );
        $stmt->execute([':p12' => $prefix12, ':p8' => $prefix8]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !hash_equals((string) $row['token_hash'], $tokenHash)) {
            return Response::error(401, 'unauthorized', 'Invalid API token');
        }

        if (!empty($row['revoked_at'])) {
            return Response::error(401, 'unauthorized', 'API token has been revoked');
        }

        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) <= time()) {
            return Response::error(401, 'unauthorized', 'API token has expired');
        }

        // IP allowlist check
        if (!empty($row['ip_allowlist'])) {
            $callerIp = $req->ip();
            if (!IpAllowlist::matches($callerIp, (string) $row['ip_allowlist'])) {
                return Response::error(403, 'forbidden', 'Caller IP is not allowed for this token');
            }
        }

        // Scopes check (JSON array or comma-separated)
        $tokenScopes = [];
        if (!empty($row['scopes'])) {
            $decoded = json_decode((string) $row['scopes'], true);
            if (is_array($decoded)) {
                $tokenScopes = $decoded;
            } else {
                $tokenScopes = array_filter(array_map('trim', explode(',', (string) $row['scopes'])));
            }
        }

        foreach ($this->requiredScopes as $required) {
            if (!in_array($required, $tokenScopes, true)) {
                return Response::error(403, 'forbidden', "Token lacks required scope: {$required}");
            }
        }

        // Update last_used_at and last_used_ip at most once per minute
        $lastUsed = !empty($row['last_used_at']) ? strtotime((string) $row['last_used_at']) : 0;
        if (time() - $lastUsed >= 60) {
            $upd = $pdo->prepare(
                'UPDATE tec_api_tokens SET last_used_at = NOW(), last_used_ip = :ip WHERE id = :id'
            );
            $upd->execute([':ip' => $req->ip(), ':id' => $row['id']]);
        }

        $req = $req->withAttr('api_token_id', (int) $row['id'])
                   ->withAttr('api_token_row', $row)
                   ->withAttr('api_token_scopes', $tokenScopes);

        return $next($req);
    }
}
