<?php

namespace App\Containers\AppSection\Machine\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetMachinesByLineTask extends ParentTask
{
    /**
     * Get active machines filtered by production line code.
     * Queries through department → production_line relationship.
     *
     * @return Collection<int, Machine>
     */
    public function run(string $line): Collection
    {
        return Machine::query()
            ->active()
            ->whereHas('department.productionLine', fn ($q) => $q->where('code', $line))
            ->orderBy('sort_order')
            ->get();
    }
}
