<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Collection;

final class ListMediaFolderTreeAction extends ParentAction
{
    /**
     * @param array<int, int> $excludeIds
     */
    public function run(array $excludeIds = []): array
    {
        $allExcludeIds = $this->getAllDescendantFolderIds($excludeIds);

        $folders = MediaFolder::query()
            ->whereNotIn('id', $allExcludeIds)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return [
            'tree' => $this->buildFolderTree($folders, null),
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

    private function buildFolderTree(Collection $folders, int|string|null $parentId = null): array
    {
        $tree = [];

        $children = $folders->filter(function ($folder) use ($parentId) {
            if ($parentId === null) {
                return $folder->parent_id === null || $folder->parent_id === 0 || $folder->parent_id === '0';
            }

            return (int) $folder->parent_id === (int) $parentId;
        });

        foreach ($children as $folder) {
            $childTree = $this->buildFolderTree($folders, $folder->id);

            $tree[] = [
                'id' => $folder->getHashedKey(),
                'name' => $folder->name,
                'parent_id' => $this->hashId($folder->parent_id),
                'children' => $childTree,
                'has_children' => count($childTree) > 0,
            ];
        }

        return $tree;
    }


}
