<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', static function (Blueprint $table) {
            $table->foreignId('shift_template_id')
                ->nullable()
                ->after('is_active')
                ->constrained('shift_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shifts', static function (Blueprint $table) {
            $table->dropForeign(['shift_template_id']);
            $table->dropColumn('shift_template_id');
        });
    }
};
