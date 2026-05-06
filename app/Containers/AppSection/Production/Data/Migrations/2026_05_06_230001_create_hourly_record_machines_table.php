<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hourly_record_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hourly_record_id')->constrained('hourly_records')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->unsignedInteger('kpi_per_hour')->default(0);
            $table->timestamps();

            $table->unique(['hourly_record_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hourly_record_machines');
    }
};
