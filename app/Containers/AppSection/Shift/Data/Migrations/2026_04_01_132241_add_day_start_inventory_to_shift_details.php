<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_details', function (Blueprint $table) {
            $table->unsignedInteger('day_start_inventory')
                ->default(0)
                ->after('kpi_per_hour')
                ->comment('Tồn đầu ngày — beginning-of-day inventory for this department');
        });
    }

    public function down(): void
    {
        Schema::table('shift_details', function (Blueprint $table) {
            $table->dropColumn('day_start_inventory');
        });
    }
};
