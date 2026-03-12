<?php

namespace App\Containers\AppSection\User\Tests\Functional\API;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\UI\API\Controllers\UpdateUserAvatarController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateUserAvatarController::class)]
final class UpdateUserAvatarTest extends ApiTestCase
{
    public function testCanUpdateAvatarAsOwner(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');

        $user = User::factory()->createOne();
        $this->actingAs($user);

        $response = $this->post(
            URL::action(UpdateUserAvatarController::class, $user->getHashedKey()),
            ['avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200)],
            ['Accept' => 'application/json'],
        );

        $response->assertOk();
        $response->assertJson(
            static fn (AssertableJson $json): AssertableJson => $json->has(
                'data',
                static fn (AssertableJson $json): AssertableJson => $json
                    ->where('type', 'User')
                    ->where('id', $user->getHashedKey())
                    ->whereType('avatar_url', 'string')
                    ->etc(),
            )->etc(),
        );

        $this->assertNotNull($user->fresh()->avatar_id);
    }

    public function testPreventAccessByUnauthenticatedUser(): void
    {
        $user = User::factory()->createOne();

        $response = $this->post(
            URL::action(UpdateUserAvatarController::class, $user->getHashedKey()),
            ['avatar' => UploadedFile::fake()->image('avatar.jpg')],
            ['Accept' => 'application/json'],
        );

        $response->assertUnauthorized();
    }

    public function testGivenUserHasNoAccessPreventsOperation(): void
    {
        Storage::fake('public');
        config()->set('media.disk', 'public');
        config()->set('media.driver', 'public');

        $this->actingAs(User::factory()->createOne());
        $targetUser = User::factory()->createOne();

        $response = $this->post(
            URL::action(UpdateUserAvatarController::class, $targetUser->getHashedKey()),
            ['avatar' => UploadedFile::fake()->image('avatar.jpg')],
            ['Accept' => 'application/json'],
        );

        $response->assertForbidden();
    }

    public function testValidationFailsWhenAvatarIsMissing(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user);

        $response = $this->post(
            URL::action(UpdateUserAvatarController::class, $user->getHashedKey()),
            [],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }
}
