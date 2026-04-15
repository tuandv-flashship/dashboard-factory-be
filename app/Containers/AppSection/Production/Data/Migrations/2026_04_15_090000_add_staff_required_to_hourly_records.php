<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->unsignedSmallInteger('staff_required')->nullable()->after('staff')
                  ->comment('Required staff = ceil(inventory / remaining_kpi_hours / kpi_per_hour)');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->dropColumn('staff_required');
        });
    }
};
