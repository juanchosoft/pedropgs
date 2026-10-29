<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Auth\VoicePin;
use Pgs\Core\Db;
use Pgs\Core\Phone;

$options = getopt('', [
    'user:',
    'phone:',
    'pin:',
    'lang::',
]);

$userId = isset($options['user']) ? (int) $options['user'] : 0;
$phone = trim((string) ($options['phone'] ?? ''));
$pin = trim((string) ($options['pin'] ?? ''));
$lang = isset($options['lang']) ? trim((string) $options['lang']) : null;

if ($userId <= 0 || $phone === '' || $pin === '') {
    echo "Usage: php user_voice_setup.php --user=<id> --phone=\"+14075551234\" --pin=\"123456\" [--lang=es|en]\n";
    exit(1);
}

$pdo = Db::pdo();
$stmt = $pdo->prepare("SELECT id, nickname, notify_email, tipo FROM tec_usuarios WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    echo "Error: User with ID $userId not found.\n";
    exit(1);
}

try {
    $e164 = Phone::toE164($phone);
} catch (\Throwable $e) {
    echo "Error validating phone: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    VoicePin::set($userId, $pin);
} catch (\Throwable $e) {
    echo "Error setting voice PIN: " . $e->getMessage() . "\n";
    exit(1);
}

// Update phone and optionally lang
$sql = "UPDATE tec_usuarios SET phone_e164 = :phone" . ($lang ? ", lang = :lang" : "") . " WHERE id = :id";
$params = [':phone' => $e164, ':id' => $userId];
if ($lang) {
    $params[':lang'] = in_array($lang, ['es', 'en'], true) ? $lang : 'es';
}
$pdo->prepare($sql)->execute($params);

echo "=== User Voice Profile Configured ===\n";
echo "User ID:      {$user['id']} ({$user['nickname']} / " . ($user['notify_email'] ?? 'no email') . ")\n";
echo "Phone E.164:  $e164\n";
echo "Voice PIN:    Configured successfully (6 digits)\n";
if ($lang) {
    echo "Language:     $lang\n";
}
echo "Status:       Active\n";
