<?php

namespace App\Containers\AppSection\User\Tests\Functional\API;

use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\UI\API\Controllers\CreateUserController;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CreateUserController::class)]
final class CreateUserTest extends ApiTestCase
{
    public function testCanCreateUser(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());

        $data = [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'gender' => Gender::MALE->value,
        ];

        $response = $this->postJson(action(CreateUserController::class), $data);

        $response->assertCreated();
        $response->assertJson(
            static fn (AssertableJson $json): AssertableJson => $json
                ->has(
                    'data',
                    static fn (AssertableJson $json): AssertableJson => $json
                        ->where('type', 'User')
                        ->where('name', $data['name'])
                        ->where('email', $data['email'])
                        ->where('gender', $data['gender'])
                        ->etc(),
                )
                ->etc(),
        );

        $this->assertDatabaseHas('users', [
            'email' => $data['email'],
            'name' => $data['name'],
        ]);
    }

    public function testCanCreateUserWithRoles(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());
        $role = Role::factory()->createOne();
        $data = [
            'name' => 'User With Role',
            'email' => 'user-with-role@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role_ids' => [$role->getHashedKey()],
        ];

        $response = $this->postJson(action(CreateUserController::class), $data);

        $response->assertCreated();
        $response->assertJson(
            static fn (AssertableJson $json): AssertableJson => $json
                ->has('data.roles.data', 1)
                ->where('data.roles.data.0.id', $role->getHashedKey())
                ->etc(),
        );

        $createdUser = User::query()->where('email', $data['email'])->firstOrFail();
        $this->assertTrue($createdUser->hasRole($role));
    }

    // TODO: move to request test
    public function testGivenUserHasNoAccessPreventsOperation(): void
    {
        $this->actingAs(User::factory()->createOne());

        $response = $this->postJson(action(CreateUserController::class), [
            'name' => 'No Access',
            'email' => 'no-access@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertForbidden();
    }
}
