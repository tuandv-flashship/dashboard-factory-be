<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tasks\CreateShiftFromTemplateTask;
use App\Containers\AppSection\Shift\Tasks\GenerateHourlyRecordsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateShiftAction extends ParentAction
{
    public function run(array $data): Shift
    {
        return DB::transaction(function () use ($data) {
            $template = ShiftTemplate::with('details')->findOrFail($data['shift_template_id']);
            $shiftNumbers = $data['shift_numbers'] ?? [1];

            $lastShift = null;

            foreach ($shiftNumbers as $shiftNumber) {
                // Calculate start/end from template details for this shift_number
                $templateDetails = $template->details
                    ->where('shift_number', $shiftNumber);

                if ($templateDetails->isEmpty()) {
                    continue;
                }

                $startTimes = $templateDetails->pluck('start_time')->filter();
                $minStart = $startTimes->min() ?? '06:00:00';

                // Calculate max end_time from start_time + work_hours
                $maxEnd = $templateDetails->map(function ($d) {
                    return Carbon::createFromFormat('H:i:s', $d->start_time)
                        ->addMinutes((int) ($d->work_hours * 60))
                        ->format('H:i');
                })->max() ?? '14:00';

                $startFormatted = Carbon::createFromFormat('H:i:s', $minStart)->format('H:i');

                $shift = Shift::create([
                    'date'              => $data['date'],
                    'shift_number'      => $shiftNumber,
                    'start_time'        => $startFormatted,
                    'end_time'          => $maxEnd,
                    'supervisor'        => $data['supervisor'] ?? null,
                    'is_active'         => true,
                    'shift_template_id' => $template->id,
                ]);

                // Copy template details → shift_details
                app(CreateShiftFromTemplateTask::class)->run($shift, $template->id);

                // Generate hourly_records
                app(GenerateHourlyRecordsTask::class)->run($shift);

                $lastShift = $shift;
            }

            return $lastShift->load(['details.department.productionLine', 'template', 'hourlyRecords']);
        });
    }
}
