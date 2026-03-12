<?php

namespace App\Containers\AppSection\User\Tests\Unit\Actions;

use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Actions\CreateUserAction;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CreateUserAction::class)]
final class CreateUserActionTest extends UnitTestCase
{
    public function testCanCreateUserWithoutRoles(): void
    {
        $data = [
            'name' => 'User No Role',
            'email' => 'user-no-role@example.com',
            'password' => 'Password123!',
        ];

        $user = app(CreateUserAction::class)->run($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($data['email'], $user->email);
        $this->assertCount(0, $user->roles);
    }

    public function testCanCreateUserWithRoles(): void
    {
        $role = Role::factory()->createOne();
        $data = [
            'name' => 'User With Role',
            'email' => 'user-has-role@example.com',
            'password' => 'Password123!',
        ];

        $user = app(CreateUserAction::class)->run($data, $role->id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->hasRole($role));
    }
}
