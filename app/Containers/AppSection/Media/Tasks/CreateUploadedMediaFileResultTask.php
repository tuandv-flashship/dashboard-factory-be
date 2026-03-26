<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\Media\Values\MediaFileOperationResult;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class CreateUploadedMediaFileResultTask extends ParentTask
{
    public function __construct(
        private readonly StoreUploadedMediaFileTask $storeUploadedMediaFileTask,
        private readonly BuildDownloadMediaFilePayloadTask $buildDownloadMediaFilePayloadTask,
    ) {
    }

    public function run(
        UploadedFile $file,
        int $folderId,
        int $userId,
        ?string $visibility = null,
        ?string $accessMode = null,
    ): MediaFileOperationResult {
        try {
            $mediaFile = $this->storeUploadedMediaFileTask->run(
                $file,
                $folderId,
                $userId,
                $visibility,
                $accessMode,
            );
        } catch (InvalidArgumentException $exception) {
            $code = $exception->getMessage() === 'File type is not allowed.'
                ? 'file_type_not_allowed'
                : 'invalid_media_input';

            return MediaFileOperationResult::validationError($exception->getMessage(), $code);
        } catch (RuntimeException $exception) {
            return MediaFileOperationResult::internalError(
                $exception->getMessage(),
                'media_store_failed',
            );
        }

        return MediaFileOperationResult::success(
            $this->buildDownloadMediaFilePayloadTask->run($mediaFile)
        );
    }
}
