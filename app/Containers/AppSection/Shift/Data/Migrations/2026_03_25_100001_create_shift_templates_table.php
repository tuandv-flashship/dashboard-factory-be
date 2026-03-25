<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', static function (Blueprint $table) {
            $table->id();
            $table->string('name');                                // Tên ca chuẩn
            $table->string('color', 20)->default('#0000FF');       // Mã màu hex
            $table->text('description')->nullable();               // Mô tả
            $table->unsignedSmallInteger('sort_order')->default(0);// Thứ tự hiển thị
            $table->string('status', 20)->default('active');       // active / inactive
            $table->boolean('applies_to_shift_1')->default(true);  // Áp dụng Ca 1
            $table->boolean('applies_to_shift_2')->default(false); // Áp dụng Ca 2
            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
