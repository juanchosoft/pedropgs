<?php

declare(strict_types=1);

namespace Pgs\Core;

final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $ms = (int) floor(microtime(true) * 1000);
        $time = '';
        for ($i = 0; $i < 10; $i++) {
            $time = self::ALPHABET[$ms % 32] . $time;
            $ms = intdiv($ms, 32);
        }
        $bytes = random_bytes(10);
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $random = '';
        foreach (str_split($bits, 5) as $chunk) {
            $random .= self::ALPHABET[bindec($chunk)];
        }
        return $time . $random;
    }
}
