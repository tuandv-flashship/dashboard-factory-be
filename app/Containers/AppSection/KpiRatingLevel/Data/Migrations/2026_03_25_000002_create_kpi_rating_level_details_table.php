<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_rating_level_details', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_level_id')
                ->constrained('kpi_rating_levels')
                ->cascadeOnDelete();
            $table->string('level_name');                        // Xuất sắc, Đạt, Trung bình, Yếu, Chưa đạt
            $table->string('bg_color', 20);                      // #4CAF50
            $table->string('text_color', 20);                    // #FFFFFF
            $table->decimal('min_score', 5, 2);                  // 100.00, 95.00, 90.00, ...
            $table->string('operator', 5)->default('>=');        // >= hoặc <
            $table->boolean('requires_reason')->default(false);  // Yêu cầu nhập lý do
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['rating_level_id', 'level_name'], 'krl_details_level_name_unique');
            $table->unique(['rating_level_id', 'min_score', 'operator'], 'krl_details_min_score_op_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_rating_level_details');
    }
};
