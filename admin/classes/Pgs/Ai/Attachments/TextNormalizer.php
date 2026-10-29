<?php

declare(strict_types=1);

namespace Pgs\Ai\Attachments;

use Pgs\Core\Config;

final class TextNormalizer
{
    /**
     * Normalizes text input to clean UTF-8, strips BOM and control chars, truncates to limit.
     */
    public static function normalize(string $text, ?int $maxChars = null): array
    {
        $max = $maxChars ?? (int) Config::get('ia_attachments', 'text_max_chars', 200000);

        // Strip UTF-8 BOM first and ensure UTF-8 encoding
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
            $encoding = 'UTF-8';
        } else {
            $encoding = mb_detect_encoding($text, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'UTF-8';
        }

        if ($encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $encoding);
        }

        // Strip control characters except newline (\n), carriage return (\r) and tab (\t)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        if ($text === null) {
            $text = '';
        }

        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        $truncated = false;
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max) . "\n[truncated]";
            $truncated = true;
        }

        return [
            'text' => $text,
            'chars' => mb_strlen($text),
            'truncated' => $truncated,
        ];
    }

    public static function clean(string $text, ?int $maxChars = null): string
    {
        return self::normalize($text, $maxChars)['text'];
    }
}
