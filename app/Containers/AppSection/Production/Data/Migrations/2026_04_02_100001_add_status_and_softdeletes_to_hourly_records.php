<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            // Status lifecycle: pending → active → completed
            $table->string('status', 12)->default('pending')->after('error_rate');
            $table->softDeletes();

            $table->index('status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['status', 'deleted_at']);
        });
    }
};
