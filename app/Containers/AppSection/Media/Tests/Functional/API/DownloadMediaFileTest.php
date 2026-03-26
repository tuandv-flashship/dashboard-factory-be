<?php

namespace App\Containers\AppSection\Media\Tests\Functional\API;

use App\Containers\AppSection\Media\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\Media\UI\API\Controllers\DownloadMediaFileController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DownloadMediaFileController::class)]
final class DownloadMediaFileTest extends ApiTestCase
{
    public function testDownloadMediaFileByUrlSuccessfully(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');
        config()->set('media.only_view_own_media', false);

        Http::fake([
            'https://example.com/*' => Http::response('hello world', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $user = User::factory()->superAdmin()->createOne();
        $this->actingAs($user);

        $response = $this->postJson(action(DownloadMediaFileController::class), [
            'url' => 'https://example.com/files/sample.txt',
            'visibility' => 'public',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'src', 'url', 'access_mode', 'signed_url'],
        ]);
        $response->assertJsonPath('data.access_mode', null);
        $response->assertJsonPath('data.signed_url', null);
    }

    public function testDownloadMediaFileValidationFailsWhenUrlMissing(): void
    {
        $user = User::factory()->superAdmin()->createOne();
        $this->actingAs($user);

        $response = $this->postJson(action(DownloadMediaFileController::class), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }

    public function testDownloadMediaFileUnauthorizedWhenUnauthenticated(): void
    {
        $response = $this->postJson(action(DownloadMediaFileController::class), [
            'url' => 'https://example.com/files/sample.txt',
        ]);

        $response->assertStatus(401);
    }

    public function testDownloadMediaFileReturnsValidationErrorForDisallowedExtension(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');

        Http::fake([
            'https://example.com/*' => Http::response('binary', 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $user = User::factory()->superAdmin()->createOne();
        $this->actingAs($user);

        $response = $this->postJson(action(DownloadMediaFileController::class), [
            'url' => 'https://example.com/files/sample.exe',
            'visibility' => 'public',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'File type is not allowed.');
        $response->assertJsonPath('error_code', 'file_type_not_allowed');
    }

    public function testDownloadMediaFileReturnsBadGatewayWhenUpstreamFails(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');

        Http::fake([
            'https://example.com/*' => Http::response('upstream error', 500),
        ]);

        $user = User::factory()->superAdmin()->createOne();
        $this->actingAs($user);

        $response = $this->postJson(action(DownloadMediaFileController::class), [
            'url' => 'https://example.com/files/sample.txt',
            'visibility' => 'public',
        ]);

        $response->assertStatus(502);
        $response->assertJsonPath('message', 'Unable to download file.');
        $response->assertJsonPath('error_code', 'download_failed');
    }
}
