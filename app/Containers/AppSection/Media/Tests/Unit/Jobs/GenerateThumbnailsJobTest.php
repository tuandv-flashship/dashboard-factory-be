<?php

namespace App\Containers\AppSection\Media\Tests\Unit\Jobs;

use App\Containers\AppSection\Media\Jobs\GenerateThumbnailsJob;
use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\ThumbnailService;
use App\Containers\AppSection\Media\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GenerateThumbnailsJob::class)]
class GenerateThumbnailsJobTest extends UnitTestCase
{
    public function testHandleCallsServiceGenerate(): void
    {
        $file = MediaFile::query()->create([
            'name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'url' => 'test.jpg',
            'folder_id' => 0,
            'user_id' => 1,
            'visibility' => 'public',
        ]);

        $service = $this->createMock(ThumbnailService::class);
        $service->expects($this->once())
            ->method('generate')
            ->with($file); // Verify it passes the specific file

        $job = new GenerateThumbnailsJob($file);
        $job->handle($service);
    }

    public function testHandleSkipsIfFileDeleted(): void
    {
        $file = MediaFile::query()->create([
            'name' => 'deleted.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'url' => 'deleted.jpg',
            'folder_id' => 0,
            'user_id' => 1,
            'visibility' => 'public',
        ]);
        
        $file->delete(); // Soft delete

        $service = $this->createMock(ThumbnailService::class);
        $service->expects($this->never())->method('generate');

        // We need to refresh/reload the file to ensure deleted_at is populated if job deserializes it
        // Or create job with deleted file instance
        $job = new GenerateThumbnailsJob($file);
        $job->handle($service);
    }
}
