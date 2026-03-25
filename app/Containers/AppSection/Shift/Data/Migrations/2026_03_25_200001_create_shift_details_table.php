<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_details', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_number');          // 1 = Ca 1, 2 = Ca 2
            $table->unsignedSmallInteger('headcount')->default(0);
            $table->time('start_time');
            $table->decimal('work_hours', 4, 1);
            $table->unsignedSmallInteger('prep_minutes')->default(0);
            $table->time('break1_start')->nullable();
            $table->unsignedSmallInteger('break1_minutes')->default(0);
            $table->time('meal_break_start')->nullable();
            $table->unsignedSmallInteger('meal_break_minutes')->default(0);
            $table->time('break2_start')->nullable();
            $table->unsignedSmallInteger('break2_minutes')->default(0);
            $table->time('break3_start')->nullable();
            $table->unsignedSmallInteger('break3_minutes')->default(0);
            $table->timestamps();

            $table->unique(['shift_id', 'department_id', 'shift_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_details');
    }
};
