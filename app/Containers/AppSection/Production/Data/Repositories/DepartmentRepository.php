<?php

namespace App\Containers\AppSection\Production\Data\Repositories;

use App\Containers\AppSection\Production\Models\Department;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of Department
 *
 * @extends ParentRepository<TModel>
 */
final class DepartmentRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'code'               => '=',
        'label'              => 'like',
        'unit'               => '=',
        'factory'            => '=',
        'is_active'          => '=',
        'production_line_id' => '=',
    ];

    public function model(): string
    {
        return Department::class;
    }
}
