<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', static function (Blueprint $table) {
            $table->id();
            $table->string('severity', 20);             // critical, warning, info
            $table->string('department', 50);            // Print, Pack & Ship, Mock Up
            $table->time('time');                         // 10:42
            $table->text('message');                      // Máy in DTF-03 ngừng hoạt động...
            $table->string('line', 20)->default('all');   // dtf1, dtf2, dtg, all
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['line', 'severity']);
            $table->index('is_resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
