<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id('group_id');
            $table->string('group_name', 100);
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('subcourse_id')->nullable();
            $table->unsignedBigInteger('teacher_id');
            $table->string('schedule', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('courses');
            $table->foreign('subcourse_id')->references('subcourse_id')->on('subcourses');
            $table->foreign('teacher_id')->references('teacher_id')->on('teachers');
        });
    }

    public function down()
    {
        Schema::dropIfExists('groups');
    }
};
