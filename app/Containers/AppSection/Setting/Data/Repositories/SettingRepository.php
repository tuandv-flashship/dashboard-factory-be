<?php

namespace App\Containers\AppSection\Setting\Data\Repositories;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of Setting
 *
 * @extends ParentRepository<TModel>
 */
final class SettingRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'id' => '=',
        'key' => 'like',
        'value' => 'like',
    ];

    public function model(): string
    {
        return Setting::class;
    }
}
