<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════
        // 1. production_lines: rename building → subtitle, add is_shared
        // ═══════════════════════════════════════════════════════
        Schema::table('production_lines', static function (Blueprint $table) {
            $table->renameColumn('building', 'subtitle');
        });

        Schema::table('production_lines', static function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('subtitle');
        });

        // Update existing subtitles
        DB::table('production_lines')->where('code', 'dtg')
            ->update(['subtitle' => 'Apollo + 2× Atlas']);

        // ═══════════════════════════════════════════════════════
        // 2. departments: add kpi_per_hour, factory; standardize unit
        // ═══════════════════════════════════════════════════════
        Schema::table('departments', static function (Blueprint $table) {
            $table->unsignedInteger('kpi_per_hour')->default(0)->after('unit');
            $table->string('factory', 10)->default('FLS')->after('kpi_per_hour');
        });

        // Standardize unit: áo → shirt
        DB::table('departments')->where('unit', 'áo')->update(['unit' => 'shirt']);
        // files → file
        DB::table('departments')->where('unit', 'files')->update(['unit' => 'file']);
        // prints → print
        DB::table('departments')->where('unit', 'prints')->update(['unit' => 'print']);

        // ═══════════════════════════════════════════════════════
        // 3. Insert pick production_line
        // ═══════════════════════════════════════════════════════
        DB::table('production_lines')->insert([
            'code' => 'pick',
            'label' => 'Pick',
            'color' => '#ec4899',
            'subtitle' => 'Lấy hàng — Chung cho DTF1 + DTF2 + DTG',
            'is_shared' => true,
            'sort_order' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pickLineId = DB::table('production_lines')->where('code', 'pick')->value('id');
        $now = now();

        // ═══════════════════════════════════════════════════════
        // 4. Insert 3 pick departments
        // ═══════════════════════════════════════════════════════
        DB::table('departments')->insert([
            [
                'production_line_id' => $pickLineId,
                'code' => 'dtf1', 'label' => 'Pick DTF 1', 'label_en' => 'Pick DTF 1',
                'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180,
                'factory' => 'FLS', 'sort_order' => 1, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'production_line_id' => $pickLineId,
                'code' => 'dtf2', 'label' => 'Pick DTF 2', 'label_en' => 'Pick DTF 2',
                'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180,
                'factory' => 'PD', 'sort_order' => 2, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'production_line_id' => $pickLineId,
                'code' => 'dtg', 'label' => 'Pick DTG', 'label_en' => 'Pick DTG',
                'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180,
                'factory' => 'PD', 'sort_order' => 3, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // ═══════════════════════════════════════════════════════
        // 5. Migrate pick_hourly_records → hourly_records
        // ═══════════════════════════════════════════════════════
        if (Schema::hasTable('pick_hourly_records')) {
            // Map old production_line_id to new pick department_id
            $pickDepts = DB::table('departments')
                ->where('production_line_id', $pickLineId)
                ->pluck('id', 'code'); // ['dtf1' => id, 'dtf2' => id, 'dtg' => id]

            $lineCodeMap = DB::table('production_lines')
                ->whereIn('code', ['dtf1', 'dtf2', 'dtg'])
                ->pluck('code', 'id'); // [lineId => 'dtf1', ...]

            $pickRecords = DB::table('pick_hourly_records')->get();

            $hourlyInserts = [];
            foreach ($pickRecords as $record) {
                $lineCode = $lineCodeMap[$record->production_line_id] ?? null;
                if (!$lineCode || !isset($pickDepts[$lineCode])) {
                    continue;
                }

                $hourlyInserts[] = [
                    'shift_id' => $record->shift_id,
                    'department_id' => $pickDepts[$lineCode],
                    'hour_slot' => $record->hour_slot,
                    'hour_index' => $record->hour_index,
                    'target' => $record->target,
                    'actual' => $record->actual,
                    'staff' => $record->staff,
                    'efficiency' => $record->efficiency,
                    'error_rate' => $record->error_rate,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                ];
            }

            if (!empty($hourlyInserts)) {
                DB::table('hourly_records')->insert($hourlyInserts);
            }

            // Generate hourly_issues for migrated records with gap > 10%
            $migratedRecords = DB::table('hourly_records')
                ->whereIn('department_id', $pickDepts->values())
                ->whereNotNull('actual')
                ->get();

            $issueInserts = [];
            foreach ($migratedRecords as $record) {
                if ($record->actual >= $record->target) {
                    continue;
                }

                $gap = $record->target - $record->actual;

                if ($gap > $record->target * 0.1) {
                    $issueInserts[] = [
                        'hourly_record_id' => $record->id,
                        'category' => 'machine',
                        'sub_item' => 'Máy chính',
                        'error' => 'Chạy chậm / Giảm tốc độ',
                        'note' => "Giảm {$gap} so với KPI",
                        'resolution' => $gap > $record->target * 0.2 ? null : 'Đã khắc phục, tăng tốc giờ sau',
                        'resolved_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($gap > $record->target * 0.05 && $gap <= $record->target * 0.1) {
                    $issueInserts[] = [
                        'hourly_record_id' => $record->id,
                        'category' => 'human',
                        'sub_item' => 'Nhân viên mới / Chưa thạo',
                        'error' => 'Chưa được đào tạo',
                        'note' => '1 nhân viên mới, cần hỗ trợ',
                        'resolution' => null,
                        'resolved_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($issueInserts)) {
                DB::table('hourly_issues')->insert($issueInserts);
            }

            // ═══════════════════════════════════════════════════════
            // 6. Drop pick_hourly_records table
            // ═══════════════════════════════════════════════════════
            Schema::dropIfExists('pick_hourly_records');
        }
    }

    public function down(): void
    {
        // Recreate pick_hourly_records table
        Schema::create('pick_hourly_records', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('production_line_id')->constrained('production_lines')->cascadeOnDelete();
            $table->string('hour_slot', 10);
            $table->unsignedTinyInteger('hour_index');
            $table->unsignedInteger('target');
            $table->unsignedInteger('actual')->nullable();
            $table->unsignedSmallInteger('staff')->default(0);
            $table->float('efficiency')->default(0);
            $table->float('error_rate')->default(0);
            $table->unsignedInteger('total_picked')->default(0);
            $table->timestamps();

            $table->index(['shift_id', 'production_line_id', 'hour_index']);
        });

        // Remove pick departments and pick line
        $pickLineId = DB::table('production_lines')->where('code', 'pick')->value('id');
        if ($pickLineId) {
            $pickDeptIds = DB::table('departments')
                ->where('production_line_id', $pickLineId)
                ->pluck('id');

            DB::table('hourly_issues')
                ->whereIn('hourly_record_id', function ($q) use ($pickDeptIds) {
                    $q->select('id')->from('hourly_records')->whereIn('department_id', $pickDeptIds);
                })->delete();

            DB::table('hourly_records')->whereIn('department_id', $pickDeptIds)->delete();
            DB::table('departments')->where('production_line_id', $pickLineId)->delete();
            DB::table('production_lines')->where('id', $pickLineId)->delete();
        }

        // Revert unit values
        DB::table('departments')->where('unit', 'shirt')->update(['unit' => 'áo']);
        DB::table('departments')->where('unit', 'file')->update(['unit' => 'files']);
        DB::table('departments')->where('unit', 'print')->update(['unit' => 'prints']);

        // Remove new columns
        Schema::table('departments', static function (Blueprint $table) {
            $table->dropColumn(['kpi_per_hour', 'factory']);
        });

        Schema::table('production_lines', static function (Blueprint $table) {
            $table->dropColumn('is_shared');
        });

        Schema::table('production_lines', static function (Blueprint $table) {
            $table->renameColumn('subtitle', 'building');
        });

        DB::table('production_lines')->where('code', 'dtg')
            ->update(['building' => null]);
    }
};
