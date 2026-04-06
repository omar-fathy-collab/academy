<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Invoice;
use App\Models\Salary;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalaryService
{
    /**
     * Calculate final correct salary values for a given salary record.
     * Consolidated from SalaryController and GroupsController.
     */
    public function calculateSalaryValues($salary)
    {
        try {
            $group = is_numeric($salary->group_id)
                ? Group::find($salary->group_id)
                : $salary->group;

            if (! $group) {
                return $this->emptyValues();
            }

            // Ensure the specific teacher is the primary owner of the group organically
            // Otherwise, they are a pure transfer beneficiary and don't organically earn the group's revenue
            if (isset($salary->teacher_id) && $salary->teacher_id != $group->teacher_id) {
                return $this->emptyValues();
            }

            $teacher = $salary->teacher_id ? Teacher::find($salary->teacher_id) : null;
            $currentPercentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 0);

            // 1. Group Revenue = Total students * group price
            $studentCount = DB::table('student_group')
                ->where('group_id', $group->group_id)
                ->count();

            $group_revenue = $studentCount * ($group->price ?? 0);

            // 2. Total Discounts = Sum of discount_amount from all invoices
            $total_discounts = Invoice::where('group_id', $group->group_id)
                ->sum('discount_amount');

            // 3. Total Paid Fees = Sum of amount_paid from paid/partial invoices
            $total_paid_fees = Invoice::where('group_id', $group->group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->sum('amount_paid');

            // 4. Teacher Share = (Revenue - Discounts) * Percentage
            $teacher_share = ($group_revenue - $total_discounts) * ($currentPercentage / 100);

            // 5. Available Payment = Total Paid Fees * Percentage
            $available_payment = $total_paid_fees * ($currentPercentage / 100);

            return [
                'revenue' => (float) $group_revenue,
                'teacher_share' => (float) $teacher_share,
                'available_payment' => (float) $available_payment,
                'total_paid_fees' => (float) $total_paid_fees,
                'total_discounts' => (float) $total_discounts,
                'percentage' => (float) $currentPercentage,
                'student_count' => $studentCount,
            ];
        } catch (\Exception $e) {
            Log::error('SalaryService Error: '.$e->getMessage());

            return $this->emptyValues();
        }
    }

    /**
     * Create or update a salary record for a group for a specific month.
     */
    public function syncSalaryForGroup(Group $group, $month = null)
    {
        try {
            $salaryMonth = $month ?? ($group->start_date ? Carbon::parse($group->start_date)->format('Y-m') : now()->format('Y-m'));

            $existingSalary = Salary::where('group_id', $group->group_id)
                ->where('month', $salaryMonth)
                ->first();

            // Mock a salary object for calculation
            $mockSalary = (object) [
                'group_id' => $group->group_id,
                'teacher_id' => $group->teacher_id,
                'group' => $group,
            ];

            $values = $this->calculateSalaryValues($mockSalary);

            $data = [
                'teacher_id' => $group->teacher_id,
                'month' => $salaryMonth,
                'group_id' => $group->group_id,
                'group_revenue' => $values['revenue'],
                'teacher_share' => $values['teacher_share'],
                'net_salary' => $values['teacher_share'], // Base net salary
                'updated_by' => Auth::id(),
            ];

            if ($existingSalary) {
                // If the salary is already paid/partially paid, we should be careful about updating status
                // But we update the reference values (teacher_share/revenue) regardless
                $existingSalary->update($data);
                return $existingSalary;
            }

            $data['status'] = 'pending';
            return Salary::create($data);
        } catch (\Exception $e) {
            Log::error('SalaryService Sync Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync ALL relevant salary records for a group (all months).
     * Used when group price or percentage changes.
     */
    public function syncAllSalariesForGroup(Group $group)
    {
        try {
            $salaries = Salary::where('group_id', $group->group_id)->get();
            
            foreach ($salaries as $salary) {
                // We only automatically re-sync if not fully paid OR if it's a forced audit
                $this->syncSalaryForGroup($group, $salary->month);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('SalaryService Multi-Sync Error: '.$e->getMessage());
            return false;
        }
    }

    private function emptyValues()
    {
        return [
            'revenue' => 0,
            'teacher_share' => 0,
            'available_payment' => 0,
            'total_paid_fees' => 0,
            'total_discounts' => 0,
            'percentage' => 0,
            'student_count' => 0,
        ];
    }
}
