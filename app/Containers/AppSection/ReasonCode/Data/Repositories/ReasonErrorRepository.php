<?php

namespace App\Containers\AppSection\ReasonCode\Data\Repositories;

use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of ReasonError
 *
 * @extends ParentRepository<TModel>
 */
final class ReasonErrorRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'code'        => '=',
        'label'       => 'like',
        'category_id' => '=',
        'scope_dept'  => '=',
        'is_active'   => '=',
    ];

    public function model(): string
    {
        return ReasonError::class;
    }
}
