<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_records', static function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('shift_number');
            $table->float('pass_rate');                    // 98.1
            $table->unsignedInteger('inspected');           // 1056
            $table->unsignedInteger('passed');              // 1036
            $table->unsignedInteger('failed');              // 20
            $table->float('avg_error_rate');                // 1.9
            $table->timestamps();

            $table->unique(['date', 'shift_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_records');
    }
};
