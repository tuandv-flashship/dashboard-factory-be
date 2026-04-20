<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', function (Blueprint $table) {
            $table->unsignedTinyInteger('kpi_minutes')
                ->default(60)
                ->after('kpi_hours')
                ->comment('Effective KPI minutes for this slot (integer, exact — no rounding loss)');

            $table->decimal('kpi_percent', 5, 2)
                ->default(100.00)
                ->after('kpi_minutes')
                ->comment('kpi_minutes / 60 * 100 — portion of a standard 1-hour block');
        });

        // Backfill existing records from kpi_hours
        DB::statement('
            UPDATE hourly_records
            SET
                kpi_minutes = ROUND(kpi_hours * 60),
                kpi_percent = ROUND(kpi_hours * 100, 2)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('hourly_records', function (Blueprint $table) {
            $table->dropColumn(['kpi_minutes', 'kpi_percent']);
        });
    }
};
