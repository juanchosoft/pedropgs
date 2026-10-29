<?php

declare(strict_types=1);

namespace Pgs\Availability;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;

final class AvailabilityService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function workersForUnit(Principal $p, int $unitId): array
    {
        UnitScope::assertUnit($p, $unitId);

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT e.id, e.nombre, e.email, e.celular
             FROM tec_worker_schedule s
             JOIN tec_employee e ON e.id = s.employee_id
             WHERE s.unidad_id = :u AND s.is_active = 1 AND e.enable = 'si'
             ORDER BY e.nombre ASC"
        );
        $stmt->execute([':u' => $unitId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$p->isSystemAdmin() && !$p->can('disponibilidad.schedule.manage')) {
            $rows = array_map(static fn (array $r) => ['id' => $r['id'], 'nombre' => $r['nombre']], $rows);
        }

        return $rows;
    }

    public function forEmployee(Principal $p, int $employeeId, int $unitId, string $from, string $to): array
    {
        UnitScope::assertUnit($p, $unitId);

        $d1 = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $d2 = \DateTimeImmutable::createFromFormat('Y-m-d', $to);
        if (!$d1 || !$d2 || $d1 > $d2) {
            return [];
        }

        $results = [];
        $cur = $d1;
        while ($cur <= $d2) {
            $dateStr = $cur->format('Y-m-d');
            $am = self::isFree($this->pdo, $employeeId, $unitId, $dateStr, 'am');
            $pm = self::isFree($this->pdo, $employeeId, $unitId, $dateStr, 'pm');
            $full = self::isFree($this->pdo, $employeeId, $unitId, $dateStr, 'full');

            $results[$dateStr] = [
                'date' => $dateStr,
                'am' => $am,
                'pm' => $pm,
                'full' => $full,
            ];

            $cur = $cur->modify('+1 day');
        }

        return $results;
    }

    public static function isFree(\PDO $pdo, int $employeeId, int $unitId, string $date, string $part): array
    {
        $targetDate = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$targetDate) {
            return ['free' => false, 'reason' => 'invalid_date'];
        }

        $now = Clock::nowUtc();
        $today = new \DateTimeImmutable($now->format('Y-m-d'));
        if ($targetDate < $today) {
            return ['free' => false, 'reason' => 'past'];
        }

        $horizonDays = (int) Config::get('voice', 'reservation_horizon_days', 60);
        $maxDate = $today->modify("+{$horizonDays} days");
        if ($targetDate > $maxDate) {
            return ['free' => false, 'reason' => 'too_far'];
        }

        $holidays = LeaveDayCounter::loadHolidays();
        if (isset($holidays[$date])) {
            return ['free' => false, 'reason' => 'holiday'];
        }

        // 2. Schedule check for unit
        $weekday = (int) $targetDate->format('N'); // 1 = Mon .. 7 = Sun
        $schedStmt = $pdo->prepare(
            'SELECT day_part FROM tec_worker_schedule
             WHERE employee_id = :e AND unidad_id = :u AND weekday = :w AND is_active = 1
               AND valid_from <= :d AND (valid_to IS NULL OR valid_to >= :d)'
        );
        $schedStmt->execute([
            ':e' => $employeeId,
            ':u' => $unitId,
            ':w' => $weekday,
            ':d' => $date,
        ]);
        $schedules = $schedStmt->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($schedules)) {
            return ['free' => false, 'reason' => 'not_scheduled'];
        }

        $covered = false;
        foreach ($schedules as $schedPart) {
            if ($schedPart === 'full' || $schedPart === $part) {
                $covered = true;
                break;
            }
        }
        if (!$covered) {
            return ['free' => false, 'reason' => 'not_scheduled'];
        }

        // 3. Approved absence check
        $absStmt = $pdo->prepare(
            'SELECT day_part FROM tec_worker_absences
             WHERE employee_id = :e AND status = "approved"
               AND date_from <= :d AND date_to >= :d'
        );
        $absStmt->execute([':e' => $employeeId, ':d' => $date]);
        $absParts = $absStmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($absParts as $absPart) {
            if ($absPart === 'full' || $absPart === $part || $part === 'full') {
                return ['free' => false, 'reason' => 'absence'];
            }
        }

        // 4. Confirmed / fulfilled reservation check across any property
        $rsvStmt = $pdo->prepare(
            'SELECT day_part FROM tec_worker_reservations
             WHERE employee_id = :e AND work_date = :d AND status IN ("confirmed","fulfilled")'
        );
        $rsvStmt->execute([':e' => $employeeId, ':d' => $date]);
        $rsvParts = $rsvStmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($rsvParts as $rsvPart) {
            if ($rsvPart === 'full' || $rsvPart === $part || $part === 'full') {
                return ['free' => false, 'reason' => 'booked'];
            }
        }

        return ['free' => true, 'reason' => null];
    }

    public static function alternatives(\PDO $pdo, int $employeeId, int $unitId, string $fromDate, string $part, int $n = 5): array
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $fromDate);
        if (!$d) {
            return [];
        }

        $alts = [];
        $cur = $d->modify('+1 day');
        $maxTries = 45;

        while (count($alts) < $n && $maxTries-- > 0) {
            $dateStr = $cur->format('Y-m-d');
            $check = self::isFree($pdo, $employeeId, $unitId, $dateStr, $part);
            if (!empty($check['free'])) {
                $alts[] = $dateStr;
            }
            $cur = $cur->modify('+1 day');
        }

        return $alts;
    }
}
