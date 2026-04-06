<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * NOTE: The `roles` table is managed by Spatie permissions (create_permission_tables migration).
     * The application uses `role_id` on the `users` table to reference the OLD roles table,
     * which is separate from Spatie's roles table.
     * 
     * We check which roles table schema exists and seed accordingly.
     */
    public function run(): void
    {
        // Detect which roles DB is active
        $columns = collect(DB::select('SHOW COLUMNS FROM roles'))->pluck('Field')->toArray();
        $usesOldSchema = in_array('role_name', $columns);
        $usesSpatie    = in_array('guard_name', $columns);

        if ($usesOldSchema) {
            // Seed the old-style roles (role_name / description)
            $oldRoles = [
                ['role_name' => 'admin',   'description' => 'مدير النظام'],
                ['role_name' => 'teacher', 'description' => 'معلم'],
                ['role_name' => 'student', 'description' => 'طالب'],
                ['role_name' => 'parent',  'description' => 'ولي أمر'],
            ];
            foreach ($oldRoles as $i => $role) {
                $pk = in_array('idroles', $columns) ? 'idroles' : 'id';
                DB::table('roles')->updateOrInsert([$pk => $i + 1], array_merge($role, [
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            $adminRoleId = 1;
        } elseif ($usesSpatie) {
            // Create a Spatie 'admin' role if it doesn't exist
            $adminRole = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->first();
            if (!$adminRole) {
                DB::table('roles')->insert([
                    'name'       => 'admin',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $adminRole = DB::table('roles')->where('name', 'admin')->first();
            }
            // Also create teacher/student roles
            foreach (['teacher', 'student', 'parent'] as $r) {
                if (!DB::table('roles')->where('name', $r)->exists()) {
                    DB::table('roles')->insert(['name' => $r, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $adminRoleId = $adminRole->id;
        } else {
            $this->command->warn('Could not determine roles table schema. Skipping roles seeding.');
            $adminRoleId = 1;
        }

        // Create Admin User via direct DB query to bypass Model Observers
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@admin.com'],
            [
                'username'   => 'Admin Manager',
                'pass'       => Hash::make('password'),
                'role_id'    => $adminRoleId,
                'is_active'  => 1,
                'uuid'       => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // If using Spatie, link the user to the role in model_has_roles
        if ($usesSpatie) {
            $user = DB::table('users')->where('email', 'admin@admin.com')->first();
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id'    => $adminRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id'   => $user->id,
                ]
            );
        }

        $this->command->info('✅ Roles and Admin user seeded successfully!');
        $this->command->info('📧 Email:    admin@admin.com');
        $this->command->info('🔑 Password: password');
    }
}
