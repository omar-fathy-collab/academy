<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'users',
            'students',
            'teachers',
            'parents',
            'groups',
            'sessions',
            'videos',
            'session_materials',
            'quizzes',
            'quiz_attempts',
            'invoices',
            'payments',
            'bookings',
            'attendance',
            'session_attendance',
            'announcements',
            'materials',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                if (!Schema::hasColumn($tableName, 'uuid')) {
                    // 1. Add nullable UUID column
                    Schema::table($tableName, function (Blueprint $table) {
                        // Put it somewhere near the beginning if possible, else just let it append
                        $table->uuid('uuid')->nullable(); // Unique constrained later to avoid duplicate nulls
                    });
                }
                
                // 2. Populate UUIDs natively via MySQL
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("UPDATE `{$tableName}` SET uuid = UUID() WHERE uuid IS NULL");
                }

                // 3. Make the column uniquely constrained
                try {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->unique('uuid');
                    });
                } catch (\Exception $e) {
                    // Ignore if unique index already exists
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
             'users', 'students', 'teachers', 'parents', 'groups', 'sessions', 'videos', 
             'session_materials', 'quizzes', 'quiz_attempts', 'invoices', 'payments', 
             'bookings', 'attendance', 'session_attendance', 'announcements', 'materials',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
