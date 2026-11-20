<?php

declare(strict_types=1);

namespace Pgs\Responders;

use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Phone;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;

final class ResponderRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT * FROM tec_responders WHERE deleted_at IS NULL ORDER BY priority_order ASC, id ASC'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $links = $this->links();
        foreach ($rows as &$row) {
            $row['unidad_ids'] = $links[(int) $row['id']] ?? [];
        }

        return $rows;
    }

    /**
     * Property ids each responder is in charge of, keyed by responder id.
     *
     * @return array<int,list<int>>
     */
    public function links(): array
    {
        $out = [];
        foreach ($this->pdo->query('SELECT responder_id, unidad_id FROM tec_responder_properties ORDER BY unidad_id ASC')->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['responder_id']][] = (int) $r['unidad_id'];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tec_responders WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new NotFoundException('Responder not found');
        }
        $row['unidad_ids'] = $this->links()[$id] ?? [];

        return $row;
    }

    /**
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public function save(array $in, ?int $id = null): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['name' => 'Name is required']);
        }
        $phone = null;
        $rawPhone = trim((string) ($in['phone'] ?? $in['phone_e164'] ?? ''));
        if ($rawPhone !== '') {
            $phone = Phone::toE164($rawPhone, 'US');
            if ($phone === null) {
                throw new ValidationException(['phone' => 'Phone number is not valid']);
            }
        }
        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['email' => 'Email is not valid']);
        }
        $receivesTransfers = !empty($in['receives_transfers']) ? 1 : 0;
        if ($receivesTransfers === 1 && $phone === null) {
            throw new ValidationException(['phone' => 'A phone number is required to receive transfers']);
        }
        $receivesAlerts = array_key_exists('receives_alerts', $in) ? (!empty($in['receives_alerts']) ? 1 : 0) : 1;
        if ($receivesAlerts === 1 && $email === '' && $phone === null) {
            throw new ValidationException(['email' => 'An email or phone is required to receive alerts']);
        }
        $from = $this->time($in['time_from'] ?? null);
        $to = $this->time($in['time_to'] ?? null);
        if (($from === null) !== ($to === null)) {
            throw new ValidationException(['time_from' => 'Set both start and end time, or leave both empty']);
        }
        $mask = isset($in['days_mask']) ? max(0, min(127, (int) $in['days_mask'])) : 127;
        $userId = isset($in['user_id']) && $in['user_id'] !== '' ? (int) $in['user_id'] : null;

        $params = [
            ':user_id' => $userId,
            ':name' => mb_substr($name, 0, 120),
            ':phone' => $phone,
            ':email' => $email !== '' ? mb_substr(strtolower($email), 0, 190) : null,
            ':priority' => isset($in['priority_order']) ? (int) $in['priority_order'] : 100,
            ':transfers' => $receivesTransfers,
            ':alerts' => $receivesAlerts,
            ':owner' => !empty($in['is_owner']) ? 1 : 0,
            ':mask' => $mask,
            ':from' => $from,
            ':to' => $to,
            ':active' => array_key_exists('is_active', $in) ? (!empty($in['is_active']) ? 1 : 0) : 1,
            ':now' => Clock::nowUtcSql(),
        ];

        if ($id === null) {
            $this->pdo->prepare(
                'INSERT INTO tec_responders
                 (user_id, name, phone_e164, email, priority_order, receives_transfers, receives_alerts, is_owner, days_mask, time_from, time_to, is_active, created_at, updated_at)
                 VALUES (:user_id, :name, :phone, :email, :priority, :transfers, :alerts, :owner, :mask, :from, :to, :active, :now, :now)'
            )->execute($params);
            $id = (int) $this->pdo->lastInsertId();
        } else {
            $this->find($id);
            $params[':id'] = $id;
            $this->pdo->prepare(
                'UPDATE tec_responders SET user_id = :user_id, name = :name, phone_e164 = :phone, email = :email,
                 priority_order = :priority, receives_transfers = :transfers, receives_alerts = :alerts, is_owner = :owner,
                 days_mask = :mask, time_from = :from, time_to = :to, is_active = :active, updated_at = :now
                 WHERE id = :id'
            )->execute($params);
        }

        if (array_key_exists('unidad_ids', $in)) {
            $this->syncProperties($id, $this->idList($in['unidad_ids']));
        }

        return $this->find($id);
    }

    /**
     * @param list<int> $unidadIds
     */
    private function syncProperties(int $responderId, array $unidadIds): void
    {
        $this->pdo->prepare('DELETE FROM tec_responder_properties WHERE responder_id = :id')->execute([':id' => $responderId]);
        $insert = $this->pdo->prepare('INSERT IGNORE INTO tec_responder_properties (responder_id, unidad_id) VALUES (:r, :u)');
        foreach ($unidadIds as $unidadId) {
            $insert->execute([':r' => $responderId, ':u' => $unidadId]);
        }
    }

    /** @return list<int> */
    private function idList(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function delete(int $id): void
    {
        $this->find($id);
        $this->pdo->prepare('UPDATE tec_responders SET deleted_at = :now, is_active = 0, updated_at = :now WHERE id = :id')
            ->execute([':now' => Clock::nowUtcSql(), ':id' => $id]);
    }

    private function time(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $value)) {
            throw new ValidationException(['time_from' => 'Time must be HH:MM']);
        }

        return substr($value, 0, 5) . ':00';
    }
}
