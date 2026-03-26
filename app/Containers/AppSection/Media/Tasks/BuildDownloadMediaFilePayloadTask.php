<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class BuildDownloadMediaFilePayloadTask extends ParentTask
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {
    }

    /**
     * @return array{id:string,src:?string,url:string,access_mode:?string,signed_url:?string}
     */
    public function run(MediaFile $file): array
    {
        $signedUrl = $this->mediaService->getSignedUrl($file);
        $accessMode = $this->mediaService->resolveAccessModeForFile($file);

        return [
            'id' => $file->getHashedKey(),
            'src' => $file->visibility === 'public' ? $this->mediaService->url($file->url) : $signedUrl,
            'url' => $file->url,
            'access_mode' => $accessMode,
            'signed_url' => $signedUrl,
        ];
    }
}

