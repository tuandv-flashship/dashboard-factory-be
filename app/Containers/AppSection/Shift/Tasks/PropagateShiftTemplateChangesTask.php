<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\Traits\RecalculatesShiftTimes;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

final class PropagateShiftTemplateChangesTask extends ParentTask
{
    use RecalculatesShiftTimes;

    private array $hiddenChildrenCache = [];
    private array $departmentKpiCache = [];

    public function __construct(
        private readonly SyncHourlyRecordsTask $syncHourlyRecordsTask,
    ) {
    }

    /**
     * Propagate the ShiftTemplate changes to all shifts using this template from today onwards.
     * Only updates the specific department-shift pairs that have actual modifications, additions, or deletions.
     * Returns today's shifts that need FPlatform resync.
     *
     * @param ShiftTemplate $template
     * @param \Illuminate\Support\Collection<int, ShiftTemplateDetail> $oldDetails
     * @param array $newDetailsData
     * @return Shift[]
     */
    public function run(ShiftTemplate $template, $oldDetails, array $newDetailsData): array
    {
        $this->hiddenChildrenCache = [];
        $this->departmentKpiCache = [];

        // 1. Detect which department-shift pairs have changed
        $changedPairs = $this->detectChangedPairs($oldDetails, $newDetailsData);

        if (empty($changedPairs)) {
            return [];
        }

        $today = Carbon::today()->toDateString();

        // 2. Find all shifts using this template from today onwards
        $shifts = Shift::where('shift_template_id', $template->id)
            ->whereDate('date', '>=', $today)
            ->get();

        if ($shifts->isEmpty()) {
            return [];
        }

        // 3. Fetch newly synchronized template details
        $newTemplateDetails = ShiftTemplateDetail::with('department')
            ->where('shift_template_id', $template->id)
            ->get();

        $todayShiftsToResync = [];

        foreach ($shifts as $shift) {
            $shiftUpdated = false;

            // Filter changed pairs for this shift's shift_number
            $shiftChangedPairs = array_filter(
                $changedPairs,
                fn ($pair) => $pair['shift_number'] === $shift->shift_number
            );

            if (empty($shiftChangedPairs)) {
                continue;
            }

            foreach ($shiftChangedPairs as $pair) {
                $deptId = $pair['department_id'];
                $action = $pair['action'];

                // Auto-resolve hidden children (e.g. pick -> pick_dtf, pick_dtg)
                $hiddenChildrenIds = $this->getHiddenChildrenIds($deptId);
                $allDeptIds = array_merge([$deptId], $hiddenChildrenIds);

                if ($action === 'delete') {
                    // Delete details for this department and its hidden children
                    ShiftDetail::where('shift_id', $shift->id)
                        ->whereIn('department_id', $allDeptIds)
                        ->where('shift_number', $shift->shift_number)
                        ->delete();
                    $shiftUpdated = true;
                } else {
                    // action === 'upsert'
                    // Find the updated template detail
                    $td = $newTemplateDetails
                        ->where('department_id', $deptId)
                        ->where('shift_number', $shift->shift_number)
                        ->first();

                    if ($td) {
                        // Upsert the visible department
                        $this->upsertShiftDetail($shift, $td);

                        // Replicate to hidden children
                        foreach ($hiddenChildrenIds as $childId) {
                            $this->upsertHiddenChildShiftDetail($shift, $td, $childId);
                        }

                        $shiftUpdated = true;
                    }
                }
            }

            if ($shiftUpdated) {
                // Căn chỉnh lại slots trong hourly records
                $this->syncHourlyRecordsTask->run($shift);

                // Tính toán lại thời gian trên Shift Header (start_time, end_time)
                $this->recalculateShiftEndTime($shift);

                // Xoá cache của shift trên dashboard
                ProductionCacheKeys::flushForShift($shift);

                // Nếu ca rơi vào ngày hôm nay, đánh dấu để resync sản lượng từ FPlatform ngoài transaction
                if ($shift->date->toDateString() === $today) {
                    $todayShiftsToResync[] = $shift;
                }
            }
        }

        return $todayShiftsToResync;
    }

