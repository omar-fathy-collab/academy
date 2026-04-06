<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id('option_id');
            $table->unsignedBigInteger('question_id');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('question_id')->references('question_id')->on('questions')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('options');
    }
};
