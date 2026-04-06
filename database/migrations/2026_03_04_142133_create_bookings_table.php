<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->integer('age')->nullable();
            $table->decimal('placement_exam_grade', 5, 2)->nullable();
            $table->date('date')->nullable();
            $table->time('time')->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('payment_status')->default('pending'); // completed, pending
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('set null');
            $table->foreign('session_id')->references('session_id')->on('sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
