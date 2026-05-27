<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Shift\Tasks\UpdateHourlyStaffTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class UpdateHourlyStaffAction extends ParentAction
{
    public function run(array $records): void
    {
        DB::transaction(function () use ($records) {
            app(UpdateHourlyStaffTask::class)->run($records);
        });

        $this->bustCacheForAffectedRecords($records);
    }

    /**
     * Clear production cache for any updated historical hourly records.
     *
     * Affected keys: deptDetail, lineSummary, allLinesHourly.
     */
    private function bustCacheForAffectedRecords(array $records): void
    {
        $ids = collect($records)->pluck('id')->filter()->toArray();

        if (empty($ids)) {
            return;
        }

        // Load all affected records with their shift + department.productionLine
        $hourlyRecords = HourlyRecord::with(['shift', 'department.productionLine'])
            ->findMany($ids);

        // Collect unique (line, dept, date, shift) combos
        $busted = [];

        foreach ($hourlyRecords as $hr) {
            $shift = $hr->shift;
            $dept  = $hr->department;
            $line  = $dept?->productionLine;

            if (!$shift || !$dept || !$line) {
                continue;
            }

            $date        = $shift->date->toDateString();
            $shiftNumber = $shift->shift_number;

            // Only historical dates are cached
            if (!ProductionCacheKeys::isHistorical($date)) {
                continue;
            }

            $comboKey = "{$line->code}:{$dept->code}:{$date}:{$shiftNumber}";
            if (isset($busted[$comboKey])) {
                continue;
            }
            $busted[$comboKey] = true;

            Cache::forget(ProductionCacheKeys::deptDetail($line->code, $dept->code, $date, $shiftNumber));
            Cache::forget(ProductionCacheKeys::lineSummary($line->code, $date, $shiftNumber));
            ProductionCacheKeys::flushAllLinesHourly($date, $shiftNumber);
        }
    }
}
