<?php

namespace App\Containers\AppSection\Media\Tests\Functional\API;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\Media\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\Media\UI\API\Controllers\UploadMediaFileController;
use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UploadMediaFileController::class)]
final class ChunkUploadTest extends ApiTestCase
{
    private function setupChunkUploadConfig(bool $enabled = true, int $maxFileSize = 2048): void
    {
        Storage::fake('local');
        Storage::fake('public');

        config()->set('media.chunk.storage.disk', 'local');
        config()->set('media.chunk.storage.chunks', 'chunks');
        config()->set('media.chunk.max_file_size', $maxFileSize);
        config()->set('media.settings_defaults.media_chunk_enabled', $enabled);
        config()->set('media.settings_defaults.media_max_file_size', $maxFileSize);
        config()->set('media.allowed_admin_to_upload_any_file_types', true);

        Setting::query()->updateOrCreate(
            ['key' => 'media_chunk_enabled'],
            ['value' => $enabled ? '1' : '0']
        );
        Setting::query()->updateOrCreate(
            ['key' => 'media_max_file_size'],
            ['value' => (string) $maxFileSize]
        );

        app(MediaSettingsStore::class)->clear();
        $this->actingAs(User::factory()->superAdmin()->createOne());
    }

    public function testChunkUploadFlow(): void
    {
        config()->set('media.chunk.enabled', true);
        $this->setupChunkUploadConfig(true);

        $uuid = (string) Str::uuid();
        $chunkSize = 1024;
        $totalSize = 2048;

        $firstChunk = UploadedFile::fake()->create('chunk.txt', 1, 'text/plain');
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $firstChunk,
            'dzuuid' => $uuid,
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 2,
            'dztotalfilesize' => $totalSize,
            'dzchunksize' => $chunkSize,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.chunked', true);

        $secondChunk = UploadedFile::fake()->create('chunk.txt', 1, 'text/plain');
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $secondChunk,
            'dzuuid' => $uuid,
            'dzchunkindex' => 1,
            'dztotalchunkcount' => 2,
            'dztotalfilesize' => $totalSize,
            'dzchunksize' => $chunkSize,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'src', 'url'],
        ]);
    }

    public function testChunkUploadReturnsValidationErrorWhenChunkUploadDisabled(): void
    {
        config()->set('media.chunk.enabled', false);
        $this->setupChunkUploadConfig(false);

        $chunk = UploadedFile::fake()->create('chunk.txt', 1, 'text/plain');
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $chunk,
            'dzuuid' => (string) Str::uuid(),
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 2,
            'dztotalfilesize' => 2048,
            'dzchunksize' => 1024,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Chunk upload is disabled.');
        $response->assertJsonPath('error_code', 'chunk_upload_disabled');
    }

    public function testChunkUploadReturnsValidationErrorWhenChunkCountMismatch(): void
    {
        config()->set('media.chunk.enabled', true);
        $this->setupChunkUploadConfig(true);

        $chunk = UploadedFile::fake()->create('chunk.txt', 1, 'text/plain');
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $chunk,
            'dzuuid' => (string) Str::uuid(),
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 3,
            'dztotalfilesize' => 2048,
            'dzchunksize' => 1024,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Chunk count does not match file size.');
        $response->assertJsonPath('error_code', 'chunk_count_mismatch');
    }

    public function testChunkUploadReturnsValidationErrorWhenChunkIndexIsInvalid(): void
    {
        config()->set('media.chunk.enabled', true);
        $this->setupChunkUploadConfig(true);

        $chunk = UploadedFile::fake()->create('chunk.txt', 1, 'text/plain');
        $response = $this->postJson(action(UploadMediaFileController::class), [
            'file' => $chunk,
            'dzuuid' => (string) Str::uuid(),
            'dzchunkindex' => 2,
            'dztotalchunkcount' => 2,
            'dztotalfilesize' => 2048,
            'dzchunksize' => 1024,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Invalid chunk index.');
        $response->assertJsonPath('error_code', 'invalid_chunk_index');
    }

    public function testChunkUploadReturnsPayloadTooLargeFromAssembledFileSize(): void
    {
        config()->set('media.chunk.enabled', true);
        $this->setupChunkUploadConfig(true, 1024);

        $uuid = (string) Str::uuid();

        $firstChunk = UploadedFile::fake()->createWithContent('chunk.txt', str_repeat('a', 700_000));
        $response = $this->post(action(UploadMediaFileController::class), [
            'file' => $firstChunk,
            'dzuuid' => $uuid,
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 2,
            'dztotalfilesize' => 1024,
            'dzchunksize' => 512,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('data.chunked', true);

        $secondChunk = UploadedFile::fake()->createWithContent('chunk.txt', str_repeat('b', 700_000));
        $response = $this->post(action(UploadMediaFileController::class), [
            'file' => $secondChunk,
            'dzuuid' => $uuid,
            'dzchunkindex' => 1,
            'dztotalchunkcount' => 2,
            'dztotalfilesize' => 1024,
            'dzchunksize' => 512,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(413);
        $response->assertJsonPath('message', 'File size exceeds the allowed limit.');
        $response->assertJsonPath('error_code', 'file_too_large');
    }
}
