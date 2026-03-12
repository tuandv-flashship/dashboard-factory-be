<?php

namespace App\Containers\AppSection\User\Tests\Functional\API;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\UI\API\Controllers\AdminBootstrapController;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AdminBootstrapController::class)]
final class AdminBootstrapTest extends ApiTestCase
{
    public function testCanGetAdminBootstrapWithProfileLikeUserPayload(): void
    {
        $user = User::factory()->createOne();
        $this->actingAs($user);

        $response = $this->getJson(action(AdminBootstrapController::class));

        $response->assertOk();
        $response->assertJson(
            static fn (AssertableJson $json): AssertableJson => $json
                ->has(
                    'data.user',
                    static fn (AssertableJson $json): AssertableJson => $json
                        ->where('type', 'User')
                        ->where('id', $user->getHashedKey())
                        ->where('email', $user->email)
                        ->where('name', $user->name)
                        ->whereType('email_verified_at', 'string')
                        ->where('gender', $user->gender->value)
                        ->whereType('birth', 'string')
                        ->where('avatar_url', null)
                        ->hasAll(['phone', 'description', 'status'])
                        ->etc(),
                )
                ->has('data.roles')
                ->has('data.permissions')
                ->has('data.admin_menu')
                ->has('data.locale.current')
                ->has('data.locale.available')
                ->etc(),
        );
    }

    public function testPreventAccessByUnauthenticatedUser(): void
    {
        $response = $this->getJson(action(AdminBootstrapController::class));

        $response->assertUnauthorized();
    }
}
