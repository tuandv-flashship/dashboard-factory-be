<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', static function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('production_line_id')
                  ->constrained('departments')->nullOnDelete();
            $table->boolean('is_hidden')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('departments', static function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'is_hidden']);
        });
    }
};
