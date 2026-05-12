<?php

namespace App\Containers\AppSection\Authorization\Tests\Unit\Data\Seeders;

use App\Containers\AppSection\Authorization\Data\Seeders\AuthorizationSeeder_1;
use App\Containers\AppSection\Authorization\Data\Seeders\RolePermissionSeeder_2;
use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuthorizationSeeder_1::class)]
#[CoversClass(RolePermissionSeeder_2::class)]
final class AuthorizationSeederTest extends UnitTestCase
{
    public function testCanSeed(): void
    {
        $guardCount = count(config('auth.guards'));
        $roleCount  = count(Role::cases());

        $this->assertDatabaseCount('roles', $guardCount * $roleCount);

        foreach (config('auth.guards') as $guardName => $value) {
            foreach (Role::cases() as $role) {
                $this->assertDatabaseHas('roles', [
                    'name'       => $role->value,
                    'guard_name' => $guardName,
                ]);
            }
        }
    }
}

