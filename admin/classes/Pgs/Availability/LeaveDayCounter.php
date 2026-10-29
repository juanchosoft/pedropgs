<?php

declare(strict_types=1);

namespace Pgs\Availability;

use Pgs\Core\Config;
use Pgs\Core\Exceptions\ValidationException;

final class LeaveDayCounter
{
    /** @var array<string, bool>|null */
    private static ?array $holidays = null;

    /**
     * @return array{days: int, by_year: array<int, int>, excluded: list<array{date: string, reason: string}>}
     */
    public function count(string $dateFrom, string $dateTo): array
    {
        $d1 = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
        $d2 = \DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);

        if (!$d1 || !$d2 || $d1 > $d2) {
            throw new ValidationException('Invalid date range for time off.');
        }

        $countedWeekdaysStr = (string) Config::get('leave', 'counted_weekdays', 'mon,tue,wed,thu,fri');
        $countedWeekdays = array_filter(array_map('trim', explode(',', strtolower($countedWeekdaysStr))));
        $dayMap = [
            1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun',
        ];

        $holidays = self::loadHolidays();

        $count = 0;
        $byYear = [];
        $excluded = [];

        $cur = $d1;
        while ($cur <= $d2) {
            $dateStr = $cur->format('Y-m-d');
            $year = (int) $cur->format('Y');
            $dayOfWeek = (int) $cur->format('N');
            $dayCode = $dayMap[$dayOfWeek] ?? '';

            if (!in_array($dayCode, $countedWeekdays, true)) {
                $excluded[] = ['date' => $dateStr, 'reason' => 'weekend'];
            } elseif (isset($holidays[$dateStr])) {
                $excluded[] = ['date' => $dateStr, 'reason' => 'holiday'];
            } else {
                $count++;
                $byYear[$year] = ($byYear[$year] ?? 0) + 1;
            }

            $cur = $cur->modify('+1 day');
        }

        if ($count === 0) {
            throw new ValidationException('The selected range has no working days.');
        }

        return [
            'days' => $count,
            'by_year' => $byYear,
            'excluded' => $excluded,
        ];
    }

    public static function countBusinessDays(string $dateFrom, string $dateTo): int
    {
        try {
            $res = (new self())->count($dateFrom, $dateTo);
            return $res['days'];
        } catch (ValidationException) {
            return 0;
        }
    }

    /**
     * @return array<string, bool>
     */
    public static function loadHolidays(): array
    {
        if (self::$holidays !== null) {
            return self::$holidays;
        }

        self::$holidays = [];
        $file = (string) Config::get('voice', 'holidays_file', '');
        if ($file === '' || !is_file($file)) {
            return self::$holidays;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return self::$holidays;
        }

        // Support JSON array or line-separated dates
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_string($item) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item)) {
                    self::$holidays[$item] = true;
                } elseif (is_array($item) && !empty($item['date'])) {
                    self::$holidays[(string) $item['date']] = true;
                }
            }
        } else {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed, $m)) {
                    self::$holidays[$m[0]] = true;
                }
            }
        }

        return self::$holidays;
    }

    public static function resetHolidays(): void
    {
        self::$holidays = null;
    }
}
