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
            if (!Schema::hasColumn('videos', 'provider')) {
                $table->string('provider')->default('local')->after('stream_type');
            }
            if (!Schema::hasColumn('videos', 'provider_id')) {
                $table->string('provider_id')->nullable()->after('provider');
            }
            if (!Schema::hasColumn('videos', 'thumbnail_url')) {
                $table->text('thumbnail_url')->nullable()->after('provider_id');
            }
            if (!Schema::hasColumn('videos', 'meta_tags')) {
                $table->json('meta_tags')->nullable()->after('thumbnail_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_id', 'thumbnail_url', 'meta_tags']);
        });
    }
};
