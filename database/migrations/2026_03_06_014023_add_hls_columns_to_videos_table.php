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
            $table->string('stream_type')->default('mp4'); // mp4 or hls
            $table->string('disk')->default('local'); // local, public, etc.
            $table->json('hls_metadata')->nullable(); // For keys, segment info if needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['stream_type', 'disk', 'hls_metadata']);
        });
    }
};
