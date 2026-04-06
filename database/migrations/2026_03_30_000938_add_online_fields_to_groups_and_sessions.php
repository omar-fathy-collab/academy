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
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('group_name');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->change();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->string('meeting_link')->nullable()->after('topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('is_online');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('meeting_link');
        });
    }
};
