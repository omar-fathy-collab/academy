<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id('expense_id');
            $table->enum('category', ['consumables', 'rent', 'utilities', 'equipment', 'marketing', 'other']);
            $table->string('description', 255);
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->foreign('recorded_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expenses');
    }
};
