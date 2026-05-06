<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('machine_count')->nullable()->after('staff_required');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', function (Blueprint $table) {
            $table->dropColumn('machine_count');
        });
    }
};
