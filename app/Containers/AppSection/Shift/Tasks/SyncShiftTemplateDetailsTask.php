<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SyncShiftTemplateDetailsTask extends ParentTask
{
    /**
     * Delete existing details and recreate from the given array.
     * Then auto-replicate to hidden children of parent departments.
     *
     * Optimized: uses bulk insert instead of N separate create() calls.
     *
     * @param  int   $shiftTemplateId
     * @param  array $details  Array of detail rows
     */
    public function run(int $shiftTemplateId, array $details): void
    {
        $now = now();

        // Preserve template details for hidden independent departments (no parent, is_hidden=true)
        // e.g. FLS Pick DTF: hidden from admin UI but needs its own template config for shift creation
        $hiddenIndependentDeptIds = Department::whereNull('parent_id')
            ->where('is_hidden', true)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        $preservedRows = [];
        if (!empty($hiddenIndependentDeptIds)) {
            $preservedRows = ShiftTemplateDetail::where('shift_template_id', $shiftTemplateId)
                ->whereIn('department_id', $hiddenIndependentDeptIds)
                ->get()
                ->map(fn ($row) => [
                    'shift_template_id' => $shiftTemplateId,
                    'department_id'     => $row->department_id,
                    'shift_number'      => $row->shift_number,
                    'headcount'         => $row->headcount,
                    'start_time'        => $row->start_time,
                    'work_hours'        => $row->work_hours,
                    'prep_minutes'      => $row->prep_minutes,
                    'break1_start'      => $row->break1_start,
                    'break1_minutes'    => $row->break1_minutes,
                    'meal_break_start'  => $row->meal_break_start,
                    'meal_break_minutes'=> $row->meal_break_minutes,
                    'break2_start'      => $row->break2_start,
                    'break2_minutes'    => $row->break2_minutes,
                    'break3_start'      => $row->break3_start,
                    'break3_minutes'    => $row->break3_minutes,
                    'created_at'        => $row->created_at ?? $now,
                    'updated_at'        => $now,
                ])
                ->toArray();
        }

        // Remove all existing details
        ShiftTemplateDetail::where('shift_template_id', $shiftTemplateId)->delete();

        // Bulk insert from FE payload (visible departments only)
        $rows = array_map(fn ($detail) => [
            'shift_template_id' => $shiftTemplateId,
            'department_id'     => $detail['department_id'],
            'shift_number'      => $detail['shift_number'],
            'headcount'         => $detail['headcount'] ?? 0,
            'start_time'        => $detail['start_time'],
            'work_hours'        => $detail['work_hours'],
            'prep_minutes'      => $detail['prep_minutes'] ?? 0,
            'break1_start'      => $detail['break1_start'] ?? null,
            'break1_minutes'    => $detail['break1_minutes'] ?? 0,
            'meal_break_start'  => $detail['meal_break_start'] ?? null,
            'meal_break_minutes'=> $detail['meal_break_minutes'] ?? 0,
            'break2_start'      => $detail['break2_start'] ?? null,
            'break2_minutes'    => $detail['break2_minutes'] ?? 0,
            'break3_start'      => $detail['break3_start'] ?? null,
            'break3_minutes'    => $detail['break3_minutes'] ?? 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ], $details);

        if (!empty($rows)) {
            ShiftTemplateDetail::insert($rows);
        }

        // Re-insert preserved rows for hidden independent departments
        if (!empty($preservedRows)) {
            ShiftTemplateDetail::insert($preservedRows);
        }

        // Auto-replicate to hidden children of parent departments
        $this->replicateToHiddenChildren($shiftTemplateId, $rows, $now);
    }

    /**
     * For each parent department in the template, copy its details
     * to all hidden children (e.g., pick → pick_dtf, pick_dtg).
     *
     * Children inherit the same schedule/breaks as the parent but
     * with headcount=0 (actual headcount comes from shift override or sync).
     *
     * Uses the already-built $parentRows to avoid re-querying the DB.
     */
    private function replicateToHiddenChildren(int $shiftTemplateId, array $parentRows, $now): void
    {
        $parentDeptIds = array_values(array_unique(array_column($parentRows, 'department_id')));

        $hiddenChildren = Department::whereIn('parent_id', $parentDeptIds)
            ->where('is_hidden', true)
            ->where('is_active', true)
            ->get()
            ->groupBy('parent_id');

        if ($hiddenChildren->isEmpty()) {
            return;
        }

        $childRows = [];

        foreach ($parentRows as $parentRow) {
            $children = $hiddenChildren->get($parentRow['department_id']);
            if (!$children) {
                continue;
            }

            foreach ($children as $child) {
                $childRows[] = [
                    'shift_template_id' => $shiftTemplateId,
                    'department_id'     => $child->id,
                    'shift_number'      => $parentRow['shift_number'],
                    'headcount'         => 0,
                    'start_time'        => $parentRow['start_time'],
                    'work_hours'        => $parentRow['work_hours'],
                    'prep_minutes'      => $parentRow['prep_minutes'],
                    'break1_start'      => $parentRow['break1_start'],
                    'break1_minutes'    => $parentRow['break1_minutes'],
                    'meal_break_start'  => $parentRow['meal_break_start'],
                    'meal_break_minutes'=> $parentRow['meal_break_minutes'],
                    'break2_start'      => $parentRow['break2_start'],
                    'break2_minutes'    => $parentRow['break2_minutes'],
                    'break3_start'      => $parentRow['break3_start'],
                    'break3_minutes'    => $parentRow['break3_minutes'],
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        if (!empty($childRows)) {
            ShiftTemplateDetail::insert($childRows);
        }
    }
}
