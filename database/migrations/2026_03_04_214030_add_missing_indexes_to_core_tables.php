<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip adding indexes if they already exist.
        Schema::table('users', function (Blueprint $table) {
            if (!collect(Schema::getIndexes('users'))->contains('name', 'users_role_id_index')) $table->index('role_id');
            if (!collect(Schema::getIndexes('users'))->contains('name', 'users_admin_type_id_index')) $table->index('admin_type_id');
            if (!collect(Schema::getIndexes('users'))->contains('name', 'users_is_active_index')) $table->index('is_active');
        });

        Schema::table('students', function (Blueprint $table) {
            if (!collect(Schema::getIndexes('students'))->contains('name', 'students_user_id_index')) $table->index('user_id');
            if (!collect(Schema::getIndexes('students'))->contains('name', 'students_preferred_course_id_index') && Schema::hasColumn('students', 'preferred_course_id')) $table->index('preferred_course_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            if (!collect(Schema::getIndexes('groups'))->contains('name', 'groups_course_id_index')) $table->index('course_id');
            if (!collect(Schema::getIndexes('groups'))->contains('name', 'groups_subcourse_id_index')) $table->index('subcourse_id');
            if (!collect(Schema::getIndexes('groups'))->contains('name', 'groups_teacher_id_index')) $table->index('teacher_id');
            if (!collect(Schema::getIndexes('groups'))->contains('name', 'groups_start_date_index')) $table->index('start_date');
            if (!collect(Schema::getIndexes('groups'))->contains('name', 'groups_end_date_index')) $table->index('end_date');
        });

        Schema::table('courses', function (Blueprint $table) {
            if (!collect(Schema::getIndexes('courses'))->contains('name', 'courses_course_name_index')) $table->index('course_name');
        });

        Schema::table('subcourses', function (Blueprint $table) {
            if (!collect(Schema::getIndexes('subcourses'))->contains('name', 'subcourses_course_id_index')) $table->index('course_id');
            if (!collect(Schema::getIndexes('subcourses'))->contains('name', 'subcourses_subcourse_number_index')) $table->index('subcourse_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (collect(Schema::getIndexes('users'))->contains('name', 'users_role_id_index')) $table->dropIndex(['role_id']);
            if (collect(Schema::getIndexes('users'))->contains('name', 'users_admin_type_id_index')) $table->dropIndex(['admin_type_id']);
            if (collect(Schema::getIndexes('users'))->contains('name', 'users_is_active_index')) $table->dropIndex(['is_active']);
        });

        Schema::table('students', function (Blueprint $table) {
            if (collect(Schema::getIndexes('students'))->contains('name', 'students_user_id_index')) $table->dropIndex(['user_id']);
            if (collect(Schema::getIndexes('students'))->contains('name', 'students_preferred_course_id_index')) $table->dropIndex(['preferred_course_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            if (collect(Schema::getIndexes('groups'))->contains('name', 'groups_course_id_index')) $table->dropIndex(['course_id']);
            if (collect(Schema::getIndexes('groups'))->contains('name', 'groups_subcourse_id_index')) $table->dropIndex(['subcourse_id']);
            if (collect(Schema::getIndexes('groups'))->contains('name', 'groups_teacher_id_index')) $table->dropIndex(['teacher_id']);
            if (collect(Schema::getIndexes('groups'))->contains('name', 'groups_start_date_index')) $table->dropIndex(['start_date']);
            if (collect(Schema::getIndexes('groups'))->contains('name', 'groups_end_date_index')) $table->dropIndex(['end_date']);
        });

        Schema::table('courses', function (Blueprint $table) {
            if (collect(Schema::getIndexes('courses'))->contains('name', 'courses_course_name_index')) $table->dropIndex(['course_name']);
        });

        Schema::table('subcourses', function (Blueprint $table) {
            if (collect(Schema::getIndexes('subcourses'))->contains('name', 'subcourses_course_id_index')) $table->dropIndex(['course_id']);
            if (collect(Schema::getIndexes('subcourses'))->contains('name', 'subcourses_subcourse_number_index')) $table->dropIndex(['subcourse_number']);
        });
    }
};
