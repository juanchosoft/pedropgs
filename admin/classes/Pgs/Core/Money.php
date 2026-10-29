<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Core\Exceptions\ValidationException;

final class Money
{
    /**
     * Normaliza un importe a un string decimal con exactamente $maxDecimals decimales.
     */
    public static function normalize(mixed $v, int $maxDecimals = 2): string
    {
        if (is_int($v)) {
            $v = (string) $v;
        } elseif (is_float($v)) {
            $v = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
        }
        if (!is_string($v)) {
            throw new ValidationException(['amount' => 'Invalid amount']);
        }
        $v = trim($v);
        if (!preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            throw new ValidationException(['amount' => 'Invalid amount']);
        }
        $decimals = str_contains($v, '.') ? strlen(substr($v, strpos($v, '.') + 1)) : 0;
        if ($decimals > $maxDecimals) {
            throw new ValidationException(['amount' => 'Too many decimals']);
        }
        [$int, $frac] = array_pad(explode('.', $v, 2), 2, '');
        $negative = str_starts_with($int, '-');
        $int = ltrim($negative ? substr($int, 1) : $int, '0');
        $int = $int === '' ? '0' : $int;
        $frac = str_pad($frac, $maxDecimals, '0');
        $out = $maxDecimals > 0 ? $int . '.' . $frac : $int;
        return ($negative && trim($out, '0.') !== '' ? '-' : '') . $out;
    }

    public static function add(string $a, string $b): string
    {
        $scale = max(self::scaleOf($a), self::scaleOf($b));
        $sum = self::toUnits($a, $scale) + self::toUnits($b, $scale);
        return self::fromUnits($sum, $scale);
    }

    /**
     * Multiplica dos decimales y redondea half-up a 2 decimales.
     */
    public static function mul(string $a, string $b): string
    {
        $sa = self::scaleOf($a);
        $sb = self::scaleOf($b);
        $product = self::toUnits($a, $sa) * self::toUnits($b, $sb);
        $scale = $sa + $sb;
        if ($scale > 2) {
            $divisor = 10 ** ($scale - 2);
            $abs = intdiv(abs($product) * 2 + $divisor, $divisor * 2);
            $product = $product < 0 ? -$abs : $abs;
            $scale = 2;
        } elseif ($scale < 2) {
            $product *= 10 ** (2 - $scale);
            $scale = 2;
        }
        return self::fromUnits($product, $scale);
    }

    private static function scaleOf(string $v): int
    {
        return str_contains($v, '.') ? strlen(substr($v, strpos($v, '.') + 1)) : 0;
    }

    private static function toUnits(string $v, int $scale): int
    {
        $negative = str_starts_with($v, '-');
        $v = ltrim($v, '-');
        [$int, $frac] = array_pad(explode('.', $v, 2), 2, '');
        $units = (int) ($int . str_pad($frac, $scale, '0'));
        return $negative ? -$units : $units;
    }

    private static function fromUnits(int $units, int $scale): string
    {
        $negative = $units < 0;
        $digits = str_pad((string) abs($units), $scale + 1, '0', STR_PAD_LEFT);
        $out = $scale > 0 ? substr($digits, 0, -$scale) . '.' . substr($digits, -$scale) : $digits;
        return ($negative ? '-' : '') . $out;
    }
}
