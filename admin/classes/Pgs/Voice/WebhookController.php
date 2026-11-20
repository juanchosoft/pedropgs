<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\AppLog;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\UnauthorizedException;
use Pgs\Requests\RequestService;
use Pgs\Responders\SettingsRepository;

final class WebhookController
{
    private const SIGNATURE_TOLERANCE_SECONDS = 1800;
    private const SAFETY_NET_MIN_SECONDS = 15;

    private \PDO $pdo;
    private SettingsRepository $settings;
    private PhoneResolver $resolver;
    private RequestService $requests;

    /** @var callable|null */
    private $fetcher;

    /**
     * @param callable|null $fetcher receives the conversation id and returns the conversation data, null when unknown, or an HTTP status code to simulate a failure
     */
    public function __construct(?\PDO $pdo = null, ?callable $fetcher = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->settings = new SettingsRepository($this->pdo);
        $this->resolver = new PhoneResolver($this->pdo);
        $this->requests = new RequestService($this->pdo, $this->settings);
        $this->fetcher = $fetcher;
    }

    /**
     * Conversation initiation webhook. Never fails: any internal error returns empty variables.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function init(array $body): array
    {
        $expectedAgent = (string) Config::get('elevenlabs', 'agent_id', '');
        $providedAgent = (string) ($body['agent_id'] ?? '');
        if ($expectedAgent !== '' && $providedAgent !== '' && !hash_equals($expectedAgent, $providedAgent)) {
            throw new ForbiddenException('Invalid agent');
        }

        try {
            if (!$this->settings->bool('voice_enabled', true)) {
                return $this->payload($this->emptyVariables());
            }
            $conversationId = mb_substr(trim((string) ($body['conversation_id'] ?? '')), 0, 100);
            if ($conversationId === '') {
                $conversationId = 'conv_' . bin2hex(random_bytes(8));
            }
            $raw = isset($body['caller_id']) ? mb_substr((string) $body['caller_id'], 0, 50) : null;
            $called = isset($body['called_number']) ? mb_substr((string) $body['called_number'], 0, 20) : null;
            $resolved = $this->resolver->resolve($raw);
            $this->upsertCall($conversationId, $raw, $called, $resolved);

            return $this->payload($this->variables($resolved));
        } catch (\Throwable $e) {
            AppLog::error('conversation-init', $e);

            return $this->payload($this->emptyVariables());
        }
    }

    /**
     * Finds the call row for a conversation, creating it on demand.
     * Only an explicit "not found" from ElevenLabs rejects the conversation; any other
     * lookup failure degrades to an unidentified caller so a request is never lost.
     *
     * @return array<string,mixed>
     */
    public function ensureCall(string $conversationId, bool $trusted = false): array
    {
        $row = $this->findCall($conversationId);
        if ($row !== null) {
            return $row;
        }

        $raw = null;
        $apiKey = (string) Config::get('elevenlabs', 'api_key', '');
        if (!$trusted && ($apiKey !== '' || $this->fetcher !== null)) {
            $lookup = $this->fetchConversation($conversationId, $apiKey);
            if ($lookup['status'] === 404 || $lookup['status'] === 422) {
                throw new NotFoundException('Unknown conversation');
            }
            if ($lookup['status'] === 200 && is_array($lookup['data'])) {
                $expectedAgent = (string) Config::get('elevenlabs', 'agent_id', '');
                if ($expectedAgent !== '' && !hash_equals($expectedAgent, (string) ($lookup['data']['agent_id'] ?? ''))) {
                    throw new NotFoundException('Unknown conversation');
                }
                $external = $lookup['data']['metadata']['phone_call']['external_number'] ?? null;
                $raw = is_string($external) && $external !== '' ? $external : null;
            } else {
                AppLog::write('warning', 'conversation-lookup', 'ElevenLabs lookup unavailable', ['status' => $lookup['status']]);
            }
        }

        $resolved = $this->resolver->resolve($raw);
        $this->upsertCall($conversationId, $raw, null, $resolved);

        return $this->findCall($conversationId) ?? throw new NotFoundException('Unknown conversation');
    }

