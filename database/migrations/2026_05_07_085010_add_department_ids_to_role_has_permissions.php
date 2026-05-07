<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->json('department_ids')->nullable()->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropColumn('department_ids');
        });
    }
};
