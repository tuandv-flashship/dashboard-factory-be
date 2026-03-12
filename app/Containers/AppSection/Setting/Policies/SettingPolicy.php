<?php

namespace App\Containers\AppSection\Setting\Policies;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class SettingPolicy extends ParentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
