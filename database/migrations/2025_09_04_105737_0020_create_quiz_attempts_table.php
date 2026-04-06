<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id('attempt_id');
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('student_id');
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->enum('status', ['in_progress', 'completed', 'graded'])->default('in_progress');
            $table->timestamps();

            $table->foreign('quiz_id')->references('quiz_id')->on('quizzes');
            $table->foreign('student_id')->references('student_id')->on('students');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
