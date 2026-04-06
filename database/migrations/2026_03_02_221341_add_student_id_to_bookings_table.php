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
        if (!Schema::hasTable('bookings')) { return; }
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'student_id')) {
                $table->unsignedBigInteger('student_id')->nullable()->after('id');
                // assuming students table primary key is student_id
                $table->foreign('student_id')->references('student_id')->on('students')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
