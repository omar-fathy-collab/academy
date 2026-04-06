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
        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->string('qr_token')->nullable()->after('teacher_subnet');
            $table->timestamp('qr_expires_at')->nullable()->after('qr_token');
            $table->integer('refresh_interval')->default(30)->after('qr_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'qr_expires_at', 'refresh_interval']);
        });
    }
};
