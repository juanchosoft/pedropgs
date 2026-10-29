<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Auth\VoicePin;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\PinLockedException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Phone;
use Pgs\Core\RateLimiter;

final class VoiceSessionController
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * GET /voice/session
     */
    public function getSession(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $principal = $req->attr('principal');
        $resolvedUid = !empty($session['resolved_user_id']) ? (int) $session['resolved_user_id'] : null;

        $pinSet = false;
        $firstName = null;
        $units = [];

        if ($resolvedUid !== null) {
            $uStmt = $this->pdo->prepare("SELECT nombre, voice_pin_hash, tipo FROM tec_usuarios WHERE id = :id");
            $uStmt->execute([':id' => $resolvedUid]);
            $u = $uStmt->fetch(\PDO::FETCH_ASSOC);

            if ($u) {
                $pinSet = !empty($u['voice_pin_hash']);
                if (($session['verification'] ?? '') === 'pin') {
                    $parts = explode(' ', trim((string) ($u['nombre'] ?? '')));
                    $firstName = $parts[0] ?? '';

                    if ($principal instanceof Principal) {
                        $unitIds = UnitScope::forUser($this->pdo, $resolvedUid, $u['tipo']);
                        if ($unitIds === null) {
                            // All units
                            $uRows = $this->pdo->query("SELECT id, nombre FROM tbl_unidades WHERE enable IN ('si', 'yes') ORDER BY nombre ASC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC);
                        } elseif (!empty($unitIds)) {
                            $inSql = implode(',', array_map('intval', $unitIds));
                            $uRows = $this->pdo->query("SELECT id, nombre FROM tbl_unidades WHERE id IN ({$inSql}) AND enable IN ('si', 'yes') ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);
                        } else {
                            $uRows = [];
                        }
                        foreach ($uRows as $ur) {
                            $units[] = ['id' => (int) $ur['id'], 'name' => (string) $ur['nombre']];
                        }
                    }
                }
            }
        }

        $data = [
            'caller_type' => $session['caller_type'] ?? 'unknown',
            'verification' => $session['verification'] ?? 'none',
            'lang' => $session['lang'] ?? 'es',
            'business_open' => BusinessHours::isOpen(),
            'pin_set' => $pinSet,
        ];

        if (($session['verification'] ?? '') === 'pin') {
            $data['first_name'] = $firstName;
            $data['units'] = $units;
        }

        return Response::ok($data);
    }

    /**
     * POST /voice/session/verify-pin
     */
    public function verifyPin(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $resolvedUid = !empty($session['resolved_user_id']) ? (int) $session['resolved_user_id'] : null;

        if ($resolvedUid === null) {
            throw new ValidationException(['pin' => 'No identified user to verify PIN']);
        }

        $body = $req->json();
        $pin = isset($body['pin']) ? trim((string) $body['pin']) : '';
        if ($pin === '') {
            throw new ValidationException(['pin' => 'PIN is required']);
        }

        // At most 10 failed PIN attempts per day per caller number
        $caller = (string) ($session['caller_e164'] ?? $req->ip());
        RateLimiter::assertAvailable('pin:' . $caller, 10, 86400);

        // Check session pin attempts
        $attempts = (int) ($session['pin_attempts'] ?? 0);
        if ($attempts >= 3) {
            throw new PinLockedException(null, false);
        }

        $sessionId = (int) $session['id'];
        $ok = VoicePin::verify($resolvedUid, $sessionId, $pin, $caller);

        if (!$ok) {
            RateLimiter::record('pin:' . $caller, 86400);
            $newAttempts = $attempts + 1;
            $this->pdo->prepare("UPDATE tec_call_sessions SET pin_attempts = :att WHERE id = :id")
                      ->execute([':att' => $newAttempts, ':id' => $sessionId]);

            $attemptsLeft = max(0, 3 - $newAttempts);
            if ($attemptsLeft === 0) {
                throw new PinLockedException(null, false);
            }
            throw new ValidationException(['pin' => 'Incorrect PIN', 'attempts_left' => $attemptsLeft]);
        }

        // Verification succeeded
        $this->pdo->prepare(
            "UPDATE tec_call_sessions
             SET verification = 'pin',
                 pin_verified_at = NOW(),
                 pin_attempts = 0
             WHERE id = :id"
        )->execute([':id' => $sessionId]);

        $uStmt = $this->pdo->prepare("SELECT nombre FROM tec_usuarios WHERE id = :id");
        $uStmt->execute([':id' => $resolvedUid]);
        $name = (string) $uStmt->fetchColumn();
        $parts = explode(' ', trim($name));
        $firstName = $parts[0] ?? '';

        return Response::ok([
            'verified' => true,
            'first_name' => $firstName,
            'caller_type' => $session['caller_type'] ?? 'unknown',
        ]);
    }

    /**
     * POST /voice/session/identify
     */
    public function identify(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();

        $rawPhone = isset($body['phone']) ? (string) $body['phone'] : null;
        $e164 = Phone::toE164($rawPhone);

        if ($e164 !== null) {
            $uStmt = $this->pdo->prepare(
                "SELECT id, tipo, lang, employee_pk, is_internal_staff
                 FROM tec_usuarios
                 WHERE phone_e164 = :phone AND habilitado IN ('yes', 'si') LIMIT 1"
            );
            $uStmt->execute([':phone' => $e164]);
            $user = $uStmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $uid = (int) $user['id'];
                $tipo = (string) ($user['tipo'] ?? '');
                $callerType = 'unknown';
                if ($tipo === 'SuperAdministrador') {
                    $callerType = 'system_admin';
                } elseif ($tipo === 'Administrador') {
                    $callerType = !empty($user['is_internal_staff']) ? 'system_admin' : 'unit_admin';
                } elseif ($tipo === 'Staff' || !empty($user['employee_pk'])) {
                    $callerType = 'employee';
                }
                $lang = in_array($user['lang'] ?? '', ['es', 'en'], true) ? (string) $user['lang'] : 'es';

                $this->pdo->prepare(
                    "UPDATE tec_call_sessions
                     SET caller_raw = :raw,
                         caller_e164 = :e164,
                         caller_trust = 'claimed',
                         resolved_user_id = :uid,
                         caller_type = :ctype,
                         lang = :lang,
                         verification = 'none'
                     WHERE id = :id"
                )->execute([
                    ':raw' => $rawPhone,
                    ':e164' => $e164,
                    ':uid' => $uid,
                    ':ctype' => $callerType,
                    ':lang' => $lang,
                    ':id' => $sessionId,
                ]);
            }
        }

        // Always returns {next: "ask_pin"} to prevent enumeration
        return Response::ok(['next' => 'ask_pin']);
    }

    /**
     * POST /voice/session/lang
     */
    public function setLang(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $lang = (string) ($body['lang'] ?? '');

        if (!in_array($lang, ['es', 'en'], true)) {
            throw new ValidationException(['lang' => "Language must be 'es' or 'en'"]);
        }

        $this->pdo->prepare("UPDATE tec_call_sessions SET lang = :lang WHERE id = :id")
                  ->execute([':lang' => $lang, ':id' => $sessionId]);

        return Response::ok(['lang' => $lang]);
    }

    /**
     * POST /voice/session/end
     */
    public function endSession(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();

        $allowedOutcomes = ['resolved', 'message_taken', 'transferred', 'abandoned', 'other'];
        $outcome = (string) ($body['outcome'] ?? 'resolved');
        if (!in_array($outcome, $allowedOutcomes, true)) {
            $outcome = 'other';
        }

        $summary = isset($body['summary']) ? mb_substr((string) $body['summary'], 0, 500) : null;
        if ($summary !== null) {
            $summary = preg_replace('/\b\d{6}\b/', '******', $summary) ?? $summary;
        }

        $this->pdo->prepare(
            "UPDATE tec_call_sessions
             SET status = 'ended',
                 ended_at = NOW(),
                 outcome = :outcome,
                 summary = COALESCE(:summary, summary)
             WHERE id = :id"
        )->execute([
            ':outcome' => $outcome,
            ':summary' => $summary,
            ':id' => $sessionId,
        ]);

        return Response::ok(['ended' => true]);
    }
}
