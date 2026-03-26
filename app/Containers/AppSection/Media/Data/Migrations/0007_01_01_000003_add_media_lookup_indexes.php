<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('media_files')) {
            Schema::table('media_files', function (Blueprint $table): void {
                if (! Schema::hasIndex('media_files', 'media_files_folder_mime_created_index')) {
                    $table->index(
                        ['folder_id', 'mime_type', 'created_at'],
                        'media_files_folder_mime_created_index'
                    );
                }
            });
        }

        if (Schema::hasTable('media_folders')) {
            Schema::table('media_folders', function (Blueprint $table): void {
                if (! Schema::hasIndex('media_folders', 'media_folders_parent_name_index')) {
                    $table->index(['parent_id', 'name'], 'media_folders_parent_name_index');
                }
            });
        }

        if (Schema::hasTable('media_settings')) {
            Schema::table('media_settings', function (Blueprint $table): void {
                if (! Schema::hasIndex('media_settings', 'media_settings_key_user_unique')) {
                    $table->unique(['key', 'user_id'], 'media_settings_key_user_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('media_files')) {
            Schema::table('media_files', function (Blueprint $table): void {
                if (Schema::hasIndex('media_files', 'media_files_folder_mime_created_index')) {
                    $table->dropIndex('media_files_folder_mime_created_index');
                }
            });
        }

        if (Schema::hasTable('media_folders')) {
            Schema::table('media_folders', function (Blueprint $table): void {
                if (Schema::hasIndex('media_folders', 'media_folders_parent_name_index')) {
                    $table->dropIndex('media_folders_parent_name_index');
                }
            });
        }

        if (Schema::hasTable('media_settings')) {
            Schema::table('media_settings', function (Blueprint $table): void {
                if (Schema::hasIndex('media_settings', 'media_settings_key_user_unique')) {
                    $table->dropUnique('media_settings_key_user_unique');
                }
            });
        }
    }
};
