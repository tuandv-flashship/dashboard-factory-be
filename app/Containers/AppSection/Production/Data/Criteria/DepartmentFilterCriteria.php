<?php

namespace App\Containers\AppSection\Production\Data\Criteria;

use Apiato\Core\Repositories\Repository;
use App\Ship\Parents\Criteria\Criteria as ParentCriteria;
use Prettus\Repository\Contracts\RepositoryInterface as PrettusRepositoryInterface;

/**
 * Filter the eager-loaded "departments" relation by factory and/or active status.
 *
 * Uses repository->scope() to register constrained eager load AFTER Apiato's
 * boot eagerLoadRequestedIncludes (which adds unconstrained ->with('departments')).
 * Since scope() appends to the scopes array, our closure runs last and
 * Eloquent's array_merge overwrites the unconstrained version.
 *
 * Usage:
 *   $repository->pushCriteria(new DepartmentFilterCriteria(deptFactory: 'FLS'));
 *   $repository->pushCriteria(new DepartmentFilterCriteria(deptActive: true));
 *   $repository->pushCriteria(new DepartmentFilterCriteria(deptFactory: 'PD', deptActive: true));
 */
final class DepartmentFilterCriteria extends ParentCriteria
{
    public function __construct(
        private readonly ?string $deptFactory = null,
        private readonly ?bool   $deptActive  = null,
    ) {}

    public function apply($model, PrettusRepositoryInterface $repository)
    {
        if ($this->deptFactory === null && $this->deptActive === null) {
            return $model;
        }

        if ($repository instanceof Repository) {
            $factory = $this->deptFactory;
            $active  = $this->deptActive;

            $repository->scope(function ($model) use ($factory, $active) {
                return $model->with(['departments' => function ($query) use ($factory, $active) {
                    if ($factory !== null) {
                        $query->where('factory', $factory);
                    }
                    if ($active !== null) {
                        $query->where('is_active', $active);
                    }
                    $query->orderBy('sort_order');
                }]);
            });
        }

        return $model;
    }
}
