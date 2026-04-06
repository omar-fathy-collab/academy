<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpandRememberTokenLength extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `remember_token` VARCHAR(512) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN remember_token TYPE varchar(512)');
        } else {
            // SQLite and others: try schema change (SQLite ignores length)
            try {
                Schema::table('users', function ($table) {
                    $table->string('remember_token', 512)->nullable()->change();
                });
            } catch (\Throwable $e) {
                // If change not supported, ignore - SQLite won't enforce length
            }
        }
    }

    public function down()
    {
        if (! Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `remember_token` VARCHAR(100) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN remember_token TYPE varchar(100)');
        } else {
            try {
                Schema::table('users', function ($table) {
                    $table->string('remember_token', 100)->nullable()->change();
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}
