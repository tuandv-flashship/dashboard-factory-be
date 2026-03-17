<?php

namespace App\Containers\AppSection\ReasonCode\Data\Repositories;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of ReasonCategory
 *
 * @extends ParentRepository<TModel>
 */
final class ReasonCategoryRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'code'      => '=',
        'label'     => 'like',
        'label_en'  => 'like',
        'is_active' => '=',
    ];

    public function model(): string
    {
        return ReasonCategory::class;
    }
}
