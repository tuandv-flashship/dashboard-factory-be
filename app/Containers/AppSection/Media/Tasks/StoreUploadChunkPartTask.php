<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreUploadChunkPartTask extends ParentTask
{
    public function run(string $disk, string $chunkDir, UploadedFile $file, int $chunkIndex): void
    {
        Storage::disk($disk)->makeDirectory($chunkDir);
        Storage::disk($disk)->putFileAs($chunkDir, $file, $chunkIndex . '.part');
    }

    public function deleteChunkDirectory(string $disk, string $chunkDir): void
    {
        Storage::disk($disk)->deleteDirectory($chunkDir);
    }
}

