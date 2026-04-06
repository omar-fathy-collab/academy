<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id('rating_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->decimal('rating_value', 3, 1);
            $table->text('comments')->nullable();
            $table->unsignedBigInteger('rated_by');
            $table->timestamp('rated_at')->useCurrent();
            $table->enum('rating_type', ['assignment', 'session', 'monthly']);
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('group_id')->references('group_id')->on('groups');
            $table->foreign('session_id')->references('session_id')->on('sessions');
            $table->foreign('rated_by')->references('id')->on('users');
            $table->index(['session_id', 'rating_type']);
            $table->index(['student_id', 'session_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ratings');
    }
};
