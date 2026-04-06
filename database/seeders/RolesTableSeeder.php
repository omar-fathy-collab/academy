<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('roles')->insert([
            ['role_name' => 'admin', 'description' => 'مدير النظام', 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'teacher', 'description' => 'معلم', 'created_at' => now(), 'updated_at' => now()],
            ['role_name' => 'student', 'description' => 'طالب', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
