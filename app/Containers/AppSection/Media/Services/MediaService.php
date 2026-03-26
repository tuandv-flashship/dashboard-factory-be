<?php

namespace App\Containers\AppSection\Media\Services;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Containers\AppSection\Media\Models\MediaSetting;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Containers\AppSection\Media\Jobs\GenerateThumbnailsJob;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Core Media Service — orchestrates upload, download, URL generation, and file operations.
 *
 * Image processing logic is delegated to ImageProcessingService.
 * File validation logic is delegated to MediaValidationService.
 */
class MediaService
{
    private const USER_ITEMS_CACHE_PREFIX = 'media:user-items';

    public function __construct(
        private readonly MediaSettingsStore $settingsStore,
        private readonly ThumbnailService $thumbnailService,
        private readonly ImageProcessingService $imageProcessingService,
        private readonly MediaValidationService $validationService,
    ) {
    }

    // ─── Disk & URL ───────────────────────────────────────────────

    public function getDisk(): string
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

    public function getPrivateDisk(): string
    {
        return (string) config('media.private_disk', 'local');
    }

    public function url(?string $path): string
    {
        if (! $path) {
            return '';
        }

        return Storage::disk($this->getDisk())->url($path);
    }

    public function getImageUrl(?string $path, ?string $size = null): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['data:image', 'http://', 'https://'])) {
            return $path;
        }

        if (! $size) {
            return $this->url($path);
        }

        $sizes = (array) config('media.sizes', []);
        if (! array_key_exists($size, $sizes)) {
            return $this->url($path);
        }

        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $thumbPath = str_replace(
            $fileName . '.' . $extension,
            $fileName . '-' . $sizes[$size] . '.' . $extension,
            $path
        );

        if (! Storage::disk($this->getDisk())->exists($thumbPath)) {
            return $this->url($path);
        }

        return $this->url($thumbPath);
    }

    public function getRealPath(string $path): string
    {
        return Storage::disk($this->getDisk())->path($path);
    }

    // ─── Access Mode & Signed URLs ────────────────────────────────

    public function resolveAccessModeForFile(MediaFile $file): ?string
    {
        if ($file->visibility !== 'private') {
            return null;
        }

        return $this->normalizeAccessMode($file->access_mode) ?: $this->getDefaultPrivateAccessMode();
    }

    public function getSignedUrl(MediaFile $file): ?string
    {
        if ($file->visibility !== 'private') {
            return null;
        }

        if ($this->resolveAccessModeForFile($file) !== 'signed') {
            return null;
        }

        $ttlMinutes = $this->getSignedUrlTtlMinutes();
        $id = dechex((int) $file->getKey());
        $hash = sha1($id);

        return URL::temporarySignedRoute('media.indirect.url', now()->addMinutes($ttlMinutes), compact('hash', 'id'));
    }

    // ─── Upload & Download ────────────────────────────────────────

    public function storeUploadedFile(
        UploadedFile $file,
        int $folderId,
        int $userId,
        ?string $visibility = null,
        ?string $accessMode = null
    ): MediaFile {
        $visibility = $visibility ?: 'public';
        $accessMode = $this->resolveAccessMode($visibility, $accessMode);
        $user = User::query()->find($userId);

        if (! $this->validationService->isAllowedFile($file, $user)) {
            throw new InvalidArgumentException('File type is not allowed.');
        }

        $folderPath = $this->getUploadFolderPath($folderId);
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin'));

        $name = MediaFile::createName($originalName, $folderId);
        $disk = $visibility === 'private' ? $this->getPrivateDisk() : $this->getDisk();
        $fileName = $this->validationService->createStorageFileName($name, $extension, $folderPath, $disk);

        $storagePath = $folderPath ? $folderPath . '/' . $fileName : $fileName;

        $mimeType = (string) ($file->getClientMimeType() ?: 'application/octet-stream');
        $size = (int) $file->getSize();

        $fileNameGenerator = fn (string $n, string $ext, string $fp) => $this->validationService->createStorageFileName($n, $ext, $fp, $disk);
        $processed = $this->imageProcessingService->processImageUpload($file, $extension, $name, $folderPath, $fileNameGenerator);

        if ($processed) {
            $fileName = $processed['file_name'];
            $storagePath = $folderPath ? $folderPath . '/' . $fileName : $fileName;
            $mimeType = $processed['mime_type'];
            $size = $processed['size'];

            Storage::disk($disk)->put($storagePath, $processed['content'], [
                'visibility' => $visibility,
            ]);
        } else {
            Storage::disk($disk)->putFileAs($folderPath ?: '', $file, $fileName, [
                'visibility' => $visibility,
            ]);
        }

        $mediaFile = MediaFile::query()->create([
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => $size,
            'url' => $storagePath,
            'options' => [
                'original_name' => $file->getClientOriginalName(),
            ],
            'folder_id' => $folderId,
            'user_id' => $userId,
            'visibility' => $visibility,
            'access_mode' => $accessMode,
        ]);

        $this->imageProcessingService->maybeApplyWatermark($mediaFile);
        $this->maybeGenerateThumbnails($mediaFile);

        return $mediaFile;
    }

    public function downloadFromUrl(
        string $url,
        int $folderId,
        int $userId,
        ?string $visibility = null,
        ?string $accessMode = null
    ): MediaFile {
        $visibility = $visibility ?: 'public';
        $accessMode = $this->resolveAccessMode($visibility, $accessMode);
        $response = Http::get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to download file.');
        }

        $basename = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $extension = $extension ? strtolower($extension) : 'bin';

        if (! in_array($extension, $this->validationService->getAllowedExtensions(), true)) {
            throw new InvalidArgumentException('File type is not allowed.');
        }

        $folderPath = $this->getUploadFolderPath($folderId);
        $name = MediaFile::createName($basename ?: 'file', $folderId);
        $disk = $visibility === 'private' ? $this->getPrivateDisk() : $this->getDisk();
        $fileName = $this->validationService->createStorageFileName($name, $extension, $folderPath, $disk);
        $storagePath = $folderPath ? $folderPath . '/' . $fileName : $fileName;

        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $binary = $response->body();
        $size = strlen($binary);

        $fileNameGenerator = fn (string $n, string $ext, string $fp) => $this->validationService->createStorageFileName($n, $ext, $fp, $disk);
        $processed = $this->imageProcessingService->processImageBinary($binary, $extension, $name, $folderPath, $mimeType, $fileNameGenerator);

        if ($processed) {
            $fileName = $processed['file_name'];
            $storagePath = $folderPath ? $folderPath . '/' . $fileName : $fileName;
            $mimeType = $processed['mime_type'];
            $size = $processed['size'];
            $binary = $processed['content'];
        }

        Storage::disk($disk)->put($storagePath, $binary, [
            'visibility' => $visibility,
        ]);

        $mediaFile = MediaFile::query()->create([
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => $size,
            'url' => $storagePath,
            'options' => [
                'original_url' => $url,
            ],
            'folder_id' => $folderId,
            'user_id' => $userId,
            'visibility' => $visibility,
            'access_mode' => $accessMode,
        ]);

        $this->imageProcessingService->maybeApplyWatermark($mediaFile);
        $this->maybeGenerateThumbnails($mediaFile);

        return $mediaFile;
    }

    // ─── File Operations ──────────────────────────────────────────

    public function moveFile(MediaFile $file, int $newFolderId): MediaFile
    {
        $folderPath = $this->getUploadFolderPath($newFolderId);
        $newPath = $folderPath ? $folderPath . '/' . basename($file->url) : basename($file->url);

        $disk = $file->visibility === 'private' ? $this->getPrivateDisk() : $this->getDisk();
        Storage::disk($disk)->move($file->url, $newPath);
        $this->moveThumbnails($file->url, $newPath, $disk);

        $file->update([
            'folder_id' => $newFolderId,
            'url' => $newPath,
        ]);

        return $file;
    }

    public function renameFile(MediaFile $file, string $newName, bool $renameOnDisk = true): MediaFile
    {
        $file->name = MediaFile::createName($newName, $file->folder_id);

        if ($renameOnDisk) {
            $extension = pathinfo($file->url, PATHINFO_EXTENSION);
            $folderPath = $this->getUploadFolderPath($file->folder_id);
            $disk = $file->visibility === 'private' ? $this->getPrivateDisk() : $this->getDisk();
            $fileName = $this->validationService->createStorageFileName($file->name, $extension, $folderPath, $disk);
            $newPath = $folderPath ? $folderPath . '/' . $fileName : $fileName;

            Storage::disk($disk)->move($file->url, $newPath);
            $this->moveThumbnails($file->url, $newPath, $disk);
            $file->url = $newPath;
        }

        $file->save();

        return $file;
    }

    public function renameFolder(MediaFolder $folder, string $newName, bool $renameOnDisk = true): MediaFolder
    {
        $folder->name = MediaFolder::createName($newName, $folder->parent_id);

        if ($renameOnDisk) {
            $folderPath = $this->getFolderPath($folder->getKey());

            if ($folderPath && Storage::disk($this->getDisk())->directoryExists($folderPath)) {
                $newSlug = MediaFolder::createSlug($newName, $folder->parent_id);
                $newFolderPath = str_replace(basename($folderPath), $newSlug, $folderPath);

                Storage::disk($this->getDisk())->move($folderPath, $newFolderPath);

                $folder->slug = $newSlug;

                MediaFile::query()
                    ->where('url', 'like', $folderPath . '/%')
                    ->get()
                    ->each(function (MediaFile $file) use ($folderPath, $newFolderPath): void {
                        $file->url = str_replace($folderPath, $newFolderPath, $file->url);
                        $file->save();
                    });
            }
        }

        $folder->save();

        return $folder;
    }

    public function deleteFileFromStorage(MediaFile $file): void
    {
        $disk = $file->visibility === 'private' ? $this->getPrivateDisk() : $this->getDisk();
        Storage::disk($disk)->delete($file->url);
        $this->deleteThumbnails($file->url, $disk);
    }

    // ─── Thumbnails ───────────────────────────────────────────────

    public function maybeGenerateThumbnails(MediaFile $file): void
    {
        if ($file->visibility !== 'public') {
            return;
        }

        if (! $file->canGenerateThumbnails()) {
            return;
        }

        if (config('appSection-media.media.queue_thumbnails', false)) {
            GenerateThumbnailsJob::dispatch($file);

            return;
        }

        $this->thumbnailService->generate($file);
    }

    public function cropImage(MediaFile $file, int $x, int $y, int $width, int $height): bool
    {
        $result = $this->thumbnailService->crop($file, $x, $y, $width, $height);

        if ($result) {
            $this->maybeGenerateThumbnails($file);
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function getThumbnailPaths(string $path): array
    {
        $sizes = (array) config('media.sizes', []);
        if ($sizes === []) {
            return [];
        }

        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $paths = [];
        foreach ($sizes as $size) {
            $paths[] = str_replace(
                $fileName . '.' . $extension,
                $fileName . '-' . $size . '.' . $extension,
                $path
            );
        }

        return $paths;
    }

    public function deleteThumbnails(string $path, ?string $disk = null): void
    {
        $disk = $disk ?: $this->getDisk();

        foreach ($this->getThumbnailPaths($path) as $thumb) {
            Storage::disk($disk)->delete($thumb);
        }
    }

    public function moveThumbnails(string $oldPath, string $newPath, ?string $disk = null): void
    {
        $disk = $disk ?: $this->getDisk();
        $oldThumbs = $this->getThumbnailPaths($oldPath);
        $newThumbs = $this->getThumbnailPaths($newPath);

        foreach ($oldThumbs as $index => $oldThumb) {
            $newThumb = $newThumbs[$index] ?? null;
            if (! $newThumb) {
                continue;
            }

            if (Storage::disk($disk)->exists($oldThumb)) {
                Storage::disk($disk)->move($oldThumb, $newThumb);
            }
        }
    }

    // ─── User Items Cache ─────────────────────────────────────────

    public function getRecentItems(int $userId): array
    {
        return $this->getUserItemsFromCache('recent_items', $userId);
    }

    public function getFavoriteItems(int $userId): array
    {
        return $this->getUserItemsFromCache('favorites', $userId);
    }

    public function forgetUserItemsCache(int $userId): void
    {
        Cache::forget($this->getUserItemsCacheKey('recent_items', $userId));
        Cache::forget($this->getUserItemsCacheKey('favorites', $userId));
    }

    // ─── Folder Path ──────────────────────────────────────────────

    public function getFolderPath(int $folderId): string
    {
        return $this->getUploadFolderPath($folderId);
    }

    // ─── Private Helpers ──────────────────────────────────────────

    private function getUploadFolderPath(int $folderId): string
    {
        $base = trim((string) config('media.default_upload_folder', ''), '/');
        $folderPath = MediaFolder::getFullPath($folderId) ?: '';

        $path = trim($folderPath, '/');
        if ($base !== '') {
            $path = trim($base . '/' . $path, '/');
        }

        if ($this->isCloudDisk()) {
            $settings = $this->settings();
            $customPath = trim(
                (string) $settings->get('media_s3_path', config('media.custom_s3_path', '')),
                '/'
            );

            if ($customPath !== '') {
                $path = trim($customPath . '/' . $path, '/');
            }
        }

        return $path;
    }

    /**
     * @return array<int, mixed>
     */
    private function getUserItemsFromCache(string $key, int $userId): array
    {
        $cacheTtl = (int) config('media.cache.user_item_ttl_seconds', 300);
        $cacheKey = $this->getUserItemsCacheKey($key, $userId);

        $resolver = function () use ($key, $userId): array {
            $value = MediaSetting::query()
                ->where('key', $key)
                ->where('user_id', $userId)
                ->value('value');

            return is_array($value) ? $value : [];
        };

        if ($cacheTtl <= 0) {
            return Cache::rememberForever($cacheKey, $resolver);
        }

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtl), $resolver);
    }

    private function getUserItemsCacheKey(string $key, int $userId): string
    {
        return sprintf('%s:%s:%d', self::USER_ITEMS_CACHE_PREFIX, $key, $userId);
    }

    private function isCloudDisk(?string $disk = null): bool
    {
        $disk = $disk ?: $this->getDisk();

        return in_array($disk, ['s3', 'r2', 'do_spaces', 'wasabi', 'bunnycdn', 'backblaze'], true);
    }

    private function settings(): MediaSettingsStore
    {
        return $this->settingsStore;
    }

    private function resolveAccessMode(?string $visibility, ?string $accessMode): ?string
    {
        if ($visibility !== 'private') {
            return null;
        }

        return $this->normalizeAccessMode($accessMode);
    }

    private function normalizeAccessMode(?string $accessMode): ?string
    {
        $accessMode = $accessMode ? strtolower($accessMode) : null;

        return in_array($accessMode, ['auth', 'signed'], true) ? $accessMode : null;
    }

    private function getDefaultPrivateAccessMode(): string
    {
        $configured = (string) config('media.private_access_mode', 'auth');
        $mode = $this->normalizeAccessMode($configured);

        return $mode ?: 'auth';
    }

    private function getSignedUrlTtlMinutes(): int
    {
        $ttl = (int) config('media.signed_url_ttl_minutes', 30);

        return $ttl > 0 ? $ttl : 30;
    }
}
