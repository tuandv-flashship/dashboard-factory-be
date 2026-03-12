<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_hourly_records', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('production_line_id')->constrained('production_lines')->cascadeOnDelete();
            $table->string('hour_slot', 10);              // "6h-7h"
            $table->unsignedTinyInteger('hour_index');     // 0-7
            $table->unsignedInteger('target');
            $table->unsignedInteger('actual')->nullable(); // null = future hour
            $table->unsignedSmallInteger('staff')->default(0);
            $table->float('efficiency')->default(0);
            $table->float('error_rate')->default(0);
            $table->unsignedInteger('total_picked')->default(0);
            $table->timestamps();

            $table->unique(['shift_id', 'production_line_id', 'hour_index'], 'pick_shift_line_hour_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_hourly_records');
    }
};
