<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Storage;

final class HasAllUploadChunksTask extends ParentTask
{
    public function run(string $disk, string $chunkDir, int $totalChunks): bool
    {
        for ($index = 0; $index < $totalChunks; $index++) {
            if (! Storage::disk($disk)->exists($chunkDir . '/' . $index . '.part')) {
                return false;
            }
        }

        return true;
    }
}

