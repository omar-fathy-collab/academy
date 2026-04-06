<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run()
    {
        DB::table('department')->insert([
            ['department_name' => 'Computer Science', 'description' => 'Department of Computer Science', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'Mathematics', 'description' => 'Department of Mathematics', 'created_at' => now(), 'updated_at' => now()],
            ['department_name' => 'Physics', 'description' => 'Department of Physics', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
