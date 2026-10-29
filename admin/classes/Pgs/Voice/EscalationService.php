<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Json;
use Pgs\Core\Phone;

final class EscalationService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Evaluates escalation rules based on business hours, holidays, and configured phone numbers (P-08, D-27).
     *
     * @return array{
     *   mode: 'transfer'|'message',
     *   business_open: bool,
     *   hours_text: string,
     *   transfer_number: ?string,
     *   emergency_advice: string
     * }
     */
    public function getEscalationInfo(?\DateTimeInterface $dt = null, string $lang = 'es'): array
    {
        $isOpen = BusinessHours::isOpen($dt);
        $hoursText = BusinessHours::hoursText($lang);
        $rawEscalationPhone = (string) Config::get('voice', 'escalation_phone', '');

        $transferNumber = null;
        if ($rawEscalationPhone !== '') {
            $transferNumber = Phone::toE164($rawEscalationPhone) ?? (preg_match('/^\+[1-9]\d{7,14}$/', $rawEscalationPhone) ? $rawEscalationPhone : null);
        }

        // P-08: If client has not configured escalation number, fallback to message taking
        if ($isOpen && $transferNumber !== null) {
            $mode = 'transfer';
        } else {
            $mode = 'message';
            $transferNumber = null;
        }

        $emergencyAdvice = $lang === 'en'
            ? 'In case of life-threatening emergency, fire, gas leak or immediate danger, please call 911 immediately.'
            : 'En caso de riesgo para la vida, incendio, fuga de gas o peligro inminente, por favor llame de inmediato al 911.';

        return [
            'mode' => $mode,
            'business_open' => $isOpen,
            'hours_text' => $hoursText,
            'transfer_number' => $transferNumber,
            'emergency_advice' => $emergencyAdvice,
        ];
    }

    /**
     * Records an escalation attempt and updates the call session.
     */
    public function recordEscalation(int $callSessionId, string $mode, ?string $reason = null): void
    {
        $payload = [
            'mode' => $mode,
            'reason' => $reason,
        ];

        $stmt = $this->pdo->prepare(
            "INSERT INTO tec_call_logs (call_session_id, kind, name, input_redacted, result_code, created_at)
             VALUES (:sid, 'event', 'escalation', :input, :mode, NOW(3))"
        );
        $stmt->execute([
            ':sid' => $callSessionId,
            ':input' => Json::encode($payload),
            ':mode' => $mode,
        ]);

        if ($mode === 'transfer') {
            $rawEscalationPhone = (string) Config::get('voice', 'escalation_phone', '');
            $e164 = Phone::toE164($rawEscalationPhone) ?? (preg_match('/^\+[1-9]\d{7,14}$/', $rawEscalationPhone) ? $rawEscalationPhone : null);
            $this->pdo->prepare(
                "UPDATE tec_call_sessions
                 SET outcome = 'transferred',
                     transferred_to = :phone,
                     last_activity_at = NOW()
                 WHERE id = :sid"
            )->execute([
                ':phone' => $e164,
                ':sid' => $callSessionId,
            ]);
        } elseif ($mode === 'message') {
            $this->pdo->prepare(
                "UPDATE tec_call_sessions
                 SET outcome = COALESCE(outcome, 'message_taken'),
                     last_activity_at = NOW()
                 WHERE id = :sid"
            )->execute([
                ':sid' => $callSessionId,
            ]);
        }
    }
}
