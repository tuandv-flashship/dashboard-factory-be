<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Actions\Handlers\CopyHandler;
use App\Containers\AppSection\Media\Actions\Handlers\TrashHandler;
use App\Containers\AppSection\Media\Actions\Handlers\UserItemsHandler;
use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Ship\Parents\Actions\Action as ParentAction;

/**
 * Dispatches media global actions to dedicated handlers.
 *
 * Each action group is handled by a focused handler class:
 * - TrashHandler: trash, restore, delete, empty_trash
 * - CopyHandler: make_copy
 * - UserItemsHandler: favorite, remove_favorite, add_recent
 * - Inline: move, rename, alt_text, crop, properties (small single-purpose methods)
 */
final class MediaGlobalActionAction extends ParentAction
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly TrashHandler $trashHandler,
        private readonly CopyHandler $copyHandler,
        private readonly UserItemsHandler $userItemsHandler,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function run(string $action, array $payload, int $userId): array
    {
        return match ($action) {
            'trash' => $this->trashHandler->handleTrash($payload),
            'restore' => $this->trashHandler->handleRestore($payload),
            'move' => $this->handleMove($payload),
            'make_copy' => $this->copyHandler->handle($payload, $userId),
            'delete' => $this->trashHandler->handleDelete($payload),
            'favorite' => $this->userItemsHandler->handleFavorite($payload, $userId),
            'remove_favorite' => $this->userItemsHandler->handleRemoveFavorite($payload, $userId),
            'add_recent' => $this->userItemsHandler->handleAddRecent($payload, $userId),
            'crop' => $this->handleCrop($payload),
            'rename' => $this->handleRename($payload),
            'alt_text' => $this->handleAltText($payload),
            'empty_trash' => $this->trashHandler->handleEmptyTrash(),
            'properties' => $this->handleProperties($payload),
            default => [
                'message' => 'Invalid action.',
            ],
        };
    }

    // ─── Inline handlers (small, single-purpose) ─────────────────

    /**
     * @param array<string, mixed> $payload
     */
    private function handleMove(array $payload): array
    {
        $destination = (int) ($payload['destination'] ?? 0);

        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! $id) {
                continue;
            }

            if (! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $file = MediaFile::query()->find($id);
                if ($file) {
                    $this->mediaService->moveFile($file, $destination);
                }

                continue;
            }

            MediaFolder::query()->where('id', $id)->update(['parent_id' => $destination]);
        }

        return ['message' => 'Moved successfully.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleRename(array $payload): array
    {
        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            $name = (string) ($item['name'] ?? '');
            if (! $id || $name === '') {
                continue;
            }

            $renameOnDisk = (bool) ($item['rename_physical_file'] ?? false);

            if (! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $file = MediaFile::query()->find($id);
                if ($file) {
                    $this->mediaService->renameFile($file, $name, $renameOnDisk);
                }

                continue;
            }

            $folder = MediaFolder::query()->find($id);
            if ($folder) {
                $this->mediaService->renameFolder($folder, $name, $renameOnDisk);
            }
        }

        return ['message' => 'Renamed successfully.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleAltText(array $payload): array
    {
        foreach ((array) ($payload['selected'] ?? []) as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! $id) {
                continue;
            }

            MediaFile::query()->where('id', $id)->update([
                'alt' => $item['alt'] ?? null,
            ]);
        }

        return ['message' => 'Alt text updated.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleCrop(array $payload): array
    {
        $imageId = (int) ($payload['imageId'] ?? 0);
        if (! $imageId) {
            return ['message' => 'Invalid image.'];
        }

        $cropData = $payload['cropData'] ?? null;
        if (is_string($cropData)) {
            $cropData = json_decode($cropData, true);
        }

        if (! is_array($cropData)) {
            return ['message' => 'Invalid crop data.'];
        }

        $x = (int) ($cropData['x'] ?? 0);
        $y = (int) ($cropData['y'] ?? 0);
        $width = (int) ($cropData['width'] ?? 0);
        $height = (int) ($cropData['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return ['message' => 'Invalid crop dimensions.'];
        }

        $file = MediaFile::query()->find($imageId);
        if (! $file) {
            return ['message' => 'Image not found.'];
        }

        $result = $this->mediaService->cropImage($file, $x, $y, $width, $height);

        return $result
            ? ['message' => 'Cropped successfully.']
            : ['message' => 'Failed to crop image.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleProperties(array $payload): array
    {
        $color = (string) ($payload['color'] ?? '');
        $selected = (array) ($payload['selected'] ?? []);

        if ($color === '') {
            return ['message' => 'Color is required.'];
        }

        $ids = array_map(static fn ($item) => (int) ($item['id'] ?? 0), $selected);
        $ids = array_filter($ids);

        MediaFolder::query()->whereIn('id', $ids)->update(['color' => $color]);

        return ['message' => 'Properties updated.'];
    }
}
