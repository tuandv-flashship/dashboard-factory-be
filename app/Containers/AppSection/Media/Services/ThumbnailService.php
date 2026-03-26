<?php

namespace App\Containers\AppSection\Media\Services;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ThumbnailService
{
    public function __construct(
        private readonly MediaSettingsStore $settings
    )
    {
    }

    /**
     * @return array<int, string>
     */
    public function generate(MediaFile $file): array
    {
        if (! $this->isGdEnabled()) {
            return [];
        }

        if (! $file->canGenerateThumbnails()) {
            return [];
        }

        $sizes = $this->getSizes();
        if ($sizes === []) {
            return [];
        }

        $binary = $this->getFileContents($file);
        if ($binary === null) {
            return [];
        }

        $source = $this->createImageFromString($binary);
        if (! $source) {
            return [];
        }

        $format = $this->getImageFormat($file);
        if (! $this->canWriteFormat($format)) {
            imagedestroy($source);
            return [];
        }

        $disk = $this->getDiskForFile($file);
        $paths = [];

        foreach ($sizes as $size) {
            [$width, $height] = $this->parseSize($size);
            if ($width === 0 && $height === 0) {
                continue;
            }

            $thumb = $this->resizeImage($source, $width, $height);
            if (! $thumb) {
                continue;
            }

            $thumbPath = $this->buildThumbPath($file->url, $size);
            $payload = $this->encodeImage($thumb, $format);
            imagedestroy($thumb);

            if ($payload === null) {
                continue;
            }

            Storage::disk($disk)->put($thumbPath, $payload, [
                'visibility' => $file->visibility,
            ]);

            $paths[] = $thumbPath;
        }

        imagedestroy($source);

        return $paths;
    }

    public function crop(MediaFile $file, int $x, int $y, int $width, int $height): bool
    {
        if (! $this->isGdEnabled()) {
            return false;
        }

        if (! Str::startsWith($file->mime_type, 'image/')) {
            return false;
        }

        $binary = $this->getFileContents($file);
        if ($binary === null) {
            return false;
        }

        $source = $this->createImageFromString($binary);
        if (! $source) {
            return false;
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        $x = max(0, min($x, $srcWidth - 1));
        $y = max(0, min($y, $srcHeight - 1));
        $width = min($width, $srcWidth - $x);
        $height = min($height, $srcHeight - $y);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return false;
        }

        $format = $this->getImageFormat($file);
        if (! $this->canWriteFormat($format)) {
            imagedestroy($source);
            return false;
        }

        $canvas = $this->createCanvas($width, $height, $format);
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $width, $height, $width, $height);
        imagedestroy($source);

        $payload = $this->encodeImage($canvas, $format);
        imagedestroy($canvas);

        if ($payload === null) {
            return false;
        }

        $disk = $this->getDiskForFile($file);
        Storage::disk($disk)->put($file->url, $payload, [
            'visibility' => $file->visibility,
        ]);

        $file->size = Storage::disk($disk)->size($file->url);
        $file->save();

