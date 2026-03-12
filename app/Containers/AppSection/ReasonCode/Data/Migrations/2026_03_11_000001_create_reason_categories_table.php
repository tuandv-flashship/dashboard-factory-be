<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_categories', static function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();        // machine, human, material, process
            $table->string('label');                       // Máy móc
            $table->string('label_en');                    // Machine
            $table->string('icon', 50);                    // Cog (lucide icon name)
            $table->string('color', 20);                   // #ef4444
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reason_categories');
    }
};
