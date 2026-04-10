<?php

namespace App\Containers\AppSection\Production\Data\Repositories;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of ProductionLine
 *
 * @extends ParentRepository<TModel>
 */
final class ProductionLineRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'code'      => '=',
        'label'     => 'like',
        'is_active' => '=',
    ];

    public function model(): string
    {
        return ProductionLine::class;
    }
}
