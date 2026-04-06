<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('certificate_requests')) {
            Schema::create('certificate_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('course_id')->constrained('courses', 'course_id')->onDelete('cascade');
                $table->foreignId('group_id')->nullable()->constrained('groups', 'group_id')->onDelete('set null');
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('certificate_requests');
    }
};
