<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Salary;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FinancialDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Generate Invoices for all students in all groups
        $students = Student::with('groups')->get();
        foreach ($students as $student) {
            foreach ($student->groups as $group) {
                // Check if invoice already exists for this student-group pair
                $exists = Invoice::where('student_id', $student->student_id)
                    ->where('group_id', $group->group_id)
                    ->exists();
                
                if (!$exists) {
                    try {
                        Invoice::create([
                            'student_id' => $student->student_id,
                            'group_id' => $group->group_id,
                            // Ensure invoice_number is truly unique using timestamp + random
                            'invoice_number' => 'INV-' . date('YmdH') . '-' . strtoupper(Str::random(10)),
                            'description' => 'Fees for group: ' . $group->group_name,
                            'amount' => $group->price,
                            'amount_paid' => 0,
                            'status' => 'pending',
                            'due_date' => now()->addDays(rand(1, 15)),
                        ]);
                    } catch (\Exception $e) {
                        // If it still fails, skip it for the seeder
                        echo "Failed to create invoice for student {$student->student_id} in group {$group->group_id}: " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        // 2. Generate Salary records for teachers
        $groups = Group::with('students', 'teacher')->get();
        $currentMonth = date('Y-m');
        
        foreach ($groups as $group) {
            if (!$group->teacher) continue;

            // Manual check to avoid Salary model's booted exception
            $existingSalary = Salary::where('group_id', $group->group_id)
                ->where('teacher_id', $group->teacher_id)
                ->where('month', $currentMonth)
                ->exists();
            
            if (!$existingSalary) {
                $revenue = $group->price * $group->students->count();
                $share = $revenue * ($group->teacher_percentage / 100);
                
                try {
                    Salary::create([
                        'teacher_id' => $group->teacher_id,
                        'group_id' => $group->group_id,
                        'month' => $currentMonth,
                        'group_revenue' => $revenue,
                        'teacher_share' => $share,
                        'net_salary' => $share,
                        'status' => 'pending',
                    ]);
                } catch (\Exception $e) {
                    echo "Failed to create salary for teacher {$group->teacher_id} in group {$group->group_id}: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}
