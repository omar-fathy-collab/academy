<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the application's permissions.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // Academy Core
            'view_courses', 'manage_courses',
            'view_students', 'manage_students',
            'view_teachers', 'manage_teachers',
            'view_groups', 'manage_groups',
            
            // Academic Ops
            'view_schedules', 'manage_schedules',
            'view_assignments', 'manage_assignments',
            'view_quizzes', 'manage_quizzes',
            
            // Financials
            'view_financials', 'manage_financials',
            'view_vault', 'manage_vault',
            
            // System Admin
            'manage_users', 'manage_roles',
            'view_logs', 'manage_settings',
            
            // Insights
            'view_reports',
            
            // Student/Teacher Specific
            'be_student', 'be_teacher'
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // 2. Ensure Roles Exist
        $superAdminRole = Role::findOrCreate('super-admin');
        $adminRole = Role::findOrCreate('admin');
        $teacherRole = Role::findOrCreate('teacher');
        $studentRole = Role::findOrCreate('student');

        // 3. Assign Permissions to Roles
        
        // Super Admin gets EVERYTHING
        $superAdminRole->syncPermissions(Permission::all());

        // Admin gets EVERYTHING (or most things)
        $adminRole->syncPermissions(Permission::all());

        // Teacher Permissions
        $teacherRole->syncPermissions([
            'be_teacher',
            'view_courses',
            'view_students',
            'view_teachers',
            'view_groups',
            'view_schedules',
            'view_assignments',
            'manage_assignments',
            'view_quizzes',
        ]);

        // Student Permissions
        $studentRole->syncPermissions([
            'be_student',
            'view_courses',
            'view_schedules',
            'view_assignments',
            'view_quizzes',
        ]);
        
        // Note: Students explicitly DO NOT get 'view_financials', 'view_vault', 'view_reports', or any 'manage_' permissions.
    }
}
