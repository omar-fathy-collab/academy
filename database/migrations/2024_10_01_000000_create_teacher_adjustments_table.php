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
        if (! Schema::hasTable('teacher_adjustments')) {
            Schema::create('teacher_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('teacher_id');
                $table->foreign('teacher_id')->references('teacher_id')->on('teachers')->onDelete('cascade');
                $table->string('description');
                $table->decimal('amount', 10, 2);
                $table->enum('type', ['bonus', 'deduction']);
                $table->date('adjustment_date');
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_adjustments');
    }
};
