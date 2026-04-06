<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id('answer_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('option_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 5, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('attempt_id')->references('attempt_id')->on('quiz_attempts');
            $table->foreign('question_id')->references('question_id')->on('questions');
            $table->foreign('option_id')->references('option_id')->on('options');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quiz_answers');
    }
};
