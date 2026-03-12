<?php

namespace App\Containers\AppSection\Machine\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetMachinesByLineTask extends ParentTask
{
    /**
     * @return Collection<int, Machine>
     */
    public function run(string $line): Collection
    {
        return Machine::query()
            ->active()
            ->forLine($line)
            ->orderBy('sort_order')
            ->get();
    }
}
