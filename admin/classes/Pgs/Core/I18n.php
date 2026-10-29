<?php

declare(strict_types=1);

namespace Pgs\Core;

final class I18n
{
    /** @var array<string,array<string,string>> */
    private static array $cache = [];

    /**
     * @param array<string,scalar> $params
     */
    public static function t(string $key, string $lang, array $params = []): string
    {
        $text = self::load($lang)[$key] ?? self::load('en')[$key] ?? $key;
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    /**
     * @return array<string,string>
     */
    private static function load(string $lang): array
    {
        $lang = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'en';
        if (!isset(self::$cache[$lang])) {
            $file = __DIR__ . '/i18n/' . $lang . '.php';
            self::$cache[$lang] = is_file($file) ? (array) require $file : [];
        }
        return self::$cache[$lang];
    }
}
