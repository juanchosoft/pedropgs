<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Requests\RequestService;
use Pgs\Responders\SettingsRepository;

final class VoiceController
{
    private \PDO $pdo;
    private RequestService $requests;
    private PropertyMatcher $matcher;
    private ResponderSelector $responders;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->requests = new RequestService($this->pdo, new SettingsRepository($this->pdo));
        $this->matcher = new PropertyMatcher($this->pdo);
        $this->responders = new ResponderSelector($this->pdo);
    }

    public function register(Request $req): Response
    {
        $call = (array) $req->attr('call');
        $body = $req->json();
        $lang = $this->lang($body, $call);

        $summary = trim((string) ($body['summary'] ?? ''));
        if (mb_strlen($summary) < 3) {
            throw new ValidationException(['summary' => 'Summary is required']);
        }

        $property = $this->resolveProperty($call, trim((string) ($body['property'] ?? '')));
        if ($property['unidad_id'] === null && $property['text'] === null) {
            if ((int) ($call['property_asked'] ?? 0) === 0) {
                $this->pdo->prepare('UPDATE tec_calls SET property_asked = 1 WHERE id = :id')->execute([':id' => (int) $call['id']]);

                return Response::ok(['created' => false, 'need' => 'property', 'say' => Say::text('need_property', $lang)]);
            }
        }

        $result = $this->requests->create([
            'summary' => $summary,
            'details' => $body['details'] ?? null,
            'priority' => (string) ($body['priority'] ?? 'normal'),
            'unidad_id' => $property['unidad_id'],
            'property_text' => $property['text'],
            'caller_e164' => $call['caller_e164'],
            'caller_name' => trim((string) ($body['caller_name'] ?? '')) ?: $call['caller_name'],
        ], (int) $call['id'], 'tool');

        if ($result['limited']) {
            return Response::ok(['created' => false, 'say' => Say::text('limited', $lang)]);
        }
        if ($result['created']) {
            $this->markOutcome((int) $call['id'], 'request_created');
        }

        $name = $property['name'] ?? ($property['text'] ?? ($lang === 'es' ? 'propiedad sin confirmar' : 'unconfirmed property'));

        return Response::ok([
            'created' => $result['created'],
            'code' => $result['code'],
            'priority' => $result['priority'],
            'property' => $property['name'],
            'duplicate' => $result['duplicate'],
            'needs_review' => $result['needs_review'],
            'say' => $result['duplicate']
                ? Say::text('duplicate', $lang)
                : Say::text('registered', $lang, [
                    'property' => $name,
                    'summary' => self::shorten($summary, 60),
                    'priority' => Say::priority($result['priority'], $lang),
                ]),
        ]);
    }

    public function open(Request $req): Response
    {
        $call = (array) $req->attr('call');
        $lang = $this->lang([], $call);
        $rows = $call['caller_e164'] !== null ? $this->requests->openForCaller((string) $call['caller_e164']) : [];
        if ($rows === []) {
            return Response::ok(['requests' => [], 'say' => Say::text('no_open', $lang)]);
        }

        $now = Clock::nowUtc()->getTimestamp();
        $out = [];
        $parts = [];
        foreach ($rows as $row) {
            $out[] = [
                'code' => $row['code'],
                'summary' => $row['summary'],
                'status' => $row['status'],
                'priority' => $row['priority'],
                'minutes_ago' => max(0, (int) floor(($now - strtotime((string) $row['received_at'] . ' UTC')) / 60)),
            ];
            $parts[] = $row['code'] . ' ' . ($lang === 'es' ? 'está' : 'is') . ' ' . Say::status((string) $row['status'], $lang);
        }

        return Response::ok(['requests' => $out, 'say' => implode('. ', $parts) . '.']);
    }

    public function responder(Request $req): Response
    {
        $call = (array) $req->attr('call');
        $body = $req->json();
        $lang = $this->lang($body, $call);
        $reason = (string) ($body['reason'] ?? '');
        if (!in_array($reason, ['urgent', 'owner', 'person'], true)) {
            throw new ValidationException(['reason' => 'Reason must be urgent, owner or person']);
        }
        if ($reason === 'person' && trim((string) ($body['person_name'] ?? '')) === '') {
            throw new ValidationException(['person_name' => 'Name of the person is required']);
        }
        $urgent = $reason === 'urgent';
        $code = null;
        $unidadId = null;

        if ($urgent) {
            $summary = trim((string) ($body['summary'] ?? ''));
            if (mb_strlen($summary) < 3) {
                throw new ValidationException(['summary' => 'Summary is required for an urgent call']);
            }
            $code = $this->existingUrgent((int) $call['id']);
            if ($code !== null) {
                $known = $this->pdo->prepare('SELECT unidad_id FROM tec_requests WHERE code = :code');
                $known->execute([':code' => $code]);
                $value = $known->fetchColumn();
                $unidadId = $value === false || $value === null ? null : (int) $value;
            }
            if ($code === null) {
                $property = $this->resolveProperty($call, trim((string) ($body['property'] ?? '')));
                $unidadId = $property['unidad_id'] === null ? null : (int) $property['unidad_id'];
                $created = $this->requests->create([
                    'summary' => $summary,
                    'priority' => 'urgent',
                    'unidad_id' => $property['unidad_id'],
                    'property_text' => $property['text'],
                    'caller_e164' => $call['caller_e164'],
                    'caller_name' => $call['caller_name'],
                ], (int) $call['id'], 'urgent_flow');
                $code = $created['code'];
            }
        }

        $target = $this->responders->forTransfer($reason, isset($body['person_name']) ? (string) $body['person_name'] : null, $urgent, null, $unidadId);
        if ($target !== null) {
            $this->pdo->prepare('UPDATE tec_calls SET outcome = "transferred", transferred_to = :t WHERE id = :id')
                ->execute([':t' => mb_substr((string) $target['name'], 0, 120), ':id' => (int) $call['id']]);

            return Response::ok([
                'mode' => 'transfer',
                'transfer' => ['name' => $target['name'], 'phone_e164' => $target['phone_e164']],
                'request_code' => $code,
                'say' => Say::text('transfer', $lang),
            ]);
        }

        $this->markOutcome((int) $call['id'], 'message');

        return Response::ok([
            'mode' => 'message',
            'next' => $urgent ? 'none' : 'register',
            'request_code' => $code,
            'say' => Say::text($urgent ? 'nobody' : 'nobody_register', $lang),
        ]);
    }

    /**
     * @param array<string,mixed> $call
     * @return array{unidad_id:?int,name:?string,text:?string}
     */
    private function resolveProperty(array $call, string $text): array
    {
        if ($call['match_status'] === 'single' && $call['unidad_id'] !== null) {
            return ['unidad_id' => (int) $call['unidad_id'], 'name' => $this->unitName((int) $call['unidad_id']), 'text' => null];
        }
        if ($text === '') {
            return ['unidad_id' => null, 'name' => null, 'text' => null];
        }
        $candidates = $call['candidate_ids'] ? array_map('intval', explode(',', (string) $call['candidate_ids'])) : null;
        $hit = $this->matcher->match($text, $candidates) ?? ($candidates !== null ? $this->matcher->match($text) : null);
        if ($hit !== null) {
            return ['unidad_id' => $hit['id'], 'name' => $hit['name'], 'text' => null];
        }

        return ['unidad_id' => null, 'name' => null, 'text' => mb_substr($text, 0, 190)];
    }

    /**
     * Cuts at a word boundary so the spoken confirmation stays short.
     */
    private static function shorten(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max / 2 ? mb_substr($cut, 0, $space) : $cut, " ,.;:");
    }

    private function unitName(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT nombre FROM tbl_unidades WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    private function existingUrgent(int $callId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT code FROM tec_requests WHERE call_id = :c AND created_via = "urgent_flow" AND deleted_at IS NULL ORDER BY id LIMIT 1');
        $stmt->execute([':c' => $callId]);
        $code = $stmt->fetchColumn();

        return $code === false ? null : (string) $code;
    }

    private function markOutcome(int $callId, string $outcome): void
    {
        $this->pdo->prepare('UPDATE tec_calls SET outcome = :o WHERE id = :id AND outcome <> "transferred"')
            ->execute([':o' => $outcome, ':id' => $callId]);
    }

    /**
     * @param array<string,mixed> $body
     * @param array<string,mixed> $call
     */
    private function lang(array $body, array $call): string
    {
        $lang = (string) ($body['lang'] ?? '');
        if ($lang !== 'es' && $lang !== 'en') {
            $lang = (string) ($call['lang'] ?? 'en');
        }

        return Say::lang($lang);
    }
}
