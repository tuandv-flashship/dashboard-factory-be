<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_lines', static function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();     // dtf1, dtf2, dtg
            $table->string('label', 50);                // DTF 1, DTF 2, DTG
            $table->string('color', 20);                // #f59e0b, #14b8a6, #8b5cf6
            $table->string('building')->nullable();      // Building 1, Building 2
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_lines');
    }
};
