<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('student_id');
            $table->enum('status', ['present', 'absent', 'late', 'excused']);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('session_id')->references('session_id')->on('sessions');
            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->unique(['session_id', 'student_id']);
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance');
    }
};
