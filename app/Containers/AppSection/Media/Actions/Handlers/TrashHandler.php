<?php

namespace App\Containers\AppSection\Media\Actions\Handlers;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaFolder;

/**
 * Handles trash (soft delete), restore, permanent delete, and empty trash operations.
 *
 * Extracted from MediaGlobalActionAction for SRP.
 */
final class TrashHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handleTrash(array $payload): array
    {
        $skipTrash = (bool) ($payload['skip_trash'] ?? false);

        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! $id) {
                continue;
            }

            if (! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $file = MediaFile::withTrashed()->find($id);
                if (! $file) {
                    continue;
                }

                $skipTrash ? $file->forceDelete() : $file->delete();
                continue;
            }

            $this->deleteFolderRecursive($id, $skipTrash);
        }

        return ['message' => 'Moved to trash successfully.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleRestore(array $payload): array
    {
        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! $id) {
                continue;
            }

            if (! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                MediaFile::onlyTrashed()->where('id', $id)->restore();
                continue;
            }

            $this->restoreFolderRecursive($id);
        }

        return ['message' => 'Restored successfully.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleDelete(array $payload): array
    {
        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! $id) {
                continue;
            }

            if (! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                MediaFile::withTrashed()->where('id', $id)->forceDelete();
                continue;
            }

            $this->deleteFolderRecursive($id, true);
        }

        return ['message' => 'Deleted successfully.'];
    }

    public function handleEmptyTrash(): array
    {
        MediaFile::onlyTrashed()->get()->each(fn (MediaFile $file) => $file->forceDelete());
        MediaFolder::onlyTrashed()->get()->each(fn (MediaFolder $folder) => $folder->forceDelete());

        return ['message' => 'Trash emptied.'];
    }

    private function deleteFolderRecursive(int $folderId, bool $force): void
    {
        $children = MediaFolder::withTrashed()->where('parent_id', $folderId)->get();
        foreach ($children as $child) {
            $this->deleteFolderRecursive($child->getKey(), $force);
        }

        $folder = MediaFolder::withTrashed()->find($folderId);
        if (! $folder) {
            return;
        }

        $force ? $folder->forceDelete() : $folder->delete();
    }

    private function restoreFolderRecursive(int $folderId): void
    {
        $children = MediaFolder::withTrashed()->where('parent_id', $folderId)->get();
        foreach ($children as $child) {
            $this->restoreFolderRecursive($child->getKey());
        }

        MediaFolder::withTrashed()->where('id', $folderId)->restore();
    }
}
