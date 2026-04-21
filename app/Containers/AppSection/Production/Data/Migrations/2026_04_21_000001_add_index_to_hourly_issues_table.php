<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite index on (hourly_record_id, resolved_at) to hourly_issues.
 *
 * Optimises GetPendingIssuesTask: WHERE hourly_record_id IN (...) AND resolved_at IS NULL
 * — avoids full table scan as the table grows with each shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hourly_issues', static function (Blueprint $table) {
            $table->index(['hourly_record_id', 'resolved_at'], 'hourly_issues_record_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hourly_issues', static function (Blueprint $table) {
            $table->dropIndex('hourly_issues_record_resolved_idx');
        });
    }
};
