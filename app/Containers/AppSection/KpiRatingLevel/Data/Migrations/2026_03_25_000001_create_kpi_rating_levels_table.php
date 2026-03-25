<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_rating_levels', static function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // Tên mức đánh giá
            $table->date('effective_from');                       // Ngày áp dụng
            $table->date('effective_until')->nullable();          // Ngày hết hiệu lực (null = vô thời hạn)
            $table->text('description')->nullable();             // Mô tả
            $table->timestamps();

            $table->index('effective_from');
            $table->index('effective_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_rating_levels');
    }
};
