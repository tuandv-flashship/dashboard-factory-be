<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_rating_level_details', static function (Blueprint $table) {
            $table->renameColumn('requires_reason', 'is_kpi_threshold');
            $table->renameColumn('warn_staff_shortage', 'is_staff_warning_threshold');
        });

        // Reset all to false, then set correct single-selection values
        DB::table('kpi_rating_level_details')->update([
            'is_kpi_threshold'          => false,
            'is_staff_warning_threshold' => false,
        ]);

        // "Đạt" = KPI threshold, "Trung bình" = staff warning threshold
        DB::table('kpi_rating_level_details')
            ->where('level_name', 'Đạt')
            ->update(['is_kpi_threshold' => true]);

        DB::table('kpi_rating_level_details')
            ->where('level_name', 'Trung bình')
            ->update(['is_staff_warning_threshold' => true]);
    }

    public function down(): void
    {
        Schema::table('kpi_rating_level_details', static function (Blueprint $table) {
            $table->renameColumn('is_kpi_threshold', 'requires_reason');
            $table->renameColumn('is_staff_warning_threshold', 'warn_staff_shortage');
        });
    }
};
