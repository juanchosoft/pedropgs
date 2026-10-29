<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Core\Clock;
use Pgs\Core\Db;

$options = getopt('', [
    'name:',
    'scopes:',
    'ip_allowlist::',
    'rate_limit::',
    'expires_at::',
    'user_id::',
]);

$name = trim((string) ($options['name'] ?? ''));
$scopes = trim((string) ($options['scopes'] ?? ''));
$ipAllowlist = isset($options['ip_allowlist']) ? trim((string) $options['ip_allowlist']) : null;
$rateLimit = isset($options['rate_limit']) ? (int) $options['rate_limit'] : null;
$expiresAt = isset($options['expires_at']) ? trim((string) $options['expires_at']) : null;
$userId = isset($options['user_id']) ? (int) $options['user_id'] : null;

if ($name === '' || $scopes === '') {
    echo "Usage: php api_token_create.php --name=\"<name>\" --scopes=\"voice:read,voice:write,...\" [--ip_allowlist=\"<ips>\"] [--rate_limit=600] [--expires_at=\"YYYY-MM-DD HH:MM:SS\"] [--user_id=<id>]\n";
    exit(1);
}

// Generate token: pgs_<8 hex>_<40 hex>
$prefixPart = bin2hex(random_bytes(4)); // 8 hex chars
$secretPart = bin2hex(random_bytes(20)); // 40 hex chars

$tokenPrefix = 'pgs_' . $prefixPart; // 12 chars
$fullToken = $tokenPrefix . '_' . $secretPart; // 53 chars
$tokenHash = hash('sha256', $fullToken);

$now = Clock::nowUtcSql();

$pdo = Db::pdo();
$stmt = $pdo->prepare(
    "INSERT INTO tec_api_tokens (
        name, token_prefix, token_hash, scopes, ip_allowlist,
        rate_limit_per_minute, created_by_user_id, created_at, expires_at
    ) VALUES (
        :name, :prefix, :hash, :scopes, :ip,
        :rate_limit, :user_id, :now, :expires_at
    )"
);

$stmt->execute([
    ':name' => $name,
    ':prefix' => $tokenPrefix,
    ':hash' => $tokenHash,
    ':scopes' => $scopes,
    ':ip' => $ipAllowlist ?: null,
    ':rate_limit' => $rateLimit ?: null,
    ':user_id' => $userId ?: null,
    ':now' => $now,
    ':expires_at' => $expiresAt ?: null,
]);

$id = $pdo->lastInsertId();

echo "=== API Token Created Successfully ===\n";
echo "ID:           $id\n";
echo "Name:         $name\n";
echo "Prefix:       $tokenPrefix\n";
echo "Scopes:       $scopes\n";
echo "Expires:      " . ($expiresAt ?: 'Never') . "\n";
echo "Token:        $fullToken\n";
echo "\nIMPORTANT: Copy the token above now. It will not be shown again.\n";
