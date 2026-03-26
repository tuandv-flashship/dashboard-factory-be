<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', static function (Blueprint $table) {
            $table->boolean('can_increase_productivity')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('departments', static function (Blueprint $table) {
            $table->dropColumn('can_increase_productivity');
        });
    }
};