    /**
     * Compare old vs new template details to detect changed (modified, added, deleted) department-shift pairs.
     */
    private function detectChangedPairs($oldDetails, array $newDetailsData): array
    {
        $oldMap = $oldDetails->keyBy(fn ($d) => "{$d->department_id}_{$d->shift_number}");
        $newMap = collect($newDetailsData)->keyBy(fn ($d) => "{$d['department_id']}_{$d['shift_number']}");

        $changedPairs = [];

        // 1. Check for modified and added
        foreach ($newDetailsData as $new) {
            $key = "{$new['department_id']}_{$new['shift_number']}";
            $old = $oldMap->get($key);

            if (!$old) {
                // Added
                $changedPairs[$key] = [
                    'department_id' => (int) $new['department_id'],
                    'shift_number'  => (int) $new['shift_number'],
                    'action'        => 'upsert',
                ];
                continue;
            }

            // Compare fields
            $fieldsToCompare = [
                'headcount', 'start_time', 'work_hours', 'prep_minutes',
                'break1_start', 'break1_minutes', 'meal_break_start', 'meal_break_minutes',
                'break2_start', 'break2_minutes', 'break3_start', 'break3_minutes',
            ];

            $hasChanged = false;
            foreach ($fieldsToCompare as $field) {
                $oldValue = $old->{$field};
                $newValue = $new[$field] ?? null;

                // Normalize times (e.g. "06:00:00" vs "06:00")
                if (in_array($field, ['start_time', 'break1_start', 'meal_break_start', 'break2_start', 'break3_start'], true)) {
                    $oldValue = $oldValue ? substr($oldValue, 0, 5) : null;
                    $newValue = $newValue ? substr($newValue, 0, 5) : null;
                }

                // Normalize numeric values
                if (in_array($field, ['headcount', 'prep_minutes', 'break1_minutes', 'meal_break_minutes', 'break2_minutes', 'break3_minutes'], true)) {
                    $oldValue = (int) $oldValue;
                    $newValue = (int) $newValue;
                }

                if (in_array($field, ['work_hours'], true)) {
                    $oldValue = (float) $oldValue;
                    $newValue = (float) $newValue;
                }

                if ($oldValue !== $newValue) {
                    $hasChanged = true;
                    break;
                }
            }

            if ($hasChanged) {
                $changedPairs[$key] = [
                    'department_id' => (int) $new['department_id'],
                    'shift_number'  => (int) $new['shift_number'],
                    'action'        => 'upsert',
                ];
            }
        }

        // Fetch hidden independent department IDs to exclude them from deletion detection
        $hiddenIndependentDeptIds = Department::whereNull('parent_id')
            ->where('is_hidden', true)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        // 2. Check for removed
        foreach ($oldDetails as $old) {
            if (in_array($old->department_id, $hiddenIndependentDeptIds, true)) {
                continue; // Do not delete hidden independent departments
            }

            $key = "{$old->department_id}_{$old->shift_number}";
            if (!$newMap->has($key)) {
                $changedPairs[$key] = [
                    'department_id' => (int) $old->department_id,
                    'shift_number'  => (int) $old->shift_number,
                    'action'        => 'delete',
                ];
            }
        }

        return array_values($changedPairs);
    }

    /**
     * Upsert ShiftDetail for a visible department using updated template detail.
     */
    private function upsertShiftDetail(Shift $shift, ShiftTemplateDetail $td): void
    {
        $existing = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $td->department_id)
            ->where('shift_number', $td->shift_number)
            ->first();

        $updateData = [
            'headcount'          => $td->headcount,
            'start_time'         => $td->start_time,
            'work_hours'         => $td->work_hours,
            'prep_minutes'       => $td->prep_minutes,
            'break1_start'       => $td->break1_start,
            'break1_minutes'     => $td->break1_minutes,
            'meal_break_start'   => $td->meal_break_start,
            'meal_break_minutes' => $td->meal_break_minutes,
            'break2_start'       => $td->break2_start,
            'break2_minutes'     => $td->break2_minutes,
            'break3_start'       => $td->break3_start,
            'break3_minutes'     => $td->break3_minutes,
        ];

        $this->normalizeTimeFields($updateData);

        if ($existing) {
            $existing->update($updateData);
        } else {
            $isPerMachineDtg = $td->department?->productivity_type?->isPerMachineDtg();
            $kpiPerHour      = $isPerMachineDtg ? 0 : ($td->department?->kpi_per_hour ?? 0);

            ShiftDetail::create(array_merge([
                'shift_id'           => $shift->id,
                'department_id'      => $td->department_id,
                'shift_number'       => $td->shift_number,
                'machine_count'      => null,
                'kpi_per_hour'       => $kpiPerHour,
                'day_start_inventory'=> 0,
            ], $updateData));
        }
    }

    /**
     * Upsert ShiftDetail for a hidden child department inheriting parent schedule.
     */
    private function upsertHiddenChildShiftDetail(Shift $shift, ShiftTemplateDetail $td, int $childId): void
    {
        $existing = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $childId)
            ->where('shift_number', $td->shift_number)
            ->first();

        $updateData = [
            'start_time'         => $td->start_time,
            'work_hours'         => $td->work_hours,
            'prep_minutes'       => $td->prep_minutes,
            'break1_start'       => $td->break1_start,
            'break1_minutes'     => $td->break1_minutes,
            'meal_break_start'   => $td->meal_break_start,
            'meal_break_minutes' => $td->meal_break_minutes,
            'break2_start'       => $td->break2_start,
            'break2_minutes'     => $td->break2_minutes,
            'break3_start'       => $td->break3_start,
            'break3_minutes'     => $td->break3_minutes,
        ];

        $this->normalizeTimeFields($updateData);

        if ($existing) {
            $existing->update($updateData);
        } else {
            if (!array_key_exists($childId, $this->departmentKpiCache)) {
                $child = Department::find($childId);
                $this->departmentKpiCache[$childId] = $child ? ($child->kpi_per_hour ?? 0) : 0;
            }
            $kpiPerHour = $this->departmentKpiCache[$childId];

            ShiftDetail::create(array_merge([
                'shift_id'           => $shift->id,
                'department_id'      => $childId,
                'shift_number'       => $td->shift_number,
                'headcount'          => 0,
                'machine_count'      => null,
                'kpi_per_hour'       => $kpiPerHour,
                'day_start_inventory'=> 0,
            ], $updateData));
        }
    }

    /**
     * Fetch hidden child department IDs for a parent department.
     */
    private function getHiddenChildrenIds(int $parentId): array
    {
        if (!array_key_exists($parentId, $this->hiddenChildrenCache)) {
            $this->hiddenChildrenCache[$parentId] = Department::where('parent_id', $parentId)
                ->where('is_hidden', true)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        }

        return $this->hiddenChildrenCache[$parentId];
    }

    /**
     * Normalize H:i formatted times to H:i:s to prevent database format mismatches.
     */
    private function normalizeTimeFields(array &$data): void
    {
        $timeFields = ['start_time', 'break1_start', 'meal_break_start', 'break2_start', 'break3_start'];
        foreach ($timeFields as $field) {
            if (!empty($data[$field]) && strlen($data[$field]) === 5) {
                $data[$field] .= ':00';
            }
        }
    }
}
