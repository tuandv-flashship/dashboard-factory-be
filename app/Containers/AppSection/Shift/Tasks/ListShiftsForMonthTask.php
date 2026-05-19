<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Collection;

/**
 * List all shifts for a given month (calendar view).
 * Optionally filter to a single day within that month.
 */
final class ListShiftsForMonthTask extends ParentTask
{
    public function run(int $year, int $month, ?int $day = null): Collection
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = Shift::with(['template:id,name,color', 'details.latestChange']);

        if ($day !== null) {
            $specificDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $query->where('date', $specificDate);
        } else {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        return $query
            ->orderBy('date')
            ->orderBy('shift_number')
            ->get();
    }
}
