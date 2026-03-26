<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('media_files')) {
            return;
        }

        if (! Schema::hasColumn('media_files', 'access_mode')) {
            Schema::table('media_files', function (Blueprint $table): void {
                $table->string('access_mode', 20)->nullable()->after('visibility');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('media_files') && Schema::hasColumn('media_files', 'access_mode')) {
            Schema::table('media_files', function (Blueprint $table): void {
                $table->dropColumn('access_mode');
            });
        }
    }
};
