<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->decimal('kpi_hours', 3, 2)->default(1.00)->after('target')
                  ->comment('Effective KPI hours = slot_duration - break overlap');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->dropColumn('kpi_hours');
        });
    }
};
