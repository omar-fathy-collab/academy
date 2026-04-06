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
        $tables = [
            'salaries',
            'assignments',
            'quiz_answers',
            'notifications',
            'teacher_payments',
            'salary_transfers',
            'activities',
            'capital_additions',
            'teacher_adjustments',
            'ratings',
            'assignment_submissions',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'uuid')) {
                    $afterColumn = Schema::hasColumn($tableName, 'id') ? 'id' : null;
                    if (!$afterColumn) {
                        $guess = (str_ends_with($tableName, 'ies') ? substr($tableName, 0, -3).'y' : substr($tableName, 0, -1)).'_id';
                        $afterColumn = Schema::hasColumn($tableName, $guess) ? $guess : null;
                    }
                    
                    if ($afterColumn) {
                        $table->uuid('uuid')->nullable()->unique()->after($afterColumn);
                    } else {
                        $table->uuid('uuid')->nullable()->unique();
                    }
                }
            });

            // Populate existing records with UUIDs where null
            \Illuminate\Support\Facades\DB::table($tableName)->whereNull('uuid')->get()->each(function ($record) use ($tableName) {
                $primaryKey = 'id';
                if (!Schema::hasColumn($tableName, 'id')) {
                    $guess = (str_ends_with($tableName, 'ies') ? substr($tableName, 0, -3).'y' : substr($tableName, 0, -1)).'_id';
                    if (Schema::hasColumn($tableName, $guess)) {
                        $primaryKey = $guess;
                    } else {
                        try {
                            $keys = \Illuminate\Support\Facades\DB::select("SHOW KEYS FROM {$tableName} WHERE Key_name = 'PRIMARY'");
                            $primaryKey = !empty($keys) ? $keys[0]->Column_name : 'id';
                        } catch (\Exception $e) {
                            $primaryKey = 'id';
                        }
                    }
                }

                \Illuminate\Support\Facades\DB::table($tableName)
                    ->where($primaryKey, $record->{$primaryKey})
                    ->update(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
            });

            // Make it non-nullable after populating
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable(false)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'salaries',
            'assignments',
            'quiz_answers',
            'notifications',
            'teacher_payments',
            'salary_transfers',
            'activities',
            'capital_additions',
            'teacher_adjustments',
            'ratings',
            'assignment_submissions',
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
