<?php

declare(strict_types=1);

namespace Pgs\Core;

final class Json
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return mixed
     */
    public static function decode(string $json): mixed
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function decodeArray(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        try {
            $value = self::decode($json);
        } catch (\JsonException) {
            return null;
        }
        return is_array($value) ? $value : null;
    }
}
