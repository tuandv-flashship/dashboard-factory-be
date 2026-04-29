<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_issues', static function (Blueprint $table) {
            $table->string('productivity_item_id', 8)
                ->nullable()
                ->after('hourly_record_id')
                ->comment('Links to _id in productivity_json; NULL = department-level issue');

            $table->index(['hourly_record_id', 'productivity_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('hourly_issues', static function (Blueprint $table) {
            $table->dropIndex(['hourly_record_id', 'productivity_item_id']);
            $table->dropColumn('productivity_item_id');
        });
    }
};
