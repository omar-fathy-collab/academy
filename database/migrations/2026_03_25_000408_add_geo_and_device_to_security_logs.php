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
        Schema::table('security_logs', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('device_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('anomaly_hint')->nullable(); // For ML/Anomaly detection notes
            $table->decimal('reputation_score', 5, 2)->default(0); // External Threat Intel Score
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('security_logs', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'device_id', 'session_id', 'anomaly_hint', 'reputation_score']);
        });
    }
};
