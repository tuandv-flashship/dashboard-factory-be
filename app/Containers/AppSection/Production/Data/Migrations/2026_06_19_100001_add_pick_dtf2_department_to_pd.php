<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add Pick DTF2 department (Pick DTF of FLS factory) to PD Pick line:
 *   - Create "pick_dtf2" child department under parent "pick" department.
 *   - Duplicate shift_template_details from parent "pick" to "pick_dtf2".
 *   - Create shift_details and hourly_records for active shifts.
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

        // 1. Find the Pick parent department
        $pickParent = DB::table('departments')
            ->join('production_lines', 'departments.production_line_id', '=', 'production_lines.id')
            ->where('production_lines.code', 'pick')
            ->where('departments.code', 'pick')
            ->whereNull('departments.parent_id')
            ->select('departments.id', 'departments.production_line_id')
            ->first();

        if (!$pickParent) {
            return; // Parent department not found
        }

        // 2. Create pick_dtf2 department if not exists
        $pickDtf2 = DB::table('departments')
            ->where('production_line_id', $pickParent->production_line_id)
            ->where('code', 'pick_dtf2')
            ->first();

        if (!$pickDtf2) {
            $pickDtf2Id = DB::table('departments')->insertGetId([
                'production_line_id' => $pickParent->production_line_id,
                'parent_id'          => $pickParent->id,
                'code'               => 'pick_dtf2',
                'label'              => 'Pick DTF2',
                'label_en'           => 'Pick DTF2',
                'icon'               => 'ShoppingCart',
                'unit'               => 'shirt',
                'kpi_per_hour'       => 180,
                'sort_order'         => 4,
                'is_active'          => true,
                'is_hidden'          => true,
                'productivity_type'  => 'per_person',
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        } else {
            $pickDtf2Id = $pickDtf2->id;
        }

        // 3. Replicate shift_template_details from parent Pick to pick_dtf2
        $parentTemplateDetails = DB::table('shift_template_details')
            ->where('department_id', $pickParent->id)
            ->get();

        foreach ($parentTemplateDetails as $parentTplDetail) {
            $exists = DB::table('shift_template_details')
                ->where('shift_template_id', $parentTplDetail->shift_template_id)
                ->where('department_id', $pickDtf2Id)
                ->where('shift_number', $parentTplDetail->shift_number)
                ->exists();

            if (!$exists) {
                DB::table('shift_template_details')->insert([
                    'shift_template_id' => $parentTplDetail->shift_template_id,
                    'department_id'     => $pickDtf2Id,
                    'shift_number'      => $parentTplDetail->shift_number,
                    'headcount'         => 0, // Default to 0 headcount
                    'start_time'        => $parentTplDetail->start_time,
                    'work_hours'        => $parentTplDetail->work_hours,
                    'prep_minutes'      => $parentTplDetail->prep_minutes,
                    'break1_start'      => $parentTplDetail->break1_start,
                    'break1_minutes'    => $parentTplDetail->break1_minutes,
                    'meal_break_start'  => $parentTplDetail->meal_break_start,
                    'meal_break_minutes'=> $parentTplDetail->meal_break_minutes,
                    'break2_start'      => $parentTplDetail->break2_start,
                    'break2_minutes'    => $parentTplDetail->break2_minutes,
                    'break3_start'      => $parentTplDetail->break3_start,
                    'break3_minutes'    => $parentTplDetail->break3_minutes,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }

        // 4. Replicate shift_details + hourly_records for active shifts
        $activeShifts = DB::table('shifts')->where('is_active', true)->get();

        foreach ($activeShifts as $shift) {
            // Find parent shift_detail
            $parentDetail = DB::table('shift_details')
                ->where('shift_id', $shift->id)
                ->where('department_id', $pickParent->id)
                ->first();

            if (!$parentDetail) {
                continue;
            }

            // Check if pick_dtf2 already has shift_detail for this shift
            $exists = DB::table('shift_details')
                ->where('shift_id', $shift->id)
                ->where('department_id', $pickDtf2Id)
                ->where('shift_number', $parentDetail->shift_number)
                ->exists();

            if (!$exists) {
                DB::table('shift_details')->insert([
                    'shift_id'           => $shift->id,
                    'department_id'      => $pickDtf2Id,
                    'shift_number'       => $parentDetail->shift_number,
                    'headcount'          => 0,
                    'kpi_per_hour'       => 180,
                    'day_start_inventory'=> 0,
                    'start_time'         => $parentDetail->start_time,
                    'work_hours'         => $parentDetail->work_hours,
                    'prep_minutes'       => $parentDetail->prep_minutes,
                    'break1_start'       => $parentDetail->break1_start,
                    'break1_minutes'     => $parentDetail->break1_minutes,
                    'meal_break_start'   => $parentDetail->meal_break_start,
                    'meal_break_minutes' => $parentDetail->meal_break_minutes,
                    'break2_start'       => $parentDetail->break2_start,
                    'break2_minutes'     => $parentDetail->break2_minutes,
                    'break3_start'       => $parentDetail->break3_start,
                    'break3_minutes'     => $parentDetail->break3_minutes,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }

            // Replicate hourly_records from parent Pick to pick_dtf2
            $parentRecords = DB::table('hourly_records')
                ->where('shift_id', $shift->id)
                ->where('department_id', $pickParent->id)
                ->whereNull('deleted_at')
                ->orderBy('hour_index')
                ->get();

            $childRecords = [];
            foreach ($parentRecords as $rec) {
                $exists = DB::table('hourly_records')
                    ->where('shift_id', $shift->id)
                    ->where('department_id', $pickDtf2Id)
                    ->where('hour_index', $rec->hour_index)
                    ->exists();

                if (!$exists) {
                    $childRecords[] = [
                        'shift_id'             => $shift->id,
                        'department_id'        => $pickDtf2Id,
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
            }

            if (!empty($childRecords)) {
                DB::table('hourly_records')->insert($childRecords);
            }
        }
    }

    public function down(): void
    {
        if (config('factory.current') !== 'PD') {
            return;
        }

        $pickDtf2 = DB::table('departments')
            ->where('code', 'pick_dtf2')
            ->first();

        if ($pickDtf2) {
            DB::table('shift_template_details')->where('department_id', $pickDtf2->id)->delete();
            DB::table('shift_details')->where('department_id', $pickDtf2->id)->delete();
            DB::table('hourly_records')->where('department_id', $pickDtf2->id)->delete();
            DB::table('departments')->where('id', $pickDtf2->id)->delete();
        }
    }
};
