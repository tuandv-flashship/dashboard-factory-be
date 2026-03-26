<?php

namespace App\Containers\AppSection\Media\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ClearChunkUploadsCommand extends Command
{
    protected $signature = 'media:chunks:clear';
    protected $description = 'Clear expired media chunk uploads.';

    public function handle(): int
    {
        $disk = (string) config('media.chunk.storage.disk', 'local');
        $baseDir = trim((string) config('media.chunk.storage.chunks', 'chunks'), '/');

        if ($baseDir === '') {
            $this->warn('Chunk storage path is empty.');
            return self::SUCCESS;
        }

        $cutoff = $this->resolveCutoffTimestamp();
        if ($cutoff === null) {
            $this->warn('Invalid media.chunk.clear.timestamp value.');
            return self::SUCCESS;
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($baseDir)) {
            $this->info('No chunk directories found.');
            return self::SUCCESS;
        }

        $deletedDirs = 0;
        foreach ($storage->directories($baseDir) as $dir) {
            $lastModified = $this->latestModified($storage, $dir);
            if ($lastModified === null || $lastModified < $cutoff) {
                $storage->deleteDirectory($dir);
                $deletedDirs++;
            }
        }

        $deletedFiles = 0;
        foreach ($storage->files($baseDir) as $file) {
            try {
                $lastModified = $storage->lastModified($file);
            } catch (Throwable) {
                continue;
            }

            if ($lastModified < $cutoff) {
                $storage->delete($file);
                $deletedFiles++;
            }
        }

        $deletedTempFiles = $this->clearAssembledTempFiles($cutoff);

        $this->info(sprintf(
            'Deleted %d chunk directories, %d chunk files, %d temp files.',
            $deletedDirs,
            $deletedFiles,
            $deletedTempFiles
        ));

        return self::SUCCESS;
    }

    private function resolveCutoffTimestamp(): ?int
    {
        $expression = (string) config('media.chunk.clear.timestamp', '-3 HOURS');
        $timestamp = strtotime($expression);

        return $timestamp === false ? null : $timestamp;
    }

    private function latestModified(FilesystemAdapter $storage, string $dir): ?int
    {
        $files = $storage->allFiles($dir);
        if ($files === []) {
            return null;
        }

        $latest = null;
        foreach ($files as $file) {
            try {
                $lastModified = $storage->lastModified($file);
            } catch (Throwable) {
                continue;
            }

            if ($latest === null || $lastModified > $latest) {
                $latest = $lastModified;
            }
        }

        return $latest;
    }

    private function clearAssembledTempFiles(int $cutoff): int
    {
        $tempDir = storage_path('app/tmp/media');
        if (! is_dir($tempDir)) {
            return 0;
        }

        $deleted = 0;
        $entries = scandir($tempDir);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $tempDir . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($path)) {
                continue;
            }

            $modified = filemtime($path);
            if ($modified === false || $modified < $cutoff) {
                if (@unlink($path)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
