<?php

namespace App\Containers\AppSection\Media\Tests\Unit\Services;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaSetting;
use App\Containers\AppSection\Media\Services\ImageProcessingService;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Containers\AppSection\Media\Services\MediaValidationService;
use App\Containers\AppSection\Media\Services\ThumbnailService;
use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\Media\Tests\UnitTestCase;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\Media\Jobs\GenerateThumbnailsJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MediaService::class)]
final class MediaServiceTest extends UnitTestCase
{
    private MediaService $service;
    private $settingsStore;
    private $thumbnailService;
    private $imageProcessingService;
    private $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->settingsStore = $this->createMock(MediaSettingsStore::class);
        $this->thumbnailService = $this->createMock(ThumbnailService::class);
        $this->imageProcessingService = $this->createMock(ImageProcessingService::class);
        $this->validationService = $this->createMock(MediaValidationService::class);

        $this->service = new MediaService(
            $this->settingsStore,
            $this->thumbnailService,
            $this->imageProcessingService,
            $this->validationService,
        );
    }

    public function testGetSignedUrlDelegatesToUrlGeneration(): void
    {
        $user = User::factory()->superAdmin()->createOne();
        $file = MediaFile::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'private-file',
            'mime_type' => 'text/plain',
            'size' => 10,
            'url' => 'private/test.txt',
            'visibility' => 'private',
            'access_mode' => 'signed',
        ]);

        // Integration test relying on actual URL generation logic within MediaService (unchanged parts)
        // or we can test if it returns a string. MediaService::getSignedUrl logic is still inline for now
        // based on previous refactor or if it delegates to a UrlService (User didn't extract UrlService yet).
        // Based on refactor: getSignedUrl is still in MediaService.

        $signedUrl = $this->service->getSignedUrl($file);

        $this->assertNotNull($signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
    }

    public function testProcessImageUploadDelegatesToProcessingService(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg');

        $this->validationService->method('isAllowedFile')->willReturn(true);
        $this->validationService->method('createStorageFileName')->willReturn('test.jpg');
        
        $this->imageProcessingService->expects($this->once())
            ->method('processImageUpload')
            ->willReturn([
                'content' => 'processed', 
                'file_name' => 'test.jpg', 
                'mime_type' => 'image/jpeg', 
                'size' => 10
            ]);

        $this->service->storeUploadedFile($file, 0, $user->id);
    }
    
    public function testRecentAndFavoriteItemsIntegration(): void
    {
        $user = User::factory()->createOne();
        
        // Re-instantiate service from container for integration testing of DB parts
        $containerService = app(MediaService::class);

        MediaSetting::query()->create([
            'key' => 'recent_items',
            'user_id' => $user->getKey(),
            'value' => [['id' => 1, 'is_folder' => false]],
        ]);

        $this->assertSame([['id' => 1, 'is_folder' => false]], $containerService->getRecentItems((int) $user->getKey()));
    }

    public function testThumbnailGenerationUsesQueueWhenConfigured(): void
    {
        Storage::fake('public');
        Queue::fake();
        // Set both keys to be safe
        Config::set('appSection-media.media.queue_thumbnails', true);
        Config::set('media.generate_thumbnails_enabled', true);
        Config::set('media.enable_thumbnail_sizes', true);
        Config::set('appSection-media.media.generate_thumbnails_enabled', true);
        Config::set('appSection-media.media.enable_thumbnail_sizes', true);

        // Mock settings store to allow thumbnail generation
        $this->settingsStore->method('getBool')->willReturn(true);

        $file = UploadedFile::fake()->image('test.jpg');
        $this->validationService->method('isAllowedFile')->willReturn(true);
        $this->validationService->method('createStorageFileName')->willReturn('test.jpg');
        
        $this->service->storeUploadedFile($file, 0, User::factory()->create()->id);

        Queue::assertPushed(GenerateThumbnailsJob::class);
    }

    public function testThumbnailGenerationUsesServiceWhenQueueDisabled(): void
    {
        Storage::fake('public');
        Queue::fake();
        Config::set('appSection-media.media.queue_thumbnails', false);
        Config::set('media.generate_thumbnails_enabled', true);
        Config::set('media.enable_thumbnail_sizes', true);
        Config::set('appSection-media.media.generate_thumbnails_enabled', true);
        Config::set('appSection-media.media.enable_thumbnail_sizes', true);

        // Mock settings store to allow thumbnail generation
        $this->settingsStore->method('getBool')->willReturn(true);

        $file = UploadedFile::fake()->image('test.jpg');
        $this->validationService->method('isAllowedFile')->willReturn(true);
        $this->validationService->method('createStorageFileName')->willReturn('test.jpg');

        $this->thumbnailService->expects($this->once())
            ->method('generate');

        $this->service->storeUploadedFile($file, 0, User::factory()->create()->id);

        Queue::assertNotPushed(GenerateThumbnailsJob::class);
    }
}
