<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_template_details', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_template_id')
                ->constrained('shift_templates')
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_number');             // 1 = Ca 1, 2 = Ca 2
            $table->unsignedSmallInteger('headcount')->default(0);   // Số nhân sự làm việc
            $table->time('start_time');                              // Giờ bắt đầu ca
            $table->decimal('work_hours', 4, 1);                     // Số giờ làm (8.5)
            $table->unsignedSmallInteger('prep_minutes')->default(0);// Thời gian chuẩn bị (phút)
            $table->time('break1_start')->nullable();                // Nghỉ giải lao 1 — bắt đầu
            $table->unsignedSmallInteger('break1_minutes')->default(0);
            $table->time('meal_break_start')->nullable();            // Nghỉ ăn — bắt đầu
            $table->unsignedSmallInteger('meal_break_minutes')->default(0);
            $table->time('break2_start')->nullable();                // Nghỉ giải lao 2 — bắt đầu
            $table->unsignedSmallInteger('break2_minutes')->default(0);
            $table->time('break3_start')->nullable();                // Nghỉ giải lao 3 — bắt đầu
            $table->unsignedSmallInteger('break3_minutes')->default(0);
            $table->timestamps();

            $table->unique(
                ['shift_template_id', 'department_id', 'shift_number'],
                'stpl_dept_shift_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_template_details');
    }
};
