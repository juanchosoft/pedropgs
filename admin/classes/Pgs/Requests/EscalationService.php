<?php

declare(strict_types=1);

namespace Pgs\Requests;

use Pgs\Core\AppLog;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Notifications\AlertComposer;
use Pgs\Notifications\Flusher;
use Pgs\Notifications\OutboxRepository;
use Pgs\Responders\SettingsRepository;
use Pgs\Voice\BusinessHours;
use Pgs\Voice\ResponderSelector;

final class EscalationService
{
    private const BATCH = 50;
    private const MAX_INDIVIDUAL_PER_RECIPIENT = 5;

    private \PDO $pdo;
    private SettingsRepository $settings;
    private ResponderSelector $responders;
    private BusinessHours $hours;
    private RequestService $requests;
    private OutboxRepository $outbox;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->settings = new SettingsRepository($this->pdo);
        $this->responders = new ResponderSelector($this->pdo);
        $this->hours = new BusinessHours($this->settings);
        $this->requests = new RequestService($this->pdo, $this->settings, $this->responders);
        $this->outbox = new OutboxRepository($this->pdo);
    }

    /**
     * @return array{alerted:int,escalated:int,deferred:int,exhausted:int,digests:int}
     */
    public function tick(?\DateTimeImmutable $now = null): array
    {
        $now ??= Clock::nowUtc();
        $stats = ['alerted' => 0, 'escalated' => 0, 'deferred' => 0, 'exhausted' => 0, 'digests' => 0];

        $stmt = $this->pdo->prepare(
            "SELECT id FROM tec_requests
             WHERE status = 'received' AND deleted_at IS NULL AND next_alert_at IS NOT NULL AND next_alert_at <= :now
             ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), next_alert_at ASC
             LIMIT " . self::BATCH
        );
        $stmt->execute([':now' => $now->format('Y-m-d H:i:s')]);

        $counts = [];
        $overflow = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            try {
                $this->processOne((int) $id, $now, $stats, $counts, $overflow);
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                AppLog::error('escalation', $e, ['request_id' => (int) $id]);
            }
        }

        $stats['digests'] = $this->sendDigests($overflow, $now);

        return $stats;
    }

    /**
     * @param array<string,int> $stats
     * @param array<string,int> $counts
     * @param array<string,array{recipient:array<string,mixed>,requests:list<array<string,mixed>>}> $overflow
     */
    private function processOne(int $id, \DateTimeImmutable $now, array &$stats, array &$counts, array &$overflow): void
    {
        $this->pdo->beginTransaction();
        $row = $this->pdo->prepare('SELECT * FROM tec_requests WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
        $row->execute([':id' => $id]);
        $request = $row->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($request) || $request['status'] !== 'received' || $request['next_alert_at'] === null || (string) $request['next_alert_at'] > $now->format('Y-m-d H:i:s')) {
            $this->pdo->rollBack();
            return;
        }

        $urgent = $request['priority'] === 'urgent';
        $remind = $urgent ? $this->settings->int('esc.urgent_remind_min', 5) : $this->settings->int('esc.remind_min', 15);
        $escalateAfter = $urgent ? $this->settings->int('esc.urgent_escalate_min', 10) : $this->settings->int('esc.escalate_min', 30);
        $repeat = max(1, $this->settings->int('esc.repeat_min', 30));
        $maxRepeats = $this->settings->int('esc.max_repeats', 3);
        $nowSql = $now->format('Y-m-d H:i:s');

        if ((int) $request['alerts_sent'] >= $maxRepeats) {
            $this->pdo->prepare('UPDATE tec_requests SET next_alert_at = NULL, updated_at = :now WHERE id = :id')->execute([':now' => $nowSql, ':id' => $id]);
            $this->writeHistory($id, 'exhausted', 'Alert limit reached without acknowledgement', $now);
            $this->requests->enqueueFor($this->requests->get($id), 'escalation', $this->finalRecipients(), "req:{$id}:final", $now);
            $this->pdo->commit();
            $stats['exhausted']++;
            return;
        }

        if (!$urgent && !$this->hours->isOpen($now)) {
            $next = $this->hours->nextOpen($now)->modify("+{$remind} minutes")->format('Y-m-d H:i:s');
            $this->pdo->prepare('UPDATE tec_requests SET next_alert_at = :next, updated_at = :now WHERE id = :id')->execute([':next' => $next, ':now' => $nowSql, ':id' => $id]);
            $this->pdo->commit();
            $stats['deferred']++;
            return;
        }

        $ageMinutes = (int) floor(($now->getTimestamp() - strtotime((string) $request['received_at'] . ' UTC')) / 60);
        $escalate = $ageMinutes >= $escalateAfter;
        $recipients = $escalate ? $this->escalationRecipients() : $this->requests->recipientsFor($this->responders->alertTargets($now, $request['unidad_id'] === null ? null : (int) $request['unidad_id']));
        if (!$this->requests->hasDeliverable($recipients)) {
            foreach ($this->settings->emailList('esc.extra_emails') as $email) {
                $recipients[] = ['email' => $email, 'phone' => null, 'lang' => 'en'];
            }
            AppLog::write('warning', 'escalation', 'No recipients configured for request alert', ['request_id' => $id]);
        }

        $full = $this->requests->get($id);
        $attempt = (int) $request['alerts_sent'] + 1;
        $individual = [];
        foreach ($recipients as $to) {
            $key = strtolower((string) ($to['email'] ?? $to['phone'] ?? ''));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            if ($counts[$key] > self::MAX_INDIVIDUAL_PER_RECIPIENT) {
                $overflow[$key]['recipient'] = $to;
                $overflow[$key]['requests'][] = $full;
                continue;
            }
            $individual[] = $to;
        }
        $this->requests->enqueueFor($full, $escalate ? 'escalation' : 'reminder', $individual, "req:{$id}:a{$attempt}", $now);

        $this->pdo->prepare(
            'UPDATE tec_requests SET alerts_sent = alerts_sent + 1, escalation_level = :lvl, last_alert_at = :now,
             next_alert_at = :next, updated_at = :now2 WHERE id = :id'
        )->execute([
            ':lvl' => $escalate ? 2 : 1,
            ':now' => $nowSql,
            ':next' => $now->modify('+' . ($escalate ? $repeat : min($repeat, max(1, $escalateAfter - $ageMinutes))) . ' minutes')->format('Y-m-d H:i:s'),
            ':now2' => $nowSql,
            ':id' => $id,
        ]);
        $this->writeHistory($id, $escalate ? 'escalated' : 'alert', ($escalate ? 'Escalated' : 'Reminder') . ' to ' . count($recipients) . ' recipient(s)', $now);
        $this->pdo->commit();
        $stats[$escalate ? 'escalated' : 'alerted']++;
    }

    /**
     * Who is told that a request ran out of alerts without anyone taking it.
     *
     * @return list<array{email:?string,phone:?string,lang:string}>
     */
    private function finalRecipients(): array
    {
        return $this->escalationRecipients();
    }

    /** @return list<array{email:?string,phone:?string,lang:string}> */
    private function escalationRecipients(): array
    {
        $out = $this->requests->recipientsFor($this->responders->owners());
        foreach ($this->settings->intList('esc.user_ids') as $uid) {
            $stmt = $this->pdo->prepare("SELECT nickname FROM tec_usuarios WHERE id = :id AND habilitado IN ('yes','si')");
            $stmt->execute([':id' => $uid]);
            $email = (string) $stmt->fetchColumn();
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = ['email' => strtolower($email), 'phone' => null, 'lang' => 'en'];
            }
        }
        foreach ($this->settings->emailList('esc.extra_emails') as $email) {
            $out[] = ['email' => $email, 'phone' => null, 'lang' => 'en'];
        }
        $seen = [];

        return array_values(array_filter($out, static function (array $r) use (&$seen): bool {
            $key = strtolower((string) ($r['email'] ?? $r['phone'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }

    /**
     * @param array<string,array{recipient:array<string,mixed>,requests:list<array<string,mixed>>}> $overflow
     */
    private function sendDigests(array $overflow, \DateTimeImmutable $now): int
    {
        $sent = 0;
        $base = rtrim((string) Config::get('app', 'base_url', ''), '/');
        foreach ($overflow as $key => $entry) {
            $email = $entry['recipient']['email'] ?? null;
            if ($email === null) {
                continue;
            }
            $mail = AlertComposer::digest($entry['requests'], $base . '/requests.php');
            $queued = $this->outbox->enqueue('email', (string) $email, 'request_digest', 'en', $mail, null, 'digest:' . $key . ':' . $now->format('YmdHi'), $now);
            if ($queued !== null) {
                $sent++;
                Flusher::request();
            }
        }

        return $sent;
    }

    private function writeHistory(int $id, string $event, string $note, \DateTimeImmutable $now): void
    {
        $this->pdo->prepare(
            'INSERT INTO tec_request_history (request_id, event, actor_label, note, created_at) VALUES (:id, :event, "system", :note, :now)'
        )->execute([':id' => $id, ':event' => $event, ':note' => $note, ':now' => $now->format('Y-m-d H:i:s')]);
    }
}
