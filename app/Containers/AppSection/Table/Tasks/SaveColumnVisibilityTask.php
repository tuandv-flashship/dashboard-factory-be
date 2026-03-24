<?php

namespace App\Containers\AppSection\Table\Tasks;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Contracts\Auth\Authenticatable;

final class SaveColumnVisibilityTask extends ParentTask
{
    public function run(Authenticatable $user, string $modelKey, array $columns): void
    {
        $key = "user:{$user->getAuthIdentifier()}:table_columns:{$modelKey}";

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($columns)],
        );
    }
}
