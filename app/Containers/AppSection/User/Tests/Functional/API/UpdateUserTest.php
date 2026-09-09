<?php

namespace App\Containers\AppSection\User\Tests\Functional\API;

use App\Containers\AppSection\Authorization\Models\Permission;
use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\User\Enums\UserStatus;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\User\UI\API\Controllers\UpdateUserController;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UpdateUserController::class)]
final class UpdateUserTest extends ApiTestCase
{
    public function testCanUpdateAsOwner(): void
    {
        $user = User::factory()->createOne([
            'name' => 'He who must not be named',
            'gender' => Gender::FEMALE,
            'password' => 'Av@dakedavra!',
        ]);
        $this->actingAs($user);
        $data = [
            'name' => 'Updated Name',
            'gender' => Gender::MALE->value,
            'birth' => Date::today()->toIso8601String(),
            'current_password' => 'Av@dakedavra!',
            'new_password' => 'updated#Password111',
            'new_password_confirmation' => 'updated#Password111',
        ];

        $response = $this->patchJson(URL::action(UpdateUserController::class, $user->getHashedKey()), $data);

        $response->assertOk();
        $response->assertJson(
            fn (AssertableJson $json): AssertableJson => $json->has(
                'data',
                fn (AssertableJson $json): AssertableJson => $json
                    ->where('type', 'User')
                    ->where('email', $user->email)
                    ->where('name', $data['name'])
                    ->where('gender', $data['gender'])
                    ->where('birth', static fn ($birth) => Date::parse($data['birth'])->isSameDay($birth))
                    ->missing('password')
                    ->etc(),
            )->etc(),
        );
        $this->assertTrue(Hash::check($data['new_password'], $user->refresh()->password));
    }

    public function testUpdatingProfileWithoutPasswordKeepsExistingPassword(): void
    {
        $user = User::factory()->createOne([
            'name' => 'He who must not be named',
            'password' => 'Av@dakedavra!',
        ]);
        $this->actingAs($user);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $user->getHashedKey()), [
            'name' => 'Updated Name',
            'gender' => Gender::MALE->value,
            'phone' => '0900000000',
            'description' => 'Some description',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertTrue(Hash::check('Av@dakedavra!', $user->password));
    }

    public function testCanSyncRolesWhenUpdatingUser(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());
        $target = User::factory()->createOne();
        $role = Role::factory()->createOne();

        $response = $this->patchJson(URL::action(UpdateUserController::class, $target->getHashedKey()), [
            'name' => 'Updated Name',
            'role_ids' => [$role->getHashedKey()],
        ]);

        $response->assertOk();
        $this->assertTrue($target->refresh()->hasRole($role));
    }

    public function testSyncingRolesReplacesThePreviousOnes(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());
        $old = Role::factory()->createOne();
        $new = Role::factory()->createOne();
        $target = User::factory()->createOne();
        $target->assignRole($old);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $target->getHashedKey()), [
            'role_ids' => [$new->getHashedKey()],
        ]);

        $response->assertOk();
        $target->refresh();
        $this->assertTrue($target->hasRole($new));
        $this->assertFalse($target->hasRole($old));
    }

    public function testUserWithoutEditPermissionCannotSelfAssignRoles(): void
    {
        $user = User::factory()->createOne();
        $role = Role::factory()->createOne();
        $this->actingAs($user);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $user->getHashedKey()), [
            'name' => 'Updated Name',
            'role_ids' => [$role->getHashedKey()],
        ]);

        $response->assertOk();
        $this->assertFalse($user->refresh()->hasRole($role));
    }

    public function testUserWithEditPermissionCanUpdateAnotherUser(): void
    {
        $editor = User::factory()->createOne();
        $editor->givePermissionTo(Permission::findOrCreate('users.edit', 'api'));
        $this->actingAs($editor);
        $target = User::factory()->createOne();

        $response = $this->patchJson(URL::action(UpdateUserController::class, $target->getHashedKey()), [
            'name' => 'Updated By Editor',
        ]);

        $response->assertOk();
        $this->assertSame('Updated By Editor', $target->refresh()->name);
    }

    public function testUserWithoutEditPermissionCannotChangeOwnStatus(): void
    {
        $user = User::factory()->createOne(['status' => UserStatus::PENDING->value]);
        $this->actingAs($user);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $user->getHashedKey()), [
            'name' => 'Updated Name',
            'status' => UserStatus::ACTIVE->value,
        ]);

        $response->assertOk();
        $this->assertSame(UserStatus::PENDING, $user->refresh()->status);
    }

    public function testUserWithEditPermissionCanChangeStatus(): void
    {
        $editor = User::factory()->createOne();
        $editor->givePermissionTo(Permission::findOrCreate('users.edit', 'api'));
        $this->actingAs($editor);
        $target = User::factory()->createOne(['status' => UserStatus::PENDING->value]);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $target->getHashedKey()), [
            'status' => UserStatus::ACTIVE->value,
        ]);

        $response->assertOk();
        $this->assertSame(UserStatus::ACTIVE, $target->refresh()->status);
    }

    public function testSendingAnEmptyRoleListClearsTheRoles(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());
        $role = Role::factory()->createOne();
        $target = User::factory()->createOne();
        $target->assignRole($role);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $target->getHashedKey()), [
            'role_ids' => [],
        ]);

        $response->assertOk();
        $this->assertCount(0, $target->refresh()->roles);
    }

    public function testOmittingRoleIdsLeavesTheRolesUntouched(): void
    {
        $this->actingAs(User::factory()->superAdmin()->createOne());
        $role = Role::factory()->createOne();
        $target = User::factory()->createOne();
        $target->assignRole($role);

        $response = $this->patchJson(URL::action(UpdateUserController::class, $target->getHashedKey()), [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertTrue($target->refresh()->hasRole($role));
    }

    // TODO: move to request test
    public function testGivenUserHasNoAccessPreventsOperation(): void
    {
        $this->actingAs(User::factory()->createOne());

        $response = $this->patchJson(URL::action(UpdateUserController::class, User::factory()->createOne()->getHashedKey()));

        $response->assertForbidden();
    }
}
