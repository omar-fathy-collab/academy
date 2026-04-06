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
            $table->unsignedInteger('duration')->nullable()->after('status')->comment('Duration in seconds');
        });

        Schema::table('video_progress', function (Blueprint $table) {
            $table->decimal('watched_percentage', 5, 2)->default(0)->after('watched_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('duration');
        });

        Schema::table('video_progress', function (Blueprint $table) {
            $table->dropColumn('watched_percentage');
        });
    }
};
