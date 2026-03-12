<?php

namespace App\Ship\Seeders;

use App\Ship\Parents\Seeders\Seeder;
use App\Ship\Supports\PermissionSyncer;

final class PermissionsSeeder extends Seeder
{
    public function run(PermissionSyncer $syncer): void
    {
        $syncer->sync();
    }
}
