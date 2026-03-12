<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hourly_issues', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('hourly_record_id')->constrained('hourly_records')->cascadeOnDelete();
            $table->string('category', 20);               // machine, human, material, process
            $table->string('sub_item');                    // machine name or reason label
            $table->string('error');                       // specific error description
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();

            $table->index('hourly_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hourly_issues');
    }
};
