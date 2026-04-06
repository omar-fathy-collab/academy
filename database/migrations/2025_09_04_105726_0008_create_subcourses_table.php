<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subcourses', function (Blueprint $table) {
            $table->id('subcourse_id');
            $table->unsignedBigInteger('course_id');
            $table->string('subcourse_name', 100);
            $table->integer('subcourse_number');
            $table->text('description')->nullable();
            $table->integer('duration_hours')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('course_id')->on('courses')->onDelete('cascade');
            $table->unique(['course_id', 'subcourse_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subcourses');
    }
};
