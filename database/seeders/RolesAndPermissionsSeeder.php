<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // =============================================
        // Define All Permissions (Granular)
        // =============================================
        $permissions = [
            // User Management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'impersonate_users',

            // Role Management
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // Course Management
            'view_courses',
            'create_courses',
            'edit_courses',
            'delete_courses',

            // Student Management
            'view_students',
            'create_students',
            'edit_students',
            'delete_students',
            'activate_students',

            // Teacher Management
            'view_teachers',
            'create_teachers',
            'edit_teachers',
            'delete_teachers',

            // Financial Management
            'view_finances',
            'manage_finances',
            'view_invoices',
            'manage_invoices',
            'view_expenses',
            'manage_expenses',
            'view_salaries',
            'manage_salaries',
            'view_profits',

            // Reports
            'view_reports',
            'view_student_reports',
            'view_financial_reports',
            'view_teacher_reports',

            // Admin Operations
            'view_activities',
            'manage_settings',
            'view_dashboard',
            'manage_rooms',
            'manage_schedules',
            'manage_certificates',
            'manage_groups',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // =============================================
        // Define Roles with their Permissions
        // =============================================

        // --- Super Admin (مدير كامل) ---
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all()); // Gets all permissions

        // --- Admin (مدير عادي / Secretary) ---
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'view_users', 'create_users', 'edit_users',
            'view_courses', 'create_courses', 'edit_courses',
            'view_students', 'create_students', 'edit_students', 'activate_students',
            'view_teachers', 'create_teachers', 'edit_teachers',
            'view_invoices', 'manage_invoices',
            'view_reports', 'view_student_reports',
            'view_dashboard',
            'manage_schedules', 'manage_groups',
            'manage_certificates',
        ]);

        // --- Teacher (مدرس) ---
        $teacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacher->syncPermissions([
            'view_courses',
            'view_students',
            'view_dashboard',
            'manage_schedules',
        ]);

        // --- Student (طالب) ---
        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->syncPermissions([
            'view_courses',
            'view_dashboard',
        ]);

        // =============================================
        // Migrate Existing Users from legacy role_id to Spatie Roles
        // =============================================
        $users = User::with('adminType')->get();
        foreach ($users as $user) {
            // Determine role based on old role_id
            switch ($user->role_id) {
                case 1: // Admin
                    // Check if Full Admin by admin_type
                    if ($user->adminType && $user->adminType->name === 'full') {
                        $user->syncRoles(['super-admin']);
                    } else {
                        $user->syncRoles(['admin']);
                    }
                    break;
                case 2: // Teacher
                    $user->syncRoles(['teacher']);
                    break;
                case 3: // Student
                    $user->syncRoles(['student']);
                    break;
                default:
                    // Unknown role - assign default admin for safety
                    $user->syncRoles(['admin']);
                    break;
            }
        }

        $this->command->info('✅ Roles and Permissions seeded successfully!');
        $this->command->info('   Users migrated: ' . $users->count());
        $this->command->info('   Roles created: super-admin, admin, teacher, student');
        $this->command->info('   Permissions created: ' . count($permissions));
    }
}
