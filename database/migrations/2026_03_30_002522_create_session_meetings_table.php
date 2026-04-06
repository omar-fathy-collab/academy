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
        Schema::create('session_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions', 'session_id')->onDelete('cascade');
            $table->string('title')->default('Main Meeting');
            $table->string('meeting_link');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_meetings');
    }
};
