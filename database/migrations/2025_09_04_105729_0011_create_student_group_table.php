<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('group_id');
            $table->date('enrollment_date')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('group_id')->references('group_id')->on('groups');
            $table->unique(['student_id', 'group_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_group');
    }
};
