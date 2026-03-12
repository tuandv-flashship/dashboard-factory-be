<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('request_logs', function (Blueprint $table): void {
            $table->index(['status_code', 'url'], 'request_logs_status_url_index');
            $table->index('created_at', 'request_logs_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('request_logs', function (Blueprint $table): void {
            $table->dropIndex('request_logs_status_url_index');
            $table->dropIndex('request_logs_created_at_index');
        });
    }
};
