<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\AuditLogger;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\UnauthorizedException;
use Pgs\Core\Json;
use Pgs\Core\Phone;

final class CallSessionService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Handles POST /webhooks/elevenlabs/conversation-init
     */
    public function initWebhook(array $body): array
    {
        $expectedAgent = (string) Config::get('elevenlabs', 'agent_id', '');
        $providedAgent = mb_substr((string) ($body['agent_id'] ?? ''), 0, 80);

        if ($expectedAgent !== '' && $providedAgent !== '' && !hash_equals($expectedAgent, $providedAgent)) {
            throw new ForbiddenException('Invalid agent_id');
        }

        $convId = mb_substr((string) ($body['conversation_id'] ?? ('conv_' . bin2hex(random_bytes(8)))), 0, 80);
        $callerRaw = isset($body['caller_id']) ? mb_substr((string) $body['caller_id'], 0, 80) : null;
        $callerE164 = Phone::toE164($callerRaw);
        $calledE164 = Phone::toE164($body['called_number'] ?? null);
        $callSid = isset($body['call_sid']) ? mb_substr((string) $body['call_sid'], 0, 120) : null;

        $callerTrust = $callerE164 !== null ? 'cli' : 'none';
        $resolvedUserId = null;
        $callerType = 'unknown';
        $verification = 'none';
        $lang = 'es';

        if ($callerE164 !== null) {
            $uStmt = $this->pdo->prepare(
                "SELECT id, nickname, tipo, lang, habilitado, employee_pk, is_internal_staff
                 FROM tec_usuarios
                 WHERE phone_e164 = :phone AND habilitado IN ('yes', 'si') LIMIT 1"
            );
            $uStmt->execute([':phone' => $callerE164]);
            $user = $uStmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $resolvedUserId = (int) $user['id'];
                $verification = 'phone';
                $lang = in_array($user['lang'] ?? '', ['es', 'en'], true) ? (string) $user['lang'] : 'es';

                $tipo = (string) ($user['tipo'] ?? '');
                if ($tipo === 'SuperAdministrador') {
                    $callerType = 'system_admin';
                } elseif ($tipo === 'Administrador') {
                    $callerType = !empty($user['is_internal_staff']) ? 'system_admin' : 'unit_admin';
                } elseif ($tipo === 'Staff' || !empty($user['employee_pk'])) {
                    $callerType = 'employee';
                }
            }
        }

        // Upsert session
        $now = Clock::nowUtcSql();
        $upsert = $this->pdo->prepare(
            "INSERT INTO tec_call_sessions (
                conversation_id, call_sid, agent_id, caller_e164, caller_raw, called_e164,
                caller_trust, resolved_user_id, caller_type, verification, lang,
                status, origin, started_at, last_activity_at, expires_at
             ) VALUES (
                :conv_id, :sid, :agent_id, :caller_e164, :caller_raw, :called_e164,
                :caller_trust, :resolved_uid, :caller_type, :verification, :lang,
                'active', 'init_webhook', :now, :now, DATE_ADD(:now, INTERVAL 30 MINUTE)
             ) ON DUPLICATE KEY UPDATE
                call_sid = VALUES(call_sid),
                agent_id = VALUES(agent_id),
                caller_e164 = VALUES(caller_e164),
                caller_raw = VALUES(caller_raw),
                called_e164 = VALUES(called_e164),
                caller_trust = VALUES(caller_trust),
                resolved_user_id = VALUES(resolved_user_id),
                caller_type = VALUES(caller_type),
                verification = VALUES(verification),
                lang = VALUES(lang),
                status = 'active',
                last_activity_at = VALUES(last_activity_at),
                expires_at = DATE_ADD(VALUES(last_activity_at), INTERVAL 30 MINUTE)"
        );
        $upsert->execute([
            ':conv_id' => $convId,
            ':sid' => $callSid,
            ':agent_id' => $providedAgent ?: null,
            ':caller_e164' => $callerE164,
            ':caller_raw' => $callerRaw,
            ':called_e164' => $calledE164,
            ':caller_trust' => $callerTrust,
            ':resolved_uid' => $resolvedUserId,
            ':caller_type' => $callerType,
            ':verification' => $verification,
            ':lang' => $lang,
            ':now' => $now,
        ]);

        $businessOpen = BusinessHours::isOpen();

        $response = [
            'type' => 'conversation_initiation_client_data',
            'dynamic_variables' => [
                'caller_type' => $callerType,
                'verification_level' => $verification,
                'caller_lang' => $lang,
                'business_open' => $businessOpen ? 'true' : 'false',
                'pgs_session_ok' => 'true',
            ],
        ];

        $allowOverrides = (bool) Config::get('elevenlabs', 'allow_overrides', true);
        if ($allowOverrides) {
            $firstMessage = $lang === 'es'
                ? "Hola, gracias por llamar a PGS Centrum. Le atiende un asistente virtual con inteligencia artificial. Esta llamada puede ser grabada y transcrita para gestionar su solicitud y sus datos se tratan según nuestro aviso de privacidad; si continúa en la línea, acepta estas condiciones. For English, just say English. ¿En qué le puedo ayudar?"
                : "Hello, thank you for calling PGS Centrum. You are speaking with an AI virtual assistant. This call may be recorded and transcribed to handle your request, and your data is processed under our privacy notice; by staying on the line you agree to this. Si prefiere español, diga español. How can I help you?";

            $response['conversation_config_override'] = [
                'agent' => [
                    'language' => $lang,
                    'first_message' => $firstMessage,
                ],
            ];
        }

        return $response;
    }

    /**
     * Resolves an active call session, or performs lazy resolution/fallback against ElevenLabs API.
     */
    public function resolveSession(string $convId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tec_call_sessions
             WHERE conversation_id = :cid
               AND status = 'active'
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1"
        );
        $stmt->execute([':cid' => $convId]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($session) {
            // Touch expiration
            $this->pdo->prepare(
                "UPDATE tec_call_sessions
                 SET expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                     last_activity_at = NOW()
                 WHERE id = :id"
            )->execute([':id' => $session['id']]);
            return $session;
        }

        // Session not in DB: check fallback via ElevenLabs API
        return $this->createLazySession($convId);
    }

    private function createLazySession(string $convId): array
    {
        $apiKey = (string) Config::get('elevenlabs', 'api_key', '');
        $expectedAgent = (string) Config::get('elevenlabs', 'agent_id', '');
        $callerE164 = null;
        $callerTrust = 'none';

        if ($apiKey !== '') {
            $url = "https://api.elevenlabs.io/v1/convai/conversations/" . urlencode($convId);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['xi-api-key: ' . $apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp !== false && $code === 200) {
                $data = json_decode((string) $resp, true);
                if (is_array($data)) {
                    $agentId = (string) ($data['agent_id'] ?? '');
                    if ($expectedAgent === '' || hash_equals($expectedAgent, $agentId)) {
                        $extNum = $data['metadata']['phone_call']['external_number'] ?? null;
                        if (!empty($extNum)) {
                            $callerE164 = Phone::toE164((string) $extNum);
                            if ($callerE164 !== null) {
                                $callerTrust = 'cli';
                            }
                        }
                    }
                }
            }
        }

        $resolvedUserId = null;
        $callerType = 'unknown';
        $verification = 'none';
        $lang = 'es';

        if ($callerE164 !== null) {
            $uStmt = $this->pdo->prepare(
                "SELECT id, tipo, lang, employee_pk, is_internal_staff FROM tec_usuarios WHERE phone_e164 = :phone AND habilitado IN ('yes', 'si') LIMIT 1"
            );
            $uStmt->execute([':phone' => $callerE164]);
            $user = $uStmt->fetch(\PDO::FETCH_ASSOC);
            if ($user) {
                $resolvedUserId = (int) $user['id'];
                $verification = 'phone';
                $lang = in_array($user['lang'] ?? '', ['es', 'en'], true) ? (string) $user['lang'] : 'es';
                $tipo = (string) ($user['tipo'] ?? '');
                if ($tipo === 'SuperAdministrador') {
                    $callerType = 'system_admin';
                } elseif ($tipo === 'Administrador') {
                    $callerType = !empty($user['is_internal_staff']) ? 'system_admin' : 'unit_admin';
                } elseif ($tipo === 'Staff' || !empty($user['employee_pk'])) {
                    $callerType = 'employee';
                }
            }
        }

        $now = Clock::nowUtcSql();
        $ins = $this->pdo->prepare(
            "INSERT INTO tec_call_sessions (
                conversation_id, caller_e164, caller_trust, resolved_user_id,
                caller_type, verification, lang, status, origin, started_at, last_activity_at, expires_at
             ) VALUES (
                :cid, :e164, :trust, :uid, :type, :verif, :lang, 'active', 'lazy', :now, :now, DATE_ADD(:now, INTERVAL 30 MINUTE)
             ) ON DUPLICATE KEY UPDATE last_activity_at = VALUES(last_activity_at)"
        );
        $ins->execute([
            ':cid' => $convId,
            ':e164' => $callerE164,
            ':trust' => $callerTrust,
            ':uid' => $resolvedUserId,
            ':type' => $callerType,
            ':verif' => $verification,
            ':lang' => $lang,
            ':now' => $now,
        ]);
        $get = $this->pdo->prepare("SELECT * FROM tec_call_sessions WHERE conversation_id = :cid");
        $get->execute([':cid' => $convId]);
        return (array) $get->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Handles POST /webhooks/elevenlabs/post-call with ElevenLabs-Signature verification.
     */
    public function handlePostCall(string $signatureHeader, string $rawBody): array
    {
        $secret = (string) Config::get('elevenlabs', 'webhook_postcall_secret', '');
        if ($secret === '') {
            throw new UnauthorizedException('Post-call webhook secret is not configured');
        }
        $this->verifyElevenLabsSignature($signatureHeader, $rawBody, $secret);

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return ['ok' => true];
        }

        $type = (string) ($data['type'] ?? '');
        if ($type === 'post_call_transcription') {
            $convId = (string) ($data['data']['conversation_id'] ?? '');
            $duration = (int) ($data['data']['metadata']['call_duration_secs'] ?? 0);
            $summary = mb_substr((string) ($data['data']['analysis']['transcript_summary'] ?? ''), 0, 1000);

            // Redact possible 6-digit PIN patterns from summary
            $cleanSummary = preg_replace('/\b\d{6}\b/', '******', $summary) ?? $summary;

            $callSuccess = $data['data']['analysis']['call_successful'] ?? null;
            $defaultOutcome = ($callSuccess === false || $callSuccess === 'failure') ? 'abandoned' : 'resolved';

            if ($convId !== '') {
                $this->pdo->prepare(
                    "UPDATE tec_call_sessions
                     SET status = 'ended',
                         ended_at = COALESCE(ended_at, NOW()),
                         duration_secs = :duration,
                         summary = :summary,
                         outcome = COALESCE(outcome, :outcome),
                         postcall_received_at = NOW()
                     WHERE conversation_id = :cid"
                )->execute([
                    ':duration' => $duration,
                    ':summary' => $cleanSummary,
                    ':outcome' => $defaultOutcome,
                    ':cid' => $convId,
                ]);
            }
        } elseif ($type === 'call_initiation_failure') {
            $convId = (string) ($data['data']['conversation_id'] ?? '');
            $callSid = (string) ($data['data']['call_sid'] ?? '');
            $sessId = null;
            if ($convId !== '' || $callSid !== '') {
                $sStmt = $this->pdo->prepare(
                    "SELECT id FROM tec_call_sessions WHERE (conversation_id = :cid AND :cid != '') OR (call_sid = :sid AND :sid != '') LIMIT 1"
                );
                $sStmt->execute([':cid' => $convId, ':sid' => $callSid]);
                $sessId = $sStmt->fetchColumn() ?: null;
            }

            if ($sessId !== null) {
                $lStmt = $this->pdo->prepare(
                    "INSERT INTO tec_call_logs (call_session_id, kind, name, input_redacted, result_code, created_at)
                     VALUES (:sid, 'event', 'call_initiation_failure', :input, 'failed', NOW(3))"
                );
                $lStmt->execute([
                    ':sid' => (int) $sessId,
                    ':input' => Json::encode($data['data'] ?? []),
                ]);
            }

            AuditLogger::log(
                \Pgs\Auth\Principal::anonymous('webhook'),
                'call.initiation_failure',
                'call_session',
                $sessId ? (int) $sessId : null,
                null,
                $data['data'] ?? []
            );
        }

        return ['ok' => true];
    }

    private function verifyElevenLabsSignature(string $sigHeader, string $rawBody, string $secret): void
    {
        if ($sigHeader === '') {
            throw new UnauthorizedException('Missing signature header');
        }

        // Format: t=<unix>,v0=<hex>[,v0=<hex>]
        if (!preg_match('/t=(\d+)/', $sigHeader, $tm)) {
            throw new UnauthorizedException('Invalid signature format');
        }
        $t = (int) $tm[1];
        if (abs(time() - $t) > 1800) {
            throw new UnauthorizedException('Signature timestamp out of bounds');
        }

        $expected = hash_hmac('sha256', "{$t}.{$rawBody}", $secret);
        preg_match_all('/v0=([a-fA-F0-9]+)/', $sigHeader, $vm);
        $v0List = $vm[1] ?? [];

        $matched = false;
        foreach ($v0List as $v0) {
            if (hash_equals($expected, $v0)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            throw new UnauthorizedException('Signature verification failed');
        }
    }

    /**
     * Records an MCP tool invocation trace in tec_call_logs.
     */
    public function recordCallLog(int $callSessionId, string $route, int $resultCode, float $latencyMs, mixed $input = null): void
    {
        $redactedInput = null;
        if ($input !== null) {
            $rawInput = is_string($input) ? $input : Json::encode($input);
            // Redact PIN and 10+ digits phones
            $redactedInput = preg_replace('/"pin"\s*:\s*"[^"]*"/', '"pin":"******"', $rawInput);
            $redactedInput = preg_replace('/\b\d{6}\b/', '******', (string) $redactedInput);
            $redactedInput = preg_replace('/\+?\d[\d\s().-]{8,}\d/', '[phone]', (string) $redactedInput);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO tec_call_logs (call_session_id, kind, name, input_redacted, result_code, latency_ms, created_at)
             VALUES (:sid, 'tool', :name, :input, :code, :lat, NOW())"
        );
        $stmt->execute([
            ':sid' => $callSessionId,
            ':name' => $route,
            ':input' => $redactedInput,
            ':code' => $resultCode,
            ':lat' => (int) round($latencyMs),
        ]);
    }
}
