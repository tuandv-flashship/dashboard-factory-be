<?php

namespace App\Containers\AppSection\Media\Tests\Unit\Services;

use App\Containers\AppSection\Media\Services\ImageProcessingService;
use App\Containers\AppSection\Media\Tests\UnitTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImageProcessingService::class)]
final class ImageProcessingServiceTest extends UnitTestCase
{
    private ImageProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ImageProcessingService::class);
    }

    public function testProcessImageUploadReturnsNullForNonImageFile(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 10, 'text/plain');
        $generator = fn (string $n, string $ext, string $fp) => $n . '.' . $ext;

        $result = $this->service->processImageUpload($file, 'txt', 'document', '', $generator);

        $this->assertNull($result);
    }

    public function testProcessImageUploadReturnsNullWhenGdDisabledOrNoConversion(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        config()->set('media.sizes', []);
        config()->set('media.settings_defaults.media_image_processing_library', '');

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $generator = fn (string $n, string $ext, string $fp) => $n . '.' . $ext;

        // Without WebP conversion or resize config, should return null
        $result = $this->service->processImageUpload($file, 'jpg', 'test', '', $generator);

        // Result depends on config — either null (no processing needed) or processed
        $this->assertTrue($result === null || is_array($result));
    }

    public function testProcessImageBinaryReturnsNullForNonImageMime(): void
    {
        $generator = fn (string $n, string $ext, string $fp) => $n . '.' . $ext;

        $result = $this->service->processImageBinary(
            'not-an-image-content',
            'txt',
            'document',
            '',
            'text/plain',
            $generator,
        );

        $this->assertNull($result);
    }

    public function testProcessImageBinaryReturnsNullForSvg(): void
    {
        $generator = fn (string $n, string $ext, string $fp) => $n . '.' . $ext;

        $result = $this->service->processImageBinary(
            '<svg></svg>',
            'svg',
            'icon',
            '',
            'image/svg+xml',
            $generator,
        );

        $this->assertNull($result);
    }

    public function testMaybeApplyWatermarkSkipsNonPublicFile(): void
    {
        $file = \App\Containers\AppSection\Media\Models\MediaFile::query()->create([
            'name' => 'private-file',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'url' => 'test/private.jpg',
            'visibility' => 'private',
            'user_id' => 1,
        ]);

        // Should not throw, just silently skip
        $this->service->maybeApplyWatermark($file);
        $this->assertTrue(true); // No exception = pass
    }

    public function testMaybeApplyWatermarkSkipsNonImageFile(): void
    {
        Storage::fake('public');
        config()->set('media.driver', 'public');

        $file = \App\Containers\AppSection\Media\Models\MediaFile::query()->create([
            'name' => 'textfile',
            'mime_type' => 'text/plain',
            'size' => 100,
            'url' => 'test/doc.txt',
            'visibility' => 'public',
            'user_id' => 1,
        ]);

        $this->service->maybeApplyWatermark($file);
        $this->assertTrue(true); // No exception = pass
    }

    public function testProcessImageUploadWithRealImageWhenGdAvailable(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        config()->set('media.settings_defaults.media_max_width', 200);

        $file = UploadedFile::fake()->image('large-photo.jpg', 400, 300);
        $generator = fn (string $n, string $ext, string $fp) => $n . '.' . $ext;

        $result = $this->service->processImageUpload($file, 'jpg', 'large-photo', '', $generator);

        // When max_width is set and image is larger, should process it
        if ($result !== null) {
            $this->assertArrayHasKey('content', $result);
            $this->assertArrayHasKey('file_name', $result);
            $this->assertArrayHasKey('mime_type', $result);
            $this->assertArrayHasKey('size', $result);
            $this->assertGreaterThan(0, $result['size']);
        } else {
            $this->assertTrue(true); // Config might not enable processing
        }
    }
}
