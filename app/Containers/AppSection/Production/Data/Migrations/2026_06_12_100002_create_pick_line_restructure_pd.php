<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restructure Pick departments for PD factory:
 *   - Create "pick" production line
 *   - Create parent "pick" department (visible)
 *   - Move DTF "pick" → "pick_dtf" child (hidden)
 *   - Move DTG "pick_dtg" child (hidden)
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

        // 1. Create "pick" production line (sort_order=1: hiển thị đầu tiên) — idempotent
        $pickLine = DB::table('production_lines')->where('code', 'pick')->first();
        if (!$pickLine) {
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
            $pickLineId = $pickLine->id;
            // Ensure correct sort_order
            DB::table('production_lines')->where('id', $pickLineId)->update([
                'sort_order' => 1,
                'updated_at' => $now,
            ]);
        }

        // Re-order existing lines: DTF=2, DTG=3, Pack&Ship=4
        DB::table('production_lines')->where('code', 'dtf')->update([
            'sort_order'  => 2,
            'updated_at'  => $now,
        ]);
        DB::table('production_lines')->where('code', 'dtg')->update([
            'sort_order'  => 3,
            'updated_at'  => $now,
        ]);
        DB::table('production_lines')->where('code', 'pack_ship')->update([
            'sort_order'  => 4,
            'updated_at'  => $now,
        ]);

        // 2. Create parent "pick" department (visible, aggregate) — idempotent
        $pickParent = DB::table('departments')
            ->where('production_line_id', $pickLineId)
            ->where('code', 'pick')
            ->whereNull('parent_id')
            ->first();

        if (!$pickParent) {
            $pickParentId = DB::table('departments')->insertGetId([
                'production_line_id' => $pickLineId,
                'parent_id'          => null,
                'code'               => 'pick',
                'label'              => 'Pick',
                'label_en'           => 'Pick',
                'icon'               => 'ShoppingCart',
                'unit'               => 'shirt',
                'kpi_per_hour'       => 0,
                'sort_order'         => 1,
                'is_active'          => true,
                'is_hidden'          => false,
                'productivity_type'  => 'per_person',
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        } else {
            $pickParentId = $pickParent->id;
        }

        // 3. Move DTF "pick" → Pick line as "pick_dtf" child (hidden)
        $dtfLine = DB::table('production_lines')->where('code', 'dtf')->first();
        if ($dtfLine) {
            // Look in DTF line first, then in Pick line (if already moved)
            $dtfPickDept = DB::table('departments')
                ->where('production_line_id', $dtfLine->id)
                ->where('code', 'pick')
                ->first();

            if ($dtfPickDept) {
                DB::table('departments')->where('id', $dtfPickDept->id)->update([
                    'production_line_id' => $pickLineId,
                    'parent_id'          => $pickParentId,
                    'code'               => 'pick_dtf',
                    'label'              => 'Pick DTF',
                    'label_en'           => 'Pick DTF',
                    'is_hidden'          => true,
                    'sort_order'         => 2,
                    'updated_at'         => $now,
                ]);
            }
            // else: already moved in a previous run — skip
        }

        // 4. Move DTG "pick_dtg" → Pick line as child (hidden)
        $dtgLine = DB::table('production_lines')->where('code', 'dtg')->first();
        if ($dtgLine) {
            $dtgPickDept = DB::table('departments')
                ->where('production_line_id', $dtgLine->id)
                ->where('code', 'pick_dtg')
                ->first();

            if ($dtgPickDept) {
                DB::table('departments')->where('id', $dtgPickDept->id)->update([
                    'production_line_id' => $pickLineId,
                    'parent_id'          => $pickParentId,
                    'is_hidden'          => true,
                    'sort_order'         => 3,
                    'updated_at'         => $now,
                ]);
            }
            // else: already moved — skip
        }
    }

    public function down(): void
    {
        if (config('factory.current') !== 'PD') {
            return;
        }

        $now = now();
        $pickLine = DB::table('production_lines')->where('code', 'pick')->first();

        if (!$pickLine) {
            return;
        }

        // Move pick_dtf back to DTF
        $dtfLine = DB::table('production_lines')->where('code', 'dtf')->first();
        if ($dtfLine) {
            DB::table('departments')
                ->where('production_line_id', $pickLine->id)
                ->where('code', 'pick_dtf')
                ->update([
                    'production_line_id' => $dtfLine->id,
                    'parent_id'          => null,
                    'code'               => 'pick',
                    'label'              => 'Pick',
                    'label_en'           => 'Pick',
                    'is_hidden'          => false,
                    'sort_order'         => 2,
                    'updated_at'         => $now,
                ]);
        }

        // Move pick_dtg back to DTG
        $dtgLine = DB::table('production_lines')->where('code', 'dtg')->first();
        if ($dtgLine) {
            DB::table('departments')
                ->where('production_line_id', $pickLine->id)
                ->where('code', 'pick_dtg')
                ->update([
                    'production_line_id' => $dtgLine->id,
                    'parent_id'          => null,
                    'is_hidden'          => false,
                    'sort_order'         => 1,
                    'updated_at'         => $now,
                ]);
        }

        // Delete parent pick department
        DB::table('departments')
            ->where('production_line_id', $pickLine->id)
            ->where('code', 'pick')
            ->whereNull('parent_id')
            ->delete();

        // Delete pick production line
        DB::table('production_lines')->where('id', $pickLine->id)->delete();

        // Restore original sort_orders: DTF=1, DTG=2, Pack&Ship=3
        DB::table('production_lines')->where('code', 'dtf')->update([
            'sort_order' => 1,
            'updated_at' => $now,
        ]);
        DB::table('production_lines')->where('code', 'dtg')->update([
            'sort_order' => 2,
            'updated_at' => $now,
        ]);
        DB::table('production_lines')->where('code', 'pack_ship')->update([
            'sort_order' => 3,
            'updated_at' => $now,
        ]);
    }
};
