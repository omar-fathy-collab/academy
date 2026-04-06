<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->bigInteger('session_id')->unsigned()->nullable()->change();
            $table->enum('visibility', ['public', 'private'])->default('private')->after('thumbnail_url');
            $table->boolean('is_library')->default(false)->after('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->bigInteger('session_id')->unsigned()->nullable(false)->change();
            $table->dropColumn(['visibility', 'is_library']);
        });
    }
};
