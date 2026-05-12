<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        $permissionsTableName = config('permission.table_names')['permissions'];
        Schema::table($permissionsTableName, static function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        $permissionsTableName = config('permission.table_names')['permissions'];
        Schema::table($permissionsTableName, static function (Blueprint $table) {
            $table->dropColumn('hidden');
        });
    }
};
