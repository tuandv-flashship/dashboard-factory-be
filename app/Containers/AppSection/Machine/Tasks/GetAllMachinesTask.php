<?php

namespace App\Containers\AppSection\Machine\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetAllMachinesTask extends ParentTask
{
    /**
     * @return Collection<int, Machine>
     */
    public function run(): Collection
    {
        return Machine::query()
            ->active()
            ->orderBy('sort_order')
            ->get();
    }
}
