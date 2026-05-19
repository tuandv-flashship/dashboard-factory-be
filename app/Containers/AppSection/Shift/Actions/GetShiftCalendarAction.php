<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Tasks\ListShiftsForMonthTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Collection;

final class GetShiftCalendarAction extends ParentAction
{
    public function run(int $year, int $month, ?int $day = null): array
    {
        $shifts = app(ListShiftsForMonthTask::class)->run($year, $month, $day);

        // Group shifts by date
        $days = $shifts->groupBy(fn ($s) => $s->date->format('Y-m-d'));

        return [
            'year'  => $year,
            'month' => $month,
            'days'  => $days,
        ];
    }
}
