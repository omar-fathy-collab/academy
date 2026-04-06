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
        // 0. Restore Rooms Table
        if (!Schema::hasTable('rooms')) {
            Schema::create('rooms', function (Blueprint $table) {
                $table->id('room_id');
                $table->string('room_name');
                $table->integer('capacity')->nullable();
                $table->string('location')->nullable();
                $table->text('facilities')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamps();
            });
        }

        // 1. Restore Schedules Table
        if (!Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id('schedule_id');
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('room_id');
                $table->string('day_of_week', 20);
                $table->time('start_time');
                $table->time('end_time');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamps();

                $table->foreign('group_id')->references('group_id')->on('groups')->onDelete('cascade');
                $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
            });
        }

        // 2. Restore Waiting Groups Table
        if (!Schema::hasTable('waiting_groups')) {
            Schema::create('waiting_groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_name');
                $table->unsignedBigInteger('course_id');
                $table->unsignedBigInteger('subcourse_id')->nullable();
                $table->text('description')->nullable();
                $table->integer('max_students')->default(20);
                $table->string('status')->default('active');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('course_id')->references('course_id')->on('courses')->onDelete('cascade');
                $table->foreign('subcourse_id')->references('subcourse_id')->on('subcourses')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 3. Restore Waiting Students Table
        if (!Schema::hasTable('waiting_students')) {
            Schema::create('waiting_students', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('waiting_group_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('user_id');
                $table->decimal('placement_exam_grade', 5, 2)->nullable();
                $table->string('assigned_level')->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('waiting');
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('converted_at')->nullable();
                $table->unsignedBigInteger('converted_to_group_id')->nullable();
                $table->unsignedBigInteger('added_by')->nullable();
                $table->timestamps();

                $table->foreign('waiting_group_id')->references('id')->on('waiting_groups')->onDelete('cascade');
                $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('added_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // 4. Fix profile.nickname constraint
        if (Schema::hasTable('profile') && Schema::hasColumn('profile', 'nickname')) {
            Schema::table('profile', function (Blueprint $table) {
                $table->string('nickname', 255)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waiting_students');
        Schema::dropIfExists('waiting_groups');
        Schema::dropIfExists('schedules');

        if (Schema::hasTable('profile') && Schema::hasColumn('profile', 'nickname')) {
            Schema::table('profile', function (Blueprint $table) {
                $table->string('nickname', 45)->nullable(false)->change();
            });
        }
    }
};
