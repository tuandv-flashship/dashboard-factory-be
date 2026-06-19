<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restructure production lines and departments for FLS factory:
 *   - Create "pick" production line (sort_order=1)
 *   - Create "pack_ship" production line (label="PACK", sort_order=3)
 *   - Update "dtf" production line (sort_order=2)
 *   - Move DTF "pick" department -> "pick" line, rename to "Pick DTF", and set kpi_per_hour=0
 *   - Move DTF "pack_ship" department -> "pack_ship" line
 *
 * PD factory is not affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('factory.current') !== 'FLS') {
            return;
        }

        $now = now();

        // 1. Create or update "pick" production line (sort_order=1)
        $pickLineId = DB::table('production_lines')->where('code', 'pick')->value('id');
        if (!$pickLineId) {
            $pickLineId = DB::table('production_lines')->insertGetId([
                'code'       => 'pick',
                'label'      => 'Pick',
                'color'      => '#06b6d4',
                'subtitle'   => 'Lấy hàng — Chung',
                'sort_order' => 1,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('production_lines')->where('id', $pickLineId)->update([
                'sort_order' => 1,
                'updated_at' => $now,
            ]);
        }

        // 2. Create or update "pack_ship" production line (label="PACK", sort_order=3)
        $packLineId = DB::table('production_lines')->where('code', 'pack_ship')->value('id');
        if (!$packLineId) {
            $packLineId = DB::table('production_lines')->insertGetId([
                'code'       => 'pack_ship',
                'label'      => 'PACK',
                'color'      => '#ec4899',
                'subtitle'   => 'Đóng gói & Giao',
                'sort_order' => 3,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('production_lines')->where('id', $packLineId)->update([
                'label'      => 'PACK',
                'sort_order' => 3,
                'updated_at' => $now,
            ]);
        }

        // 3. Update "dtf" production line (sort_order=2)
        DB::table('production_lines')->where('code', 'dtf')->update([
            'sort_order' => 2,
            'updated_at' => $now,
        ]);

        // 4. Move "pick" department to "pick" line, rename to "Pick DTF", and set KPI to 0
        DB::table('departments')
            ->where('code', 'pick')
            ->whereNull('parent_id')
            ->update([
                'production_line_id' => $pickLineId,
                'label'              => 'Pick DTF',
                'label_en'           => 'Pick DTF',
                'kpi_per_hour'       => 0,
                'is_hidden'          => true,
                'updated_at'         => $now,
            ]);

        // 5. Move "pack_ship" department to "pack_ship" line
        DB::table('departments')
            ->where('code', 'pack_ship')
            ->update([
                'production_line_id' => $packLineId,
                'updated_at'         => $now,
            ]);
    }

    public function down(): void
    {
        if (config('factory.current') !== 'FLS') {
            return;
        }

        $now = now();

        $dtfLine = DB::table('production_lines')->where('code', 'dtf')->first();
        if (!$dtfLine) {
            return;
        }

        // Move pick department back to dtf
        DB::table('departments')
            ->where('code', 'pick')
            ->whereNull('parent_id')
            ->update([
                'production_line_id' => $dtfLine->id,
                'label'              => 'Pick',
                'label_en'           => 'Pick',
                'kpi_per_hour'       => 180,
                'is_hidden'          => false,
                'updated_at'         => $now,
            ]);

        // Move pack_ship department back to dtf
        DB::table('departments')
            ->where('code', 'pack_ship')
            ->update([
                'production_line_id' => $dtfLine->id,
                'updated_at'         => $now,
            ]);

        // Delete new lines
        DB::table('production_lines')->whereIn('code', ['pick', 'pack_ship'])->delete();

        // Restore dtf sort order
        DB::table('production_lines')->where('code', 'dtf')->update([
            'sort_order' => 1,
            'updated_at' => $now,
        ]);
    }
};
