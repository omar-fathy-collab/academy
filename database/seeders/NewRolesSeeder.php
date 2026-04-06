<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class NewRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 0. Ensure all required permissions exist
        $permissions = [
            'view_financials',
            'manage_financials',
            'view_invoices',
            'manage_invoices',
            'view_expenses',
            'manage_expenses',
            'view_salaries',
            'manage_salaries',
            'view_profits',
            'view_reports',
            'view_financial_reports',
            'view_student_reports',
            'view_teacher_reports',
            'view_dashboard',
            'view_students',
            'view_student_details',
            'create_students',
            'edit_students',
            'activate_students',
            'manage_groups',
            'manage_schedules',
            'view_courses',
            'create_courses',
            'edit_courses',
            'view_teachers',
            'create_teachers',
            'edit_teachers',
            'manage_certificates',
            'view_users',
            'create_users',
            'edit_users',
            'view_roles',
            'manage_roles',
            'view_logs',
            'rollback_logs',
            'manage_settings',
            'manage_rooms',
            'view_vault',
            'manage_vault',
            'view_assignments',
            'manage_assignments',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        
        // Ensure functional permissions exist
        Permission::firstOrCreate(['name' => 'be_teacher', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'be_student', 'guard_name' => 'web']);

        // 1. Financial Manager (إدارة الماليات)
        $financialManager = Role::firstOrCreate(['name' => 'financial-manager', 'guard_name' => 'web']);
        $financialManager->syncPermissions([
            'view_financials',
            'manage_financials',
            'view_invoices',
            'manage_invoices',
            'view_expenses',
            'manage_expenses',
            'view_salaries',
            'manage_salaries',
            'view_profits',
            'view_reports',
            'view_financial_reports',
            'view_dashboard',
            'view_vault',
            'manage_vault',
        ]);

        // 2. Student Manager (إدارة الطلبة)
        $studentManager = Role::firstOrCreate(['name' => 'student-manager', 'guard_name' => 'web']);
        $studentManager->syncPermissions([
            'view_students',
            'view_student_details',
            'create_students',
            'edit_students',
            'activate_students',
            'view_reports',
            'view_student_reports',
            'manage_groups',
            'manage_schedules',
            'view_dashboard',
            'view_assignments',
            'manage_assignments',
        ]);

        // 3. Academic Manager (إدارة الشؤون الأكاديمية)
        $academicManager = Role::firstOrCreate(['name' => 'academic-manager', 'guard_name' => 'web']);
        $academicManager->syncPermissions([
            'view_courses',
            'create_courses',
            'edit_courses',
            'view_teachers',
            'create_teachers',
            'edit_teachers',
            'manage_schedules',
            'manage_groups',
            'manage_certificates',
            'view_reports',
            'view_teacher_reports',
            'view_dashboard',
            'view_assignments',
            'manage_assignments',
        ]);

        // 4. Admin Assistant (مساعد إداري / مدير نظام)
        $adminAssistant = Role::firstOrCreate(['name' => 'admin-assistant', 'guard_name' => 'web']);
        $adminAssistant->syncPermissions([
            'view_users',
            'create_users',
            'edit_users',
            'view_roles',
            'manage_roles',
            'view_logs',
            'rollback_logs',
            'manage_settings',
            'manage_rooms',
            'view_dashboard',
        ]);

        // 5. Receptionist (موظف استقبال)
        $receptionist = Role::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        $receptionist->syncPermissions([
            'view_students',
            'view_student_details',
            'view_courses',
            'view_teachers',
            'view_dashboard',
            'view_schedules',
        ]);

        // 6. Teacher Role
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacherRole->syncPermissions([
            'view_dashboard',
            'be_teacher',
            'view_courses',
            'view_assignments',
        ]);

        // 7. Student Role
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $studentRole->syncPermissions([
            'view_dashboard',
            'be_student',
        ]);

        // 8. Guest Role
        Role::firstOrCreate(['name' => 'Guest', 'guard_name' => 'web']);

        $this->command->info('✅ Roles updated and permissions synced successfully.');
    }
}
