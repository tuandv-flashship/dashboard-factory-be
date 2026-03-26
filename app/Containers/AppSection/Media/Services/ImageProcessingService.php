<?php

namespace App\Containers\AppSection\Media\Services;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles image transformation: watermark, resize, format conversion.
 *
 * Extracted from MediaService to isolate GD-based image processing concerns.
 */
class ImageProcessingService
{
    public function __construct(
        private readonly MediaSettingsStore $settingsStore,
    ) {
    }

    /**
     * Apply watermark to a media file if configured and applicable.
     */
    public function maybeApplyWatermark(MediaFile $file): void
    {
        if (! $this->isGdEnabled()) {
            return;
        }

        $settings = $this->settings();

        if (! $settings->getBool('media_watermark_enabled', (bool) config('media.watermark.enabled', false))) {
            return;
        }

        if (! Str::startsWith($file->mime_type, 'image/')) {
            return;
        }

        $watermarkPath = (string) $settings->get('media_watermark_source', config('media.watermark.source'));
        if ($watermarkPath === '' || $watermarkPath === $file->url) {
            return;
        }

        $allowedFolders = $settings->getArray('media_folders_can_add_watermark', []);
        if ($allowedFolders !== [] && ! in_array($file->folder_id, $allowedFolders, true)) {
            return;
        }

        $disk = $file->visibility === 'private'
            ? (string) config('media.private_disk', 'local')
            : $this->resolvePublicDisk();

        $imageContent = Storage::disk($disk)->exists($file->url)
            ? Storage::disk($disk)->get($file->url)
            : null;

        $watermarkContent = $this->resolveWatermarkContent($watermarkPath);

        if (! $imageContent || ! $watermarkContent) {
            return;
        }

        $image = $this->createImageFromString($imageContent);
        $watermark = $this->createImageFromString($watermarkContent);

        if (! $image || ! $watermark) {
            return;
        }

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $sizePercent = $settings->getInt('media_watermark_size', (int) config('media.watermark.size', 10));
        $targetWidth = (int) round($imageWidth * ($sizePercent / 100));
        $targetWidth = max(1, min($targetWidth, $imageWidth));

        $watermark = $this->scaleImageToWidth($watermark, $targetWidth);
        if (! $watermark) {
            imagedestroy($image);
            return;
        }

        $watermarkWidth = imagesx($watermark);
        $watermarkHeight = imagesy($watermark);

        $position = (string) $settings->get('media_watermark_position', config('media.watermark.position', 'bottom-right'));
        $offsetX = $settings->getInt('media_watermark_position_x', (int) config('media.watermark.x', 10));
        $offsetY = $settings->getInt('media_watermark_position_y', (int) config('media.watermark.y', 10));
        $opacity = $settings->getInt('media_watermark_opacity', (int) config('media.watermark.opacity', 70));

        [$dstX, $dstY] = $this->resolveWatermarkPosition(
            $position,
            $imageWidth,
            $imageHeight,
            $watermarkWidth,
            $watermarkHeight,
            $offsetX,
            $offsetY
        );

        $this->mergeWatermark($image, $watermark, $dstX, $dstY, $opacity);

        $format = $this->normalizeImageFormat(pathinfo($file->url, PATHINFO_EXTENSION), $file->mime_type);
        $encoded = $this->encodeImage($image, $format, 90);
        imagedestroy($image);
        imagedestroy($watermark);

        if ($encoded === null) {
            return;
        }

        Storage::disk($disk)->put($file->url, $encoded, [
            'visibility' => $file->visibility,
        ]);

        $file->size = Storage::disk($disk)->size($file->url);
        $file->save();
    }

    /**
     * Process an uploaded image file (resize, convert to WebP, etc.).
     *
     * @return array{content:string,file_name:string,mime_type:string,size:int}|null
     */
    public function processImageUpload(
        \Illuminate\Http\UploadedFile $file,
        string $extension,
        string $name,
        string $folderPath,
        \Closure $fileNameGenerator
    ): ?array {
        if (! $this->isGdEnabled()) {
            return null;
        }

        $mimeType = (string) ($file->getClientMimeType() ?: 'application/octet-stream');

        if (! Str::startsWith($mimeType, 'image/')) {
            return null;
        }

        $binary = @file_get_contents($file->getRealPath());
        if (! is_string($binary)) {
            return null;
        }

        return $this->processImageBinary($binary, $extension, $name, $folderPath, $mimeType, $fileNameGenerator);
    }

