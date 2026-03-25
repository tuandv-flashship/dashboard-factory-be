<?php

namespace App\Containers\AppSection\Shift\Data\Repositories;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

final class ShiftRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'date'         => '=',
        'shift_number' => '=',
        'is_active'    => '=',
    ];

    public function model(): string
    {
        return Shift::class;
    }
}
