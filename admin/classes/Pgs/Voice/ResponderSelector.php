<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\Clock;
use Pgs\Core\Db;

final class ResponderSelector
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Active responders available at the given instant, ordered by priority.
     *
     * @return list<array<string,mixed>>
     */
    public function onDuty(?\DateTimeInterface $at = null): array
    {
        $local = ($at === null ? Clock::nowUtc() : \DateTimeImmutable::createFromInterface($at))->setTimezone(Clock::businessTz());
        $dayBit = 1 << ((int) $local->format('N') - 1);
        $time = $local->format('H:i:s');

        $out = [];
        foreach ($this->active() as $row) {
            if ((((int) $row['days_mask']) & $dayBit) === 0) {
                continue;
            }
            $from = $row['time_from'];
            $to = $row['time_to'];
            if ($from !== null && $to !== null) {
                $within = $from <= $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);
                if (!$within) {
                    continue;
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Picks the transfer destination for a request. For urgent calls on a known property the people in charge of it
     * come first, followed by responders without property assignments.
     *
     * @return array<string,mixed>|null
     */
    public function forTransfer(string $reason, ?string $personName, bool $urgent, ?\DateTimeInterface $at = null, ?int $unidadId = null): ?array
    {
        $onDuty = array_values(array_filter(
            $this->onDuty($at),
            static fn (array $r): bool => (int) $r['receives_transfers'] === 1 && $r['phone_e164'] !== null
        ));
        $scoped = $this->scope($onDuty, $unidadId);

        if ($reason === 'person' && $personName !== null && trim($personName) !== '') {
            $needle = $this->fold($personName);
            if ($needle === '') {
                return null;
            }
            foreach ($onDuty as $row) {
                if (str_contains($this->fold((string) $row['name']), $needle)) {
                    return $row;
                }
            }

            return null;
        }

        if ($reason === 'owner') {
            foreach ($onDuty as $row) {
                if ((int) $row['is_owner'] === 1) {
                    return $row;
                }
            }

            return $urgent ? $this->ownerFallback() : null;
        }

        if ($scoped !== []) {
            return $scoped[0];
        }

        return $urgent ? $this->ownerFallback() : null;
    }

    /** @return array<string,mixed>|null */
    private function ownerFallback(): ?array
    {
        foreach ($this->active() as $row) {
            if ((int) $row['is_owner'] === 1 && (int) $row['receives_transfers'] === 1 && $row['phone_e164'] !== null) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Responders that must be alerted: on duty plus owners, all with alerts enabled.
     * With a known property only its people in charge and responders without assignments are included.
     *
     * @return list<array<string,mixed>>
     */
    public function alertTargets(?\DateTimeInterface $at = null, ?int $unidadId = null): array
    {
        $onDutyIds = array_map(static fn (array $r): int => (int) $r['id'], $this->scope($this->onDuty($at), $unidadId));
        $out = [];
        foreach ($this->active() as $row) {
            if ((int) $row['receives_alerts'] !== 1) {
                continue;
            }
            if (in_array((int) $row['id'], $onDutyIds, true) || (int) $row['is_owner'] === 1) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Keeps the responders in charge of the property first, then the ones with no assignments.
     * Without a property nobody is excluded.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function scope(array $rows, ?int $unidadId): array
    {
        if ($unidadId === null) {
            return $rows;
        }
        $links = [];
        foreach ($this->pdo->query('SELECT responder_id, unidad_id FROM tec_responder_properties')->fetchAll(\PDO::FETCH_ASSOC) as $l) {
            $links[(int) $l['responder_id']][] = (int) $l['unidad_id'];
        }
        $assigned = [];
        $general = [];
        foreach ($rows as $row) {
            $ids = $links[(int) $row['id']] ?? [];
            if ($ids === []) {
                $general[] = $row;
            } elseif (in_array($unidadId, $ids, true)) {
                $assigned[] = $row;
            }
        }

        return array_merge($assigned, $general);
    }

    /** @return list<array<string,mixed>> */
    public function owners(): array
    {
        return array_values(array_filter($this->active(), static fn (array $r): bool => (int) $r['is_owner'] === 1));
    }

    /** @return list<array<string,mixed>> */
    private function active(): array
    {
        return $this->pdo->query(
            'SELECT * FROM tec_responders WHERE is_active = 1 AND deleted_at IS NULL ORDER BY priority_order ASC, id ASC'
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fold(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return strtolower(trim((string) preg_replace('/[^a-z0-9 ]+/i', '', $ascii === false ? $value : $ascii)));
    }
}
