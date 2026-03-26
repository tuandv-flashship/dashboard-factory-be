<?php

namespace App\Containers\AppSection\Media\Tests\Unit\Services;

use App\Containers\AppSection\Media\Services\MediaValidationService;
use App\Containers\AppSection\Media\Tests\UnitTestCase;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MediaValidationService::class)]
final class MediaValidationServiceTest extends UnitTestCase
{
    private MediaValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MediaValidationService::class);
    }

    public function testIsAllowedFileReturnsFalseForInvalidUpload(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(false);

        $this->assertFalse($this->service->isAllowedFile($file, null));
    }

    public function testIsAllowedFileReturnsTrueForAllowedExtension(): void
    {
        config()->set('media.allowed_mime_types', 'jpg,png,gif,txt');
        config()->set('media.mime_types', [
            'text' => ['text/plain'],
        ]);

        $file = UploadedFile::fake()->create('test.txt', 1, 'text/plain');

        $this->assertTrue($this->service->isAllowedFile($file, null));
    }

    public function testIsAllowedFileReturnsFalseForDisallowedExtension(): void
    {
        config()->set('media.allowed_mime_types', 'jpg,png,gif');

        $file = UploadedFile::fake()->create('malware.exe', 1, 'application/x-msdownload');

        $this->assertFalse($this->service->isAllowedFile($file, null));
    }

    public function testSuperAdminCanUploadAnyFileTypeWhenConfigEnabled(): void
    {
        config()->set('media.allowed_mime_types', 'jpg,png');
        config()->set('media.allowed_admin_to_upload_any_file_types', true);

        $user = User::factory()->superAdmin()->createOne();
        $file = UploadedFile::fake()->create('script.php', 1, 'application/x-php');

        $this->assertTrue($this->service->isAllowedFile($file, $user));
    }

    public function testSuperAdminCannotUploadAnyFileTypeWhenConfigDisabled(): void
    {
        config()->set('media.allowed_mime_types', 'jpg,png');
        config()->set('media.allowed_admin_to_upload_any_file_types', false);

        $user = User::factory()->superAdmin()->createOne();
        $file = UploadedFile::fake()->create('script.php', 1, 'application/x-php');

        $this->assertFalse($this->service->isAllowedFile($file, $user));
    }

    public function testGetAllowedExtensionsReturnsLowercaseArray(): void
    {
        config()->set('media.allowed_mime_types', 'JPG, PNG, gif, TXT');

        $extensions = $this->service->getAllowedExtensions();

        $this->assertSame(['jpg', 'png', 'gif', 'txt'], $extensions);
    }

    public function testGetAllowedExtensionsHandlesEmptyConfig(): void
    {
        config()->set('media.allowed_mime_types', '');

        $extensions = $this->service->getAllowedExtensions();

        $this->assertSame([], $extensions);
    }

    public function testCreateStorageFileNameGeneratesUniqueFilename(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('existing-file.txt', 'content');

        $fileName = $this->service->createStorageFileName('existing-file', 'txt', '', 'public');

        $this->assertNotSame('existing-file.txt', $fileName);
        $this->assertStringEndsWith('.txt', $fileName);
    }

    public function testCreateStorageFileNameReturnsUuidWhenConfigured(): void
    {
        Storage::fake('public');
        config()->set('media.convert_file_name_to_uuid', true);

        // Must also set the setting in the DB/cache so MediaSettingsStore picks it up
        \App\Containers\AppSection\Setting\Models\Setting::query()->updateOrCreate(
            ['key' => 'media_convert_file_name_to_uuid'],
            ['value' => '1'],
        );
        app(\App\Containers\AppSection\Media\Supports\MediaSettingsStore::class)->clear();

        $service = app(MediaValidationService::class);
        $fileName = $service->createStorageFileName('any-name', 'jpg', '', 'public');

        // UUID format: 8-4-4-4-12 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$/', $fileName);
    }

    public function testGetAllowedMimeTypesIncludesImageJpgFallback(): void
    {
        config()->set('media.mime_types', [
            'image' => ['image/jpeg', 'image/png'],
        ]);

        $mimeTypes = $this->service->getAllowedMimeTypes();

        $this->assertContains('image/jpeg', $mimeTypes);
        $this->assertContains('image/png', $mimeTypes);
        $this->assertContains('image/jpg', $mimeTypes); // Always included as fallback
    }

    public function testIsAllowedMimeTypeAcceptsOctetStream(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/octet-stream');
        $file->method('getClientMimeType')->willReturn('application/octet-stream');

        $this->assertTrue($this->service->isAllowedMimeType($file));
    }
}
