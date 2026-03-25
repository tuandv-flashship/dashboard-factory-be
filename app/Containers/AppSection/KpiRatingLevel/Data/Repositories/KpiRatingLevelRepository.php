<?php

namespace App\Containers\AppSection\KpiRatingLevel\Data\Repositories;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevel;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of KpiRatingLevel
 *
 * @extends ParentRepository<TModel>
 */
final class KpiRatingLevelRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'name' => 'like',
    ];

    public function model(): string
    {
        return KpiRatingLevel::class;
    }
}
