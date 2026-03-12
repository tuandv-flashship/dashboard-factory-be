<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reason_errors', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('reason_categories')->cascadeOnDelete();
            $table->string('code', 100);                   // err-breakdown, herr-late, merr-outstock
            $table->string('label');                        // Hỏng máy / Ngừng hoạt động
            $table->string('scope_dept', 20)->nullable();   // null = all depts in this category
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'scope_dept']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reason_errors');
    }
};
