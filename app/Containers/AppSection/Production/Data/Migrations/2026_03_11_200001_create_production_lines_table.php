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
            $table->string('code', 20)->unique();     // dtf, dtg, pack_ship
            $table->string('label', 50);                // DTF, DTG, Pack & Ship
            $table->string('color', 20);                // #f59e0b, #8b5cf6, #ec4899
            $table->string('subtitle')->nullable();      // Building 1, Apollo + 2× Atlas
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
