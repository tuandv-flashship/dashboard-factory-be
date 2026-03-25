<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Collection;

/**
 * List all shifts for a given month (calendar view).
 */
final class ListShiftsForMonthTask extends ParentTask
{
    public function run(int $year, int $month): Collection
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        return Shift::with('template:id,name,color')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('shift_number')
            ->get();
    }
}
