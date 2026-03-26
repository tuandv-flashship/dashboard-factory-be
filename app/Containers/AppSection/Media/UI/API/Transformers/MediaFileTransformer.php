<?php

namespace App\Containers\AppSection\Media\UI\API\Transformers;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class MediaFileTransformer extends ParentTransformer
{
    /**
     * @param array<int, int> $favoriteFileIds
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly bool $includeSignedUrl = false,
        private readonly array $favoriteFileIds = [],
    )
    {
    }

    public function transform(MediaFile $file): array
    {
        $accessMode = $this->mediaService->resolveAccessModeForFile($file);

        $data = [
            'id' => $file->getHashedKey(),
            'is_favorite' => in_array((int) $file->getKey(), $this->favoriteFileIds, true),
            'name' => $file->name,
            'basename' => $file->basename,
            'url' => $file->url,
            'full_url' => $file->visibility === 'public' ? $this->mediaService->url($file->url) : null,
            'type' => $file->type,
            'thumb' => $file->visibility === 'public' && $file->canGenerateThumbnails()
                ? $this->mediaService->getImageUrl($file->url, 'thumb')
                : null,
            'size' => $file->human_size,
            'mime_type' => $file->mime_type,
            'created_at' => $file->created_at?->toISOString(),
            'updated_at' => $file->updated_at?->toISOString(),
            'options' => $file->options,
            'folder_id' => $this->hashId($file->folder_id),
            'preview_url' => $file->preview_url,
            'preview_type' => $file->preview_type,
            'indirect_url' => $file->indirect_url,
            'alt' => $file->alt,
            'visibility' => $file->visibility,
            'access_mode' => $accessMode,
        ];

        if ($this->includeSignedUrl) {
            $data['signed_url'] = $accessMode === 'signed' ? $this->mediaService->getSignedUrl($file) : null;
        }

        return $data;
    }


}
