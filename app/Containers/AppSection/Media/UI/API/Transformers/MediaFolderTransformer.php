<?php

namespace App\Containers\AppSection\Media\UI\API\Transformers;

use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class MediaFolderTransformer extends ParentTransformer
{
    /**
     * @param array<int, int> $favoriteFolderIds
     */
    public function __construct(
        private readonly array $favoriteFolderIds = [],
    ) {
    }

    public function transform(MediaFolder $folder): array
    {
        return [
            'id' => $folder->getHashedKey(),
            'is_favorite' => in_array((int) $folder->getKey(), $this->favoriteFolderIds, true),
            'name' => $folder->name,
            'color' => $folder->color,
            'created_at' => $folder->created_at?->toISOString(),
            'updated_at' => $folder->updated_at?->toISOString(),
        ];
    }
}
