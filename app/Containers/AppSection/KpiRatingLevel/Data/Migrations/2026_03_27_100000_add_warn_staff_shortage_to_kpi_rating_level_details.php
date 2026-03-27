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
            $table->boolean('warn_staff_shortage')->default(false)->after('requires_reason');
        });

        // Update existing data: enable warning for levels that require a reason
        DB::table('kpi_rating_level_details')
            ->where('requires_reason', true)
            ->update(['warn_staff_shortage' => true]);
    }

    public function down(): void
    {
        Schema::table('kpi_rating_level_details', static function (Blueprint $table) {
            $table->dropColumn('warn_staff_shortage');
        });
    }
};
