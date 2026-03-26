<?php

namespace App\Containers\AppSection\Media\Actions\Handlers;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Containers\AppSection\Media\Services\MediaService;
use Illuminate\Support\Facades\Storage;

/**
 * Handles file/folder copy operations (make_copy action).
 *
 * Extracted from MediaGlobalActionAction for SRP.
 */
final class CopyHandler
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload, int $userId): array
    {
        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! $id) {
                continue;
            }

            if (! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $file = MediaFile::query()->find($id);
                if ($file) {
                    $this->copyFile($file, null, $userId);
                }

                continue;
            }

            $folder = MediaFolder::query()->find($id);
            if ($folder) {
                $this->copyFolder($folder, (int) $folder->parent_id, $userId);
            }
        }

        return ['message' => 'Copied successfully.'];
    }

    public function copyFile(MediaFile $file, ?int $newFolderId, int $userId): MediaFile
    {
        $copy = $file->replicate();
        $copy->user_id = $userId;

        $disk = $file->visibility === 'private' ? $this->mediaService->getPrivateDisk() : $this->mediaService->getDisk();

        $targetFolderId = $newFolderId ?? (int) $file->folder_id;
        $copy->name = MediaFile::createName($file->name . '-(copy)', $targetFolderId);

        if ($newFolderId === null) {
            $newPath = $this->generateCopyPath($file->url, $disk);

            Storage::disk($disk)->copy($file->url, $newPath);
            $copy->url = $newPath;
            $this->copyThumbnails($file->url, $newPath, $disk);
        } else {
            $folderPath = $this->mediaService->getFolderPath($newFolderId);
            $fileName = basename($file->url);
            $newPath = $folderPath ? $folderPath . '/' . $fileName : $fileName;

            $newPath = $this->ensureUniquePath($newPath, $disk);
            if ($folderPath !== '' && ! Storage::disk($disk)->directoryExists($folderPath)) {
                Storage::disk($disk)->makeDirectory($folderPath);
            }
            Storage::disk($disk)->copy($file->url, $newPath);
            $copy->url = $newPath;
            $copy->folder_id = $newFolderId;
            $this->copyThumbnails($file->url, $newPath, $disk);
        }

        $copy->save();

        return $copy;
    }

    public function copyFolder(MediaFolder $folder, int $parentId, int $userId): MediaFolder
    {
        $newName = $folder->name . '-(copy)';
        $newFolder = MediaFolder::query()->create([
            'name' => MediaFolder::createName($newName, $parentId),
            'slug' => MediaFolder::createSlug($newName, $parentId),
            'parent_id' => $parentId,
            'user_id' => $userId,
            'color' => $folder->color,
        ]);

        $folder->files()->get()->each(function (MediaFile $file) use ($newFolder, $userId): void {
            $this->copyFile($file, $newFolder->getKey(), $userId);
        });

        MediaFolder::query()
            ->where('parent_id', $folder->getKey())
            ->get()
            ->each(function (MediaFolder $child) use ($newFolder, $userId): void {
                $this->copyFolder($child, $newFolder->getKey(), $userId);
            });

        return $newFolder;
    }

    private function copyThumbnails(string $oldPath, string $newPath, string $disk): void
    {
        $oldThumbs = $this->mediaService->getThumbnailPaths($oldPath);
        $newThumbs = $this->mediaService->getThumbnailPaths($newPath);

        foreach ($oldThumbs as $index => $oldThumb) {
            $newThumb = $newThumbs[$index] ?? null;
            if (! $newThumb) {
                continue;
            }

            if (Storage::disk($disk)->exists($oldThumb)) {
                Storage::disk($disk)->copy($oldThumb, $newThumb);
            }
        }
    }

    private function generateCopyPath(string $path, string $disk): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $folderPath = trim(dirname($path), '.');
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $candidate = $baseName . '-copy';
        $fileName = $candidate . '.' . $extension;
        $target = $folderPath ? $folderPath . '/' . $fileName : $fileName;

        return $this->ensureUniquePath($target, $disk);
    }

    private function ensureUniquePath(string $path, string $disk): string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $folderPath = trim(dirname($path), '.');
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $index = 1;

        do {
            $candidate = $baseName . '-' . $index++;
            $fileName = $candidate . '.' . $extension;
            $newPath = $folderPath ? $folderPath . '/' . $fileName : $fileName;
        } while (Storage::disk($disk)->exists($newPath));

        return $newPath;
    }
}
