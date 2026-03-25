<?php

namespace App\Containers\AppSection\Shift\Data\Repositories;

use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

final class ShiftDetailRepository extends ParentRepository
{
    protected $fieldSearchable = [];

    public function model(): string
    {
        return ShiftDetail::class;
    }
}
