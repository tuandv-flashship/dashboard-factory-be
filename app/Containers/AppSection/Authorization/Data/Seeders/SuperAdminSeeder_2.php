<?php

namespace App\Containers\AppSection\Authorization\Data\Seeders;

use App\Containers\AppSection\User\Actions\CreateAdminAction;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;

final class SuperAdminSeeder_2 extends ParentSeeder
{
    public function run(CreateAdminAction $action): void
    {
        $userData = [
            'email' => 'admin@admin.com',
            'password' => 'admin',
            'name' => 'Super Admin',
        ];

        if (User::query()->where('email', $userData['email'])->exists()) {
            return;
        }

        $action->run($userData);
    }
}
