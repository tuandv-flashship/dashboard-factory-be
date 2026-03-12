<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetAllProductionLinesTask extends ParentTask
{
    /**
     * @return Collection<int, ProductionLine>
     */
    public function run(): Collection
    {
        return ProductionLine::query()
            ->where('is_active', true)
            ->with('departments')
            ->orderBy('sort_order')
            ->get();
    }
}
