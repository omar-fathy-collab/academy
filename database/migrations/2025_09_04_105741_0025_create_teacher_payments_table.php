<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teacher_payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('salary_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50)->nullable();
            $table->timestamp('payment_date')->useCurrent();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_image', 255)->nullable();
            $table->boolean('whatsapp_sent')->default(false);
            $table->timestamps();

            $table->foreign('teacher_id')->references('teacher_id')->on('teachers');
            $table->foreign('salary_id')->references('salary_id')->on('salaries');
            $table->foreign('confirmed_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('teacher_payments');
    }
};
