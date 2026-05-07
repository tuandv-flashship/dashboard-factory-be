<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use Apiato\Support\Facades\Response;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Enums\HourlyRecordStatus;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\HourlyRecordMachine;
use App\Containers\AppSection\Production\UI\API\Transformers\HourlyRecordTransformer;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Traits\InvalidatesProductionCache;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateHourlyRecordRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class CreateHourlyRecordController extends ApiController
{
    use InvalidatesProductionCache;

    public function __invoke(CreateHourlyRecordRequest $request): JsonResponse
    {
        $shiftId = $request->shift_id;
        $deptId  = $request->department_id;

        $dept = Department::findOrFail($deptId);
        $productivityType = $dept->productivity_type;

        $record = DB::transaction(function () use ($shiftId, $deptId, $request, $productivityType) {
            // 1a. Find highest hour_index INCLUDING soft-deleted (unique constraint is DB-level)
            $lastByIndex = HourlyRecord::withTrashed()
                ->where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->orderByDesc('hour_index')
                ->first();

            $newHourIndex = $lastByIndex ? $lastByIndex->hour_index + 1 : 0;

            // 1b. Find last ACTIVE record for hour_slot continuity
            $lastActive = HourlyRecord::where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->orderByDesc('hour_index')
                ->first();

            // 2. Compute hour_slot label from the last active slot's end hour
            $hourSlot = $this->computeNextHourSlot($lastActive, $newHourIndex);

            // 3. Build create data
            $kpiMinutes = (int) $request->input('kpi_minutes');

            $createData = [
                'shift_id'             => $shiftId,
                'department_id'        => $deptId,
                'hour_slot'            => $hourSlot,
                'hour_index'           => $newHourIndex,
                'kpi_minutes'          => $kpiMinutes,
                'kpi_hours'            => round($kpiMinutes / 60, 2),
                'kpi_percent'          => round($kpiMinutes / 60 * 100, 2),
                'target'               => $request->input('target'),
                'staff_required'       => $request->input('staff_required'),
                'note'                 => $request->input('note'),
                'staff'                => null,
                'actual'               => null,
                'hour_start_inventory' => 0,
                'efficiency'           => 0,
                'error_rate'           => 0,
                'status'               => HourlyRecordStatus::Pending->value,
            ];

            // ── machine_count (DTF/DTG only) ──
            if ($request->has('machine_count')
                && ($productivityType?->isPerMachineDtf() || $productivityType?->isPerMachineDtg())
            ) {
                $createData['machine_count'] = $request->input('machine_count');
            }

            $record = HourlyRecord::create($createData);

            // ── active_machine_ids → pivot sync (DTG only) ──
            if ($request->has('active_machine_ids') && $productivityType?->isPerMachineDtg()) {
                $machineIds = $request->input('active_machine_ids', []);

                if (empty($machineIds)) {
                    $record->update(['machine_count' => 0]);
                } else {
                    $machines = Machine::whereIn('id', $machineIds)
                        ->where('department_id', $deptId)
                        ->get();

                    $pivotRows = [];
                    $totalKpi = 0;

                    foreach ($machines as $machine) {
                        $pivotRows[] = [
                            'hourly_record_id' => $record->id,
                            'machine_id'       => $machine->id,
                            'kpi_per_hour'     => $machine->kpi_per_hour,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ];
                        $totalKpi += $machine->kpi_per_hour;
                    }

                    if (!empty($pivotRows)) {
                        HourlyRecordMachine::insert($pivotRows);
                    }

                    // Auto-update machine_count + target (if no manual target)
                    $autoUpdates = ['machine_count' => count($pivotRows)];
                    if (!$request->has('target')) {
                        $kpiPercent = $record->kpi_percent ?? 100;
                        $autoUpdates['target'] = (int) round($totalKpi * $kpiPercent / 100);
                    }
                    $record->update($autoUpdates);
                }
            }

            // 4. Increase shift_details.work_hours by 1
            ShiftDetail::where('shift_id', $shiftId)
                ->where('department_id', $deptId)
                ->increment('work_hours', 1);

            return $record;
        });

        // 5. Invalidate production dashboard cache for historical shifts
        $this->invalidateProductionCache($shiftId, $deptId);

        $record->load(['issues', 'department', 'hourlyMachines.machine']);

        return Response::create($record, HourlyRecordTransformer::class)->ok();
    }

    /**
     * Compute the next hour slot label based on the last record.
     *
     * e.g., last slot "14h-15h" → next "15h-16h"
     */
    private function computeNextHourSlot(?HourlyRecord $lastRecord, int $newIndex): string
    {
        if (!$lastRecord) {
            return '0h-1h';
        }

        // Parse end hour from last slot (e.g., "14h-15h" → 15)
        $parts   = explode('-', $lastRecord->hour_slot);
        $endHour = (int) str_replace('h', '', $parts[1] ?? $parts[0]);

        $nextEnd = $endHour + 1;

        return "{$endHour}h-{$nextEnd}h";
    }
}
