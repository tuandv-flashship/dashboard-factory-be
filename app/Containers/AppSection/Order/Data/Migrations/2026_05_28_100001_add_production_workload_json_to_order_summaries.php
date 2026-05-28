<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_summaries', static function (Blueprint $table) {
            $table->json('production_workload_json')->nullable()->after('progress')
                ->comment('SQL #28 estimate breakdown: [{estimate_date, tong_don, da_lam, chua_lam}]');
        });
    }

    public function down(): void
    {
        Schema::table('order_summaries', static function (Blueprint $table) {
            $table->dropColumn('production_workload_json');
        });
    }
};
