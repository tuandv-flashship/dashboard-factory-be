<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->foreignId('avatar_id')->nullable()->after('birth');
            $table->string('phone')->nullable()->after('avatar_id');
            $table->text('description')->nullable()->after('phone');
            $table->string('status')->default('active')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn(['avatar_id', 'phone', 'description', 'status']);
        });
    }
};
