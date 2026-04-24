<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->unsignedInteger('target')->nullable()->change();
            $table->unsignedSmallInteger('staff')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->unsignedInteger('target')->nullable(false)->default(0)->change();
            $table->unsignedSmallInteger('staff')->nullable(false)->default(0)->change();
        });
    }
};
