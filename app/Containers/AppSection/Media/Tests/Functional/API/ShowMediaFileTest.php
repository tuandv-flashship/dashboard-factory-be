<?php

namespace App\Containers\AppSection\Media\Tests\Functional\API;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Containers\AppSection\Media\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\Media\UI\API\Controllers\ShowMediaFileController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ShowMediaFileController::class)]
final class ShowMediaFileTest extends ApiTestCase
{
    public function testShowPublicMediaFileRedirectsToPublicUrl(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.only_view_own_media', false);

        $user = User::factory()->superAdmin()->createOne();

        Storage::disk('public')->put('files/public-file.txt', 'public');

        $file = MediaFile::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'public-file',
            'mime_type' => 'text/plain',
            'size' => 6,
            'url' => 'files/public-file.txt',
            'visibility' => 'public',
        ]);

        $id = dechex((int) $file->getKey());
        $hash = sha1($id);

        $response = $this->get(action(ShowMediaFileController::class, compact('hash', 'id')));

        $expectedUrl = app(MediaService::class)->url($file->url);

        $response->assertRedirect($expectedUrl);
    }

    public function testShowMediaFileValidationFailsForInvalidRouteParams(): void
    {
        $response = $this->getJson(action(ShowMediaFileController::class, [
            'hash' => 'invalid-hash',
            'id' => 'not-hex',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hash', 'id']);
    }

    public function testShowPrivateMediaFileRequiresAuthenticationForAuthMode(): void
    {
        Storage::fake('local');
        config()->set('media.private_disk', 'local');
        config()->set('media.only_view_own_media', false);

        $user = User::factory()->superAdmin()->createOne();

        Storage::disk('local')->put('private/auth-file.txt', 'private');

        $file = MediaFile::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'auth-file',
            'mime_type' => 'text/plain',
            'size' => 7,
            'url' => 'private/auth-file.txt',
            'visibility' => 'private',
            'access_mode' => 'auth',
        ]);

        $id = dechex((int) $file->getKey());
        $hash = sha1($id);

        $response = $this->get(action(ShowMediaFileController::class, compact('hash', 'id')));

        $response->assertForbidden();
    }
}
