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
        Schema::create('video_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students', 'student_id')->onDelete('cascade');
            $table->foreignId('video_id')->constrained('videos', 'id')->onDelete('cascade');
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->unsignedInteger('last_position')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'video_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_progress');
    }
};
