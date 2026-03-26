<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Tasks\BuildDownloadMediaFilePayloadTask;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Containers\AppSection\Media\Values\MediaFileOperationResult;
use App\Ship\Parents\Actions\Action as ParentAction;
use InvalidArgumentException;
use RuntimeException;

final class DownloadMediaFileAction extends ParentAction
{
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly BuildDownloadMediaFilePayloadTask $buildDownloadMediaFilePayloadTask,
    ) {
    }

    public function run(
        string $url,
        int $folderId,
        int $userId,
        ?string $visibility = null,
        ?string $accessMode = null
    ): MediaFileOperationResult
    {
        try {
            $file = $this->mediaService->downloadFromUrl($url, $folderId, $userId, $visibility, $accessMode);
        } catch (InvalidArgumentException $exception) {
            $code = $exception->getMessage() === 'File type is not allowed.'
                ? 'file_type_not_allowed'
                : 'invalid_download_input';

            return MediaFileOperationResult::validationError($exception->getMessage(), $code);
        } catch (RuntimeException $exception) {
            return MediaFileOperationResult::externalServiceError(
                $exception->getMessage(),
                'download_failed',
            );
        }

        return MediaFileOperationResult::success(
            $this->buildDownloadMediaFilePayloadTask->run($file)
        );
    }
}
