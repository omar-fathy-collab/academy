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
        Schema::table('session_meetings', function (Blueprint $table) {
            $table->time('end_time')->nullable()->after('meeting_link');
            $table->boolean('is_closed')->default(false)->after('end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_meetings', function (Blueprint $table) {
            //
        });
    }
};
