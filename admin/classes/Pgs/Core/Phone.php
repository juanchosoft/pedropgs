<?php

declare(strict_types=1);

namespace Pgs\Core;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class Phone
{
    public static function toE164(?string $raw, ?string $region = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $region = $region ?? (string) Config::get('voice', 'default_region', 'US');
        $util = PhoneNumberUtil::getInstance();
        try {
            $number = $util->parse(trim($raw), $region);
        } catch (NumberParseException) {
            return null;
        }
        if (!$util->isValidNumber($number)) {
            return null;
        }
        return $util->format($number, PhoneNumberFormat::E164);
    }

    public static function normalize(?string $raw, ?string $region = null): ?string
    {
        return self::toE164($raw, $region);
    }

    public static function mask(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $e164) ?? '';
        if (strlen($digits) < 5) {
            return '•••';
        }
        $util = PhoneNumberUtil::getInstance();
        $cc = '';
        try {
            $cc = (string) $util->parse($e164, null)->getCountryCode();
        } catch (NumberParseException) {
            $cc = substr($digits, 0, 1);
        }
        return '+' . $cc . ' ••• ••• ' . substr($digits, -4);
    }
}
