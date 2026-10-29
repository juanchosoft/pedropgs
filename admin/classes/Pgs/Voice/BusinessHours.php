<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Availability\LeaveDayCounter;
use Pgs\Core\Config;

final class BusinessHours
{
    public static function isOpen(?\DateTimeInterface $dt = null): bool
    {
        $tzName = (string) Config::get('app', 'timezone', 'America/Bogota');
        $tz = new \DateTimeZone($tzName);

        if ($dt === null) {
            $now = new \DateTimeImmutable('now', $tz);
        } else {
            $now = \DateTimeImmutable::createFromInterface($dt)->setTimezone($tz);
        }

        $dow = (int) $now->format('N'); // 1 (Mon) to 7 (Sun)
        if ($dow > 5) {
            return false; // Weekend closed
        }

        $holidays = LeaveDayCounter::loadHolidays();
        if (isset($holidays[$now->format('Y-m-d')])) {
            return false; // Holiday closed
        }

        $timeStr = $now->format('H:i');
        $cfg = (string) Config::get('voice', 'business_hours', 'mon-fri 08:00-17:00');

        $start = '08:00';
        $end = '17:00';
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $cfg, $m)) {
            $start = str_pad($m[1], 5, '0', STR_PAD_LEFT);
            $end = str_pad($m[2], 5, '0', STR_PAD_LEFT);
        }

        return $timeStr >= $start && $timeStr < $end;
    }

    public static function isHoliday(?\DateTimeInterface $dt = null): bool
    {
        $tzName = (string) Config::get('app', 'timezone', 'America/Bogota');
        $tz = new \DateTimeZone($tzName);

        if ($dt === null) {
            $now = new \DateTimeImmutable('now', $tz);
        } else {
            $now = \DateTimeImmutable::createFromInterface($dt)->setTimezone($tz);
        }

        $holidays = LeaveDayCounter::loadHolidays();
        return isset($holidays[$now->format('Y-m-d')]);
    }

    public static function hoursText(string $lang = 'es'): string
    {
        if ($lang === 'en') {
            return 'Monday to Friday, 8:00 AM to 5:00 PM';
        }
        return 'Lunes a Viernes de 8:00 AM a 5:00 PM';
    }
}