    /**
     * Post-call webhook. Verifies the HMAC signature, closes the call and applies the safety net.
     *
     * @return array<string,mixed>
     */
    public function postCall(string $signatureHeader, string $rawBody): array
    {
        $secret = (string) Config::get('elevenlabs', 'webhook_postcall_secret', '');
        if ($secret === '') {
            throw new UnauthorizedException('Post-call secret is not configured');
        }
        $this->verifySignature($signatureHeader, $rawBody, $secret);

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || ($payload['type'] ?? '') !== 'post_call_transcription') {
            return ['ok' => true];
        }
        $data = (array) ($payload['data'] ?? []);
        $conversationId = mb_substr((string) ($data['conversation_id'] ?? ''), 0, 100);
        if ($conversationId === '') {
            return ['ok' => true];
        }

        try {
            $call = $this->ensureCall($conversationId, true);
        } catch (NotFoundException) {
            return ['ok' => true];
        }

        $duration = (int) ($data['metadata']['call_duration_secs'] ?? 0);
        $summary = trim((string) ($data['analysis']['transcript_summary'] ?? ''));
        $transfer = $this->transferState((array) ($data['transcript'] ?? []));

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM tec_requests WHERE call_id = :c AND deleted_at IS NULL');
        $count->execute([':c' => (int) $call['id']]);
        $hasRequest = (int) $count->fetchColumn() > 0;

        $created = false;
        if (!$hasRequest && (($transfer['attempted'] && $transfer['failed']) || (!$transfer['succeeded'] && $duration >= self::SAFETY_NET_MIN_SECONDS))) {
            $this->requests->create([
                'summary' => $summary !== '' ? mb_substr($summary, 0, 300) : 'Call ended without a registered request',
                'details' => $summary !== '' ? $summary : null,
                'priority' => $transfer['failed'] ? 'high' : 'normal',
                'unidad_id' => $call['unidad_id'],
                'caller_e164' => $call['caller_e164'],
                'caller_name' => $call['caller_name'],
                'needs_review' => true,
            ], (int) $call['id'], 'post_call');
            $created = true;
        }

        $outcome = $hasRequest || $created ? 'request_created' : ($transfer['succeeded'] ? 'transferred' : 'none');
        $this->pdo->prepare(
            'UPDATE tec_calls SET state = "ended", outcome = IF(outcome IN ("transferred","message","request_created"), outcome, :outcome),
             duration_seconds = :dur, summary = :summary, ended_at = :now WHERE id = :id'
        )->execute([':outcome' => $outcome, ':dur' => $duration, ':summary' => $summary !== '' ? mb_substr($summary, 0, 2000) : null, ':now' => Clock::nowUtcSql(), ':id' => (int) $call['id']]);

