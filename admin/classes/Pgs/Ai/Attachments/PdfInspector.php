<?php

declare(strict_types=1);

namespace Pgs\Ai\Attachments;

use Pgs\Core\Exceptions\ValidationException;

final class PdfInspector
{
    /**
     * Inspects a PDF file for page count and encryption.
     *
     * @return array{pages: int, encrypted: bool}
     * @throws ValidationException
     */
    public static function inspect(string $path): array
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new ValidationException('PDF file not found or unreadable.');
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            throw new ValidationException('Unable to open PDF file.');
        }

        $header = fread($handle, 1024);
        if ($header === false || !str_starts_with($header, '%PDF-')) {
            fclose($handle);
            throw new ValidationException('File is not a valid PDF document (missing %PDF- header).');
        }

        $encrypted = false;
        $pageCount = 0;

        // Read through file in 128KB chunks
        rewind($handle);
        $tail = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 131072);
            if ($chunk === false) {
                break;
            }
            $searchBlock = $tail . $chunk;

            if (!$encrypted && preg_match('/\/Encrypt\b/', $searchBlock)) {
                $encrypted = true;
            }

            // Count /Type /Page (excluding /Pages)
            $matches = [];
            $count = preg_match_all('/\/Type\s*\/Page(?!s)\b/', $searchBlock, $matches);
            if ($count !== false) {
                $pageCount += $count;
            }

            $tail = substr($chunk, -256);
        }
        fclose($handle);

        // Fallback: if regex gave 0 pages, check for /Count in page tree
        if ($pageCount === 0) {
            $content = (string) @file_get_contents($path);
            if (preg_match('/\/Type\s*\/Pages\b[^\/]*\/Count\s+(\d+)/s', $content, $m)) {
                $pageCount = (int) $m[1];
            } else {
                $pageCount = 1; // Default minimum
            }
        }

        return [
            'valid' => true,
            'pages' => $pageCount,
            'encrypted' => $encrypted,
        ];
    }
}
