<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AssembleUploadChunksTask extends ParentTask
{
    public function run(string $disk, string $chunkDir, int $totalChunks, string $originalName): string
    {
        $tempDir = storage_path('app/tmp/media');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $targetPath = $tempDir . '/' . Str::uuid() . '-' . basename($originalName);
        $target = fopen($targetPath, 'wb');

        for ($index = 0; $index < $totalChunks; $index++) {
            $chunkPath = $chunkDir . '/' . $index . '.part';
            $stream = Storage::disk($disk)->readStream($chunkPath);

            if (! is_resource($stream)) {
                if (is_resource($target)) {
                    fclose($target);
                }
                throw new \RuntimeException('Missing chunk data.');
            }

            stream_copy_to_stream($stream, $target);
            fclose($stream);
        }

        fclose($target);

        return $targetPath;
    }
}

