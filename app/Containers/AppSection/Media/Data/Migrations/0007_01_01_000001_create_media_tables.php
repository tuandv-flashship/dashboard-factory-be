<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('media_folders')) {
            Schema::create('media_folders', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('color')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['parent_id', 'user_id', 'created_at'], 'media_folders_index');
            });
        }

        if (! Schema::hasTable('media_files')) {
            Schema::create('media_files', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name');
                $table->string('alt')->nullable();
                $table->unsignedBigInteger('folder_id')->default(0);
                $table->string('mime_type', 120);
                $table->integer('size');
                $table->string('url');
                $table->text('options')->nullable();
                $table->string('visibility')->default('public');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['folder_id', 'user_id', 'created_at'], 'media_files_index');
            });
        }

        if (! Schema::hasTable('media_settings')) {
            Schema::create('media_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 120);
                $table->text('value')->nullable();
                $table->unsignedBigInteger('media_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_settings');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('media_folders');
    }
};
