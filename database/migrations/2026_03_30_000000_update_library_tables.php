<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update videos table
        Schema::table('videos', function (Blueprint $table) {
            // First drop the old enum if necessary or just modify it
            // In MySQL, we can modify the column
            $table->decimal('price', 10, 2)->default(0)->after('visibility');
        });

        // Use raw query for enum update to ensure compatibility with all DB drivers that support it
        DB::statement("ALTER TABLE videos MODIFY COLUMN visibility ENUM('public', 'private', 'group') NOT NULL DEFAULT 'private'");

        // 2. Create books table
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('session_id')->unsigned()->nullable();
            $table->bigInteger('group_id')->unsigned()->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('thumbnail_url')->nullable();
            $table->enum('visibility', ['public', 'private', 'group'])->default('private');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_library')->default(false);
            $table->string('status')->default('ready');
            $table->timestamps();

            $table->foreign('session_id')->references('session_id')->on('sessions')->onDelete('set null');
            $table->foreign('group_id')->references('group_id')->on('groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
        
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('price');
        });
        
        DB::statement("ALTER TABLE videos MODIFY COLUMN visibility ENUM('public', 'private') NOT NULL DEFAULT 'private'");
    }
};
