<?php

namespace App\Containers\AppSection\Authorization\Data\Seeders;

use App\Containers\AppSection\Authorization\Enums\Role;
use App\Containers\AppSection\Authorization\Models\Role as RoleModel;
use App\Containers\AppSection\Authorization\Tasks\CreateRoleTask;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;

final class AuthorizationSeeder_1 extends ParentSeeder
{
    public function run(CreateRoleTask $task): void
    {
        $roleName = Role::SUPER_ADMIN->value;

        foreach (array_keys(config('auth.guards')) as $guardName) {

            $exists = RoleModel::query()
                ->where('name', strtolower($roleName))
                ->where('guard_name', $guardName)
                ->exists();

            if ($exists) {
                continue;
            }

            $task->run(
                $roleName,
                'Administrator',
                Role::SUPER_ADMIN->label(),
                $guardName,
            );
        }
    }
}