        return true;
    }

    private function getDiskForFile(MediaFile $file): string
    {
        return $file->visibility === 'private'
            ? $this->getPrivateDisk()
            : $this->getDisk();
    }

    private function getDisk(): string
    {
        $driver = (string) $this->settings->get(
            'media_driver',
            config('media.driver', config('media.disk', 'public'))
        );

        $allowed = ['public', 'local', 's3', 'r2', 'do_spaces', 'wasabi', 'bunnycdn', 'backblaze'];
        if (! in_array($driver, $allowed, true)) {
            return (string) config('media.disk', 'public');
        }

        return $driver;
    }

    private function getPrivateDisk(): string
    {
        return (string) config('media.private_disk', 'local');
    }

    private function getFileContents(MediaFile $file): ?string
    {
        $disk = $this->getDiskForFile($file);

        if (! Storage::disk($disk)->exists($file->url)) {
            return null;
        }

        return Storage::disk($disk)->get($file->url);
    }

    private function createImageFromString(string $binary): mixed
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        return @imagecreatefromstring($binary) ?: null;
    }

    private function getImageFormat(MediaFile $file): string
    {
        $extension = strtolower(pathinfo($file->url, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpeg', 'jpg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            default => Str::startsWith($file->mime_type, 'image/') ? 'jpeg' : 'jpeg',
        };
    }

    private function canWriteFormat(string $format): bool
    {
        return match ($format) {
            'jpeg', 'png', 'gif' => true,
            'webp' => function_exists('imagewebp'),
            default => false,
        };
    }

    private function resizeImage(mixed $source, int $width, int $height): mixed
    {
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        if ($width === 0 && $height === 0) {
            return null;
        }

        if ($width === 0) {
            $width = (int) round($srcWidth * ($height / $srcHeight));
        } elseif ($height === 0) {
            $height = (int) round($srcHeight * ($width / $srcWidth));
        }

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $scale = max($width / $srcWidth, $height / $srcHeight);
        $tmpWidth = (int) ceil($srcWidth * $scale);
        $tmpHeight = (int) ceil($srcHeight * $scale);

        $tmp = $this->createCanvas($tmpWidth, $tmpHeight, 'png');
        imagecopyresampled($tmp, $source, 0, 0, 0, 0, $tmpWidth, $tmpHeight, $srcWidth, $srcHeight);

        $dst = $this->createCanvas($width, $height, 'png');
        [$srcX, $srcY] = $this->resolveCropOffset($tmpWidth, $tmpHeight, $width, $height);

        imagecopyresampled($dst, $tmp, 0, 0, $srcX, $srcY, $width, $height, $width, $height);
        imagedestroy($tmp);

        return $dst;
    }

    private function createCanvas(int $width, int $height, string $format): mixed
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (in_array($format, ['png', 'gif', 'webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        }

        return $canvas;
    }

    private function encodeImage(mixed $image, string $format): ?string
    {
        ob_start();

        $result = match ($format) {
            'jpeg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'webp' => function_exists('imagewebp') ? imagewebp($image) : false,
            default => false,
        };

        $data = ob_get_clean();

        return $result && is_string($data) ? $data : null;
    }

    private function buildThumbPath(string $path, string $size): string
    {
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return str_replace(
            $fileName . '.' . $extension,
            $fileName . '-' . $size . '.' . $extension,
            $path
        );
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseSize(string $size): array
    {
        $parts = explode('x', strtolower($size));
        if (count($parts) !== 2) {
            return [0, 0];
        }

        $width = $parts[0] === 'auto' ? 0 : (int) $parts[0];
        $height = $parts[1] === 'auto' ? 0 : (int) $parts[1];

        return [$width, $height];
    }

    /**
     * @return array<int, string>
     */
    private function getSizes(): array
    {
        if (! $this->settings->getBool('media_enable_thumbnail_sizes', (bool) config('media.enable_thumbnail_sizes', true))) {
            return [];
        }

        $sizes = (array) config('media.sizes', []);
        if ($sizes === []) {
            return [];
        }

        $result = [];

        foreach ($sizes as $name => $size) {
            $parts = explode('x', strtolower((string) $size));
            if (count($parts) !== 2) {
                continue;
            }

            $width = $this->settings->getInt(sprintf('media_sizes_%s_width', $name), (int) $parts[0]);
            $height = $this->settings->getInt(sprintf('media_sizes_%s_height', $name), (int) $parts[1]);

            $result[] = $width . 'x' . $height;
        }

        return $result;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveCropOffset(int $tmpWidth, int $tmpHeight, int $width, int $height): array
    {
        $position = (string) $this->settings->get('media_thumbnail_crop_position', 'center');

        $srcX = (int) max(0, floor(($tmpWidth - $width) / 2));
        $srcY = (int) max(0, floor(($tmpHeight - $height) / 2));

        if ($position === 'left') {
            $srcX = 0;
        } elseif ($position === 'right') {
            $srcX = max(0, $tmpWidth - $width);
        } elseif ($position === 'top') {
            $srcY = 0;
        } elseif ($position === 'bottom') {
            $srcY = max(0, $tmpHeight - $height);
        }

        return [$srcX, $srcY];
    }

    private function isGdEnabled(): bool
    {
        $library = strtolower((string) $this->settings->get(
            'media_image_processing_library',
            config('media.image_processing_library', 'gd')
        ));

        return $library === 'gd';
    }
}
