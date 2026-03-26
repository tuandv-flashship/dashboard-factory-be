<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class ValidateChunkUploadInputTask extends ParentTask
{
    public function __construct(
        private readonly MediaSettingsStore $mediaSettingsStore,
    ) {
    }

    /**
     * @param array{
     *  has_chunk_uuid?: bool,
     *  has_chunk_index?: bool,
     *  dztotalfilesize?: int,
     *  dzchunksize?: int
     * } $input
     *
     * @return array{message:string,code:string}|null
     */
    public function run(array $input, int $maxSize, int $totalChunks): ?array
    {
        $chunkEnabled = $this->mediaSettingsStore->getBool('media_chunk_enabled', (bool) config('media.chunk.enabled', false));
        if (! $chunkEnabled) {
            return ['message' => 'Chunk upload is disabled.', 'code' => 'chunk_upload_disabled'];
        }

        if (! ($input['has_chunk_uuid'] ?? false)) {
            return ['message' => 'Missing chunk uuid.', 'code' => 'missing_chunk_uuid'];
        }

        if (! ($input['has_chunk_index'] ?? false)) {
            return ['message' => 'Missing chunk index.', 'code' => 'missing_chunk_index'];
        }

        $totalSize = (int) ($input['dztotalfilesize'] ?? 0);
        $chunkSize = (int) ($input['dzchunksize'] ?? 0);

        if ($maxSize > 0 && $totalSize <= 0) {
            return ['message' => 'Missing total file size.', 'code' => 'missing_total_file_size'];
        }

        if ($maxSize > 0 && $totalSize > $maxSize) {
            return ['message' => 'File size exceeds the allowed limit.', 'code' => 'file_too_large'];
        }

        if ($totalSize > 0 && $chunkSize > 0) {
            $expectedChunks = (int) ceil($totalSize / $chunkSize);
            if ($expectedChunks !== $totalChunks) {
                return ['message' => 'Chunk count does not match file size.', 'code' => 'chunk_count_mismatch'];
            }
        }

        return null;
    }
}
