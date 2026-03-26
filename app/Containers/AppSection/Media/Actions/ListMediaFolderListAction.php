<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ListMediaFolderListAction extends ParentAction
{
    /**
     * @param array<int, int> $excludeIds
     */
    public function run(int $parentId, array $excludeIds = []): array
    {
        $allExcludeIds = $this->getAllDescendantFolderIds($excludeIds);

        $query = MediaFolder::query()
            ->where(function ($builder) use ($parentId): void {
                if (! $parentId) {
                    $builder->whereNull('parent_id')
                        ->orWhere('parent_id', 0)
                        ->orWhere('parent_id', '0');
                } else {
                    $builder->where('parent_id', $parentId);
                }
            })
            ->whereNotIn('id', $allExcludeIds);

        $folders = $query->orderBy('name')
            ->get(['id', 'name', 'parent_id'])
            ->map(function (MediaFolder $folder) use ($allExcludeIds) {
                return [
                    'id' => $folder->getHashedKey(),
                    'name' => $folder->name,
                    'parent_id' => $this->hashId($folder->parent_id),
                    'has_children' => MediaFolder::query()
                        ->where('parent_id', $folder->getKey())
                        ->whereNotIn('id', $allExcludeIds)
                        ->exists(),
                ];
            });

        $currentFolder = $parentId
            ? MediaFolder::query()->find($parentId, ['id', 'name', 'parent_id'])
            : null;

        return [
            'current_folder' => $currentFolder ? [
                'id' => $currentFolder->getHashedKey(),
                'name' => $currentFolder->name,
                'parent_id' => $this->hashId($currentFolder->parent_id),
            ] : [
                'id' => 0,
                'name' => 'Root',
                'parent_id' => null,
            ],
            'folders' => $folders,
            'breadcrumbs' => $this->buildFolderBreadcrumbs($parentId),
        ];
    }

    /**
     * @param array<int, int> $folderIds
     * @return array<int, int>
     */
    private function getAllDescendantFolderIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        $allIds = $folderIds;
        $childIds = MediaFolder::query()
            ->whereIn('parent_id', $allIds)
            ->pluck('id')
            ->toArray();

        if ($childIds !== []) {
            $allIds = array_merge($allIds, $this->getAllDescendantFolderIds($childIds));
        }

        return array_values(array_unique($allIds));
    }

    private function buildFolderBreadcrumbs(int $folderId): array
    {
        $breadcrumbs = [['id' => 0, 'name' => 'Root']];

        if (! $folderId) {
            return $breadcrumbs;
        }

        $folder = MediaFolder::query()->find($folderId);
        $path = [];

        while ($folder) {
            array_unshift($path, ['id' => $folder->getHashedKey(), 'name' => $folder->name]);
            $folder = $folder->parent_id
                ? MediaFolder::query()->find($folder->parent_id)
                : null;
        }

        return array_merge($breadcrumbs, $path);
    }


}
