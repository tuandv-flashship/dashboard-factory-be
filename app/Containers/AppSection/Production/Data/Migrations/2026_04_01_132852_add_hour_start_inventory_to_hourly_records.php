<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', function (Blueprint $table) {
            $table->unsignedInteger('hour_start_inventory')
                ->default(0)
                ->after('staff')
                ->comment('Tồn đầu giờ — beginning-of-hour inventory for this time slot');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', function (Blueprint $table) {
            $table->dropColumn('hour_start_inventory');
        });
    }
};
