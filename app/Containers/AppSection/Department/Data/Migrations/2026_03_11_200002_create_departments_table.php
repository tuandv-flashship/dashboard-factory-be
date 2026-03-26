<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_line_id')->constrained('production_lines')->cascadeOnDelete();
            $table->string('code', 30);                  // print, cut, mockup, pack_ship, pick, dtg_print
            $table->string('label', 50);                  // In ấn, Cắt, Ráp mẫu, Đóng gói & Giao
            $table->string('label_en', 50);               // Print, Cut, Mock Up, Pack & Ship
            $table->string('icon', 30);                   // Printer, Scissors, Layers, Package
            $table->string('unit', 20)->default('files');  // files, áo, prints
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['production_line_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
