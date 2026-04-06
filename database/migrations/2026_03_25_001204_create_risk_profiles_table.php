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
        Schema::create('risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique(); // IP or UserID_hash
            $table->integer('trust_score')->default(100); // 0-100, 100=perfect trust
            $table->integer('attack_count')->default(0);
            $table->integer('false_positive_count')->default(0);
            $table->json('behavior_metadata')->nullable(); // tracking freq, paths
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_profiles');
    }
};
