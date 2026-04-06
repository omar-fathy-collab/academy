<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = [
    'teacher_adjustments',
    'assignment_submissions',
    'ratings',
    'transactions',
    'notifications',
    'salaries',
    'teacher_payments',
    'salary_transfers',
    'activities',
    'capital_additions',
    'student_transfers',
    'group_change_logs'
];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        $hasUuid = Schema::hasColumn($table, 'uuid');
        echo "Table: $table | Has UUID: " . ($hasUuid ? 'YES' : 'NO') . "\n";
    } else {
        echo "Table: $table | EXISTS: NO\n";
    }
}
