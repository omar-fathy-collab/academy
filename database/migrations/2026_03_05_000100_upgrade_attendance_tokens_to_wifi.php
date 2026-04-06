<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_tokens', function (Blueprint $table) {
            // Drop old QR-specific columns
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'expires_at']);

            // New WiFi proximity columns
            $table->string('teacher_subnet', 45)->nullable()->after('session_id')
                ->comment('First 3 octets of teacher IP, e.g. 192.168.1');
            $table->boolean('is_open')->default(false)->after('teacher_subnet')
                ->comment('Whether attendance window is currently open');
            $table->timestamp('opened_at')->nullable()->after('is_open');
            $table->timestamp('closed_at')->nullable()->after('opened_at');
        });

        // Ensure unique per session (only one window per session)
        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->unique('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->dropUnique(['session_id']);
            $table->dropColumn(['teacher_subnet', 'is_open', 'opened_at', 'closed_at']);
            $table->string('token', 128)->unique()->nullable()->after('session_id');
            $table->timestamp('expires_at')->nullable()->after('token');
        });
    }
};
