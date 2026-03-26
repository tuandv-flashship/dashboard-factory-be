<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\Media\Values\MediaFileOperationResult;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class HandleChunkUploadTask extends ParentTask
{
    public function __construct(
        private readonly StoreUploadChunkPartTask $storeUploadChunkPartTask,
        private readonly HasAllUploadChunksTask $hasAllUploadChunksTask,
        private readonly AssembleUploadChunksTask $assembleUploadChunksTask,
        private readonly CreateUploadedMediaFileResultTask $createUploadedMediaFileResultTask,
    ) {
    }

    /**
     * @param array{
     *  folder_id?: int,
     *  visibility?: string|null,
     *  access_mode?: string|null,
     *  dzuuid?: string|null,
     *  dzchunkindex?: int|null,
     *  filename?: string|null
     * } $input
     *
     */
    public function run(
        array $input,
        UploadedFile $file,
        int $userId,
        int $totalChunks,
        int $maxSize = 0,
    ): MediaFileOperationResult
    {
        $chunkIndex = (int) ($input['dzchunkindex'] ?? 0);
        if ($chunkIndex >= $totalChunks) {
            return MediaFileOperationResult::validationError(
                'Invalid chunk index.',
                'invalid_chunk_index',
            );
        }

        $uuid = (string) ($input['dzuuid'] ?? (string) Str::uuid());
        $disk = (string) config('media.chunk.storage.disk', 'local');
        $baseDir = trim((string) config('media.chunk.storage.chunks', 'chunks'), '/');
        $chunkDir = $baseDir . '/' . $uuid;

        $this->storeUploadChunkPartTask->run($disk, $chunkDir, $file, $chunkIndex);

        $done = (int) ceil((($chunkIndex + 1) / max(1, $totalChunks)) * 100);
        if (($chunkIndex + 1) < $totalChunks) {
            return MediaFileOperationResult::success(
                $this->buildChunkProgressPayload($done, $chunkIndex, $totalChunks)
            );
        }

        if (! $this->hasAllUploadChunksTask->run($disk, $chunkDir, $totalChunks)) {
            return MediaFileOperationResult::accepted(
                $this->buildChunkProgressPayload($done, $chunkIndex, $totalChunks)
            );
        }

        $originalName = (string) ($input['filename'] ?? $file->getClientOriginalName());
        $assembledPath = null;

        try {
            $assembledPath = $this->assembleUploadChunksTask->run($disk, $chunkDir, $totalChunks, $originalName);

            $uploadedFile = new UploadedFile(
                $assembledPath,
                $originalName,
                $file->getClientMimeType() ?: null,
                null,
                true
            );

            $assembledSize = $uploadedFile->getSize();
            if ($assembledSize === false || $assembledSize === null) {
                $rawSize = filesize($assembledPath);
                $assembledSize = is_numeric($rawSize) ? (int) $rawSize : 0;
            }

            if ($maxSize > 0 && (int) $assembledSize > $maxSize) {
                return MediaFileOperationResult::payloadTooLarge();
            }

            return $this->createUploadedMediaFileResultTask->run(
                $uploadedFile,
                (int) ($input['folder_id'] ?? 0),
                $userId,
                $input['visibility'] ?? null,
                $input['access_mode'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return MediaFileOperationResult::validationError(
                $exception->getMessage(),
                'chunk_assemble_failed',
            );
        } catch (Throwable $exception) {
            return MediaFileOperationResult::internalError(
                $exception->getMessage(),
                'chunk_processing_failed',
            );
        } finally {
            if ($assembledPath !== null && is_file($assembledPath)) {
                unlink($assembledPath);
            }
            $this->storeUploadChunkPartTask->deleteChunkDirectory($disk, $chunkDir);
        }
    }

    /**
     * @return array{chunked:bool,done:int,chunk_index:int,chunk_total:int}
     */
    private function buildChunkProgressPayload(int $done, int $chunkIndex, int $totalChunks): array
    {
        return [
            'chunked' => true,
            'done' => $done,
            'chunk_index' => $chunkIndex,
            'chunk_total' => $totalChunks,
        ];
    }
}
