<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', static function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('shift_number');    // 1, 2, 3
            $table->time('start_time');                      // 06:00
            $table->time('end_time');                         // 14:00
            $table->string('supervisor', 100)->nullable();
            $table->boolean('is_active')->default(true);     // current active shift
            $table->timestamps();

            $table->unique(['date', 'shift_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
