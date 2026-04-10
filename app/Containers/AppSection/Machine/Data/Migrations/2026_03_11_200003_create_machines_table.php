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
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('code', 50);                     // apollo, atlas_01, atlas_02
            $table->string('name', 100);                     // Apollo, Atlas-01, Atlas-02
            $table->string('status', 20)->default('online'); // online, offline, maintenance
            $table->string('description')->nullable();
            $table->string('unit', 20)->default('print');    // đơn vị tính năng suất (print, file, shirt)
            $table->unsignedInteger('kpi_per_hour')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'code']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
