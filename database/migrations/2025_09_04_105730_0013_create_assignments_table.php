<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('teacher_file', 255)->nullable();
            $table->string('user_file', 255)->nullable();
            $table->dateTime('due_date');
            $table->integer('max_score')->default(100);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('group_id')->references('group_id')->on('groups');
            $table->foreign('created_by')->references('id')->on('users');
            $table->unique(['session_id', 'group_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('assignments');
    }
};
