<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id('quiz_id');
            $table->unsignedBigInteger('session_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->integer('time_limit')->nullable()->comment('الزمن بالدقائق');
            $table->integer('max_attempts')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('session_id')->references('session_id')->on('sessions');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quizzes');
    }
};
