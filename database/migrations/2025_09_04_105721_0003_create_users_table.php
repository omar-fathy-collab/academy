<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 45)->unique();
            $table->string('email')->unique();
            $table->string('pass')->nullable();
            $table->unsignedBigInteger('role_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('role_id')->references('idroles')->on('roles');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
