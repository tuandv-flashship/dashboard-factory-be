<?php

namespace App\Containers\AppSection\ReasonCode\Data\Repositories;

use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of ReasonSubItem
 *
 * @extends ParentRepository<TModel>
 */
final class ReasonSubItemRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'code'        => '=',
        'label'       => 'like',
        'category_id' => '=',
        'scope_type'  => '=',
        'scope_line'  => '=',
        'scope_dept'  => '=',
        'is_active'   => '=',
    ];

    public function model(): string
    {
        return ReasonSubItem::class;
    }
}
