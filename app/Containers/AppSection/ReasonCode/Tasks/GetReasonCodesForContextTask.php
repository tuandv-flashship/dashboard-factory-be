<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetReasonCodesForContextTask extends ParentTask
{
    /**
     * Get all reason categories with sub-items and errors filtered by context.
     *
     * @return Collection<int, ReasonCategory>
     */
    public function run(?string $line = null, ?string $dept = null): Collection
    {
        return ReasonCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'subItems' => function ($query) use ($line, $dept) {
                    $query->forContext($line, $dept);
                },
                'errors' => function ($query) use ($dept) {
                    $query->forDept($dept);
                },
            ])
            ->get();
    }
}
