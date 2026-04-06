<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesTableSeeder::class);
        $this->call(DepartmentSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'username' => 'Test Admin',
            'email' => 'admin@example.com',
            'role_id' => 1, // admin
        ]);

        $teacherUser = User::factory()->create([
            'username' => 'Test Teacher',
            'email' => 'teacher@example.com',
            'role_id' => 2, // teacher
        ]);

        // Create teacher record
        DB::table('teachers')->insert([
            'teacher_name' => 'Test Teacher',
            'user_id' => $teacherUser->id,
            'department_id' => 1,
            'hire_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
