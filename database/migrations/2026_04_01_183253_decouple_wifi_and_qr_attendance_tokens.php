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
            $table->boolean('is_wifi_open')->default(false)->after('is_open');
            $table->boolean('is_qr_open')->default(false)->after('is_wifi_open');
            $table->decimal('lat', 10, 8)->nullable()->after('is_qr_open');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
            $table->integer('radius_meters')->default(200)->after('lng');
        });

        // Initialize new flags based on old is_open flag for partial compatibility
        \DB::table('attendance_tokens')->where('is_open', true)->update([
            'is_wifi_open' => true,
            'is_qr_open' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->dropColumn(['is_wifi_open', 'is_qr_open', 'lat', 'lng', 'radius_meters']);
        });
    }
};
