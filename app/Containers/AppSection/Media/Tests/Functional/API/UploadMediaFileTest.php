<?php

namespace App\Containers\AppSection\Media\Tests\Functional\API;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\Media\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\Media\UI\API\Controllers\UploadMediaFileController;
use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UploadMediaFileController::class)]
final class UploadMediaFileTest extends ApiTestCase
{
    public function testUploadMediaFileSuccessfully(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');
        config()->set('media.allowed_admin_to_upload_any_file_types', true);

        $this->actingAs(User::factory()->superAdmin()->createOne());

        $file = UploadedFile::fake()->create('sample.txt', 1, 'text/plain');

        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $file,
            'visibility' => 'public',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'src', 'url', 'access_mode', 'signed_url'],
        ]);
        $response->assertJsonPath('data.access_mode', null);
    }

    public function testUploadMediaFileValidationFailsWhenFileMissing(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());

        $response = $this->postJson(action(UploadMediaFileController::class), [
            'visibility' => 'public',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function testUploadMediaFileUnauthorizedWhenUnauthenticated(): void
    {
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'visibility' => 'public',
        ]);

        $response->assertStatus(401);
    }

    public function testUploadMediaFileReturnsPayloadTooLargeWhenExceedingLimit(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');
        config()->set('media.allowed_admin_to_upload_any_file_types', true);
        config()->set('media.settings_defaults.media_max_file_size', 512);

        Setting::query()->updateOrCreate(
            ['key' => 'media_max_file_size'],
            ['value' => '512']
        );
        app(MediaSettingsStore::class)->clear();

        $this->actingAs(User::factory()->superAdmin()->createOne());

        $file = UploadedFile::fake()->create('oversized.txt', 2, 'text/plain');
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $file,
            'visibility' => 'public',
        ]);

        $response->assertStatus(413);
        $response->assertJsonPath('message', 'File size exceeds the allowed limit.');
        $response->assertJsonPath('error_code', 'file_too_large');
    }
}