    /**
     * Process raw image binary (resize, convert to WebP, etc.).
     *
     * @return array{content:string,file_name:string,mime_type:string,size:int}|null
     */
    public function processImageBinary(
        string $binary,
        string $extension,
        string $name,
        string $folderPath,
        string $mimeType,
        \Closure $fileNameGenerator
    ): ?array {
        if (! $this->isGdEnabled()) {
            return null;
        }

        $settings = $this->settings();
        $keepOriginal = $settings->getBool('media_keep_original_file_size_and_quality', false);
        $shouldConvertToWebp = $settings->getBool('media_convert_image_to_webp', false)
            && in_array($extension, ['jpg', 'jpeg', 'png'], true)
            && function_exists('imagewebp');

        $shouldResize = $settings->getBool('media_reduce_large_image_size', false)
            && in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            && ! $keepOriginal;

        if (! $shouldConvertToWebp && $keepOriginal && ! $shouldResize) {
            return null;
        }

        $image = $this->createImageFromString($binary);
        if (! $image) {
            return null;
        }

        if ($shouldResize) {
            $maxWidth = $settings->getInt('media_image_max_width', (int) config('media.image_max_width', 0));
            $maxHeight = $settings->getInt('media_image_max_height', (int) config('media.image_max_height', 0));
            $resized = $this->scaleDownImage($image, $maxWidth, $maxHeight);
            if ($resized !== $image) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        $format = $this->normalizeImageFormat($extension, $mimeType);
        if ($shouldConvertToWebp) {
            $format = 'webp';
        }

        $quality = $keepOriginal ? 100 : 90;
        $content = $this->encodeImage($image, $format, $quality);
        imagedestroy($image);

        if ($content === null) {
            return null;
        }

        $newExtension = $format === 'jpeg' ? 'jpg' : $format;
        $fileName = $fileNameGenerator($name, $newExtension, $folderPath);
        $newMimeType = match ($format) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return [
            'content' => $content,
            'file_name' => $fileName,
            'mime_type' => $newMimeType,
            'size' => strlen($content),
        ];
    }

    public function isGdEnabled(): bool
    {
        $library = strtolower((string) $this->settings()->get(
            'media_image_processing_library',
            config('media.image_processing_library', 'gd')
        ));

        return $library === 'gd';
    }

    // ─── GD Helpers ───────────────────────────────────────────────

    public function createImageFromString(string $binary): mixed
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        return @imagecreatefromstring($binary) ?: null;
    }

    public function scaleDownImage(mixed $image, int $maxWidth, int $maxHeight): mixed
    {
        if (! is_resource($image) && ! ($image instanceof \GdImage)) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            return $image;
        }

        $maxWidth = $maxWidth > 0 ? $maxWidth : $width;
        $maxHeight = $maxHeight > 0 ? $maxHeight : $height;

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $image;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    public function normalizeImageFormat(string $extension, string $mimeType): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            default => Str::startsWith($mimeType, 'image/') ? 'jpeg' : 'jpeg',
        };
    }

    public function encodeImage(mixed $image, string $format, int $quality = 90): ?string
    {
        ob_start();

        $result = match ($format) {
            'jpeg' => imagejpeg($image, null, $quality),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, $quality) : false,
            default => false,
        };

        $data = ob_get_clean();

        return $result && is_string($data) ? $data : null;
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function settings(): MediaSettingsStore
    {
        return $this->settingsStore;
    }

    private function resolvePublicDisk(): string
    {
        $settings = $this->settings();
        $driver = (string) $settings->get(
            'media_driver',
            config('media.driver', config('media.disk', 'public'))
        );

        $allowed = ['public', 'local', 's3', 'r2', 'do_spaces', 'wasabi', 'bunnycdn', 'backblaze'];
        if (! in_array($driver, $allowed, true)) {
            return (string) config('media.disk', 'public');
        }

        return $driver;
    }

    private function resolveWatermarkContent(string $path): ?string
    {
        $disk = $this->resolvePublicDisk();

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->get($path);
        }

        $publicPath = public_path($path);
        if (file_exists($publicPath)) {
            return @file_get_contents($publicPath) ?: null;
        }

        $storagePath = storage_path('app/public/' . ltrim($path, '/'));
        if (file_exists($storagePath)) {
            return @file_get_contents($storagePath) ?: null;
        }

        return null;
    }

    private function scaleImageToWidth(mixed $image, int $targetWidth): mixed
    {
        if (! is_resource($image) && ! ($image instanceof \GdImage)) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0 || $targetWidth <= 0) {
            return $image;
        }

        if ($width === $targetWidth) {
            return $image;
        }

        $ratio = $targetWidth / $width;
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $newHeight, $transparent);

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $canvas;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveWatermarkPosition(
        string $position,
        int $imageWidth,
        int $imageHeight,
        int $watermarkWidth,
        int $watermarkHeight,
        int $offsetX,
        int $offsetY
    ): array {
        $position = strtolower($position);

        $x = match ($position) {
            'top-right', 'bottom-right' => $imageWidth - $watermarkWidth - $offsetX,
            'center' => (int) round(($imageWidth - $watermarkWidth) / 2) + $offsetX,
            default => $offsetX,
        };

        $y = match ($position) {
            'bottom-left', 'bottom-right' => $imageHeight - $watermarkHeight - $offsetY,
            'center' => (int) round(($imageHeight - $watermarkHeight) / 2) + $offsetY,
            default => $offsetY,
        };

        return [max(0, $x), max(0, $y)];
    }

    private function mergeWatermark(mixed $image, mixed $watermark, int $x, int $y, int $opacity): void
    {
        $opacity = max(0, min($opacity, 100));
        $width = imagesx($watermark);
        $height = imagesy($watermark);

        if ($opacity >= 100) {
            imagecopy($image, $watermark, $x, $y, 0, 0, $width, $height);
            return;
        }

        $tmp = imagecreatetruecolor($width, $height);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefilledrectangle($tmp, 0, 0, $width, $height, $transparent);
        imagecopy($tmp, $watermark, 0, 0, 0, 0, $width, $height);

        imagecopymerge($image, $tmp, $x, $y, 0, 0, $width, $height, $opacity);
        imagedestroy($tmp);
    }
}
