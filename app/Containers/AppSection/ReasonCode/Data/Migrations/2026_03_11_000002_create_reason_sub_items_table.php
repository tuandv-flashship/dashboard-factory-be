<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_sub_items', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('reason_categories')->cascadeOnDelete();
            $table->string('code', 100);                   // machine-dtf-01, human-absent, mat-ink-white
            $table->string('label');                        // DTF-01, Vắng mặt / Nghỉ phép, Mực trắng
            $table->string('scope_type', 20);               // global, per_department, per_line_department
            $table->string('scope_line', 20)->nullable();    // dtf1, dtf2, dtg (null = all)
            $table->string('scope_dept', 20)->nullable();    // print, cut, mockup, pack_ship, pick (null = all)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'scope_type']);
            $table->index(['scope_line', 'scope_dept']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reason_sub_items');
    }
};
