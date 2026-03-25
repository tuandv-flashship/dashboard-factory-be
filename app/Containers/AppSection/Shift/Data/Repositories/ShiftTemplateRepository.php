<?php

namespace App\Containers\AppSection\Shift\Data\Repositories;

use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of ShiftTemplate
 *
 * @extends ParentRepository<TModel>
 */
final class ShiftTemplateRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'name'   => 'like',
        'status' => '=',
    ];

    public function model(): string
    {
        return ShiftTemplate::class;
    }
}
