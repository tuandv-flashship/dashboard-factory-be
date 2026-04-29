<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')
            ->where('productivity_type', 'per_machine')
            ->update(['productivity_type' => 'per_machine_dtg']);
    }

    public function down(): void
    {
        DB::table('departments')
            ->where('productivity_type', 'per_machine_dtg')
            ->update(['productivity_type' => 'per_machine']);
    }
};
