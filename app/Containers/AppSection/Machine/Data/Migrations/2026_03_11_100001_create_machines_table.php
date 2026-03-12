<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', static function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();          // dtf1-dtg01, dtf2-cut01
            $table->string('name', 100);                    // DTF-01, CUT-01, Apollo
            $table->string('status', 20)->default('online'); // online, offline, maintenance
            $table->string('department', 30);                // print, cut, mockup, pack_ship, pick
            $table->string('line', 20);                      // dtf1, dtf2, dtg
            $table->string('description')->nullable();       // Mô tả bổ sung
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['line', 'department']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
