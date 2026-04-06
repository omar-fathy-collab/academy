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
        // Add 'partial' to the status ENUM in the salaries table
        DB::statement("ALTER TABLE salaries MODIFY COLUMN status ENUM('pending', 'partial', 'paid', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the ENUM (note: if there are any 'partial' records, this might fail or truncate data)
        DB::statement("ALTER TABLE salaries MODIFY COLUMN status ENUM('pending', 'paid', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
