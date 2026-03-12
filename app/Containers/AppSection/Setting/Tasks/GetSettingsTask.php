<?php

namespace App\Containers\AppSection\Setting\Tasks;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Arr;

final class GetSettingsTask extends ParentTask
{
    /**
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function run(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = Setting::query()
            ->whereIn('key', $keys)
            ->get(['key', 'value']);

        $data = [];

        foreach ($rows as $row) {
            Arr::set($data, $row->key, $row->value);
        }

        return $data;
    }
}
