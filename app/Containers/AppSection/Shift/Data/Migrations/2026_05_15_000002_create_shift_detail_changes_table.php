<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_detail_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_detail_id')
                  ->constrained('shift_details')
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
        Schema::dropIfExists('shift_detail_changes');
    }
};
