<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', static function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('label_en');
        });
    }

    public function down(): void
    {
        Schema::table('departments', static function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
