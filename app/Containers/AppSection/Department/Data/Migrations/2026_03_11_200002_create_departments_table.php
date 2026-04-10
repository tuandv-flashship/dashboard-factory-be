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
            $table->string('code', 30);                  // print, pick, cut, mockup, pack_ship, apollo, ...

            $table->string('label', 50);                  // In ấn, Pick, Cắt, Ráp mẫu, ...
            $table->string('label_en', 50);               // Print, Pick, Cut, Mock Up, ...
            $table->string('description', 255)->nullable();
            $table->string('icon', 30);                   // Printer, ShoppingCart, Scissors, Layers, Package
            $table->string('unit', 20)->default('file');   // file, shirt, print
            $table->unsignedInteger('kpi_per_hour')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('productivity_type', 20)->default('per_person'); // per_person, per_machine
            $table->timestamps();

            $table->unique(['production_line_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
