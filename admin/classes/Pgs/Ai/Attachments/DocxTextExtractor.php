<?php

declare(strict_types=1);

namespace Pgs\Ai\Attachments;

use Pgs\Core\Config;
use Pgs\Core\Exceptions\ValidationException;
use XMLReader;
use ZipArchive;

final class DocxTextExtractor
{
    private int $maxEntries;
    private int $maxDocumentXmlBytes;
    private int $maxTotalUncompressed;
    private int $maxRatio;
    private int $maxChars;

    public function __construct(array $limits = [])
    {
        $this->maxEntries = (int) ($limits['docx_max_entries'] ?? Config::get('ia_attachments', 'docx_max_entries', 2000));
        $this->maxDocumentXmlBytes = (int) ($limits['docx_max_document_xml_bytes'] ?? Config::get('ia_attachments', 'docx_max_document_xml_bytes', 20971520));
        $this->maxTotalUncompressed = (int) ($limits['docx_max_total_uncompressed'] ?? Config::get('ia_attachments', 'docx_max_total_uncompressed', 62914560));
        $this->maxRatio = (int) ($limits['docx_max_ratio'] ?? Config::get('ia_attachments', 'docx_max_ratio', 100));
        $this->maxChars = (int) ($limits['text_max_chars'] ?? Config::get('ia_attachments', 'text_max_chars', 200000));
    }

    /**
     * Extracts text from a .docx document using ZipArchive and XMLReader.
     *
     * @return array{text: string, chars: int, truncated: bool, has_images: bool}
     * @throws ValidationException
     */
    public function extract(string $path): array
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw new ValidationException('Word file not found or unreadable.');
        }

        $zip = new ZipArchive();
        $res = $zip->open($path, ZipArchive::RDONLY);
        if ($res !== true) {
            throw new ValidationException('Invalid Word file.');
        }

        try {
            $numFiles = $zip->numFiles;
            if ($numFiles > $this->maxEntries) {
                throw new ValidationException("Word file exceeds entry limit ({$numFiles} > {$this->maxEntries}).");
            }

            $totalUncompressed = 0;
            $hasImages = false;
            $hasContentTypes = false;
            $hasDocumentXml = false;

            for ($i = 0; $i < $numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) {
                    continue;
                }

                $name = $stat['name'];
                // Path traversal and null byte protection
                if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\') || str_contains($name, "\0")) {
                    throw new ValidationException('Word file rejected for suspicious archive entry names.');
                }

                $size = (int) $stat['size'];
                $compSize = (int) $stat['comp_size'];
                $totalUncompressed += $size;

                if ($totalUncompressed > $this->maxTotalUncompressed) {
                    throw new ValidationException('Word file exceeds maximum uncompressed size limit.');
                }

                if ($compSize > 0 && ($size / $compSize) > $this->maxRatio) {
                    throw new ValidationException('Word file rejected due to suspicious compression ratio.');
                }

                if (str_starts_with($name, 'word/media/')) {
                    $hasImages = true;
                }

                if ($name === '[Content_Types].xml') {
                    $hasContentTypes = true;
                } elseif ($name === 'word/document.xml') {
                    $hasDocumentXml = true;
                    if ($size > $this->maxDocumentXmlBytes) {
                        throw new ValidationException('Word document XML exceeds 20 MB size limit.');
                    }
                }
            }

            if (!$hasContentTypes || !$hasDocumentXml) {
                throw new ValidationException('Invalid Word file: missing standard OpenXML document structure.');
            }

            $textParts = [];

            // Primary body
            $mainText = $this->extractXmlText($zip, 'word/document.xml');
            if ($mainText !== '') {
                $textParts[] = $mainText;
            }

            // Headers & Footers
            for ($i = 0; $i < $numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) {
                    continue;
                }
                $name = $stat['name'];
                if (preg_match('/^word\/(header\d+|footer\d+|footnotes|endnotes)\.xml$/', $name)) {
                    $secText = $this->extractXmlText($zip, $name);
                    if ($secText !== '') {
                        $label = ucfirst(basename($name, '.xml'));
                        $textParts[] = "--- {$label} ---\n" . $secText;
                    }
                }
            }

            $fullText = implode("\n\n", $textParts);

            // Collapse multiple blank lines
            $fullText = preg_replace("/\n{3,}/", "\n\n", $fullText);
            $fullText = preg_replace("/\s*\n\s*\|\s*/", " | ", $fullText);
            $fullText = trim((string) $fullText);

            $truncated = false;
            if (mb_strlen($fullText) > $this->maxChars) {
                $fullText = mb_substr($fullText, 0, $this->maxChars) . "\n[truncated]";
                $truncated = true;
            }

            if ($hasImages) {
                $fullText = "[Note: embedded images in this Word file are not read; attach them separately if needed]\n\n" . $fullText;
            }

            return [
                'text' => $fullText,
                'chars' => mb_strlen($fullText),
                'truncated' => $truncated,
                'has_images' => $hasImages,
            ];
        } finally {
            $zip->close();
        }
    }

    private function extractXmlText(ZipArchive $zip, string $entryName): string
    {
        $stream = $zip->getStream($entryName);
        if (!$stream) {
            return '';
        }

        $xml = '';
        $bytesRead = 0;
        while (!feof($stream) && $bytesRead < $this->maxDocumentXmlBytes) {
            $chunk = fread($stream, 65536);
            if ($chunk === false) {
                break;
            }
            $xml .= $chunk;
            $bytesRead += strlen($chunk);
        }
        fclose($stream);

        // Security inspection: reject DTD / external entities (XXE)
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new ValidationException('Word file rejected for security reasons.');
        }

        $reader = new XMLReader();
        if (!$reader->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return '';
        }

        $out = '';
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $tag = $reader->localName;
                if ($tag === 't') {
                    $out .= $reader->readString();
                } elseif ($tag === 'tab') {
                    $out .= "\t";
                } elseif ($tag === 'br' || $tag === 'cr') {
                    $out .= "\n";
                } elseif ($tag === 'del') {
                    // Skip deleted text revisions
                    $reader->next();
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                $tag = $reader->localName;
                if ($tag === 'p') {
                    $out .= "\n";
                } elseif ($tag === 'tc') {
                    $out .= " | ";
                } elseif ($tag === 'tr') {
                    $out .= "\n";
                }
            }
        }

        $reader->close();
        return trim($out);
    }

    public static function extractText(string $path): string
    {
        $res = (new self())->extract($path);
        return $res['text'];
    }
}
