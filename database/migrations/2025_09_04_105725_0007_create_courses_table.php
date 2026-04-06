<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id('course_id');
            $table->string('course_name', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();

            $table->string('schedule', 100)->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('department_id')->on('department');
            $table->foreign('teacher_id')->references('teacher_id')->on('teachers');
        });
    }

    public function down()
    {
        Schema::dropIfExists('courses');
    }
};
