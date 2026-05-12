<?php

namespace App\Containers\AppSection\Department\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Tasks\Task as ParentTask;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Database\Eloquent\Collection;

/**
 * Find all departments belonging to a specific production line.
 * Provides a clean cross-container interface for Production to query departments.
 */
final class FindDepartmentsByLineIdTask extends ParentTask
{
    /**
     * @return Collection<int, Department>
     */
    public function run(int $productionLineId): Collection
    {
        $query = Department::query()
            ->where('production_line_id', $productionLineId)
            ->with('machines')
            ->orderBy('sort_order');

        // Apply department scope — only return departments user has access to
        DepartmentScope::applyToQuery($query, auth()->user(), 'dashboard.view', 'id');

        return $query->get();
    }
}
