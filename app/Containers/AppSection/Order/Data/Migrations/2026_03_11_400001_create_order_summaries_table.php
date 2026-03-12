<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_summaries', static function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('shift_number');
            $table->string('line', 20)->nullable();       // dtf1, dtf2, dtg (null = total)
            $table->string('line_label', 50)->nullable();  // DTF 1, DTF 2, DTG
            $table->unsignedInteger('total');
            $table->unsignedInteger('completed');
            $table->unsignedInteger('remaining');
            $table->string('estimated_done', 10);          // "16:30"
            $table->unsignedInteger('rush_completed')->default(0);
            $table->unsignedInteger('rush_total')->default(0);
            $table->float('progress');                     // 57.0
            $table->timestamps();

            $table->index(['date', 'shift_number']);
            $table->index('line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_summaries');
    }
};
