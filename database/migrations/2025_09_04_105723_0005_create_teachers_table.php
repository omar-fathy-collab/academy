<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id('teacher_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('teacher_name', 45);
            $table->date('hire_date')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('salary_percentage', 5, 2)->default(80.00);
            $table->decimal('base_salary', 10, 2)->default(0.00);
            $table->string('bank_account', 100)->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'vodafone_cash'])->default('cash');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('department_id')->references('department_id')->on('department');
        });
    }

    public function down()
    {
        Schema::dropIfExists('teachers');
    }
};
