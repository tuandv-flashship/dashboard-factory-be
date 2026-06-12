<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrate shift_template_details for PD Pick restructuring.
 *
 * For existing shift templates:
 *   - Rename dtf-pick → pick-pick_dtf (update department_id)
 *   - Move dtg-pick_dtg → pick-pick_dtg (update department_id)
 *   - Create new template detail for parent Pick department
 *
 * FLS factory is not affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('factory.current') !== 'PD') {
            return;
        }

        $now = now();

        // Fetch relevant department IDs
        $pickParent = DB::table('departments')
            ->join('production_lines', 'departments.production_line_id', '=', 'production_lines.id')
            ->where('production_lines.code', 'pick')
            ->where('departments.code', 'pick')
            ->whereNull('departments.parent_id')
            ->select('departments.id')
            ->first();

        $pickDtf = DB::table('departments')
            ->join('production_lines', 'departments.production_line_id', '=', 'production_lines.id')
            ->where('production_lines.code', 'pick')
            ->where('departments.code', 'pick_dtf')
            ->select('departments.id')
            ->first();

        $pickDtg = DB::table('departments')
            ->join('production_lines', 'departments.production_line_id', '=', 'production_lines.id')
            ->where('production_lines.code', 'pick')
            ->where('departments.code', 'pick_dtg')
            ->select('departments.id')
            ->first();

        if (!$pickParent || !$pickDtf || !$pickDtg) {
            return; // Migration 100002 hasn't run yet
        }

        // Get all templates
        $templates = DB::table('shift_templates')->get();

        foreach ($templates as $template) {
            // Find the pick_dtf detail (uses pick_dtf dept id — already updated by migration 100002)
            $pickDtfDetail = DB::table('shift_template_details')
                ->where('shift_template_id', $template->id)
                ->where('department_id', $pickDtf->id)
                ->first();

            if (!$pickDtfDetail) {
                continue;
            }

            // Create parent Pick template detail (same schedule as pick_dtf)
            $exists = DB::table('shift_template_details')
                ->where('shift_template_id', $template->id)
                ->where('department_id', $pickParent->id)
                ->where('shift_number', $pickDtfDetail->shift_number)
                ->exists();

            if (!$exists) {
                DB::table('shift_template_details')->insert([
                    'shift_template_id' => $template->id,
                    'department_id'     => $pickParent->id,
                    'shift_number'      => $pickDtfDetail->shift_number,
                    'headcount'         => 0,
                    'start_time'        => $pickDtfDetail->start_time,
                    'work_hours'        => $pickDtfDetail->work_hours,
                    'prep_minutes'      => $pickDtfDetail->prep_minutes,
                    'break1_start'      => $pickDtfDetail->break1_start,
                    'break1_minutes'    => $pickDtfDetail->break1_minutes,
                    'meal_break_start'  => $pickDtfDetail->meal_break_start,
                    'meal_break_minutes'=> $pickDtfDetail->meal_break_minutes,
                    'break2_start'      => $pickDtfDetail->break2_start,
                    'break2_minutes'    => $pickDtfDetail->break2_minutes,
                    'break3_start'      => $pickDtfDetail->break3_start,
                    'break3_minutes'    => $pickDtfDetail->break3_minutes,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }

        // Also create shift_detail + hourly_records for active shifts
        $activeShifts = DB::table('shifts')->where('is_active', true)->get();

        foreach ($activeShifts as $shift) {
            // Check if parent Pick already has a shift_detail
            $parentDetailExists = DB::table('shift_details')
                ->where('shift_id', $shift->id)
                ->where('department_id', $pickParent->id)
                ->exists();

            if ($parentDetailExists) {
                continue;
            }

            // Copy from pick_dtf child detail
            $childDetail = DB::table('shift_details')
                ->where('shift_id', $shift->id)
                ->where('department_id', $pickDtf->id)
                ->first();

            if (!$childDetail) {
                continue;
            }

            // Create parent shift_detail
            $parentDetailId = DB::table('shift_details')->insertGetId([
                'shift_id'           => $shift->id,
                'department_id'      => $pickParent->id,
                'shift_number'       => $childDetail->shift_number,
                'headcount'          => 0,
                'kpi_per_hour'       => 0,
                'day_start_inventory'=> 0,
                'start_time'         => $childDetail->start_time,
                'work_hours'         => $childDetail->work_hours,
                'prep_minutes'       => $childDetail->prep_minutes,
                'break1_start'       => $childDetail->break1_start,
                'break1_minutes'     => $childDetail->break1_minutes,
                'meal_break_start'   => $childDetail->meal_break_start,
                'meal_break_minutes' => $childDetail->meal_break_minutes,
                'break2_start'       => $childDetail->break2_start,
                'break2_minutes'     => $childDetail->break2_minutes,
                'break3_start'       => $childDetail->break3_start,
                'break3_minutes'     => $childDetail->break3_minutes,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            // Copy hourly_records from pick_dtf child → parent (with zeroed actual/staff)
            $childRecords = DB::table('hourly_records')
                ->where('shift_id', $shift->id)
                ->where('department_id', $pickDtf->id)
                ->whereNull('deleted_at')
                ->orderBy('hour_index')
                ->get();

            $parentRecords = [];
            foreach ($childRecords as $rec) {
                $parentRecords[] = [
                    'shift_id'             => $shift->id,
                    'department_id'        => $pickParent->id,
                    'hour_slot'            => $rec->hour_slot,
                    'hour_index'           => $rec->hour_index,
                    'target'               => null,
                    'actual'               => null,
                    'staff'                => null,
                    'hour_start_inventory' => 0,
                    'efficiency'           => 0,
                    'error_rate'           => 0,
                    'status'               => 'pending',
                    'kpi_hours'            => $rec->kpi_hours,
                    'kpi_minutes'          => $rec->kpi_minutes,
                    'kpi_percent'          => $rec->kpi_percent,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }

            if (!empty($parentRecords)) {
                DB::table('hourly_records')->insert($parentRecords);
            }
        }
    }

    public function down(): void
    {
        if (config('factory.current') !== 'PD') {
            return;
        }

        $pickParent = DB::table('departments')
            ->join('production_lines', 'departments.production_line_id', '=', 'production_lines.id')
            ->where('production_lines.code', 'pick')
            ->where('departments.code', 'pick')
            ->whereNull('departments.parent_id')
            ->select('departments.id')
            ->first();

        if ($pickParent) {
            DB::table('shift_template_details')->where('department_id', $pickParent->id)->delete();
            DB::table('hourly_records')->where('department_id', $pickParent->id)->delete();
            DB::table('shift_details')->where('department_id', $pickParent->id)->delete();
        }
    }
};
