<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Tasks\CreateUploadedMediaFileResultTask;
use App\Containers\AppSection\Media\Tasks\HandleChunkUploadTask;
use App\Containers\AppSection\Media\Tasks\ValidateChunkUploadInputTask;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\Media\Values\MediaFileOperationResult;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Http\UploadedFile;

final class UploadMediaFileAction extends ParentAction
{
    public function __construct(
        private readonly MediaSettingsStore $mediaSettingsStore,
        private readonly CreateUploadedMediaFileResultTask $createUploadedMediaFileResultTask,
        private readonly ValidateChunkUploadInputTask $validateChunkUploadInputTask,
        private readonly HandleChunkUploadTask $handleChunkUploadTask,
    ) {
    }

    /**
     * @param array{
     *  folder_id?: int,
     *  visibility?: string|null,
     *  access_mode?: string|null,
     *  dzuuid?: string|null,
     *  dzchunkindex?: int|null,
     *  dztotalchunkcount?: int,
     *  dztotalfilesize?: int,
     *  dzchunksize?: int,
     *  filename?: string|null,
     *  has_chunk_index?: bool,
     *  has_chunk_uuid?: bool
     * } $input
     *
     */
    public function run(array $input, ?UploadedFile $file, int $userId): MediaFileOperationResult
    {
        $totalChunks = max(1, (int) ($input['dztotalchunkcount'] ?? 1));
        $maxSize = $this->mediaSettingsStore->getInt('media_max_file_size', (int) config('media.chunk.max_file_size', 0));

        if ($totalChunks > 1) {
            $chunkValidationError = $this->validateChunkUploadInputTask->run($input, $maxSize, $totalChunks);
            if ($chunkValidationError !== null) {
                if ($chunkValidationError['code'] === 'file_too_large') {
                    return MediaFileOperationResult::payloadTooLarge(
                        $chunkValidationError['message'],
                        $chunkValidationError['code'],
                    );
                }

                return MediaFileOperationResult::validationError(
                    $chunkValidationError['message'],
                    $chunkValidationError['code'],
                );
            }

            if (! $file instanceof UploadedFile) {
                return MediaFileOperationResult::validationError(
                    'Missing chunk file.',
                    'missing_chunk_file',
                );
            }

            return $this->handleChunkUploadTask->run($input, $file, $userId, $totalChunks, $maxSize);
        }

        if (! $file instanceof UploadedFile) {
            return MediaFileOperationResult::validationError(
                'Missing upload file.',
                'missing_upload_file',
            );
        }

        if ($maxSize > 0 && $file->getSize() > $maxSize) {
            return MediaFileOperationResult::payloadTooLarge();
        }

        return $this->createUploadedMediaFileResultTask->run(
            $file,
            (int) ($input['folder_id'] ?? 0),
            $userId,
            $input['visibility'] ?? null,
            $input['access_mode'] ?? null,
        );
    }
}
