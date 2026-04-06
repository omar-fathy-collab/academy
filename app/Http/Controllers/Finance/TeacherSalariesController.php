<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Notification;
use App\Models\Salary;
use App\Models\Teacher;
use App\Models\TeacherAdjustment;
use App\Models\TeacherPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TeacherSalariesController extends Controller
{
    public function show(Request $request, $teacher_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        // Get teacher details
        $teacher = Teacher::with('user.profile')->find($teacher_id);
        if (! $teacher) {
            return redirect()->route('teachers.index')->with('error', 'Teacher not found');
        }

        // Handle bonus/deduction creation
        if ($request->isMethod('post') && $request->has('create_adjustment')) {
            return $this->createAdjustment($request, $teacher_id);
        }

        // Get current month for filtering (default to '0' for all months)
        $currentMonth = $request->get('month', '0');

        // Get salary records
        $salaryData = $this->getTeacherSalaryData($teacher_id, $currentMonth);

        // Get adjustments for this teacher
        $adjustments = $this->getTeacherAdjustments($teacher_id);

        // ========== جلب التحويلات الصادرة ==========
        // التحويلات التي تم خصمها من هذا المدرس (المدرس هو المصدر)
        $outgoingTransfers = DB::table('salary_transfers')
            ->select(
                'salary_transfers.*',
                'target.teacher_name as target_teacher_name'
            )
            ->join('teachers as target', 'salary_transfers.target_teacher_id', '=', 'target.teacher_id')
            ->where('salary_transfers.source_teacher_id', $teacher_id)
            ->orderBy('salary_transfers.created_at', 'desc')
            ->get();

        // ========== جلب التحويلات الواردة ==========
        // التحويلات التي تم إضافتها لهذا المدرس (المدرس هو المستفيد)
        $incomingTransfers = DB::table('salary_transfers')
            ->select(
                'salary_transfers.*',
                'source.teacher_name as source_teacher_name'
            )
            ->join('teachers as source', 'salary_transfers.source_teacher_id', '=', 'source.teacher_id')
            ->where('salary_transfers.target_teacher_id', $teacher_id)
            ->orderBy('salary_transfers.created_at', 'desc')
            ->get();

        return view('teachers.salary_management', array_merge($salaryData, [
            'teacher' => $teacher,
            'adjustments' => $adjustments,
            'currentMonth' => $currentMonth,
            'months' => $this->getAvailableMonths($teacher_id),
            'outgoingTransfers' => $outgoingTransfers, // إضافة التحويلات الصادرة
            'incomingTransfers' => $incomingTransfers, // إضافة التحويلات الواردة
        ]));
    }

    private function calculateFinalCorrectSalaryValues($salary)
    {
        try {
            // الحصول على بيانات المجموعة
            $group = DB::table('groups')
                ->select('group_id', 'group_name', 'teacher_percentage', 'price')
                ->where('group_id', $salary->group_id)
                ->first();

            if (! $group) {
                return [
                    'revenue' => 0,
                    'teacher_share' => 0,
                    'available_payment' => 0,
                    'total_paid_fees' => 0,
                    'total_discounts' => 0,
                    'actual_percentage' => 0,
                ];
            }

            // حساب عدد الطلاب
            $student_count = DB::table('student_group')
                ->where('group_id', $group->group_id)
                ->count();

            // الإيرادات: عدد الطلاب × سعر المجموعة
            $group_revenue = $student_count * $group->price;

            // الحصول على جميع الفواتير
            $all_invoices = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->get();

            // إجمالي الخصومات
            $total_discounts = $all_invoices->sum('discount_amount');

            // الفواتير المدفوعة
            $paid_invoices = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->get();

            $total_paid_fees = $paid_invoices->sum('amount_paid');

            // نسبة المدرس
            $teacher = DB::table('teachers')->where('teacher_id', $salary->teacher_id)->first();
            $current_percentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 0);

            // حصة المدرس: (الإيرادات - الخصومات) × النسبة
            $teacher_share = ($group_revenue - $total_discounts) * ($current_percentage / 100);

            // المبلغ المتاح: المبالغ المدفوعة فعلياً × النسبة
            $available_payment = $total_paid_fees * ($current_percentage / 100);

            return [
                'revenue' => $group_revenue,
                'teacher_share' => $teacher_share,
                'available_payment' => $available_payment,
                'total_paid_fees' => $total_paid_fees,
                'total_discounts' => $total_discounts,
                'actual_percentage' => $current_percentage,
            ];

        } catch (\Exception $e) {
            \Log::error('Error in calculateFinalCorrectSalaryValues: '.$e->getMessage());

            return [
                'revenue' => 0,
                'teacher_share' => 0,
                'available_payment' => 0,
                'total_paid_fees' => 0,
                'total_discounts' => 0,
                'actual_percentage' => 0,
            ];
        }
    }

    public function getTeacherSalaryData($teacher_id, $month)
    {
        // تحديث الاستعلام ليشمل bonuses, deductions, courses
        $salaryRecordsQuery = DB::table('salaries')
            ->join('groups', 'salaries.group_id', '=', 'groups.group_id')
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->where('salaries.teacher_id', $teacher_id)
            ->where('salaries.teacher_share', '>', 0);

        if ($month !== '0') {
            $salaryRecordsQuery->where('salaries.month', $month);
        }

        $salaryRecords = $salaryRecordsQuery
            ->select(
                'salaries.*',
                'groups.group_name',
                'groups.end_date as group_end_date',
                'groups.schedule',
                'groups.price as group_price',
                'groups.teacher_percentage',
                'courses.course_name'
            )
            ->get()
            ->unique(function ($item) {
                return $item->group_id.'_'.$item->month;
            });

        $processedRecords = [];
        $totalBonusesAmount = 0;
        $totalDeductionsAmount = 0;

        foreach ($salaryRecords as $salary) {
            $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);

            $studentCount = DB::table('student_group')
                ->where('group_id', $salary->group_id)
                ->count();

            $paidAmount = DB::table('teacher_payments')
                ->where('salary_id', $salary->salary_id)
                ->sum('amount');

            // استخدام bonuses و deductions من جدول salaries
            $bonuses = $salary->bonuses ?? 0;
            $deductions = $salary->deductions ?? 0;

            $totalBonusesAmount += $bonuses;
            $totalDeductionsAmount += $deductions;

            // حساب net_salary مع adjustments
            $netSalary = $calculatedValues['available_payment'] + $bonuses - $deductions;

            $remaining = max(0, $netSalary - $paidAmount);

            $status = 'pending';
            if ($netSalary <= 0) {
                $status = 'pending';
            } elseif ($paidAmount >= $netSalary && $netSalary > 0) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
            }

            $record = [
                'group_id' => $salary->group_id,
                'group_name' => $salary->group_name,
                'course_name' => $salary->course_name ?? 'N/A',
                'group_end_date' => $salary->group_end_date,
                'schedule' => $salary->schedule,
                'month' => $salary->month,
                'student_count' => $studentCount,
                'group_price' => $salary->group_price,
                'group_revenue' => $calculatedValues['revenue'],
                'teacher_share' => $calculatedValues['teacher_share'],
                'teacher_percentage' => $calculatedValues['actual_percentage'],
                'available_payment' => $calculatedValues['available_payment'],
                'bonuses' => $bonuses,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'paid_amount' => $paidAmount,
                'remaining' => $remaining,
                'status' => $status,
                'salary_id' => $salary->salary_id,
                'payment_date' => $salary->payment_date ?? null,
                'total_paid_fees' => $calculatedValues['total_paid_fees'],
                'total_discounts' => $calculatedValues['total_discounts'],
            ];

            $processedRecords[] = $record;
        }

        // حساب الإجماليات
        $totalGroupRevenue = array_sum(array_column($processedRecords, 'group_revenue'));
        $totalTeacherShare = array_sum(array_column($processedRecords, 'teacher_share'));
        $totalAvailablePayment = array_sum(array_column($processedRecords, 'available_payment'));
        $totalPaidAmount = array_sum(array_column($processedRecords, 'paid_amount'));
        $totalRemaining = array_sum(array_column($processedRecords, 'remaining'));

        // الحصول على التعديلات غير المرتبطة بسجلات رواتب محددة
        $unattachedAdjustments = DB::table('teacher_adjustments')
            ->where('teacher_id', $teacher_id)
            ->whereNull('salary_id')
            ->get();

        $adjustmentBonuses = $unattachedAdjustments->where('type', 'bonus')->where('payment_status', 'paid')->sum('amount');
        $adjustmentDeductions = $unattachedAdjustments->where('type', 'deduction')->where('payment_status', 'paid')->sum('amount');

        // جمع bonuses من salaries و adjustments غير المرتبطة
        $totalBonuses = $totalBonusesAmount + $adjustmentBonuses;
        $totalDeductions = $totalDeductionsAmount + $adjustmentDeductions;
        $netAdjustments = $totalBonuses - $totalDeductions;

        return [
            'salaries' => $processedRecords,
            'total_group_revenue' => $totalGroupRevenue,
            'total_teacher_share' => $totalTeacherShare,
            'total_available_payment' => $totalAvailablePayment,
            'total_paid_amount' => $totalPaidAmount,
            'total_remaining' => $totalRemaining,
            'total_bonuses' => $totalBonuses,
            'total_deductions' => $totalDeductions,
            'net_adjustments' => $netAdjustments,
            'groups_count' => count($processedRecords),
        ];
    }

    /**
     * Get teacher adjustments - مع معلومات سجل الراتب
     */
    private function getTeacherAdjustments($teacher_id)
    {
        try {
            return DB::table('teacher_adjustments')
                ->leftJoin('salaries', 'teacher_adjustments.salary_id', '=', 'salaries.salary_id')
                ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id')
                ->where('teacher_adjustments.teacher_id', $teacher_id)
                ->select(
                    'teacher_adjustments.*',
                    'salaries.group_id',
                    'groups.group_name',
                    'salaries.month as salary_month',
                    'salaries.salary_id'
                )
                ->orderBy('teacher_adjustments.adjustment_date', 'desc')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Get available months for filter
     */
    private function getAvailableMonths($teacher_id)
    {
        return DB::table('groups')
            ->join('salaries', function ($join) use ($teacher_id) {
                $join->on('groups.group_id', '=', 'salaries.group_id')
                    ->where('salaries.teacher_id', $teacher_id)
                    ->where('salaries.teacher_share', '>', 0);
            })
            ->select(DB::raw('DISTINCT DATE_FORMAT(salaries.month, "%Y-%m") as month'))
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->toArray();
    }

    /**
     * Create adjustment
     */
    private function createAdjustment(Request $request, $teacher_id)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:bonus,deduction',
            'adjustment_date' => 'required|date',
            'payment_status' => 'required|in:paid,pending',
            'apply_to_salary' => 'nullable|in:0,1',
            'salary_id' => 'required_if:apply_to_salary,1|exists:salaries,salary_id',
        ]);

        try {
            $data = [
                'teacher_id' => $teacher_id,
                'description' => $request->description,
                'amount' => $request->amount,
                'type' => $request->type,
                'adjustment_date' => $request->adjustment_date,
                'payment_status' => $request->payment_status,
                'created_by' => Auth::id(),
                'created_at' => now(),
            ];

            // إذا كان التعديل مرتبط بسجل راتب معين
            if ($request->apply_to_salary == '1' && $request->salary_id) {
                $data['salary_id'] = $request->salary_id;

                // إذا كانت الحالة مدفوع، يمكن تحديث سجل الراتب مباشرة
                if ($request->payment_status == 'paid') {
                    $this->applyAdjustmentToSalary($request->salary_id, $request->type, $request->amount);
                }
            }

            // إذا كانت حالة الدفع "مدفوع"، أضف معلومات الدفع
            if ($request->payment_status == 'paid') {
                $data['payment_date'] = $request->adjustment_date;
                $data['payment_method'] = $request->payment_method ?? 'cash';
                $data['paid_by'] = Auth::id();
            }

            TeacherAdjustment::create($data);

            $message = ucfirst($request->type).' added successfully!';
            if ($request->apply_to_salary == '1') {
                $message .= ' Applied to selected salary record.';
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to add adjustment: '.$e->getMessage());
        }
    }

    /**
     * Apply adjustment to salary record
     */
    private function applyAdjustmentToSalary($salary_id, $type, $amount)
    {
        try {
            $salary = DB::table('salaries')->where('salary_id', $salary_id)->first();
            if (! $salary) {
                return;
            }

            $currentBonuses = $salary->bonuses ?? 0;
            $currentDeductions = $salary->deductions ?? 0;

            $updateData = [];

            if ($type == 'bonus') {
                $updateData['bonuses'] = $currentBonuses + $amount;
            } else {
                $updateData['deductions'] = $currentDeductions + $amount;
            }

            // إعادة حساب net_salary
            $updateData['net_salary'] = ($salary->teacher_share + ($updateData['bonuses'] ?? $currentBonuses))
                                       - ($updateData['deductions'] ?? $currentDeductions);

            DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->update($updateData);

        } catch (\Exception $e) {
            \Log::error('Error applying adjustment to salary: '.$e->getMessage());
        }
    }

    /**
     * Mark adjustment as paid
     */
    public function markAdjustmentPaid(Request $request, $teacher_id, $adjustment_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payment_method' => 'required|in:cash,bank_transfer,vodafone_cash',
            'payment_date' => 'required|date',
        ]);

        try {
            DB::table('teacher_adjustments')
                ->where('id', $adjustment_id)
                ->where('teacher_id', $teacher_id)
                ->update([
                    'payment_status' => 'paid',
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method,
                    'paid_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            return redirect()->back()->with('success', 'Adjustment marked as paid successfully!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to mark adjustment as paid: '.$e->getMessage());
        }
    }

    /**
     * Mark adjustment as unpaid
     */
    public function markAdjustmentUnpaid($teacher_id, $adjustment_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        try {
            DB::table('teacher_adjustments')
                ->where('id', $adjustment_id)
                ->where('teacher_id', $teacher_id)
                ->update([
                    'payment_status' => 'pending',
                    'payment_date' => null,
                    'payment_method' => null,
                    'paid_by' => null,
                    'updated_at' => now(),
                ]);

            return redirect()->back()->with('success', 'Adjustment marked as unpaid successfully!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to mark adjustment as unpaid: '.$e->getMessage());
        }
    }

    /**
     * Show edit adjustment form
     */
    public function editAdjustment($teacher_id, $adjustment_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        $adjustment = DB::table('teacher_adjustments')
            ->where('id', $adjustment_id)
            ->where('teacher_id', $teacher_id)
            ->first();

        if (! $adjustment) {
            return redirect()->back()->with('error', 'Adjustment not found');
        }

        $teacher = Teacher::find($teacher_id);

        // جلب الرواتب المتاحة لهذا المدرس
        $salaries = DB::table('salaries')
            ->select('salaries.*', 'groups.group_name')
            ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id')
            ->where('salaries.teacher_id', $teacher_id)
            ->orderBy('salaries.month', 'desc')
            ->get();

        // إذا كان التعديل مرتبط براتب، جلب تفاصيل الراتب
        if ($adjustment->salary_id) {
            $adjustment->salary = DB::table('salaries')
                ->select('salaries.*', 'groups.group_name')
                ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id')
                ->where('salaries.salary_id', $adjustment->salary_id)
                ->first();
        }

        return view('teacher_salaries.edit_adjustment', compact('teacher', 'adjustment', 'salaries'));
    }

    /**
     * Update adjustment
     */
    public function updateAdjustment(Request $request, $teacher_id, $adjustment_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'type' => 'required|in:bonus,deduction',
            'adjustment_date' => 'required|date',
            'salary_id' => 'nullable|exists:salaries,salary_id',
        ]);

        try {
            DB::table('teacher_adjustments')
                ->where('id', $adjustment_id)
                ->where('teacher_id', $teacher_id)
                ->update([
                    'description' => $request->description,
                    'amount' => $request->amount,
                    'type' => $request->type,
                    'adjustment_date' => $request->adjustment_date,
                    'salary_id' => $request->salary_id ?: null,
                    'updated_at' => now(),
                ]);

            return redirect()->route('teachers.salary_management', $teacher_id)
                ->with('success', 'Adjustment updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update adjustment: '.$e->getMessage());
        }
    }

    /**
     * Delete adjustment
     */
    public function deleteAdjustment($teacher_id, $adjustment_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        try {
            $adjustment = DB::table('teacher_adjustments')
                ->where('id', $adjustment_id)
                ->where('teacher_id', $teacher_id)
                ->first();

            if (! $adjustment) {
                return redirect()->back()->with('error', 'Adjustment not found');
            }

            DB::table('teacher_adjustments')
                ->where('id', $adjustment_id)
                ->delete();

            return redirect()->back()->with('success', 'Adjustment deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete adjustment: '.$e->getMessage());
        }
    }

    private function getGroupActiveMonths($group_id)
    {
        $group = DB::table('groups')
            ->select('start_date', 'end_date')
            ->where('group_id', $group_id)
            ->first();

        if (! $group) {
            return [];
        }

        $start = \Carbon\Carbon::parse($group->start_date);
        $end = \Carbon\Carbon::parse($group->end_date);
        $months = [];

        while ($start->lte($end)) {
            $months[] = $start->format('Y-m');
            $start->addMonth();
        }

        return $months;
    }

    /**
     * Calculate salary for a specific group and month - FIXED: Ensure salary record exists
     */
    private function calculateGroupSalary($teacher_id, $month, $group)
    {
        // Check if group was active in this month
        $isActive = DB::table('groups')
            ->where('group_id', $group->group_id)
            ->where(function ($query) use ($month) {
                $query->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$month])
                    ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$month]);
            })
            ->exists();

        if (! $isActive) {
            return null;
        }

        // Get teacher percentage (group-specific or default)
        $teacher = DB::table('teachers')->where('teacher_id', $teacher_id)->first();
        $teacherPercentage = $group->teacher_percentage ?? $teacher->salary_percentage ?? 0;

        // Calculate group revenue (group price * number of students)
        $studentCount = DB::table('student_group')
            ->where('group_id', $group->group_id)
            ->count();
        $groupRevenue = $group->price * $studentCount;

        // Calculate teacher share (group revenue * teacher percentage)
        $teacherShare = $groupRevenue * ($teacherPercentage / 100);

        // Calculate available payment (total paid by students * teacher percentage)
        $paidFees = $this->getTotalPaidFeesForGroup($group->group_id);
        $availablePayment = $paidFees * ($teacherPercentage / 100);

        // FIXED: Get or CREATE salary record if it doesn't exist
        $salary = $this->getOrCreateSalaryRecord($teacher_id, $group->group_id, $month, $groupRevenue, $teacherShare);

        // Get payments for this salary
        $paidAmount = 0;
        if ($salary) {
            $paidAmount = DB::table('teacher_payments')
                ->where('salary_id', $salary->salary_id)
                ->sum('amount');
        }

        $remaining = $teacherShare - $paidAmount;

        // Determine status
        $status = 'pending';
        if ($paidAmount >= $availablePayment && $availablePayment > 0) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partial';
        }

        return [
            'group_id' => $group->group_id,
            'group_name' => $group->group_name,
            'schedule' => $group->schedule ?? 'Not specified',
            'month' => $month,
            'student_count' => $studentCount,
            'group_price' => $group->price,
            'group_revenue' => $groupRevenue,
            'teacher_share' => $teacherShare,
            'teacher_percentage' => $teacherPercentage,
            'available_payment' => $availablePayment,
            'paid_amount' => $paidAmount,
            'remaining' => $remaining,
            'status' => $status,
            'salary_id' => $salary->salary_id ?? null,
        ];
    }

    /**
     * Get or create salary record - NEW METHOD
     */
    /**
     * Get or create salary record - FIXED: Prevent duplicates
     */
    /**
     * Get or create salary record - FIXED: Prevent duplicates with unique constraint
     */
    // داخل class TeacherSalariesController

    /**
     * Mark adjustment as paid
     */

    /**
     * Mark adjustment as unpaid
     */

    /**
     * Update getTeacherAdjustments method
     */

    /**
     * Update calculate in getTeacherSalaryData method
     */
    // عدّل في دالة getTeacherSalaryData:

    private function getOrCreateSalaryRecord($teacher_id, $group_id, $month, $groupRevenue, $teacherShare)
    {
        try {
            // استخدام firstOrCreate لمنع التكرار
            $salary = DB::table('salaries')
                ->where('teacher_id', $teacher_id)
                ->where('group_id', $group_id)
                ->where('month', $month)
                ->first();

            if (! $salary && $teacherShare > 0) {
                $salary = Salary::create([
                    'teacher_id' => $teacher_id,
                    'group_id' => $group_id,
                    'month' => $month,
                    'group_revenue' => $groupRevenue,
                    'teacher_share' => $teacherShare,
                    'net_salary' => $teacherShare,
                    'status' => 'pending',
                ]);
            }

            return $salary;

        } catch (\Exception $e) {
            // في حالة وجود تكرار، إرجاع السجل الموجود
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return DB::table('salaries')
                    ->where('teacher_id', $teacher_id)
                    ->where('group_id', $group_id)
                    ->where('month', $month)
                    ->first();
            }

            \Log::error('Error creating salary record: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get total paid fees for a group
     */
    private function getTotalPaidFeesForGroup($group_id)
    {
        try {
            $paidFees = DB::table('invoices')
                ->where('group_id', $group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->sum('amount_paid');

            // If no results, try alternative methods
            if ($paidFees == 0) {
                $group = DB::table('groups')->where('group_id', $group_id)->first();
                if ($group) {
                    $paidFees = DB::table('invoices')
                        ->where('description', 'LIKE', '%'.$group->group_name.'%')
                        ->whereIn('status', ['paid', 'partial'])
                        ->sum('amount_paid');
                }
            }

            if ($paidFees == 0) {
                $paidFees = DB::table('student_group')
                    ->join('students', 'student_group.student_id', '=', 'students.student_id')
                    ->join('invoices', 'students.student_id', '=', 'invoices.student_id')
                    ->where('student_group.group_id', $group_id)
                    ->whereIn('invoices.status', ['paid', 'partial'])
                    ->sum('invoices.amount_paid');
            }

            return $paidFees;

        } catch (\Exception $e) {
            \Log::error('Error calculating paid fees for group: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Get teacher adjustments
     */
    /**
     * Get teacher adjustments - مع معلومات سجل الراتب
     */

    /**
     * Get available months for filter
     */
    /**
     * Get available months for filter - FIXED: Include only months with teacher share
     */

    /**
     * Create adjustment
     */
    // في دالة createAdjustment
    /**
     * Create adjustment
     */

    /**
     * Apply adjustment to salary record
     */

    /**
     * Process salary payment
     */
    public function pay($salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        // الحصول على بيانات الراتب (كما هو موجود)
        $salary = DB::table('salaries')
            ->select(
                'salaries.*',
                'teachers.teacher_name',
                'teachers.salary_percentage',
                'teachers.base_salary',
                'teachers.bank_account',
                'teachers.payment_method',
                'teachers.user_id'
            )
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->where('salaries.salary_id', $salary_id)
            ->first();

        if (! $salary) {
            abort(404, 'Salary record not found');
        }

        // الحصول على المدفوعات (كما هو موجود)
        $payments = DB::table('teacher_payments')
            ->where('salary_id', $salary_id)
            ->get();
        $paid_amount = $payments->sum('amount');

        // حساب القيم الفعلية (كما هو موجود)
        $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);
        $available_payment = $calculatedValues['available_payment'];
        $max_allowed_payment = max($available_payment, $salary->teacher_share);
        $remaining_amount = max(0, $max_allowed_payment - $paid_amount);

        // ==========  إضافة: جلب البونصات والخصومات ==========
        // جلب جميع التعديلات (بونص/خصم) للمدرس والتي:
        // 1. مرتبطة بنفس salary_id.
        // 2. أو ليست مرتبطة بأي راتب (salary_id = null) ولكنها لنفس المدرس ولم يتم دفعها بعد.
        //    هذا يسمح بإضافة بونصات عامة للمدرس ثم ربطها براتب معين عند الدفع.
        $pendingAdjustments = TeacherAdjustment::where('teacher_id', $salary->teacher_id)
            ->where(function ($query) use ($salary_id) {
                $query->where('salary_id', $salary_id)
                    ->orWhereNull('salary_id');
            })
            ->where('payment_status', 'pending') // فقط التي لم تدفع بعد
            ->get();

        // فصل البونصات عن الخصومات
        $pendingBonuses = $pendingAdjustments->where('type', 'bonus');
        $pendingDeductions = $pendingAdjustments->where('type', 'deduction');
        $total_pending_bonuses = $pendingBonuses->sum('amount');
        $total_pending_deductions = $pendingDeductions->sum('amount');
        // ========== نهاية الإضافة ==========

        // الحصول على المدرسين الآخرين للتحويل (كما هو موجود)
        $teachers = DB::table('teachers')
            ->select('teacher_id', 'teacher_name', 'salary_percentage', 'base_salary')
            ->where('teacher_id', '!=', $salary->teacher_id)
            ->orderBy('teacher_name')
            ->get();

        $max_allowed = $max_allowed_payment;

        // تمرير القيم الجديدة إلى الـ View
        return view('teacher_salaries.pay', compact(
            'salary',
            'paid_amount',
            'remaining_amount',
            'available_payment',
            'max_allowed',
            'teachers',
            'pendingBonuses',
            'pendingDeductions',
            'total_pending_bonuses',
            'total_pending_deductions'
        ));
    }

    /**
     * Process the salary payment (simplified version).
     */
    public function processPayment(Request $request, $salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        \Log::info('=== START processPayment ===', [
            'salary_id' => $salary_id,
            'request_data' => $request->all(),
            'user' => auth()->user()->username ?? 'Unknown',
        ]);

        // التحقق من الصلاحيات (كما هو موجود)
        if (! auth()->check() || ! auth()->user()->isAdminFull()) {
            \Log::warning('Unauthorized access attempt');

            return response()->json([
                'success' => false,
                'error' => 'ليس لديك صلاحية للقيام بهذه العملية',
            ], 403);
        }

        // تحقق من البيانات (تم إضافة قواعد للبونصات المحددة)
        $validator = Validator::make($request->all(), [
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,vodafone_cash',
            'notes' => 'nullable|string|max:500',
            'selected_bonuses' => 'nullable|array',
            'selected_bonuses.*' => 'exists:teacher_adjustments,id', // التحقق من وجود البونصات المحددة
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);

            return response()->json([
                'success' => false,
                'error' => 'بيانات غير صحيحة',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // الحصول على بيانات الراتب (كما هو موجود)
            $salary = DB::table('salaries')
                ->select('salaries.*', 'teachers.user_id')
                ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
                ->where('salaries.salary_id', $salary_id)
                ->lockForUpdate()
                ->first();

            if (! $salary) {
                \Log::error('Salary record not found', ['salary_id' => $salary_id]);

                return response()->json([
                    'success' => false,
                    'error' => 'سجل الراتب غير موجود',
                ], 404);
            }

            // حساب القيم الفعلية (كما هو موجود)
            $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);
            $available_payment = $calculatedValues['available_payment'];
            $paid_amount = DB::table('teacher_payments')
                ->where('salary_id', $salary_id)
                ->sum('amount');
            $max_allowed_payment = max($available_payment, $salary->teacher_share);
            $remaining_amount = max(0, $max_allowed_payment - $paid_amount);

            // ==========  إضافة: حساب إجمالي البونصات المحددة ==========
            $selectedBonusIds = $request->input('selected_bonuses', []);
            $totalSelectedBonuses = 0;
            $bonusesToUpdate = [];

            if (! empty($selectedBonusIds)) {
                // جلب البونصات المحددة من قاعدة البيانات للتأكد من أنها لا تزال pending وتخص هذا المدرس
                $bonusesToUpdate = TeacherAdjustment::whereIn('id', $selectedBonusIds)
                    ->where('teacher_id', $salary->teacher_id)
                    ->where('payment_status', 'pending')
                    ->where('type', 'bonus')
                    ->get();

                $totalSelectedBonuses = $bonusesToUpdate->sum('amount');
                \Log::info('Selected bonuses total', ['amount' => $totalSelectedBonuses]);
            }
            // ========== نهاية الإضافة ==========

            $payment_amount = floatval($request->payment_amount);

            // التحقق من أن المبلغ لا يتجاوز المتبقي (مع مراعاة البونصات المحددة)
            if ($payment_amount > ($remaining_amount + $totalSelectedBonuses)) {
                \Log::warning('Payment amount exceeds remaining + selected bonuses', [
                    'payment_amount' => $payment_amount,
                    'remaining_amount' => $remaining_amount,
                    'selected_bonuses' => $totalSelectedBonuses,
                ]);

                $error_msg = 'المبلغ يتجاوز الحد المسموح به للدفع. ';
                $error_msg .= 'المتبقي للدفع من الراتب: '.number_format($remaining_amount, 2).' جنيه. ';
                $error_msg .= 'البونصات المحددة: '.number_format($totalSelectedBonuses, 2).' جنيه. ';
                $error_msg .= 'الإجمالي المسموح به: '.number_format($remaining_amount + $totalSelectedBonuses, 2).' جنيه.';

                return response()->json([
                    'success' => false,
                    'error' => $error_msg,
                ], 400);
            }

            // التحقق من أن المبلغ أكبر من 0 (كما هو موجود)
            if ($payment_amount <= 0) {
                \Log::warning('Invalid payment amount', ['payment_amount' => $payment_amount]);

                return response()->json([
                    'success' => false,
                    'error' => 'المبلغ يجب أن يكون أكبر من صفر',
                ], 400);
            }

            // تسجيل الدفع (كما هو موجود)
            $paymentData = [
                'teacher_id' => $salary->teacher_id,
                'salary_id' => $salary_id,
                'amount' => $payment_amount,
                'payment_method' => $request->payment_method,
                'payment_date' => now(),
                'notes' => $request->notes.($totalSelectedBonuses > 0 ? ' (يشمل بونصات)' : ''),
                'confirmed_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            \Log::info('Inserting payment record', $paymentData);

            $paymentInserted = TeacherPayment::create($paymentData);
            $totalpaid = DB::table('teacher_payments')
                ->where('teacher_id', $salary->teacher_id)
                ->where('salary_id', $salary_id)
                ->sum('amount');

            if (! $paymentInserted) {
                throw new \Exception('فشل في تسجيل الدفع');
            }

            // ==========  إضافة: تحديث حالة البونصات المحددة إلى "paid" ==========
            if (! empty($bonusesToUpdate)) {
                foreach ($bonusesToUpdate as $bonus) {
                    $bonus->payment_status = 'paid';
                    $bonus->payment_date = now();
                    $bonus->paid_by = auth()->id();
                    $bonus->save();

                    \Log::info('Bonus marked as paid', ['bonus_id' => $bonus->id, 'amount' => $bonus->amount]);
                }
            }
            // ========== نهاية الإضافة ==========

            // حساب المبالغ الجديدة (كما هو موجود)
            $new_paid_amount = $paid_amount + $payment_amount;
            $new_remaining_amount = max(0, $max_allowed_payment - $new_paid_amount);

            // تحديث حالة الراتب بناءً على المدفوعات (كما هو موجود)
            $new_status = 'partial';
            if ($new_remaining_amount <= 0.01) {
                $new_status = 'paid';
                $new_remaining_amount = 0;
            }

            \Log::info('Updating salary record', [
                'new_paid_amount' => $new_paid_amount,
                'new_remaining_amount' => $new_remaining_amount,
                'new_status' => $new_status,
            ]);

            DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->update([
                    'status' => $new_status,
                    // 'net_salary' => $totalpaid, // لا حاجة لتحديث net_salary هنا
                    'updated_at' => now(),
                    'payment_date' => now(),
                    'updated_by' => auth()->id(),
                ]);

            // إرسال إشعار للمدرس (كما هو موجود)
            if ($salary->user_id) {
                $notification_msg = 'تم دفع مبلغ '.number_format($payment_amount, 2).
                    ' جنيه من راتبك لشهر '.date('F Y', strtotime($salary->month.'-01')).
                    '. المتبقي: '.number_format($new_remaining_amount, 2).' جنيه';
                if ($totalSelectedBonuses > 0) {
                    $notification_msg .= ' (شامل '.number_format($totalSelectedBonuses, 2).' جنيه بونص)';
                }

                Notification::create([
                    'user_id' => $salary->user_id,
                    'title' => 'دفع راتب',
                    'message' => $notification_msg,
                    'type' => 'salary',
                    'related_id' => $salary_id,
                ]);
            }

            DB::commit();

            \Log::info('Payment completed successfully');

            return response()->json([
                'success' => true,
                'message' => 'تمت عملية الدفع بنجاح!'.($totalSelectedBonuses > 0 ? ' (شمل البونصات المحددة)' : ''),
                'paid_amount' => $new_paid_amount,
                'remaining_amount' => $new_remaining_amount,
                'status' => $new_status,
                'redirect_url' => route('salaries.show', $salary_id),
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Payment transaction failed', [
                'salary_id' => $salary_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'خطأ في عملية الدفع: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete adjustment
     */

    /**
     * Show edit adjustment form
     */
    /**
     * Show edit adjustment form
     */

    /**
     * Update adjustment
     */
    /**
     * Update adjustment
     */

    /**
     * Mark salary as unpaid
     */
    public function markUnpaid(Request $request, $teacher_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        $request->validate([
            'salary_id' => 'required|exists:salaries,salary_id',
        ]);

        try {
            DB::beginTransaction();

            // Delete all payments for this salary
            DB::table('teacher_payments')
                ->where('salary_id', $request->salary_id)
                ->delete();

            // Update salary status
            DB::table('salaries')
                ->where('salary_id', $request->salary_id)
                ->update([
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);

            DB::commit();

            return redirect()->back()->with('success', 'Salary marked as unpaid successfully!');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->with('error', 'Failed to mark as unpaid: '.$e->getMessage());
        }
    }

    /**
     * Create single salary record via AJAX
     */
    public function createSalaryRecord(Request $request, $teacher_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'group_id' => 'required|exists:groups,group_id',
            'month' => 'required|date_format:Y-m',
        ]);

        try {
            $group = DB::table('groups')->where('group_id', $request->group_id)->first();
            $teacher = DB::table('teachers')->where('teacher_id', $teacher_id)->first();

            if (! $group || ! $teacher) {
                return response()->json(['success' => false, 'error' => 'Group or teacher not found']);
            }

            // Calculate values
            $studentCount = DB::table('student_group')
                ->where('group_id', $request->group_id)
                ->count();
            $groupRevenue = $group->price * $studentCount;
            $teacherPercentage = $group->teacher_percentage ?? $teacher->salary_percentage ?? 0;
            $teacherShare = $groupRevenue * ($teacherPercentage / 100);

            // Create salary record
            $salary = Salary::create([
                'teacher_id' => $teacher_id,
                'group_id' => $request->group_id,
                'month' => $request->month,
                'group_revenue' => $groupRevenue,
                'teacher_share' => $teacherShare,
                'net_salary' => $teacherShare,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salary record created successfully',
                'salary_id' => $salary->salary_id,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error creating salary record: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to create salary record: '.$e->getMessage()]);
        }
    }

    /**
     * Create missing salary records in bulk
     */
    public function createMissingSalaryRecords(Request $request, $teacher_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        try {
            $teacher = DB::table('teachers')->where('teacher_id', $teacher_id)->first();
            if (! $teacher) {
                return response()->json(['success' => false, 'error' => 'Teacher not found']);
            }

            $created = 0;
            $groups = DB::table('groups')->where('teacher_id', $teacher_id)->get();

            foreach ($groups as $group) {
                $groupMonths = $this->getGroupActiveMonths($group->group_id);

                foreach ($groupMonths as $month) {
                    // Check if salary record already exists
                    $existingSalary = DB::table('salaries')
                        ->where('teacher_id', $teacher_id)
                        ->where('group_id', $group->group_id)
                        ->where('month', $month)
                        ->first();

                    if (! $existingSalary) {
                        // Calculate values
                        $studentCount = DB::table('student_group')
                            ->where('group_id', $group->group_id)
                            ->count();
                        $groupRevenue = $group->price * $studentCount;
                        $teacherPercentage = $group->teacher_percentage ?? $teacher->salary_percentage ?? 0;
                        $teacherShare = $groupRevenue * ($teacherPercentage / 100);

                        // Only create record if teacher share > 0
                        if ($teacherShare > 0) {
                            Salary::create([
                                'teacher_id' => $teacher_id,
                                'group_id' => $group->group_id,
                                'month' => $month,
                                'group_revenue' => $groupRevenue,
                                'teacher_share' => $teacherShare,
                                'net_salary' => $teacherShare,
                                'status' => 'pending',
                            ]);
                            $created++;
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Created  salary records',
                'created' => $created,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error creating missing salary records: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'Failed to create salary records: '.$e->getMessage()]);
        }
    }

    /**
     * Delete salary record
     */
    public function deleteSalary(Request $request, $teacher_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'Unauthorized access');
        }

        $request->validate([
            'salary_id' => 'required|exists:salaries,salary_id',
        ]);

        try {
            DB::beginTransaction();

            // Delete payments first
            DB::table('teacher_payments')
                ->where('salary_id', $request->salary_id)
                ->delete();

            // Then delete salary record
            DB::table('salaries')
                ->where('salary_id', $request->salary_id)
                ->delete();

            DB::commit();

            return redirect()->back()->with('success', 'Salary record deleted successfully!');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->with('error', 'Failed to delete salary record: '.$e->getMessage());
        }
    }
}
