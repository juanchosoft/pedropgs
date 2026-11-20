<?php

declare(strict_types=1);

namespace Pgs\Responders;

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Phone;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;

final class VoiceSettingsService
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    private const MINUTE_KEYS = ['esc.remind_min', 'esc.escalate_min', 'esc.urgent_remind_min', 'esc.urgent_escalate_min', 'esc.repeat_min'];

    private \PDO $pdo;
    private SettingsRepository $settings;
    private ResponderRepository $responders;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->settings = new SettingsRepository($this->pdo);
        $this->responders = new ResponderRepository($this->pdo);
    }

    /** @return array<string,mixed> */
    public function overview(): array
    {
        $last = $this->settings->get('cron.last_tick', '');
        $age = $last !== '' ? max(0, Clock::nowUtc()->getTimestamp() - (int) strtotime($last . ' UTC')) : null;
        $count = fn (string $sql): int => (int) $this->pdo->query($sql)->fetchColumn();

        return [
            'cron_age_s' => $age,
            'voice_enabled' => $this->settings->bool('voice_enabled', true),
            'whatsapp_enabled' => Config::bool('whatsapp', 'enabled', false),
            'email_enabled' => Config::bool('smtp_notifications', 'enabled', false),
            'responders_active' => $count('SELECT COUNT(*) FROM tec_responders WHERE is_active = 1 AND deleted_at IS NULL'),
            'transfer_targets' => $count('SELECT COUNT(*) FROM tec_responders WHERE is_active = 1 AND deleted_at IS NULL AND receives_transfers = 1'),
            'alert_targets' => $count('SELECT COUNT(*) FROM tec_responders WHERE is_active = 1 AND deleted_at IS NULL AND receives_alerts = 1'),
            'outbox_pending' => $count("SELECT COUNT(*) FROM tec_outbox WHERE status IN ('pending','sending')"),
            'outbox_failed' => $count("SELECT COUNT(*) FROM tec_outbox WHERE status = 'failed'"),
            'discarded_phones' => $count('SELECT COUNT(*) FROM tec_phone_discards'),
            'calls_today' => $count("SELECT COUNT(*) FROM tec_calls WHERE started_at >= '" . Clock::todayLocal()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "'"),
        ];
    }

    /** @return list<array{id:int,name:string}> */
    public function properties(): array
    {
        $rows = $this->pdo->query("SELECT id, nombre FROM tbl_unidades WHERE enable IN ('yes','si') ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['nombre']], $rows);
    }

    /** @return list<array<string,mixed>> */
    public function listResponders(): array
    {
        return $this->responders->all();
    }

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public function saveResponder(array $in): array
    {
        $id = isset($in['id']) && (int) $in['id'] > 0 ? (int) $in['id'] : null;
        if (isset($in['user_id']) && $in['user_id'] !== '') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tec_usuarios WHERE id = :id');
            $stmt->execute([':id' => (int) $in['user_id']]);
            if ((int) $stmt->fetchColumn() === 0) {
                throw new ValidationException(['user_id' => 'User not found']);
            }
        }

        return $this->responders->save($in, $id);
    }

    public function deleteResponder(int $id): void
    {
        $this->responders->delete($id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function phones(?string $q, ?string $kind): array
    {
        $where = ['1=1'];
        $params = [];
        if (in_array($kind, ['property', 'staff'], true)) {
            $where[] = 'd.kind = :kind';
            $params[':kind'] = $kind;
        }
        $q = trim((string) $q);
        if ($q !== '') {
            $where[] = '(d.phone_e164 LIKE :q1 OR u.nombre LIKE :q2 OR e.nombre LIKE :q3 OR d.label LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
                $params[':' . $k] = '%' . addcslashes($q, '%_\\') . '%';
            }
        }
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.kind, d.unidad_id, d.employee_id, d.phone_e164, d.label, d.source, d.is_active,
                    u.nombre AS unit_name, e.nombre AS employee_name,
                    (SELECT COUNT(*) FROM tec_phone_directory x WHERE x.phone_e164 = d.phone_e164 AND x.is_active = 1 AND x.kind = d.kind) AS shared_with
             FROM tec_phone_directory d
             LEFT JOIN tbl_unidades u ON u.id = d.unidad_id
             LEFT JOIN tec_employee e ON e.id = d.employee_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY d.kind ASC, u.nombre ASC, d.phone_e164 ASC LIMIT 500'
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public function savePhone(array $in): array
    {
        $unitId = (int) ($in['unidad_id'] ?? 0);
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM tbl_unidades WHERE id = :id');
        $exists->execute([':id' => $unitId]);
        if ($unitId <= 0 || (int) $exists->fetchColumn() === 0) {
            throw new ValidationException(['unidad_id' => 'Select a property']);
        }
        $phone = Phone::toE164(trim((string) ($in['phone'] ?? '')), 'US');
        if ($phone === null) {
            throw new ValidationException(['phone' => 'Phone number is not valid']);
        }
        $now = Clock::nowUtcSql();
        $label = mb_substr(trim((string) ($in['label'] ?? '')), 0, 100);
        $this->pdo->prepare(
            'INSERT INTO tec_phone_directory (kind, unidad_id, employee_id, phone_e164, label, source, is_active, created_at, updated_at)
             VALUES ("property", :u, 0, :p, :l, "manual", 1, :n, :n)
             ON DUPLICATE KEY UPDATE is_active = 1, label = IF(VALUES(label) = "", label, VALUES(label)), updated_at = VALUES(updated_at)'
        )->execute([':u' => $unitId, ':p' => $phone, ':l' => $label !== '' ? $label : null, ':n' => $now]);

        $stmt = $this->pdo->prepare('SELECT * FROM tec_phone_directory WHERE kind = "property" AND unidad_id = :u AND employee_id = 0 AND phone_e164 = :p');
        $stmt->execute([':u' => $unitId, ':p' => $phone]);

        return (array) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function setPhoneActive(int $id, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE tec_phone_directory SET is_active = :a, updated_at = :n WHERE id = :id');
        $stmt->execute([':a' => $active ? 1 : 0, ':n' => Clock::nowUtcSql(), ':id' => $id]);
        if ($stmt->rowCount() === 0 && !$this->phoneExists($id)) {
            throw new NotFoundException('Phone not found');
        }
    }

    /**
     * Manual entries are deleted; entries loaded from legacy data are only deactivated.
     */
    public function deletePhone(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT source FROM tec_phone_directory WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $source = $stmt->fetchColumn();
        if ($source === false) {
            throw new NotFoundException('Phone not found');
        }
        if ($source === 'manual') {
            $this->pdo->prepare('DELETE FROM tec_phone_directory WHERE id = :id')->execute([':id' => $id]);
        } else {
            $this->setPhoneActive($id, false);
        }
    }

    /** @return list<array<string,mixed>> */
    public function discards(): array
    {
        return $this->pdo->query(
            'SELECT d.id, d.source_table, d.source_id, d.field_name, d.raw_value, d.reason,
                    u.nombre AS unit_name, e.nombre AS employee_name
             FROM tec_phone_discards d
             LEFT JOIN tbl_unidades u ON d.source_table = "tbl_unidades" AND u.id = d.source_id
             LEFT JOIN tec_employee e ON d.source_table = "tec_employee" AND e.id = d.source_id
             ORDER BY d.source_table ASC, d.source_id ASC LIMIT 500'
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fixes a discarded legacy number: registers a corrected phone for a property and removes the discard.
     *
     * @return array<string,mixed>
     */
    public function assignDiscard(int $id, string $phone, int $unitId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tec_phone_discards WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() === false) {
            throw new NotFoundException('Discarded phone not found');
        }
        $saved = $this->savePhone(['unidad_id' => $unitId, 'phone' => $phone, 'label' => 'corrected']);
        $this->pdo->prepare('DELETE FROM tec_phone_discards WHERE id = :id')->execute([':id' => $id]);

        return $saved;
    }

    public function dismissDiscard(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tec_phone_discards WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new NotFoundException('Discarded phone not found');
        }
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        $users = [];
        foreach ($this->settings->intList('esc.user_ids') as $uid) {
            $stmt = $this->pdo->prepare("SELECT id, TRIM(CONCAT_WS(' ', nombre, apellido)) AS name, nickname FROM tec_usuarios WHERE id = :id");
            $stmt->execute([':id' => $uid]);
            if (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $users[] = ['id' => (int) $row['id'], 'name' => $row['name'] !== '' ? $row['name'] : (string) $row['nickname']];
            }
        }
        $schedule = json_decode($this->settings->get('hours.schedule', ''), true);
        $schedule = is_array($schedule) ? $schedule : [];
        $days = [];
        foreach (self::DAYS as $day) {
            $entry = $schedule[$day] ?? null;
            $days[$day] = is_array($entry) && count($entry) >= 2 ? ['open' => true, 'from' => (string) $entry[0], 'to' => (string) $entry[1]] : ['open' => false, 'from' => '08:00', 'to' => '17:00'];
        }

        return [
            'voice_enabled' => $this->settings->bool('voice_enabled', true),
            'esc.remind_min' => $this->settings->int('esc.remind_min', 15),
            'esc.escalate_min' => $this->settings->int('esc.escalate_min', 30),
            'esc.urgent_remind_min' => $this->settings->int('esc.urgent_remind_min', 5),
            'esc.urgent_escalate_min' => $this->settings->int('esc.urgent_escalate_min', 10),
            'esc.repeat_min' => $this->settings->int('esc.repeat_min', 30),
            'esc.max_repeats' => $this->settings->int('esc.max_repeats', 3),
            'esc.users' => $users,
            'esc.extra_emails' => implode(', ', $this->settings->emailList('esc.extra_emails')),
            'hours' => $days,
            'timezone' => (string) Config::get('app', 'business_timezone', 'America/New_York'),
        ];
    }

    /**
     * @param array<string,mixed> $in
     */
    public function saveSettings(array $in, ?int $userId): void
    {
        $errors = [];
        $values = [];

        foreach (self::MINUTE_KEYS as $key) {
            $raw = $in[$key] ?? null;
            if (!is_numeric($raw) || (int) $raw < 1 || (int) $raw > 1440) {
                $errors[$key] = 'Enter minutes between 1 and 1440';
                continue;
            }
            $values[$key] = (string) (int) $raw;
        }
        $repeats = $in['esc.max_repeats'] ?? null;
        if (!is_numeric($repeats) || (int) $repeats < 0 || (int) $repeats > 20) {
            $errors['esc.max_repeats'] = 'Enter a number between 0 and 20';
        } else {
            $values['esc.max_repeats'] = (string) (int) $repeats;
        }
        if (isset($values['esc.remind_min'], $values['esc.escalate_min']) && (int) $values['esc.escalate_min'] < (int) $values['esc.remind_min']) {
            $errors['esc.escalate_min'] = 'Escalation must not be earlier than the reminder';
        }
        if (isset($values['esc.urgent_remind_min'], $values['esc.urgent_escalate_min']) && (int) $values['esc.urgent_escalate_min'] < (int) $values['esc.urgent_remind_min']) {
            $errors['esc.urgent_escalate_min'] = 'Escalation must not be earlier than the reminder';
        }

        $userIds = [];
        foreach ((array) ($in['esc.user_ids'] ?? []) as $uid) {
            if (!is_numeric($uid) || (int) $uid <= 0) {
                continue;
            }
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_usuarios WHERE id = :id AND habilitado IN ('yes','si')");
            $stmt->execute([':id' => (int) $uid]);
            if ((int) $stmt->fetchColumn() === 0) {
                $errors['esc.user_ids'] = 'One of the selected users is not available';
                break;
            }
            $userIds[] = (int) $uid;
        }

        $emails = [];
        foreach (preg_split('/[\s,;]+/', (string) ($in['esc.extra_emails'] ?? '')) ?: [] as $email) {
            $email = trim($email);
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['esc.extra_emails'] = "Invalid email: {$email}";
                break;
            }
            $emails[] = strtolower($email);
        }

        $schedule = [];
        foreach (self::DAYS as $day) {
            $entry = (array) (($in['hours'] ?? [])[$day] ?? []);
            if (empty($entry['open']) || $entry['open'] === 'false') {
                $schedule[$day] = null;
                continue;
            }
            $from = (string) ($entry['from'] ?? '');
            $to = (string) ($entry['to'] ?? '');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $from) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $to) || $from >= $to) {
                $errors['hours.' . $day] = 'Enter a valid opening and closing time (opening before closing)';
                continue;
            }
            $schedule[$day] = [$from, $to];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $this->settings->set($key, $value, $userId);
            }
            $this->settings->set('voice_enabled', !empty($in['voice_enabled']) && $in['voice_enabled'] !== 'false' ? '1' : '0', $userId);
            $this->settings->set('esc.user_ids', implode(',', array_values(array_unique($userIds))), $userId);
            $this->settings->set('esc.extra_emails', implode(', ', array_values(array_unique($emails))), $userId);
            $this->settings->set('hours.schedule', (string) json_encode($schedule), $userId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function phoneExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tec_phone_directory WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
