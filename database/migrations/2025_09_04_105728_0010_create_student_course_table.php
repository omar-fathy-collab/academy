<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_course', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->timestamp('enrollment_date')->nullable()->useCurrent();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('course_id')->references('course_id')->on('courses');
            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_course');
    }
};
