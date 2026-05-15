<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hourly_record_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hourly_record_id')
                  ->constrained('hourly_records')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 100);
            $table->json('changes');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hourly_record_changes');
    }
};
