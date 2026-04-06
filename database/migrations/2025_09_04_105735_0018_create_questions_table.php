<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id('question_id');
            $table->unsignedBigInteger('quiz_id');
            $table->text('question_text');
            $table->enum('question_type', ['single_choice', 'multiple_choice', 'true_false', 'short_answer']);
            $table->integer('points')->default(1);
            $table->string('image_path', 255)->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('quiz_id')->on('quizzes')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('questions');
    }
};
