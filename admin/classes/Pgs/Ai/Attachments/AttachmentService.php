<?php

declare(strict_types=1);

namespace Pgs\Ai\Attachments;

use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\RateLimitedException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Ulid;
use ZipArchive;

final class AttachmentService
{
    private AttachmentRepository $repo;

    public function __construct(?AttachmentRepository $repo = null)
    {
        $this->repo = $repo ?? new AttachmentRepository();
    }

    /**
     * Uploads, validates, normalizes, and stores an attachment.
     *
     * @param array $file $_FILES['file'] entry
     * @return array Standardized descriptor of uploaded attachment
     */
    public function upload(Principal $p, array $file): array
    {
        $p->require('ia.adjuntos.use');

        $enabled = (int) Config::get('ia_attachments', 'enabled', 1);
        if ($enabled !== 1) {
            throw new ForbiddenException('AI attachments feature is currently disabled.');
        }

        $userId = (int) ($p->id() ?? 0);
        $maxPerHour = (int) Config::get('ia_attachments', 'max_uploads_per_hour', 20);
        $recentCount = $this->repo->countUploadsLastHour($userId);
        if ($recentCount >= $maxPerHour) {
            throw new RateLimitedException(3600);
        }

        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            throw new ValidationException('No valid file received or upload error occurred.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new ValidationException('Uploaded file is empty.');
        }

        $origName = basename((string) ($file['name'] ?? 'file'));
        $origName = preg_replace('/[^\w\.\-\s]/u', '_', $origName);
        if (mb_strlen($origName) > 120) {
            $origName = mb_substr($origName, 0, 120);
        }

        $tmpPath = $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = (string) finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // Classify kind & validate MIME
        $kind = null;
        if (str_starts_with($detectedMime, 'image/')) {
            if (in_array($detectedMime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                $kind = 'image';
            }
        } elseif ($detectedMime === 'application/pdf') {
            $kind = 'pdf';
        } elseif (in_array($detectedMime, ['text/plain', 'text/csv', 'text/markdown'], true)
            || (str_starts_with($detectedMime, 'text/') && !str_contains($detectedMime, 'html'))) {
            $kind = 'text';
        } elseif (in_array($detectedMime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream'
        ], true)) {
            // Verify if zip has docx entries
            $zip = new ZipArchive();
            if ($zip->open($tmpPath, ZipArchive::RDONLY) === true) {
                if ($zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false) {
                    $kind = 'docx';
                }
                $zip->close();
            }
        }

        if ($detectedMime === 'application/msword') {
            throw new ValidationException('Legacy .doc files are not supported. Save it as .docx or PDF and try again.');
        }

        if ($kind === null) {
            throw new ValidationException("Unsupported file type: {$detectedMime}.");
        }

        // Validate size per kind
        $maxBytes = match ($kind) {
            'image' => (int) Config::get('ia_attachments', 'image_max_bytes', 10485760),
            'pdf' => (int) Config::get('ia_attachments', 'pdf_max_bytes', 15728640),
            'text' => (int) Config::get('ia_attachments', 'txt_max_bytes', 1048576),
            'docx' => (int) Config::get('ia_attachments', 'docx_max_bytes', 10485760),
        };

        if ($size > $maxBytes) {
            $mb = round($maxBytes / 1048576, 1);
            throw new ValidationException("File size exceeds {$mb} MB limit for {$kind} files.");
        }

        $sha256 = hash_file('sha256', $tmpPath);

        // Deduplication check: if uploaded in the last 60s, return existing
        $dup = $this->repo->findRecentBySha256($userId, $sha256, 60);
        if ($dup !== null) {
            return $this->formatDescriptor($dup);
        }

        // Prepare destination storage
        $publicId = Ulid::generate();
        $date = Clock::nowUtc();
        $yyyy = $date->format('Y');
        $mm = $date->format('m');

        $privateDir = Config::privatePath();
        $targetDir = rtrim($privateDir, '/\\') . "/ia/{$userId}/{$yyyy}/{$mm}";
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0700, true);
        }

        $storagePath = "{$targetDir}/{$publicId}.bin";
        $derivedPath = null;
        $width = null;
        $height = null;
        $pages = null;
        $textChars = null;
        $finalMime = $detectedMime;
        $finalSize = $size;

        try {
            if ($kind === 'image') {
                $norm = ImageNormalizer::normalize($tmpPath, $storagePath);
                $width = $norm['width'];
                $height = $norm['height'];
                $finalMime = $norm['mime'];
                $finalSize = $norm['size'];
            } elseif ($kind === 'pdf') {
                $insp = PdfInspector::inspect($tmpPath);
                if ($insp['encrypted']) {
                    throw new ValidationException('Encrypted / password-protected PDFs are not supported.');
                }
                $maxPages = (int) Config::get('ia_attachments', 'pdf_max_pages', 50);
                if ($insp['pages'] > $maxPages) {
                    throw new ValidationException("PDF exceeds limit of {$maxPages} pages (has {$insp['pages']}).");
                }
                $pages = $insp['pages'];
                copy($tmpPath, $storagePath);
                @chmod($storagePath, 0600);
            } elseif ($kind === 'text') {
                $rawText = (string) file_get_contents($tmpPath);
                $normText = TextNormalizer::normalize($rawText);
                $derivedPath = "{$targetDir}/{$publicId}.txt";
                file_put_contents($derivedPath, $normText['text']);
                copy($tmpPath, $storagePath);
                @chmod($storagePath, 0600);
                @chmod($derivedPath, 0600);
                $textChars = $normText['chars'];
            } elseif ($kind === 'docx') {
                $extractor = new DocxTextExtractor();
                $extracted = $extractor->extract($tmpPath);
                $derivedPath = "{$targetDir}/{$publicId}.txt";
                file_put_contents($derivedPath, $extracted['text']);
                copy($tmpPath, $storagePath);
                @chmod($storagePath, 0600);
                @chmod($derivedPath, 0600);
                $textChars = $extracted['chars'];
            }
        } catch (\Throwable $e) {
            AuditLogger::log($p, 'ia.attachment.rejected', 'ia_attachment', null, null, [
                'name' => $origName,
                'sha256' => $sha256,
                'kind' => $kind,
                'reason' => $e->getMessage(),
            ]);
            throw $e;
        }

        $unusedTtlHours = (int) Config::get('ia_attachments', 'unused_ttl_hours', 24);
        $expiresAt = $date->modify("+{$unusedTtlHours} hours")->format('Y-m-d H:i:s');
        $createdAt = $date->format('Y-m-d H:i:s');

        $record = [
            'public_id' => $publicId,
            'user_id' => $userId,
            'original_name' => $origName,
            'kind' => $kind,
            'mime' => $finalMime,
            'size_bytes' => $finalSize,
            'sha256' => $sha256,
            'storage_path' => $storagePath,
            'derived_path' => $derivedPath,
            'text_chars' => $textChars,
            'pages' => $pages,
            'width' => $width,
            'height' => $height,
            'status' => 'uploaded',
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ];

        $this->repo->create($record);

        AuditLogger::log($p, 'ia.attachment.uploaded', 'ia_attachment', null, null, [
            'public_id' => $publicId,
            'name' => $origName,
            'kind' => $kind,
            'size' => $finalSize,
            'sha256' => $sha256,
        ]);

        return $this->formatDescriptor($record);
    }

    public function preview(Principal $p, string $publicId): void
    {
        $row = $this->repo->findByPublicId($publicId);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Attachment not found']);
            return;
        }

        if ((int) $row['user_id'] !== (int) ($p->id() ?? 0) && !$p->isSystemAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        $kind = $row['kind'];
        if ($kind === 'image' && file_exists((string) $row['storage_path'])) {
            header('Content-Type: ' . $row['mime']);
            header('Cache-Control: private, max-age=300');
            readfile($row['storage_path']);
            return;
        }

        if ($kind === 'pdf') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'kind' => 'pdf',
                'pages' => (int) ($row['pages'] ?? 1),
                'name' => $row['original_name'],
            ]);
            return;
        }

        if (($kind === 'text' || $kind === 'docx') && !empty($row['derived_path']) && file_exists((string) $row['derived_path'])) {
            $excerpt = mb_substr((string) file_get_contents($row['derived_path']), 0, 300);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'kind' => $kind,
                'name' => $row['original_name'],
                'excerpt' => $excerpt,
            ]);
            return;
        }

        http_response_code(404);
    }

    public function delete(Principal $p, string $publicId): void
    {
        $row = $this->repo->findByPublicId($publicId);
        if (!$row) {
            throw new NotFoundException('Attachment not found');
        }

        if ((int) $row['user_id'] !== (int) ($p->id() ?? 0) && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Access denied');
        }

        $this->repo->markDeleted($publicId);

        if (!empty($row['storage_path']) && file_exists((string) $row['storage_path'])) {
            @unlink($row['storage_path']);
        }
        if (!empty($row['derived_path']) && file_exists((string) $row['derived_path'])) {
            @unlink($row['derived_path']);
        }

        AuditLogger::log($p, 'ia.attachment.deleted', 'ia_attachment', null, null, [
            'public_id' => $publicId,
            'name' => $row['original_name'],
        ]);
    }

    public function attachToMessage(Principal $p, array $publicIds, int $conversacionId, int $mensajeId): array
    {
        $out = [];
        foreach ($publicIds as $pid) {
            $row = $this->repo->findByPublicId($pid);
            if ($row && (int) $row['user_id'] === (int) ($p->id() ?? 0)) {
                $this->repo->markAttached($pid, $conversacionId, $mensajeId);
                $out[] = $pid;
            }
        }
        return $out;
    }

    public function toContentBlocks(int $userId, string $publicId): array
    {
        $row = $this->repo->findByPublicId($publicId);
        if (!$row || (int) $row['user_id'] !== $userId || $row['status'] === 'deleted') {
            return [['type' => 'text', 'text' => "[Attachment: {$publicId} (unavailable)]"]];
        }

        $name = htmlspecialchars((string) $row['original_name'], ENT_QUOTES);
        $kind = (string) $row['kind'];
        $storagePath = (string) $row['storage_path'];
        $derivedPath = (string) ($row['derived_path'] ?? '');

        if ($kind === 'image' && file_exists($storagePath)) {
            $raw = @file_get_contents($storagePath);
            if ($raw !== false) {
                return [
                    ['type' => 'text', 'text' => "<attached_file name=\"{$name}\" kind=\"image\">"],
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => (string) $row['mime'],
                            'data' => base64_encode($raw),
                        ],
                    ],
                    ['type' => 'text', 'text' => '</attached_file>'],
                ];
            }
        } elseif ($kind === 'pdf' && file_exists($storagePath)) {
            $raw = @file_get_contents($storagePath);
            if ($raw !== false) {
                return [
                    [
                        'type' => 'document',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => 'application/pdf',
                            'data' => base64_encode($raw),
                        ],
                        'title' => $name,
                    ],
                ];
            }
        } elseif (($kind === 'text' || $kind === 'docx') && file_exists($derivedPath)) {
            $raw = @file_get_contents($derivedPath);
            if ($raw !== false) {
                return [
                    [
                        'type' => 'document',
                        'source' => [
                            'type' => 'text',
                            'media_type' => 'text/plain',
                            'data' => $raw,
                        ],
                        'title' => $name,
                    ],
                ];
            }
        }

        return [['type' => 'text', 'text' => "[Attachment: {$name} ({$kind})]"]];
    }

    public function copyToPrivate(Principal $p, string $publicId, string $targetSubdir, array $allowedKinds): string
    {
        $row = $this->repo->findByPublicId($publicId);
        if (!$row) {
            throw new NotFoundException('Attachment not found');
        }

        if ((int) $row['user_id'] !== (int) ($p->id() ?? 0) && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Access to attachment denied');
        }

        $kind = (string) $row['kind'];
        if (!in_array($kind, $allowedKinds, true)) {
            throw new ValidationException("Attachment of type '{$kind}' is not allowed for this target (allowed: " . implode(', ', $allowedKinds) . ").");
        }

        $srcPath = (string) $row['storage_path'];
        if (!file_exists($srcPath)) {
            throw new NotFoundException('Attachment binary file missing from storage.');
        }

        $privateDir = Config::privatePath();
        $destDir = rtrim($privateDir, '/\\') . '/' . trim($targetSubdir, '/\\');
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0700, true);
        }

        $ext = match ($kind) {
            'image' => ($row['mime'] === 'image/png' ? 'png' : 'jpg'),
            'pdf' => 'pdf',
            'docx' => 'docx',
            'text' => 'txt',
        };

        $destFilename = Ulid::generate() . '.' . $ext;
        $destPath = "{$destDir}/{$destFilename}";

        if (!copy($srcPath, $destPath)) {
            throw new \RuntimeException('Failed to copy attachment to target storage.');
        }

        @chmod($destPath, 0600);
        return $destPath;
    }

    public function deleteForConversation(int $conversacionId): int
    {
        return $this->repo->deleteByConversation($conversacionId);
    }

    public function cleanup(): array
    {
        $unusedHours = (int) Config::get('ia_attachments', 'unused_ttl_hours', 24);
        $attachedDays = (int) Config::get('ia_attachments', 'attached_ttl_days', 30);
        $stale = $this->repo->findStale($unusedHours, $attachedDays);

        $cleaned = 0;
        foreach ($stale as $r) {
            if (!empty($r['storage_path']) && file_exists((string) $r['storage_path'])) {
                @unlink($r['storage_path']);
            }
            if (!empty($r['derived_path']) && file_exists((string) $r['derived_path'])) {
                @unlink($r['derived_path']);
            }
            $this->repo->markDeleted((string) $r['public_id']);
            $cleaned++;
        }

        return ['cleaned' => $cleaned];
    }

    private function formatDescriptor(array $r): array
    {
        return [
            'public_id' => (string) $r['public_id'],
            'kind' => (string) $r['kind'],
            'name' => (string) $r['original_name'],
            'size' => (int) $r['size_bytes'],
            'width' => isset($r['width']) ? (int) $r['width'] : null,
            'height' => isset($r['height']) ? (int) $r['height'] : null,
            'pages' => isset($r['pages']) ? (int) $r['pages'] : null,
            'text_chars' => isset($r['text_chars']) ? (int) $r['text_chars'] : null,
            'preview_url' => 'admin/ajax/ia_adjunto.php?id=' . urlencode((string) $r['public_id']) . '&preview=1',
        ];
    }
}
