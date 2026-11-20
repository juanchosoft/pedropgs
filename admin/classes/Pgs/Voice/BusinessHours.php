<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\Clock;
use Pgs\Responders\SettingsRepository;

final class BusinessHours
{
    private const DAYS = [1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat', 7 => 'sun'];

    private SettingsRepository $settings;

    public function __construct(?SettingsRepository $settings = null)
    {
        $this->settings = $settings ?? new SettingsRepository();
    }

    public function isOpen(?\DateTimeInterface $at = null): bool
    {
        $local = $this->local($at);
        $window = $this->windowFor($local);
        if ($window === null) {
            return false;
        }
        $time = $local->format('H:i');

        return $time >= $window[0] && $time < $window[1];
    }

    /**
     * First opening instant strictly after $after, in UTC.
     */
    public function nextOpen(?\DateTimeInterface $after = null): \DateTimeImmutable
    {
        $local = $this->local($after);
        for ($i = 0; $i < 8; $i++) {
            $day = $local->modify("+{$i} day");
            $window = $this->windowFor($day);
            if ($window === null) {
                continue;
            }
            [$h, $m] = array_map('intval', explode(':', $window[0]));
            $open = $day->setTime($h, $m, 0);
            if ($open > $local) {
                return $open->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        return $local->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'));
    }

    private function local(?\DateTimeInterface $at): \DateTimeImmutable
    {
        $base = $at === null ? Clock::nowUtc() : \DateTimeImmutable::createFromInterface($at);

        return $base->setTimezone(Clock::businessTz());
    }

    /** @return array{0:string,1:string}|null */
    private function windowFor(\DateTimeImmutable $local): ?array
    {
        $decoded = json_decode($this->settings->get('hours.schedule', ''), true);
        $entry = (is_array($decoded) ? $decoded : [])[self::DAYS[(int) $local->format('N')]] ?? null;
        if (!is_array($entry) || count($entry) < 2) {
            return null;
        }

        return [(string) $entry[0], (string) $entry[1]];
    }
}
