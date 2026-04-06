<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->enum('status', ['pending', 'partial', 'paid'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('group_id')->references('group_id')->on('groups');
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
