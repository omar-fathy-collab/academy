<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('group_user')) {
            Schema::create('group_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->foreign('group_id')->references('group_id')->on('groups')->onDelete('cascade');
                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->string('role')->nullable(); // student/instructor etc
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('group_user');
    }
};
