<?php

namespace App\Containers\AppSection\Shift\Data\Repositories;

use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of ShiftTemplateDetail
 *
 * @extends ParentRepository<TModel>
 */
final class ShiftTemplateDetailRepository extends ParentRepository
{
    public function model(): string
    {
        return ShiftTemplateDetail::class;
    }
}
