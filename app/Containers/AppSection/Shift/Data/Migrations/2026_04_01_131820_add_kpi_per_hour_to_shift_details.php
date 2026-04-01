<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_details', function (Blueprint $table) {
            $table->unsignedInteger('kpi_per_hour')
                ->nullable()
                ->after('headcount')
                ->comment('Snapshot of department kpi_per_hour at shift creation time');
        });
    }

    public function down(): void
    {
        Schema::table('shift_details', function (Blueprint $table) {
            $table->dropColumn('kpi_per_hour');
        });
    }
};
