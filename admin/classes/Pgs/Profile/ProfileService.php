<?php

declare(strict_types=1);

namespace Pgs\Profile;

use Pgs\Auth\Principal;
use Pgs\Auth\VoicePin;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Phone;
use Pgs\Notifications\NotificationService;

final class ProfileService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Gets profile details for the given Principal.
     */
    public function get(Principal $p): array
    {
        $userId = $p->id();
        if ($userId === null) {
            throw new NotFoundException('Principal has no user ID');
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, apellido, nickname, tipo, phone_e164, notify_email, lang,
                    whatsapp_opt_in, whatsapp_opt_in_at, is_internal_staff, employee_pk
             FROM tec_usuarios
             WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new NotFoundException('User not found');
        }

        $fullName = trim((string) ($row['nombre'] ?? '') . ' ' . (string) ($row['apellido'] ?? ''));

        return [
            'id' => (int) $row['id'],
            'nombre' => (string) ($row['nombre'] ?? ''),
            'apellido' => (string) ($row['apellido'] ?? ''),
            'full_name' => $fullName,
            'nickname' => (string) ($row['nickname'] ?? ''),
            'tipo' => (string) ($row['tipo'] ?? ''),
            'phone_e164' => (string) ($row['phone_e164'] ?? ''),
            'notify_email' => (string) ($row['notify_email'] ?? $row['nickname'] ?? ''),
            'lang' => (string) ($row['lang'] ?? 'en'),
            'whatsapp_opt_in' => (int) ($row['whatsapp_opt_in'] ?? 0) === 1,
            'whatsapp_opt_in_at' => $row['whatsapp_opt_in_at'],
            'is_internal_staff' => (int) ($row['is_internal_staff'] ?? 0) === 1,
            'employee_pk' => $row['employee_pk'] !== null ? (int) $row['employee_pk'] : null,
            'voice_pin' => VoicePin::status($userId),
        ];
    }

    /**
     * Saves user settings (phone, notification email, language, whatsapp opt-in).
     */
    public function save(Principal $p, array $in): array
    {
        $userId = $p->id();
        if ($userId === null) {
            throw new NotFoundException('Principal has no user ID');
        }

        $current = $this->get($p);
        $errors = [];

        // Validate and normalize phone
        $phoneRaw = trim((string) ($in['phone'] ?? $in['phone_e164'] ?? ''));
        $phoneE164 = null;
        if ($phoneRaw !== '') {
            try {
                $phoneE164 = Phone::toE164($phoneRaw, 'US');
            } catch (\Throwable $e) {
                $errors['phone'] = 'Invalid phone number format: ' . $e->getMessage();
            }

            if ($phoneE164 !== null) {
                // PRF-02: Check uniqueness across other users
                $dupStmt = $this->pdo->prepare('SELECT id FROM tec_usuarios WHERE phone_e164 = :phone AND id != :uid LIMIT 1');
                $dupStmt->execute([':phone' => $phoneE164, ':uid' => $userId]);
                if ($dupStmt->fetch()) {
                    throw new ConflictException("Phone number {$phoneE164} is already registered to another user account.", 'phone_in_use');
                }
            }
        }

        // Validate notify_email
        $notifyEmail = trim((string) ($in['notify_email'] ?? ''));
        if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['notify_email'] = 'Invalid notification email format';
        }

        // Language
        $lang = strtolower(trim((string) ($in['lang'] ?? 'en')));
        if (!in_array($lang, ['en', 'es'], true)) {
            $lang = 'en';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // WhatsApp opt-in
        $optIn = !empty($in['whatsapp_opt_in']) ? 1 : 0;
        $optInAt = $current['whatsapp_opt_in_at'];
        if ($optIn === 1 && !$current['whatsapp_opt_in']) {
            $optInAt = Clock::nowUtcSql();
        } elseif ($optIn === 0) {
            $optInAt = null;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE tec_usuarios
             SET phone_e164 = :phone,
                 notify_email = :email,
                 lang = :lang,
                 whatsapp_opt_in = :optin,
                 whatsapp_opt_in_at = :optinat
             WHERE id = :id'
        );
        $stmt->execute([
            ':phone' => $phoneE164,
            ':email' => $notifyEmail !== '' ? $notifyEmail : null,
            ':lang' => $lang,
            ':optin' => $optIn,
            ':optinat' => $optInAt,
            ':id' => $userId,
        ]);

        AuditLogger::log($p, 'profile.updated', 'user', $userId, [
            'phone' => $phoneE164,
            'notify_email' => $notifyEmail,
            'lang' => $lang,
            'whatsapp_opt_in' => $optIn,
        ]);

        return $this->get($p);
    }

    /**
     * Sets or changes the Voice PIN with current password verification.
     */
    public function setPin(Principal $p, string $currentPassword, string $newPin, string $confirmPin): array
    {
        $userId = $p->id();
        if ($userId === null) {
            throw new NotFoundException('Principal has no user ID');
        }

        if ($newPin !== $confirmPin) {
            throw new ValidationException(['confirm_pin' => 'PIN confirmation does not match new PIN']);
        }

        // Verify current password against hashpass
        $stmt = $this->pdo->prepare('SELECT hashpass FROM tec_usuarios WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $currentHash = (string) $stmt->fetchColumn();

        require_once dirname(__DIR__, 2) . '/Util.php';
        $calcHash = \Util::make_hash_pass(md5($currentPassword));

        if (!hash_equals($currentHash, $calcHash)) {
            throw new ValidationException(['current_password' => 'Incorrect current password']);
        }

        // Validates 6 digits, non-repeating, non-sequential (PRF-01)
        VoicePin::set($userId, $newPin);

        AuditLogger::log($p, 'voice_pin.set', 'user', $userId, []);

        return [
            'status' => 'active',
            'message' => 'Voice PIN set successfully',
        ];
    }

    /**
     * Admin action: Resets a user's voice PIN.
     */
    public function resetPinByAdmin(Principal $p, int $targetUserId): array
    {
        $p->require('configuracion.usuarios.update');

        $stmt = $this->pdo->prepare('SELECT id, nombre, apellido, notify_email FROM tec_usuarios WHERE id = :id');
        $stmt->execute([':id' => $targetUserId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            throw new NotFoundException("User #{$targetUserId} not found");
        }

        VoicePin::reset($targetUserId);

        AuditLogger::log($p, 'voice_pin.reset', 'user', $targetUserId, [
            'reset_by' => $p->id(),
        ]);

        // Notify user via NotificationService in-app
        try {
            (new NotificationService($this->pdo))->emit(
                $this->pdo,
                'phone.message',
                'user',
                $targetUserId,
                [['user_id' => $targetUserId]],
                [
                    'title' => 'Voice PIN Reset',
                    'body' => 'Your Voice PIN was reset by an administrator. Please set a new PIN in your profile.',
                    'link_url' => 'my_profile.php',
                ]
            );
        } catch (\Throwable $e) {
            // Notification failure does not abort reset
        }

        return ['reset' => true, 'user_id' => $targetUserId];
    }

    /**
     * Links an employee PK to a user account.
     */
    public function linkEmployee(Principal $p, int $targetUserId, int $employeePk): array
    {
        $p->require('configuracion.usuarios.update');

        $empStmt = $this->pdo->prepare('SELECT id, cc, nombre FROM tec_employee WHERE id = :pk');
        $empStmt->execute([':pk' => $employeePk]);
        $emp = $empStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$emp) {
            throw new NotFoundException("Employee PK #{$employeePk} not found");
        }

        $stmt = $this->pdo->prepare(
            'UPDATE tec_usuarios
             SET employee_pk = :pk,
                 employee_id = :cc
             WHERE id = :uid'
        );
        $stmt->execute([
            ':pk' => $employeePk,
            ':cc' => $emp['cc'],
            ':uid' => $targetUserId,
        ]);

        AuditLogger::log($p, 'user.employee_linked', 'user', $targetUserId, [
            'employee_pk' => $employeePk,
            'employee_cc' => $emp['cc'],
        ]);

        return [
            'linked' => true,
            'user_id' => $targetUserId,
            'employee_pk' => $employeePk,
            'employee_name' => (string) $emp['nombre'],
        ];
    }

    /**
     * Toggles is_internal_staff (D-25 Safeguard). Strictly SuperAdministrador only.
     */
    public function setInternalStaff(Principal $p, int $targetUserId, bool $isInternal): array
    {
        if (!$p->isSystemAdmin()) {
            throw new ForbiddenException('Only SuperAdministrador can toggle internal staff status');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE tec_usuarios
             SET is_internal_staff = :is_int,
                 perm_version = perm_version + 1
             WHERE id = :uid'
        );
        $stmt->execute([
            ':is_int' => $isInternal ? 1 : 0,
            ':uid' => $targetUserId,
        ]);

        AuditLogger::log($p, 'user.internal_staff_changed', 'user', $targetUserId, [
            'is_internal_staff' => $isInternal,
            'changed_by' => $p->id(),
        ]);

        return [
            'user_id' => $targetUserId,
            'is_internal_staff' => $isInternal,
        ];
    }
}
