<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Http\UploadedFile;

final class StoreUploadedMediaFileTask extends ParentTask
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {
    }

    public function run(
        UploadedFile $file,
        int $folderId,
        int $userId,
        ?string $visibility = null,
        ?string $accessMode = null,
    ): MediaFile {
        $mediaFile = $this->mediaService->storeUploadedFile($file, $folderId, $userId, $visibility, $accessMode);

        AuditLogRecorder::recordModel('created', $mediaFile);

        return $mediaFile;
    }
}

