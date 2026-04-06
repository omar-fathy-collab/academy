<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['users', 'students', 'groups', 'courses', 'subcourses'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'uuid')) {
                    // Use a temporary nullable column to allow population
                    $table->uuid('uuid')->nullable()->unique()->after(Schema::hasColumn($tableName, 'id') ? 'id' : (substr($tableName, 0, -1).'_id'));
                }
            });

            // Populate existing records with UUIDs where null
            DB::table($tableName)->whereNull('uuid')->get()->each(function ($record) use ($tableName) {
                $primaryKey = Schema::hasColumn($tableName, 'id') ? 'id' : (substr($tableName, 0, -1).'_id');
                // Special case for subcourses if it follows a different pattern, but let's assume {table_singular}_id
                if (!Schema::hasColumn($tableName, $primaryKey)) {
                    // Try to find the primary key if the simple guess fails
                    $primaryKey = DB::select("SHOW KEYS FROM {$tableName} WHERE Key_name = 'PRIMARY'")[0]->Column_name ?? 'id';
                }

                DB::table($tableName)
                    ->where($primaryKey, $record->{$primaryKey})
                    ->update(['uuid' => (string) Str::uuid()]);
            });

            // Make it non-nullable after populating
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Change column to non-nullable
                $table->uuid('uuid')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['users', 'students', 'groups', 'courses', 'subcourses'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};
