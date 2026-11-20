<?php

declare(strict_types=1);

namespace Pgs\Requests;

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Sequence;
use Pgs\Core\SignedLinks;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Notifications\AlertComposer;
use Pgs\Notifications\Flusher;
use Pgs\Notifications\OutboxRepository;
use Pgs\Responders\SettingsRepository;
use Pgs\Voice\PropertyMatcher;
use Pgs\Voice\ResponderSelector;

final class RequestService
{
    public const MAX_PER_CALL = 5;
    public const MAX_PER_CALLER_HOUR = 5;
    public const MAX_CRITICAL_PER_CALLER_HOUR = 20;

    private \PDO $pdo;
    private SettingsRepository $settings;
    private ResponderSelector $responders;
    private OutboxRepository $outbox;

    public function __construct(?\PDO $pdo = null, ?SettingsRepository $settings = null, ?ResponderSelector $responders = null, ?OutboxRepository $outbox = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->settings = $settings ?? new SettingsRepository($this->pdo);
        $this->responders = $responders ?? new ResponderSelector($this->pdo);
        $this->outbox = $outbox ?? new OutboxRepository($this->pdo);
    }

    /**
     * Creates a request, or reports a duplicate / limit without creating anything.
     *
     * @param array<string,mixed> $in summary, details, priority, unidad_id, property_text, caller_e164, caller_name, origin
     * @return array{created:bool,duplicate:bool,limited:bool,id:?int,code:?string,priority:string,needs_review:bool}
     */
    public function create(array $in, ?int $callId, string $via, ?int $actorUserId = null, ?\DateTimeImmutable $at = null): array
    {
        $summary = trim((string) ($in['summary'] ?? ''));
        if ($summary === '') {
            throw new ValidationException(['summary' => 'Summary is required']);
        }
        $summary = mb_substr($summary, 0, 300);
        $priority = (string) ($in['priority'] ?? 'normal');
        if (!in_array($priority, StatusMachine::PRIORITIES, true)) {
            $priority = 'normal';
        }
        if ($via === 'tool' && $priority === 'urgent') {
            $priority = 'high';
        }

        $now = $at ?? Clock::nowUtc();
        $dedupe = $callId !== null ? sha1($callId . '|' . PropertyMatcher::normalize($summary)) : null;

        if ($callId !== null && $via === 'tool') {
            $merged = $this->mergeIntoUrgent($callId, $summary, $in, $actorUserId);
            if ($merged !== null) {
                return $merged;
            }
        }
        if ($dedupe !== null) {
            $existing = $this->pdo->prepare('SELECT id, code, priority, needs_review FROM tec_requests WHERE dedupe_key = :k');
            $existing->execute([':k' => $dedupe]);
            if (($row = $existing->fetch(\PDO::FETCH_ASSOC)) !== false) {
                return $this->result(true, true, false, (int) $row['id'], (string) $row['code'], (string) $row['priority'], (int) $row['needs_review'] === 1);
            }
        }
        if ($this->overLimit($callId, (string) ($in['caller_e164'] ?? ''), $now, $via)) {
            return $this->result(false, false, true, null, null, $priority, false);
        }

        $unidadId = isset($in['unidad_id']) && (int) $in['unidad_id'] > 0 ? (int) $in['unidad_id'] : null;
        $propertyText = isset($in['property_text']) && trim((string) $in['property_text']) !== '' ? mb_substr(trim((string) $in['property_text']), 0, 190) : null;
        $needsReview = !empty($in['needs_review']) || ($unidadId === null);
        $remind = $priority === 'urgent' ? $this->settings->int('esc.urgent_remind_min', 5) : $this->settings->int('esc.remind_min', 15);

        $this->pdo->beginTransaction();
        try {
            $code = Sequence::code($this->pdo, 'REQ', $now);
            $stamp = $now->format('Y-m-d H:i:s');
            $this->pdo->prepare(
                'INSERT INTO tec_requests
                 (code, unidad_id, property_text, caller_e164, caller_name, summary, details, priority, status, assigned_user_id,
                  origin, created_via, call_id, dedupe_key, needs_review, received_at, next_alert_at, created_by, updated_at)
                 VALUES (:code, :unidad, :ptext, :caller, :cname, :summary, :details, :priority, "received", NULL,
                  :origin, :via, :call, :dedupe, :review, :received, :next, :by, :updated)'
            )->execute([
                ':code' => $code,
                ':unidad' => $unidadId,
                ':ptext' => $propertyText,
                ':caller' => isset($in['caller_e164']) && $in['caller_e164'] !== '' ? mb_substr((string) $in['caller_e164'], 0, 20) : null,
                ':cname' => isset($in['caller_name']) && trim((string) $in['caller_name']) !== '' ? mb_substr(trim((string) $in['caller_name']), 0, 120) : null,
                ':summary' => $summary,
                ':details' => isset($in['details']) && trim((string) $in['details']) !== '' ? mb_substr(trim((string) $in['details']), 0, 4000) : null,
                ':priority' => $priority,
                ':origin' => ($in['origin'] ?? 'phone') === 'app' ? 'app' : 'phone',
                ':via' => in_array($via, ['tool', 'urgent_flow', 'post_call', 'app'], true) ? $via : 'tool',
                ':call' => $callId,
                ':dedupe' => $dedupe,
                ':review' => $needsReview ? 1 : 0,
                ':received' => $stamp,
                ':next' => $now->modify("+{$remind} minutes")->format('Y-m-d H:i:s'),
                ':by' => $actorUserId,
                ':updated' => $stamp,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->record($id, 'created', null, 'received', $actorUserId, $via === 'app' ? null : 'voice', mb_substr($summary, 0, 200), $now);
            $this->enqueueInitialAlerts($id, $now);
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($dedupe !== null && $e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_dedupe')) {
                $existing = $this->pdo->prepare('SELECT id, code, priority, needs_review FROM tec_requests WHERE dedupe_key = :k');
                $existing->execute([':k' => $dedupe]);
                if (($row = $existing->fetch(\PDO::FETCH_ASSOC)) !== false) {
                    return $this->result(true, true, false, (int) $row['id'], (string) $row['code'], (string) $row['priority'], (int) $row['needs_review'] === 1);
                }
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        Flusher::request();

        return $this->result(true, false, false, $id, $code, $priority, $needsReview);
    }

    /**
     * @return array<string,mixed>
     */
    public function get(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.nombre AS unit_name FROM tec_requests r
             LEFT JOIN tbl_unidades u ON u.id = r.unidad_id
             WHERE r.id = :id AND r.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Request not found');
        }

        return $row;
    }

    public function changeStatus(int $id, string $to, ?int $actorUserId, ?string $actorLabel = null, ?string $note = null, bool $allowReopen = false): array
    {
        if (!in_array($to, StatusMachine::STATUSES, true)) {
            throw new ValidationException(['status' => 'Unknown status']);
        }
        $this->pdo->beginTransaction();
        try {
            $row = $this->lock($id);
            $from = (string) $row['status'];
            if ($from === $to) {
                $this->pdo->commit();
                return $this->get($id);
            }
            if (!StatusMachine::canMove($from, $to) || ($from === 'completed' && !$allowReopen)) {
                throw new ValidationException(['status' => "Cannot move from {$from} to {$to}"]);
            }
            $now = Clock::nowUtcSql();
            $sets = ['status = :to', 'updated_at = :now'];
            if ($to === 'assigned') {
                $sets[] = 'assigned_at = COALESCE(assigned_at, :now)';
            } elseif ($to === 'in_progress') {
                $sets[] = 'started_at = COALESCE(started_at, :now)';
            } elseif ($to === 'completed') {
                $sets[] = 'completed_at = :now';
            }
            if ($from === 'received') {
                $sets[] = 'next_alert_at = NULL';
            }
            $stmt = $this->pdo->prepare('UPDATE tec_requests SET ' . implode(', ', $sets) . ' WHERE id = :id AND status = :from');
            $stmt->execute([':to' => $to, ':now' => $now, ':id' => $id, ':from' => $from]);
            if ($stmt->rowCount() !== 1) {
                throw new ValidationException(['status' => 'The request changed, reload and try again']);
            }
            $this->record($id, 'status', $from, $to, $actorUserId, $actorLabel, $note);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->get($id);
    }

    public function assign(int $id, ?int $userId, ?int $actorUserId, ?string $note = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->lock($id);
            if (!StatusMachine::isOpen((string) $row['status'])) {
                throw new ValidationException(['status' => 'A closed request cannot be reassigned']);
            }
            $now = Clock::nowUtcSql();
            $from = (string) $row['status'];
            $to = ($from === 'received' && $userId !== null) ? 'assigned' : $from;
            $this->pdo->prepare(
                'UPDATE tec_requests SET assigned_user_id = :u, status = :to, updated_at = :now,
                 assigned_at = CASE WHEN :has = 1 THEN COALESCE(assigned_at, :now2) ELSE assigned_at END,
                 next_alert_at = CASE WHEN :lift = 1 THEN NULL ELSE next_alert_at END
                 WHERE id = :id'
            )->execute([':u' => $userId, ':to' => $to, ':now' => $now, ':has' => $userId !== null ? 1 : 0, ':now2' => $now, ':lift' => $to !== $from ? 1 : 0, ':id' => $id]);
            $this->record($id, 'assigned', $from, $to, $actorUserId, null, $note);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->get($id);
    }

    /**
     * Acknowledges a request from an email link.
     */
    public function acknowledge(int $id, string $label): array
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->lock($id);
            if ($row['status'] === 'received') {
                $now = Clock::nowUtcSql();
                $this->pdo->prepare(
                    'UPDATE tec_requests SET status = "assigned", acknowledged_at = :now, assigned_at = COALESCE(assigned_at, :now2), next_alert_at = NULL, updated_at = :now3 WHERE id = :id'
                )->execute([':now' => $now, ':now2' => $now, ':now3' => $now, ':id' => $id]);
                $this->record($id, 'ack', 'received', 'assigned', null, $label);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->get($id);
    }

    public function addNote(int $id, ?int $actorUserId, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            throw new ValidationException(['note' => 'Note is required']);
        }
        $this->get($id);
        $this->record($id, 'note', null, null, $actorUserId, null, mb_substr($note, 0, 500));
    }

    public function markReviewed(int $id, ?int $actorUserId): void
    {
        $this->get($id);
        $this->pdo->prepare('UPDATE tec_requests SET needs_review = 0, updated_at = :now WHERE id = :id')
            ->execute([':now' => Clock::nowUtcSql(), ':id' => $id]);
        $this->record($id, 'edited', null, null, $actorUserId, null, 'Marked as reviewed');
    }

    /**
     * Open requests of a caller, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function openForCaller(string $e164, int $limit = 3): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT code, summary, status, priority, received_at FROM tec_requests
             WHERE caller_e164 = :p AND status IN ("received","assigned","in_progress") AND deleted_at IS NULL
             ORDER BY received_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([':p' => $e164]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function timeline(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.event, h.from_status, h.to_status, h.actor_label, h.note, h.created_at, h.actor_user_id, u.nombre AS actor_name
             FROM tec_request_history h LEFT JOIN tec_usuarios u ON u.id = h.actor_user_id
             WHERE h.request_id = :id ORDER BY h.id ASC'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function record(int $id, string $event, ?string $from, ?string $to, ?int $actor, ?string $label, ?string $note = null, ?\DateTimeImmutable $at = null): void
    {
        $this->pdo->prepare(
            'INSERT INTO tec_request_history (request_id, event, from_status, to_status, actor_user_id, actor_label, note, created_at)
             VALUES (:id, :event, :from, :to, :actor, :label, :note, :now)'
        )->execute([
            ':id' => $id,
            ':event' => $event,
            ':from' => $from,
            ':to' => $to,
            ':actor' => $actor,
            ':label' => $label !== null ? mb_substr($label, 0, 100) : null,
            ':note' => $note !== null ? mb_substr($note, 0, 500) : null,
            ':now' => ($at ?? Clock::nowUtc())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Queues the first alert to everyone on duty. Falls back to the extra emails when nobody is configured.
     */
    private function enqueueInitialAlerts(int $id, \DateTimeImmutable $now): void
    {
        $request = $this->get($id);
        $recipients = $this->recipientsFor($this->responders->alertTargets($now, $request['unidad_id'] === null ? null : (int) $request['unidad_id']));
        if (!$this->hasDeliverable($recipients)) {
            foreach ($this->settings->emailList('esc.extra_emails') as $email) {
                $recipients[] = ['email' => $email, 'phone' => null, 'lang' => 'en'];
            }
        }
        $this->enqueueFor($request, 'new', $recipients, "req:{$id}:a0", $now);
    }

    /**
     * Whether at least one recipient can actually be reached: an email, or a phone while WhatsApp is enabled.
     *
     * @param list<array{email:?string,phone:?string,lang:string}> $recipients
     */
    public function hasDeliverable(array $recipients): bool
    {
        $whatsapp = Config::bool('whatsapp', 'enabled', false);
        foreach ($recipients as $to) {
            if (!empty($to['email']) || ($whatsapp && !empty($to['phone']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $responders
     * @return list<array{email:?string,phone:?string,lang:string}>
     */
    public function recipientsFor(array $responders): array
    {
        $out = [];
        foreach ($responders as $r) {
            $out[] = ['email' => $r['email'] !== null ? (string) $r['email'] : null, 'phone' => $r['phone_e164'] !== null ? (string) $r['phone_e164'] : null, 'lang' => 'en'];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $request
     * @param list<array{email:?string,phone:?string,lang:string}> $recipients
     */
    public function enqueueFor(array $request, string $kind, array $recipients, string $dedupePrefix, ?\DateTimeImmutable $now = null): int
    {
        $base = rtrim((string) Config::get('app', 'base_url', ''), '/');
        $openUrl = $base . '/requests.php?code=' . rawurlencode((string) $request['code']);
        $whatsapp = Config::bool('whatsapp', 'enabled', false);
        $queued = 0;
        foreach ($recipients as $to) {
            if (!empty($to['email'])) {
                $token = SignedLinks::issue('request_ack', (string) $request['id'], 'ack', null, 'email', 7 * 86400, 10);
                $mail = AlertComposer::email($request, $kind, $base . '/api/ack?t=' . $token, $openUrl);
                $queued += $this->outbox->enqueue('email', (string) $to['email'], 'request_' . $kind, $to['lang'], $mail, (int) $request['id'], $dedupePrefix . ':e:' . strtolower((string) $to['email']), $now) !== null ? 1 : 0;
            }
            if ($whatsapp && !empty($to['phone'])) {
                $queued += $this->outbox->enqueue('whatsapp', (string) $to['phone'], 'pgs_new_request', $to['lang'], ['params' => AlertComposer::whatsappParams($request)], (int) $request['id'], $dedupePrefix . ':w:' . $to['phone'], $now) !== null ? 1 : 0;
            }
        }
        if ($queued > 0) {
            Flusher::request();
        }

        return $queued;
    }

    /**
     * @param array<string,mixed> $in
     * @return array{created:bool,duplicate:bool,limited:bool,id:?int,code:?string,priority:string,needs_review:bool}|null
     */
    private function mergeIntoUrgent(int $callId, string $summary, array $in, ?int $actorUserId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, priority, summary, needs_review, unidad_id FROM tec_requests WHERE call_id = :c AND created_via = "urgent_flow" AND deleted_at IS NULL ORDER BY id LIMIT 1');
        $stmt->execute([':c' => $callId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $unidadId = isset($in['unidad_id']) && (int) $in['unidad_id'] > 0 ? (int) $in['unidad_id'] : null;
        $this->pdo->prepare(
            'UPDATE tec_requests SET summary = :s,
             details = COALESCE(:d, details),
             unidad_id = COALESCE(unidad_id, :u),
             needs_review = IF(COALESCE(unidad_id, :u2) IS NULL, needs_review, 0),
             updated_at = :now WHERE id = :id'
        )->execute([
            ':s' => $summary,
            ':d' => isset($in['details']) && trim((string) $in['details']) !== '' ? mb_substr(trim((string) $in['details']), 0, 4000) : null,
            ':u' => $unidadId,
            ':u2' => $unidadId,
            ':now' => Clock::nowUtcSql(),
            ':id' => (int) $row['id'],
        ]);
        $this->record((int) $row['id'], 'merged', null, null, $actorUserId, 'voice', mb_substr($summary, 0, 200));

        return $this->result(true, true, false, (int) $row['id'], (string) $row['code'], (string) $row['priority'], (int) $row['needs_review'] === 1 && $unidadId === null);
    }

    private function overLimit(?int $callId, string $callerE164, \DateTimeImmutable $now, string $via): bool
    {
        $critical = in_array($via, ['urgent_flow', 'post_call'], true);
        if ($callId !== null && !$critical) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tec_requests WHERE call_id = :c');
            $stmt->execute([':c' => $callId]);
            if ((int) $stmt->fetchColumn() >= self::MAX_PER_CALL) {
                return true;
            }
        }
        if ($callerE164 !== '') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tec_requests WHERE caller_e164 = :p AND received_at >= :since');
            $stmt->execute([':p' => $callerE164, ':since' => $now->modify('-1 hour')->format('Y-m-d H:i:s')]);
            if ((int) $stmt->fetchColumn() >= ($critical ? self::MAX_CRITICAL_PER_CALLER_HOUR : self::MAX_PER_CALLER_HOUR)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function lock(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tec_requests WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Request not found');
        }

        return $row;
    }

    /** @return array{created:bool,duplicate:bool,limited:bool,id:?int,code:?string,priority:string,needs_review:bool} */
    private function result(bool $created, bool $duplicate, bool $limited, ?int $id, ?string $code, string $priority, bool $needsReview): array
    {
        return ['created' => $created, 'duplicate' => $duplicate, 'limited' => $limited, 'id' => $id, 'code' => $code, 'priority' => $priority, 'needs_review' => $needsReview];
    }
}
