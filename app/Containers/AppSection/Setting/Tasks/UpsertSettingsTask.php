<?php

namespace App\Containers\AppSection\Setting\Tasks;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Arr;

final class UpsertSettingsTask extends ParentTask
{
    /**
     * @param array<string, mixed> $data
     */
    public function run(array $data): void
    {
        if ($data === []) {
            return;
        }

        $flat = Arr::dot($data);
        $keys = array_keys($flat);
        $existingKeys = Setting::query()->whereIn('key', $keys)->pluck('key')->all();

        foreach ($flat as $key => $value) {
            if (in_array($key, $existingKeys, true)) {
                Setting::query()->where('key', $key)->update(['value' => $value]);
            } else {
                Setting::query()->create([
                    'key' => $key,
                    'value' => $value,
                ]);
            }
        }
    }
}
