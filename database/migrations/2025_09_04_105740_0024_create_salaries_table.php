<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id('salary_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('month', 7);
            $table->unsignedBigInteger('group_id')->nullable();
            $table->decimal('group_revenue', 10, 2)->default(0.00);
            $table->decimal('teacher_share', 10, 2)->default(0.00);
            $table->decimal('deductions', 10, 2)->default(0.00);
            $table->decimal('bonuses', 10, 2)->default(0.00);
            $table->decimal('net_salary', 10, 2)->default(0.00);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->date('payment_date')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('teacher_id')->references('teacher_id')->on('teachers');
            $table->foreign('group_id')->references('group_id')->on('groups');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('salaries');
    }
};
