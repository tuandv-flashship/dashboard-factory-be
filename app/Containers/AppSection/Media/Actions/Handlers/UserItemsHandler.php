<?php

namespace App\Containers\AppSection\Media\Actions\Handlers;

use App\Containers\AppSection\Media\Models\MediaSetting;
use App\Containers\AppSection\Media\Services\MediaService;
use Illuminate\Support\Arr;

/**
 * Handles user-specific media operations: favorites and recent items.
 *
 * Extracted from MediaGlobalActionAction for SRP.
 */
final class UserItemsHandler
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleFavorite(array $payload, int $userId): array
    {
        $meta = MediaSetting::query()->firstOrCreate([
            'key' => 'favorites',
            'user_id' => $userId,
        ]);

        $current = is_array($meta->value) ? $meta->value : [];
        $selected = (array) ($payload['selected'] ?? []);

        $meta->value = array_values(array_merge($current, $selected));
        $meta->save();
        $this->mediaService->forgetUserItemsCache($userId);

        return ['message' => 'Added to favorites.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleRemoveFavorite(array $payload, int $userId): array
    {
        $meta = MediaSetting::query()->firstOrCreate([
            'key' => 'favorites',
            'user_id' => $userId,
        ]);

        $value = is_array($meta->value) ? $meta->value : [];
        $selected = (array) ($payload['selected'] ?? []);

        $meta->value = array_values(array_filter($value, function ($item) use ($selected) {
            foreach ($selected as $selectedItem) {
                if (
                    Arr::get($item, 'is_folder') == Arr::get($selectedItem, 'is_folder') &&
                    Arr::get($item, 'id') == Arr::get($selectedItem, 'id')
                ) {
                    return false;
                }
            }

            return true;
        }));

        $meta->save();
        $this->mediaService->forgetUserItemsCache($userId);

        return ['message' => 'Removed from favorites.'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handleAddRecent(array $payload, int $userId): array
    {
        $item = (array) ($payload['item'] ?? []);
        $itemId = (int) ($item['id'] ?? 0);

        if (! $itemId) {
            return ['message' => 'Invalid item.'];
        }

        $meta = MediaSetting::query()->firstOrCreate([
            'key' => 'recent_items',
            'user_id' => $userId,
        ]);

        $value = is_array($meta->value) ? $meta->value : [];
        $recentItem = [
            'id' => $itemId,
            'is_folder' => filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $value = array_values(array_filter($value, function ($existing) use ($recentItem) {
            return ! (
                Arr::get($existing, 'id') == $recentItem['id'] &&
                Arr::get($existing, 'is_folder') == $recentItem['is_folder']
            );
        }));

        array_unshift($value, $recentItem);
        $value = array_slice($value, 0, 20);

        $meta->value = $value;
        $meta->save();
        $this->mediaService->forgetUserItemsCache($userId);

        return ['message' => 'Added to recent.'];
    }
}
