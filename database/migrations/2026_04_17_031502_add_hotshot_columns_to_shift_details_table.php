<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_details', function (Blueprint $table) {
            $table->unsignedInteger('hotshot_total')->default(0)->after('day_start_inventory');
            $table->unsignedInteger('hotshot_completed')->default(0)->after('hotshot_total');
        });
    }

    public function down(): void
    {
        Schema::table('shift_details', function (Blueprint $table) {
            $table->dropColumn(['hotshot_total', 'hotshot_completed']);
        });
    }
};
