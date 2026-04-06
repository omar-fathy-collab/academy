<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('certificates')) {
            Schema::create('certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->enum('certificate_type', ['individual', 'group_completion'])->default('individual');
                $table->foreignId('course_id')->nullable()->constrained('courses', 'course_id')->onDelete('set null');
                $table->foreignId('group_id')->nullable()->constrained('groups', 'group_id')->onDelete('set null');
                $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('certificate_number')->unique();
                $table->date('issue_date')->nullable();
                $table->decimal('attendance_percentage', 5, 2)->nullable();
                $table->decimal('quiz_average', 5, 2)->nullable();
                $table->decimal('final_rating', 5, 2)->nullable();
                $table->string('file_path')->nullable();
                $table->enum('status', ['draft', 'issued', 'revoked'])->default('draft');
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('certificates');
    }
};
