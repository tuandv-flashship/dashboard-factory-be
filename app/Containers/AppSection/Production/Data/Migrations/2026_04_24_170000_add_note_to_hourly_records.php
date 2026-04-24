<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->text('note')->nullable()->after('staff_required')
                  ->comment('Manual note for this hourly slot');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_records', static function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