        return ['ok' => true, 'request_created' => $created];
    }

    /**
     * @param array<string,mixed> $resolved
     */
    private function upsertCall(string $conversationId, ?string $raw, ?string $called, array $resolved): void
    {
        $ids = implode(',', array_map(static fn (array $c): int => $c['id'], $resolved['candidates']));
        $this->pdo->prepare(
            'INSERT INTO tec_calls
             (conversation_id, caller_e164, caller_raw, called_number, match_status, unidad_id, candidate_ids, caller_name, caller_kind, state, outcome, started_at)
             VALUES (:conv, :e164, :raw, :called, :status, :unit, :cands, :name, :kind, "active", "unknown", :now)
             ON DUPLICATE KEY UPDATE caller_e164 = VALUES(caller_e164), caller_raw = VALUES(caller_raw), called_number = VALUES(called_number),
             match_status = VALUES(match_status), unidad_id = VALUES(unidad_id), candidate_ids = VALUES(candidate_ids),
             caller_name = VALUES(caller_name), caller_kind = VALUES(caller_kind), state = "active"'
        )->execute([
            ':conv' => $conversationId,
            ':e164' => $resolved['e164'],
            ':raw' => $raw,
            ':called' => $called,
            ':status' => $resolved['status'],
            ':unit' => $resolved['unidad_id'],
            ':cands' => $ids !== '' ? mb_substr($ids, 0, 255) : null,
            ':name' => $resolved['caller_name'] !== null ? mb_substr((string) $resolved['caller_name'], 0, 120) : null,
            ':kind' => $resolved['caller_kind'],
            ':now' => Clock::nowUtcSql(),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function findCall(string $conversationId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tec_calls WHERE conversation_id = :c');
        $stmt->execute([':c' => $conversationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $resolved
     * @return array<string,string>
     */
    private function variables(array $resolved): array
    {
        $names = array_map(static fn (array $c): string => $c['name'], $resolved['candidates']);

        return [
            'known_property' => $resolved['status'] === 'single' ? 'yes' : 'no',
            'property_name' => (string) ($resolved['unit_name'] ?? ''),
            'property_candidates' => implode('; ', $names),
            'caller_name' => (string) ($resolved['caller_name'] ?? ''),
            'caller_kind' => $resolved['caller_kind'],
            'business_open' => (new BusinessHours($this->settings))->isOpen() ? 'yes' : 'no',
        ];
    }

    /** @return array<string,string> */
    private function emptyVariables(): array
    {
        return [
            'known_property' => 'no',
            'property_name' => '',
            'property_candidates' => '',
            'caller_name' => '',
            'caller_kind' => 'unknown',
            'business_open' => 'yes',
        ];
    }

    /**
     * @param array<string,string> $variables
     * @return array<string,mixed>
     */
    private function payload(array $variables): array
    {
        return ['type' => 'conversation_initiation_client_data', 'dynamic_variables' => $variables];
    }

    /**
     * @param list<array<string,mixed>> $transcript
     * @return array{attempted:bool,failed:bool,succeeded:bool}
     */
    private function transferState(array $transcript): array
    {
        $attempted = false;
        $failed = false;
        foreach ($transcript as $turn) {
            foreach ((array) ($turn['tool_calls'] ?? []) as $call) {
                if (($call['tool_name'] ?? '') === 'transfer_to_number') {
                    $attempted = true;
                }
            }
            foreach ((array) ($turn['tool_results'] ?? []) as $result) {
                if (($result['tool_name'] ?? '') !== 'transfer_to_number') {
                    continue;
                }
                $attempted = true;
                $text = is_string($result['result_value'] ?? null) ? $result['result_value'] : '';
                if (!empty($result['is_error']) || preg_match('/fail|error|unavailable|no.?answer|busy|declin/i', $text)) {
                    $failed = true;
                }
            }
        }

        return ['attempted' => $attempted, 'failed' => $failed, 'succeeded' => $attempted && !$failed];
    }

    private function verifySignature(string $header, string $rawBody, string $secret): void
    {
        if ($header === '' || !preg_match('/t=(\d+)/', $header, $tm)) {
            throw new UnauthorizedException('Invalid signature');
        }
        $timestamp = (int) $tm[1];
        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            throw new UnauthorizedException('Signature timestamp out of bounds');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
        preg_match_all('/v0=([a-fA-F0-9]+)/', $header, $matches);
        foreach ($matches[1] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return;
            }
        }
        throw new UnauthorizedException('Invalid signature');
    }

    /** @return array{status:int,data:?array<string,mixed>} */
    private function fetchConversation(string $conversationId, string $apiKey): array
    {
        if ($this->fetcher !== null) {
            $data = ($this->fetcher)($conversationId);
            if (is_int($data)) {
                return ['status' => $data, 'data' => null];
            }

            return $data === null ? ['status' => 404, 'data' => null] : ['status' => 200, 'data' => $data];
        }
        $ch = curl_init('https://api.elevenlabs.io/v1/convai/conversations/' . rawurlencode($conversationId));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['xi-api-key: ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = $body !== false ? json_decode((string) $body, true) : null;

        return ['status' => $code, 'data' => is_array($data) ? $data : null];
    }
}
