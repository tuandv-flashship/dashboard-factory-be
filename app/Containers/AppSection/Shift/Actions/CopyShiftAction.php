<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Tasks\CopyShiftToDatesTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

/**
 * Copy one or more shifts to target dates.
 *
 * Supports multi-shift copy (e.g. Ca 1 + Ca 2 cùng lúc).
 * Returns aggregated { created: [...], skipped: [...] }.
 */
final class CopyShiftAction extends ParentAction
{
    public function run(array $shiftIds, array $targetDates): array
    {
        return DB::transaction(function () use ($shiftIds, $targetDates) {
            $allCreated = [];
            $allSkipped = [];

            foreach ($shiftIds as $shiftId) {
                $source = Shift::findOrFail($shiftId);
                $result = app(CopyShiftToDatesTask::class)->run($source, $targetDates);

                // Tag with shift info for FE clarity
                foreach ($result['created'] as $date) {
                    $allCreated[] = [
                        'date'         => $date,
                        'shift_number' => $source->shift_number,
                    ];
                }

                foreach ($result['skipped'] as $skip) {
                    $allSkipped[] = array_merge($skip, [
                        'shift_number' => $source->shift_number,
                    ]);
                }
            }

            return [
                'created' => $allCreated,
                'skipped' => $allSkipped,
            ];
        });
    }
}
