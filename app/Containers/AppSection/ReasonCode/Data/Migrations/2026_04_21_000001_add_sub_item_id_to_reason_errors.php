<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure reason_errors for 3-level hierarchy:
 *   reason_categories → reason_sub_items → reason_errors
 *
 * Idempotent: each step checks current DB state before executing,
 * safe to run even if a previous attempt failed mid-way.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add sub_item_id if not already present
        if (! Schema::hasColumn('reason_errors', 'sub_item_id')) {
            Schema::table('reason_errors', static function (Blueprint $table) {
                $table->foreignId('sub_item_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('reason_sub_items')
                    ->cascadeOnDelete();
            });
        }

        // Step 2: clean up scope_dept if it still exists
        if (Schema::hasColumn('reason_errors', 'scope_dept')) {
            $isMysql = DB::getDriverName() === 'mysql';

            if ($isMysql) {
                Schema::table('reason_errors', static function (Blueprint $table) {
                    // Drop FK on category_id (backed by composite index)
                    $fks = collect(DB::select("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.TABLE_CONSTRAINTS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'reason_errors'
                          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                          AND CONSTRAINT_NAME LIKE '%category_id%'
                    "));

                    foreach ($fks as $fk) {
                        $table->dropForeign($fk->CONSTRAINT_NAME);
                    }

                    // Drop composite index then the column
                    $indexes = collect(DB::select("SHOW INDEX FROM reason_errors WHERE Key_name = 'reason_errors_category_id_scope_dept_index'"));
                    if ($indexes->isNotEmpty()) {
                        $table->dropIndex('reason_errors_category_id_scope_dept_index');
                    }

                    $table->dropColumn('scope_dept');
                });

                // Re-add FK on category_id with a dedicated single-column index
                Schema::table('reason_errors', static function (Blueprint $table) {
                    $fks = collect(DB::select("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.TABLE_CONSTRAINTS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'reason_errors'
                          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                          AND CONSTRAINT_NAME LIKE '%category_id%'
                    "));

                    if ($fks->isEmpty()) {
                        $table->foreign('category_id')
                            ->references('id')
                            ->on('reason_categories')
                            ->cascadeOnDelete();
                    }
                });
            } else {
                // SQLite: drop index first, then column
                Schema::table('reason_errors', static function (Blueprint $table) {
                    try {
                        $table->dropIndex('reason_errors_category_id_scope_dept_index');
                    } catch (\Throwable) {
                        // Index may not exist
                    }
                });
                Schema::table('reason_errors', static function (Blueprint $table) {
                    $table->dropColumn('scope_dept');
                });
            }
        }
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::table('reason_errors', static function (Blueprint $table) use ($isMysql) {
            if (Schema::hasColumn('reason_errors', 'sub_item_id')) {
                if ($isMysql) {
                    $table->dropForeign(['sub_item_id']);
                }
                $table->dropColumn('sub_item_id');
            }

            if (! Schema::hasColumn('reason_errors', 'scope_dept')) {
                if ($isMysql) {
                    $table->dropForeign(['category_id']);
                }
                $table->string('scope_dept', 20)->nullable()->after('label');
                $table->index(['category_id', 'scope_dept']);
                if ($isMysql) {
                    $table->foreign('category_id')
                        ->references('id')
                        ->on('reason_categories')
                        ->cascadeOnDelete();
                }
            }
        });
    }
};
