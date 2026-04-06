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
        Schema::table('video_progress', function (Blueprint $table) {
            $table->json('watched_segments')->nullable()->after('watched_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_progress', function (Blueprint $table) {
            $table->dropColumn('watched_segments');
        });
    }
};
