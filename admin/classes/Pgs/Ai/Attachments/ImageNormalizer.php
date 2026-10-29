<?php

declare(strict_types=1);

namespace Pgs\Ai\Attachments;

use Pgs\Core\Config;
use Pgs\Core\Exceptions\ValidationException;

final class ImageNormalizer
{
    /**
     * Normalizes an image: validates dimensions, resizes if edge exceeds limit,
     * strips EXIF, and saves as optimized JPEG/PNG.
     *
     * @return array{width: int, height: int, mime: string, size: int}
     * @throws ValidationException
     */
    public static function normalize(string $sourcePath, ?string $destPath = null, array $limits = []): array
    {
        if ($destPath === null) {
            $destPath = tempnam(sys_get_temp_dir(), 'norm_img_');
        }
        $maxEdge = (int) ($limits['image_max_edge'] ?? Config::get('ia_attachments', 'image_max_edge', 1568));
        $maxInputPx = (int) ($limits['image_max_input_px'] ?? Config::get('ia_attachments', 'image_max_input_px', 8000));
        $maxEncodedBytes = (int) ($limits['image_max_encoded_bytes'] ?? Config::get('ia_attachments', 'image_max_encoded_bytes', 5242880));

        $info = @getimagesize($sourcePath);
        if (!$info) {
            throw new ValidationException('Invalid image file.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $mime = (string) $info['mime'];

        // Dimension safety checks
        if ($width > $maxInputPx || $height > $maxInputPx) {
            throw new ValidationException("Image dimension ({$width}x{$height}) exceeds limit of {$maxInputPx}px.");
        }

        // 40 MP total pixels limit
        if (($width * $height) > 40000000) {
            throw new ValidationException('Image exceeds 40 megapixel limit.');
        }

        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/gif' => @imagecreatefromgif($sourcePath),
            default => false,
        };

        if (!$img) {
            throw new ValidationException('Failed to process image format.');
        }

        // Check if downscale is required
        $targetW = $width;
        $targetH = $height;
        if ($width > $maxEdge || $height > $maxEdge) {
            if ($width >= $height) {
                $targetW = $maxEdge;
                $targetH = (int) round(($height / $width) * $maxEdge);
            } else {
                $targetH = $maxEdge;
                $targetW = (int) round(($width / $height) * $maxEdge);
            }

            $scaled = imagescale($img, $targetW, $targetH, IMG_BILINEAR_FIXED);
            if ($scaled !== false) {
                imagedestroy($img);
                $img = $scaled;
            }
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0700, true);
        }

        $isPngWithAlpha = false;
        if ($mime === 'image/png') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $isPngWithAlpha = true;
        }

        $finalMime = $isPngWithAlpha ? 'image/png' : 'image/jpeg';

        if ($isPngWithAlpha) {
            imagepng($img, $destPath, 6);
        } else {
            imagejpeg($img, $destPath, 85);
        }

        $fileSize = file_exists($destPath) ? (int) filesize($destPath) : 0;

        // If JPEG exceeds maxEncodedBytes (5MB), reduce quality to 75
        if (!$isPngWithAlpha && $fileSize > $maxEncodedBytes) {
            imagejpeg($img, $destPath, 75);
            $fileSize = file_exists($destPath) ? (int) filesize($destPath) : $fileSize;
        }

        imagedestroy($img);
        @chmod($destPath, 0600);

        return [
            'width' => $targetW,
            'height' => $targetH,
            'mime' => $finalMime,
            'size' => $fileSize,
            'path' => $destPath,
        ];
    }
}
