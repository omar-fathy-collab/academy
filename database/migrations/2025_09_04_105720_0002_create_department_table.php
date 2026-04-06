<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('department', function (Blueprint $table) {
            $table->id('department_id');
            $table->string('department_name', 45);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('head_teacher_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('department');
    }
};
