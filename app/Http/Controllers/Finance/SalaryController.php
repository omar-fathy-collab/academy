<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Expense;
use App\Models\Salary;
use App\Models\Teacher;
use App\Models\TeacherAdjustment;
use App\Services\SalaryService; // Add this import
// Add this if using Schema in ensureSalaryTransfersTable
use Illuminate\Http\Request; // Add this import
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SalaryController extends Controller
{
    /**
     * Display a listing of salaries.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! Auth::check() || ($user && ! $user->isAdminFull())) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        // Build query with filters
        $query = DB::table('salaries')
            ->select(
                'salaries.*',
                'teachers.teacher_name',
                'teachers.salary_percentage',
                'groups.group_name',
                'groups.end_date as group_end_date',
                'groups.schedule',
                'groups.teacher_percentage as group_teacher_percentage',
                'groups.price as group_price',
                DB::raw('CASE WHEN salaries.teacher_id = groups.teacher_id THEN COALESCE(groups.teacher_percentage, teachers.salary_percentage, 0) ELSE 0 END as actual_percentage'),
                DB::raw('CASE WHEN salaries.teacher_id = groups.teacher_id THEN (SELECT COUNT(*) FROM student_group WHERE student_group.group_id = salaries.group_id) ELSE 0 END as student_count'),
                DB::raw('COALESCE((SELECT SUM(amount) FROM teacher_payments WHERE teacher_payments.salary_id = salaries.salary_id), 0) as paid_amount'),
                // التعديل هنا: حساب المتبقي بناءً على Available Payment بدلاً من Teacher Share
                DB::raw('(
                CASE 
                    WHEN (SELECT SUM(amount_paid) FROM invoices WHERE invoices.group_id = salaries.group_id AND invoices.status IN ("paid", "partial")) * 
                         (COALESCE(groups.teacher_percentage, teachers.salary_percentage, 0) / 100) > COALESCE((SELECT SUM(amount) FROM teacher_payments WHERE teacher_payments.salary_id = salaries.salary_id), 0)
                    THEN (SELECT SUM(amount_paid) FROM invoices WHERE invoices.group_id = salaries.group_id AND invoices.status IN ("paid", "partial")) * 
                         (COALESCE(groups.teacher_percentage, teachers.salary_percentage, 0) / 100) - COALESCE((SELECT SUM(amount) FROM teacher_payments WHERE teacher_payments.salary_id = salaries.salary_id), 0)
                    ELSE 0
                END
            ) as remaining_based_on_available')
            )
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id');

        // Apply filters
        if ($request->has('teacher_id') && $request->teacher_id) {
            $query->where('salaries.teacher_id', $request->teacher_id);
        }

        if ($request->has('month') && $request->month) {
            $query->where('salaries.month', $request->month);
        }

        if ($request->has('status') && $request->status) {
            $query->where('salaries.status', $request->status);
        }

        $salaries = $query->orderBy('salaries.month', 'desc')
            ->orderBy('salaries.created_at', 'desc')
            ->get();

        // NOW CALCULATE THE CORRECT VALUES FOR EACH SALARY
        $salaryService = app(SalaryService::class);
        foreach ($salaries as $salary) {
            // Calculate outgoing transfers from this salary record
            $outgoingTransfers = DB::table('salary_transfers')
                ->where('source_salary_id', $salary->salary_id)
                ->sum('transfer_amount');

            // If this salary record is purely an incoming transfer (beneficiary)
            // It was created by the transfer system with group_revenue=0 and teacher_share=0
            if ($salary->group_revenue == 0 && $salary->teacher_share == 0 && $salary->bonuses > 0 && isset($salary->notes) && str_contains($salary->notes, 'راتب ناتج عن تحويل')) {
                $salary->actual_revenue = 0;
                $salary->actual_teacher_share = $salary->bonuses;
                $salary->available_payment = $salary->bonuses;
                $salary->total_paid_fees = 0;
                $salary->total_discounts = 0;
            } else {
                // Organic salary record
                $values = $salaryService->calculateSalaryValues($salary);

                $totalBonuses = $salary->bonuses ?? 0;
                $totalDeductions = $salary->deductions ?? 0;

                $salary->actual_revenue = $values['revenue'];
                $salary->actual_teacher_share = max(0, $values['teacher_share'] + $totalBonuses - $totalDeductions - $outgoingTransfers);
                $salary->available_payment = max(0, $values['available_payment'] + $totalBonuses - $totalDeductions - $outgoingTransfers);
                $salary->total_paid_fees = $values['total_paid_fees'];
                $salary->total_discounts = $values['total_discounts'];
            }

            $salary->payment_status_based_on_available = $this->calculatePaymentStatusBasedOnAvailable($salary);
        }

        // Calculate totals
        $total_revenue = $salaries->sum('actual_revenue');
        $total_teacher_share = $salaries->sum('actual_teacher_share');
        $total_available_payment = $salaries->sum('available_payment');
        $total_paid_amount = $salaries->sum('paid_amount');
        $total_remaining = $total_teacher_share - $total_paid_amount;

        // Get adjustments totals - ADD THIS BACK
        $adjustments = DB::table('teacher_adjustments')
            ->selectRaw('SUM(CASE WHEN type = "bonus" THEN amount ELSE 0 END) as total_bonuses')
            ->selectRaw('SUM(CASE WHEN type = "deduction" THEN amount ELSE 0 END) as total_deductions')
            ->first();

        $total_bonuses = $adjustments->total_bonuses ?? 0;
        $total_deductions = $adjustments->total_deductions ?? 0;
        $net_adjustments = $total_bonuses - $total_deductions; // ADD THIS LINE

        // Get data for filters
        $teachers = DB::table('teachers')
            ->select('teacher_id', 'teacher_name')
            ->orderBy('teacher_name')
            ->get();

        $months = DB::table('salaries')
            ->select(DB::raw('DISTINCT month'))
            ->orderBy('month', 'desc')
            ->pluck('month');

        return view('salaries.index', compact(
            'salaries',
            'total_revenue',
            'total_teacher_share',
            'total_available_payment',
            'total_paid_amount',
            'total_remaining',
            'total_bonuses', // ADD THIS
            'total_deductions', // ADD THIS
            'net_adjustments', // ADD THIS
            'teachers',
            'months'
        ));
    }

    private function calculatePaymentStatusBasedOnAvailable($salary)
    {
        $available_payment = $salary->available_payment ?? 0;
        $paid_amount = $salary->paid_amount ?? 0;

        if ($paid_amount <= 0) {
            return 'pending';
        } elseif ($paid_amount >= $available_payment) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    private function calculateFinalCorrectSalaryValues($salary)
    {
        try {
            // If this salary record is purely an incoming transfer (beneficiary)
            // It was created by the transfer system with group_revenue=0 and teacher_share=0
            if (isset($salary->group_revenue) && $salary->group_revenue == 0 && 
                isset($salary->teacher_share) && $salary->teacher_share == 0 && 
                isset($salary->bonuses) && $salary->bonuses > 0 && 
                isset($salary->notes) && str_contains($salary->notes, 'راتب ناتج عن تحويل')) {
                return [
                    'revenue' => 0,
                    'teacher_share' => $salary->bonuses,
                    'available_payment' => $salary->bonuses,
                    'total_paid_fees' => 0,
                    'total_discounts' => 0,
                ];
            }

            Log::info('=== START calculateFinalCorrectSalaryValues ===');
            Log::info("Salary ID: {$salary->salary_id}, Teacher: {$salary->teacher_name}, Group: {$salary->group_id}");

            // Get the specific group for this salary
            $group = DB::table('groups')
                ->select('group_id', 'group_name', 'teacher_percentage', 'price', 'teacher_id')
                ->where('group_id', $salary->group_id)
                ->first();

            // Only calculate organic group revenue if the teacher is the formal owner of the group
            if (! $group || $salary->teacher_id != $group->teacher_id) {
                if (! $group) {
                    Log::warning("Group not found for salary: {$salary->salary_id}");
                } else {
                    Log::info("Teacher {$salary->teacher_name} is not the primary owner of group {$group->group_name}. Organic revenue is 0.");
                }

                $totalBonuses = $salary->bonuses ?? 0;
                $totalDeductions = $salary->deductions ?? 0;
                $outgoingTransfers = DB::table('salary_transfers')
                    ->where('source_salary_id', $salary->salary_id)
                    ->sum('transfer_amount');
                    
                $final_available = max(0, $totalBonuses - $totalDeductions - $outgoingTransfers);

                return [
                    'revenue' => 0,
                    'teacher_share' => $final_available,
                    'available_payment' => $final_available,
                    'total_paid_fees' => 0,
                    'total_discounts' => 0,
                ];
            }

            // Get number of students in the group
            $student_count = DB::table('student_group')
                ->where('group_id', $group->group_id)
                ->count();

            Log::info("Student count: {$student_count}, Group price: {$group->price}");

            // CORRECT: Group Revenue = عدد الطلاب × سعر الجروب
            $group_revenue = $student_count * $group->price;

            // Get ALL invoices for this group to calculate discounts
            $all_invoices = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->get();

            // CORRECT: Total discounts = مجموع discount_amount من كل الفواتير
            $total_discounts = $all_invoices->sum('discount_amount');

            // CORRECT: Get ONLY paid and partial invoices for available payment
            $paid_invoices = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->get();

            // CORRECT: Total paid fees = مجموع amount_paid من الفواتير المدفوعة والجزئية
            $total_paid_fees = $paid_invoices->sum('amount_paid');

            Log::info('Total invoices: '.$all_invoices->count());
            Log::info('Paid/Partial invoices: '.$paid_invoices->count());
            Log::info("Total discounts: {$total_discounts}, Total paid fees: {$total_paid_fees}");

            // Debug: log each invoice
            foreach ($paid_invoices as $invoice) {
                Log::info("Invoice {$invoice->invoice_id}: Amount={$invoice->amount}, Discount={$invoice->discount_amount}, Paid={$invoice->amount_paid}, Status={$invoice->status}");
            }

            // Get teacher percentage - use group percentage first, then teacher default
            $teacher = DB::table('teachers')->where('teacher_id', $salary->teacher_id)->first();
            $current_percentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 0);

            Log::info("Percentage used: {$current_percentage}%");

            // CORRECT: Teacher Share = (عدد الطلاب × سعر الجروب - إجمالي الخصومات) × نسبة المدرس
            $teacher_share = ($group_revenue - $total_discounts) * ($current_percentage / 100);

            // CORRECT: Available Payment = مجموع amount_paid من الفواتير المدفوعة والجزئية × نسبة المدرس
            $available_payment = $total_paid_fees * ($current_percentage / 100);

            // Add bonuses and subtract deductions and outgoing transfers
            $totalBonuses = $salary->bonuses ?? 0;
            $totalDeductions = $salary->deductions ?? 0;
            
            $outgoingTransfers = DB::table('salary_transfers')
                ->where('source_salary_id', $salary->salary_id)
                ->sum('transfer_amount');

            $final_teacher_share = max(0, $teacher_share + $totalBonuses - $totalDeductions - $outgoingTransfers);
            $final_available_payment = max(0, $available_payment + $totalBonuses - $totalDeductions - $outgoingTransfers);

            Log::info('FINAL CORRECT CALCULATIONS:');
            Log::info("Group Revenue: {$student_count} × {$group->price} = {$group_revenue}");
            Log::info("Total Discounts: {$total_discounts}");
            Log::info("Organic Teacher Share: ({$group_revenue} - {$total_discounts}) × {$current_percentage}% = {$teacher_share}");
            Log::info("Organic Available Payment: {$total_paid_fees} × {$current_percentage}% = {$available_payment}");
            Log::info("Adjustments: Bonuses= +{$totalBonuses}, Deductions= -{$totalDeductions}, Outgoing Transfers= -{$outgoingTransfers}");
            Log::info("Final Teacher Share: {$final_teacher_share}");
            Log::info("Final Available Payment: {$final_available_payment}");
            Log::info('=== END calculateFinalCorrectSalaryValues ===');

            return [
                'revenue' => $group_revenue,
                'teacher_share' => $final_teacher_share,
                'available_payment' => $final_available_payment,
                'total_paid_fees' => $total_paid_fees,
                'total_discounts' => $total_discounts,
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating final correct salary values: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'revenue' => 0,
                'teacher_share' => 0,
                'available_payment' => 0,
                'total_paid_fees' => 0,
                'total_discounts' => 0,
            ];
        }
    }

    /**
     * AJAX search for salaries
     */
    public function search(Request $request)
    {
        try {
            $query = DB::table('salaries')
                ->select(
                    'salaries.*',
                    'teachers.teacher_name',
                    'teachers.salary_percentage',
                    'teachers.base_salary',
                    'teachers.bank_account',
                    'teachers.payment_method',
                    'groups.group_name',
                    'groups.group_id',
                    'groups.price as group_price'
                )
                ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
                ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id');

            // Search term
            if ($request->has('search') && ! empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('teachers.teacher_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('salaries.status', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('salaries.month', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('groups.group_name', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Status filter
            if ($request->has('status') && ! empty($request->status)) {
                $query->where('salaries.status', $request->status);
            }

            // Month filter
            if ($request->has('month') && ! empty($request->month)) {
                $query->where('salaries.month', $request->month);
            }

            $salaries = $query->orderBy('salaries.month', 'desc')
                ->orderBy('salaries.created_at', 'desc')
                ->get();

            // Process salaries with FINAL correct calculations
            $total_revenue = 0;
            $total_teacher_share = 0;
            $total_available_payment = 0;

            foreach ($salaries as $salary) {
                $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);

                $salary->calculated_revenue = $calculatedValues['revenue'];
                $salary->calculated_teacher_share = $calculatedValues['teacher_share'];
                $salary->calculated_available_payment = $calculatedValues['available_payment'];
                $salary->calculated_total_paid_fees = $calculatedValues['total_paid_fees'];
                $salary->calculated_total_discounts = $calculatedValues['total_discounts'];

                // Get payment details
                $payments = DB::table('teacher_payments')
                    ->where('salary_id', $salary->salary_id)
                    ->get();

                $salary->paid_amount = $payments->sum('amount');
                $salary->remaining_amount = $salary->calculated_teacher_share - $salary->paid_amount;

                // Update totals
                $total_revenue += $calculatedValues['revenue'];
                $total_teacher_share += $calculatedValues['teacher_share'];
                $total_available_payment += $calculatedValues['available_payment'];
            }

            return response()->json([
                'success' => true,
                'salaries' => $salaries,
                'total_count' => $salaries->count(),
                'totals' => [
                    'revenue' => $total_revenue,
                    'teacher_share' => $total_teacher_share,
                    'available_payment' => $total_available_payment,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Salary search error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Search failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * حساب القيم المطلوبة للراتب
     */
    private function calculateSalaryValues($salary)
    {
        // Calculate Revenue from ACTUAL payments considering discounts
        if (isset($salary->group_id) && $salary->group_id) {
            $payment_info = $this->calculateAvailablePaymentForGroup($salary->teacher_id, $salary->month, $salary->group_id);
        } else {
            $payment_info = $this->calculateAvailablePayment($salary->teacher_id, $salary->month);
        }

        $total_paid_fees = $payment_info['total_paid_fees'];
        $available_payment = $payment_info['available_payment'];
        $teacher_share_percentage = $payment_info['teacher_share_percentage'];

        // Calculate Teacher Share based on actual percentage and actual payments
        $teacher_share = $total_paid_fees * ($teacher_share_percentage / 100);

        return [
            'revenue' => $total_paid_fees,
            'teacher_share' => $teacher_share,
            'available_payment' => $available_payment,
            'total_paid_fees' => $total_paid_fees,
            'actual_percentage' => $teacher_share_percentage,
            'groups_count' => count($salary->unique_group_names),
        ];
    }

    private function calculateCorrectSalaryValues($salary)
    {
        try {
            Log::info('=== START calculateCorrectSalaryValues ===');
            Log::info("Salary ID: {$salary->salary_id}, Teacher: {$salary->teacher_name}, Group: {$salary->group_id}");

            // Get the specific group for this salary
            $group = DB::table('groups')
                ->select('group_id', 'group_name', 'teacher_percentage', 'price')
                ->where('group_id', $salary->group_id)
                ->first();

            if (! $group) {
                Log::warning("Group not found for salary: {$salary->salary_id}");

                return [
                    'revenue' => 0,
                    'teacher_share' => 0,
                    'available_payment' => 0,
                    'total_paid_fees' => 0,
                    'total_discounts' => 0,
                ];
            }

            // Get number of students in the group
            $student_count = DB::table('student_group')
                ->where('group_id', $group->group_id)
                ->count();

            Log::info("Student count: {$student_count}, Group price: {$group->price}");

            // CORRECT: Group Revenue = عدد الطلاب × سعر الجروب
            $group_revenue = $student_count * $group->price;

            // Get all invoices for this group
            $invoices = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->get();

            Log::info('Invoices found: '.$invoices->count());

            // CORRECT: Total discounts = مجموع discount_amount من كل الفواتير
            $total_discounts = $invoices->sum('discount_amount');

            // CORRECT: Total paid fees = مجموع amount_paid من الفواتير المدفوعة والجزئية
            $paid_invoices = $invoices->whereIn('status', ['paid', 'partial']);
            $total_paid_fees = $paid_invoices->sum('amount_paid');

            Log::info("Total discounts: {$total_discounts}, Total paid fees: {$total_paid_fees}");

            // Get teacher percentage
            $teacher = DB::table('teachers')->where('teacher_id', $salary->teacher_id)->first();
            $current_percentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 0);

            Log::info("Percentage used: {$current_percentage}%");

            // CORRECT: Teacher Share = (عدد الطلاب × سعر الجروب - إجمالي الخصومات) × نسبة المدرس
            $teacher_share = ($group_revenue - $total_discounts) * ($current_percentage / 100);

            // CORRECT: Available Payment = مجموع amount_paid × نسبة المدرس
            $available_payment = $total_paid_fees * ($current_percentage / 100);

            Log::info('CORRECT CALCULATIONS:');
            Log::info("Group Revenue: {$student_count} × {$group->price} = {$group_revenue}");
            Log::info("Teacher Share: ({$group_revenue} - {$total_discounts}) × {$current_percentage}% = {$teacher_share}");
            Log::info("Available Payment: {$total_paid_fees} × {$current_percentage}% = {$available_payment}");
            Log::info('=== END calculateCorrectSalaryValues ===');

            return [
                'revenue' => $group_revenue,
                'teacher_share' => $teacher_share,
                'available_payment' => $available_payment,
                'total_paid_fees' => $total_paid_fees,
                'total_discounts' => $total_discounts,
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating correct salary values: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'revenue' => 0,
                'teacher_share' => 0,
                'available_payment' => 0,
                'total_paid_fees' => 0,
                'total_discounts' => 0,
            ];
        }
    }

    /**
     * حساب المبلغ المتاح للمدرس لمجموعة محددة
     */
    private function calculateAvailablePaymentForGroup($teacher_id, $month, $group_id)
    {
        try {
            Log::info('=== START calculateAvailablePaymentForGroup ===');
            Log::info("Teacher: {$teacher_id}, Month: {$month}, Group: {$group_id}");

            // Get the specific group
            $group = DB::table('groups')
                ->select('group_id', 'group_name', 'teacher_percentage', 'price')
                ->where('group_id', $group_id)
                ->where('teacher_id', $teacher_id)
                ->first();

            if (! $group) {
                Log::warning("Group {$group_id} not found for teacher {$teacher_id}");

                return [
                    'available_payment' => 0,
                    'total_paid_fees' => 0,
                    'teacher_share_percentage' => 0,
                ];
            }

            Log::info("Processing specific group: {$group->group_name} (ID: {$group->group_id})");

            // CORRECT: Get total paid fees from invoices
            $total_paid_fees = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->sum('amount_paid');

            Log::info("Total paid fees for group {$group->group_name}: {$total_paid_fees}");

            // Get teacher percentage
            $teacher = DB::table('teachers')->where('teacher_id', $teacher_id)->first();
            $current_percentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 0);

            Log::info("Percentage used: {$current_percentage}%");

            // CORRECT: Calculate available payment = مجموع amount_paid × نسبة المدرس
            $available_payment = $total_paid_fees * ($current_percentage / 100);

            Log::info("CORRECT Available Payment: {$total_paid_fees} × {$current_percentage}% = {$available_payment}");
            Log::info('=== END calculateAvailablePaymentForGroup ===');

            return [
                'available_payment' => $available_payment,
                'total_paid_fees' => $total_paid_fees,
                'teacher_share_percentage' => $current_percentage,
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating available payment for group: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return [
                'available_payment' => 0,
                'total_paid_fees' => 0,
                'teacher_share_percentage' => 0,
            ];
        }
    }

    private function calculateAvailablePayment($teacher_id, $month)
    {
        try {
            Log::info('=== START calculateAvailablePayment ===');
            Log::info("Teacher: {$teacher_id}, Month: {$month}");

            // Get teacher's groups for the specified month
            $groups = DB::table('groups')
                ->select('group_id', 'group_name', 'teacher_percentage', 'price')
                ->where('teacher_id', $teacher_id)
                ->where(function ($query) use ($month) {
                    $query->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$month])
                        ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$month]);
                })
                ->get();

            Log::info('Groups found: '.$groups->count());

            $total_available_payment = 0;
            $total_paid_fees = 0;
            $teacher_share_percentage = 0;

            if ($groups->isEmpty()) {
                Log::warning("No groups found for teacher {$teacher_id} in month {$month}");

                return [
                    'available_payment' => 0,
                    'total_paid_fees' => 0,
                    'teacher_share_percentage' => 0,
                ];
            }

            foreach ($groups as $group) {
                Log::info("Processing group: {$group->group_name} (ID: {$group->group_id})");

                // Get ACTUAL paid invoices for this group
                $invoices = DB::table('invoices')
                    ->where('group_id', $group->group_id)
                    ->whereIn('status', ['paid', 'partial'])
                    ->get();

                $group_paid_fees = $invoices->sum('amount_paid');
                $total_paid_fees += $group_paid_fees;

                Log::info("Group {$group->group_name} - Paid fees: {$group_paid_fees}");

                // Get teacher percentage
                $teacher = DB::table('teachers')->where('teacher_id', $teacher_id)->first();
                $current_percentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 0);

                Log::info("Percentage used: {$current_percentage}%");

                // Calculate available payment based on ACTUAL payments
                $group_available_payment = $group_paid_fees * ($current_percentage / 100);
                $total_available_payment += $group_available_payment;

                Log::info("CORRECT Group calculation: {$group_paid_fees} * {$current_percentage}% = {$group_available_payment}");

                if ($current_percentage > $teacher_share_percentage) {
                    $teacher_share_percentage = $current_percentage;
                }
            }

            Log::info("FINAL CORRECT RESULT - Total Paid: {$total_paid_fees}, Available: {$total_available_payment}, Percentage: {$teacher_share_percentage}%");
            Log::info('=== END calculateAvailablePayment ===');

            return [
                'available_payment' => $total_available_payment,
                'total_paid_fees' => $total_paid_fees,
                'teacher_share_percentage' => $teacher_share_percentage,
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating available payment: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return [
                'available_payment' => 0,
                'total_paid_fees' => 0,
                'teacher_share_percentage' => 0,
            ];
        }
    }

    public function diagnosePaymentIssue($salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        $salary = DB::table('salaries')
            ->where('salary_id', $salary_id)
            ->first();

        if (! $salary) {
            return response()->json(['error' => 'Salary not found'], 404);
        }

        $diagnosis = [
            'salary_info' => $salary,
            'groups_related' => [],
            'groups_all' => [],
            'payment_breakdown' => [],
        ];

        // المجموعات المرتبطة بالراتب (إذا كان group_id موجوداً)
        if (isset($salary->group_id) && $salary->group_id) {
            $diagnosis['groups_related'] = DB::table('groups')
                ->where('group_id', $salary->group_id)
                ->get();
        }

        // جميع مجموعات المدرس في الشهر (للتوضيح فقط)
        $diagnosis['groups_all'] = DB::table('groups')
            ->where('teacher_id', $salary->teacher_id)
            ->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$salary->month])
            ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$salary->month])
            ->get();

        return response()->json($diagnosis);
    }

    public function debugSalary($salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        $salary = DB::table('salaries')
            ->where('salary_id', $salary_id)
            ->first();

        if (! $salary) {
            return response()->json(['error' => 'Salary not found'], 404);
        }

        $debug_info = [
            'salary' => $salary,
            'groups' => DB::table('groups')
                ->where('teacher_id', $salary->teacher_id)
                ->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$salary->month])
                ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$salary->month])
                ->get(),
            'invoices' => [],
            'group_users' => [],
        ];

        // Get invoices and group users for each group
        foreach ($debug_info['groups'] as $group) {
            $debug_info['invoices'][$group->group_id] = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->get();

            $debug_info['group_users'][$group->group_id] = DB::table('group_user')
                ->where('group_id', $group->group_id)
                ->get();
        }

        // Calculate available payment
        $payment_info = $this->calculateAvailablePayment($salary->teacher_id, $salary->month);
        $debug_info['payment_calculation'] = $payment_info;

        return response()->json($debug_info);
    }

    /**
     * Show the form for editing a salary record.
     */
    public function edit($salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        // Get salary details - include notes field
        $salary = DB::table('salaries')
            ->select('salaries.*', 'teachers.teacher_name', 'teachers.salary_percentage', 'teachers.base_salary')
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->where('salaries.salary_id', $salary_id)
            ->first();

        if (! $salary) {
            abort(404, 'Salary record not found');
        }

        // Get all teachers
        $teachers = DB::table('teachers')
            ->select('teacher_id', 'teacher_name', 'salary_percentage', 'base_salary')
            ->orderBy('teacher_name')
            ->get();

        // Get all groups
        $groups = DB::table('groups')
            ->select(
                'groups.group_id',
                'groups.group_name',
                'groups.teacher_id',
                'groups.price',
                'groups.teacher_percentage',
                'courses.course_name',
                'teachers.teacher_name'
            )
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->orderBy('groups.group_name')
            ->get();

        return view('salaries.edit', compact('salary', 'teachers', 'groups'));
    }

    /**
     * Update the specified salary record.
     */
    public function update(Request $request, $salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        // التحقق من الهيكل أولاً
        $existingColumns = DB::getSchemaBuilder()->getColumnListing('salaries');

        $request->validate([
            'teacher_id' => 'required|exists:teachers,teacher_id',
            'group_id' => 'required|exists:groups,group_id',
            'month' => 'required|date_format:Y-m',
            'teacher_percentage' => 'required|numeric|min:0|max:100',
            'group_revenue' => 'required|numeric|min:0',
            'teacher_share' => 'required|numeric|min:0',
            'net_salary' => 'required|numeric',
            'status' => 'required|in:pending,partial,paid',
        ]);

        try {
            DB::beginTransaction();

            // بناء بيانات التحديث بناءً على الأعمدة الموجودة
            $updateData = [
                'teacher_id' => $request->teacher_id,
                'group_id' => $request->group_id,
                'month' => $request->month,
                'group_revenue' => $request->group_revenue,
                'teacher_share' => $request->teacher_share,
                'net_salary' => $request->net_salary,
                'status' => $request->status,
                'updated_at' => now(),
                'updated_by' => Auth::id(),
            ];

            // إضافة الحقول الاختيارية فقط إذا كانت موجودة في الجدول
            if (in_array('base_salary', $existingColumns)) {
                $updateData['base_salary'] = $request->base_salary ?? 0;
            }

            if (in_array('bonuses', $existingColumns)) {
                $updateData['bonuses'] = $request->bonuses ?? 0;
            }

            if (in_array('deductions', $existingColumns)) {
                $updateData['deductions'] = $request->deductions ?? 0;
            }

            if (in_array('notes', $existingColumns)) {
                $updateData['notes'] = $request->notes ?? null;
            }

            // Update salary record
            DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->update($updateData);

            // If status changed to paid and no payments exist, create one
            if ($request->status === 'paid') {
                $existing_payments = DB::table('teacher_payments')
                    ->where('salary_id', $salary_id)
                    ->exists();

                if (! $existing_payments) {
                    \App\Models\TeacherPayment::create([
                        'teacher_id' => $request->teacher_id,
                        'salary_id' => $salary_id,
                        'amount' => $request->net_salary,
                        'payment_method' => 'manual_entry',
                        'payment_date' => now(),
                        'notes' => 'Manual salary update - marked as paid',
                        'confirmed_by' => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('salaries.index')
                ->with('success', 'Salary record updated successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating salary record: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'Error updating salary record: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified salary record.
     */
    /**
     * Remove the specified salary record.
     */
    /**
     * Remove the specified salary record along with its payments.
     */
    public function destroy($salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        try {
            DB::beginTransaction();

            // Check if user has permission
            if (! Auth::check() || ! Auth::user()->isAdminFull()) {
                return redirect()->back()
                    ->with('error', 'ليس لديك صلاحية لحذف سجلات الرواتب.');
            }

            // Check if salary exists
            $salary = DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->first();

            if (! $salary) {
                return redirect()->back()
                    ->with('error', 'سجل الراتب غير موجود.');
            }

            // Delete all payments associated with this salary FIRST
            $payments_deleted = DB::table('teacher_payments')
                ->where('salary_id', $salary_id)
                ->delete();

            Log::info("Deleted {$payments_deleted} payments for salary ID: {$salary_id}");

            // Now delete the salary record
            $deleted = DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->delete();

            if ($deleted) {
                DB::commit();

                // Log the action
                Log::info("Salary record {$salary_id} and its payments deleted successfully by user: ".Auth::id());

                return redirect()->route('salaries.index')
                    ->with('success', 'تم حذف سجل الراتب والمدفوعات المرتبطة به بنجاح!');
            } else {
                DB::rollback();

                return redirect()->back()
                    ->with('error', 'فشل في حذف سجل الراتب.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting salary record and payments: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return redirect()->back()
                ->with('error', 'خطأ في حذف سجل الراتب: '.$e->getMessage());
        }
    }

    /**
     * Show salary details
     */
    public function show($salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (! is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) {
                $salary_id = $resolved_id;
            }
        }

        // Get salary details with teacher information
        $salary = DB::table('salaries')
            ->select('salaries.*', 'teachers.teacher_name', 'teachers.salary_percentage', 'teachers.base_salary', 'teachers.bank_account', 'teachers.payment_method', 'users.username as confirmed_by_name', 'teacher_users.email as teacher_email', 'profile.phone_number as teacher_phone')
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->leftJoin('users', 'salaries.updated_by', '=', 'users.id')
            ->leftJoin('users as teacher_users', 'teachers.user_id', '=', 'teacher_users.id')
            ->leftJoin('profile', 'teacher_users.id', '=', 'profile.user_id')
            ->where('salaries.salary_id', $salary_id)
            ->first();

        if (! $salary) {
            abort(404, 'Salary record not found');
        }

        // Get groups included in this salary with detailed payment information
        $salary_groups = DB::table('groups')
            ->select(
                'groups.group_name',
                'groups.group_id',
                'groups.teacher_percentage',
                DB::raw('(
                SELECT COUNT(*) 
                FROM student_group
                WHERE student_group.group_id = groups.group_id
            ) as total_students'),
                DB::raw('(
                SELECT COALESCE(SUM(invoices.amount_paid), 0)
                FROM invoices 
                WHERE invoices.group_id = groups.group_id
                AND invoices.status IN ("paid", "partial")
            ) as total_paid_fees'),
                DB::raw('(
                SELECT COUNT(*) 
                FROM invoices 
                WHERE invoices.group_id = groups.group_id
            ) as total_invoices'),
                DB::raw('(
                SELECT COUNT(*) 
                FROM invoices 
                WHERE invoices.group_id = groups.group_id
                AND invoices.status IN ("paid", "partial")
            ) as paid_invoices'),
                DB::raw('(
                SELECT GROUP_CONCAT(CONCAT(students.student_name, " - ", COALESCE(invoices.amount_paid, 0), " EGP (", COALESCE(invoices.status, "unpaid"), ")") SEPARATOR " | ") 
                FROM student_group
                LEFT JOIN students ON student_group.student_id = students.student_id
                LEFT JOIN invoices ON students.student_id = invoices.student_id AND invoices.group_id = groups.group_id
                WHERE student_group.group_id = groups.group_id
            ) as students_payment_details')
            )
            ->where('groups.teacher_id', $salary->teacher_id)
            ->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$salary->month])
            ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$salary->month])
            ->groupBy('groups.group_id', 'groups.group_name', 'groups.teacher_percentage')
            ->get();

        // Calculate teacher share for each group and get detailed student information
        foreach ($salary_groups as $group) {
            $teacher_percentage = $group->teacher_percentage ?? $salary->salary_percentage;
            $group->teacher_share = $group->total_paid_fees * ($teacher_percentage / 100);
            $group->teacher_percentage_display = $teacher_percentage;

            // الحصول على تفاصيل الطلاب ومدفوعاتهم بشكل منفصل
            $group->student_details = DB::table('student_group')
                ->select(
                    'students.student_id',
                    'students.student_name',
                    'invoices.invoice_id',
                    'invoices.amount as invoice_amount',
                    'invoices.amount_paid as paid_amount',
                    'invoices.status as invoice_status'
                )
                ->leftJoin('students', 'student_group.student_id', '=', 'students.student_id')
                ->leftJoin('invoices', function ($join) use ($group) {
                    $join->on('invoices.student_id', '=', 'students.student_id')
                        ->where('invoices.group_id', '=', $group->group_id);
                })
                ->where('student_group.group_id', $group->group_id)
                ->get();
        }

        // Get payment history for this salary
        $payments = DB::table('teacher_payments')
            ->select('teacher_payments.*', 'users.username as confirmed_by_name')
            ->leftJoin('users', 'teacher_payments.confirmed_by', '=', 'users.id')
            ->where('teacher_payments.salary_id', $salary_id)
            ->orderBy('teacher_payments.payment_date', 'desc')
            ->get();

        // Get outgoing transfers to deduct from remaining amount
        $outgoingTransfersAmount = DB::table('salary_transfers')
            ->where('source_salary_id', $salary_id)
            ->sum('transfer_amount');

        // Calculate paid amount and remaining
        $paid_amount = $payments->sum('amount');
        $remaining_amount = max(0, $salary->net_salary - $paid_amount - $outgoingTransfersAmount);

        return view('salaries.show', compact('salary', 'payments', 'paid_amount', 'remaining_amount', 'salary_groups', 'outgoingTransfersAmount'));
    }

    /**
     * Show the form for paying a salary.
     */
    /**
     * Show the form for paying a salary.
     */
    /**
     * Show the form for paying a salary.
     */
    /**
     * Show the form for paying a salary.
     */
    /**
     * Show the form for paying a salary.
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

        // الحصول على بيانات الراتب
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

        // الحصول على المدفوعات
        $payments = DB::table('teacher_payments')
            ->where('salary_id', $salary_id)
            ->get();
        $paid_amount = $payments->sum('amount');

        // حساب القيم الفعلية
        $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);
        $available_payment = $calculatedValues['available_payment'];
        $max_allowed_payment = max($available_payment, $salary->teacher_share);
        $remaining_amount = max(0, $max_allowed_payment - $paid_amount);

        // ========== جلب التحويلات الصادرة (خصم) ==========
        $outgoingTransfers = DB::table('salary_transfers')
            ->select(
                'salary_transfers.*',
                'target.teacher_name as target_teacher_name'
            )
            ->join('teachers as target', 'salary_transfers.target_teacher_id', '=', 'target.teacher_id')
            ->where('salary_transfers.source_salary_id', $salary_id)
            ->orderBy('salary_transfers.created_at', 'desc')
            ->get();

        // حساب إجمالي التحويلات الصادرة
        $outgoing_transfers_total = $outgoingTransfers->sum('transfer_amount');

        // التحويلات الصادرة التي تم دفعها بالفعل للمستفيدين
        $outgoing_paid_transfers_total = $outgoingTransfers
            ->where('payment_status', 'paid')
            ->sum('transfer_amount');

        // التحويلات الصادرة المعلقة (التي لم تدفع بعد للمستفيد)
        $outgoing_pending_transfers_total = $outgoingTransfers
            ->whereIn('payment_status', ['pending', 'partial'])
            ->sum(function ($transfer) {
                return $transfer->transfer_amount - $transfer->paid_amount;
            });

        // ========== حساب المبلغ الفعلي المتاح للدفع ==========
        // المبلغ المتاح = المتبقي من الراتب - إجمالي التحويلات الصادرة (مدفوعة + معلقة)
        $total_outgoing_deductions = $outgoing_paid_transfers_total + $outgoing_pending_transfers_total;
        $max_allowed_with_transfers = max(0, $remaining_amount - $total_outgoing_deductions);

        // ========== جلب التحويلات الواردة (إضافة) ==========
        $incomingTransfers = DB::table('salary_transfers')
            ->select(
                'salary_transfers.*',
                'source.teacher_name as source_teacher_name',
                'salaries.month as transfer_month',
                'salaries.group_id as transfer_group_id'
            )
            ->join('teachers as source', 'salary_transfers.source_teacher_id', '=', 'source.teacher_id')
            ->join('salaries', 'salary_transfers.source_salary_id', '=', 'salaries.salary_id')
            ->where('salary_transfers.target_teacher_id', $salary->teacher_id)
            ->where('salary_transfers.payment_status', '!=', 'paid')
            ->orderBy('salary_transfers.created_at', 'desc')
            ->get();

        $incoming_transfers_total = $incomingTransfers->sum('transfer_amount');
        $incoming_paid_transfers_total = $incomingTransfers->where('payment_status', 'paid')->sum('transfer_amount');
        $incoming_pending_transfers_total = $incomingTransfers
            ->whereIn('payment_status', ['pending', 'partial'])
            ->sum(function ($transfer) {
                return $transfer->transfer_amount - $transfer->paid_amount;
            });

        // ========== جلب البونصات والخصومات ==========
        $pendingAdjustments = TeacherAdjustment::where('teacher_id', $salary->teacher_id)
            ->where(function ($query) use ($salary_id) {
                $query->where('salary_id', $salary_id)
                    ->orWhereNull('salary_id');
            })
            ->where('payment_status', 'pending')
            ->get();

        $pendingBonuses = $pendingAdjustments->where('type', 'bonus');
        $pendingDeductions = $pendingAdjustments->where('type', 'deduction');
        $total_pending_bonuses = $pendingBonuses->sum('amount');
        $total_pending_deductions = $pendingDeductions->sum('amount');

        // الحصول على المدرسين الآخرين للتحويل
        $teachers = DB::table('teachers')
            ->select('teacher_id', 'teacher_name', 'salary_percentage', 'base_salary')
            ->where('teacher_id', '!=', $salary->teacher_id)
            ->orderBy('teacher_name')
            ->get();

        $max_allowed = $max_allowed_with_transfers;

        return view('salaries.pay', compact(
            'salary',
            'paid_amount',
            'remaining_amount',
            'available_payment',
            'max_allowed',
            'teachers',
            'pendingBonuses',
            'pendingDeductions',
            'total_pending_bonuses',
            'total_pending_deductions',
            'outgoingTransfers',
            'incomingTransfers',
            'outgoing_transfers_total',
            'outgoing_paid_transfers_total',
            'outgoing_pending_transfers_total',
            'incoming_transfers_total',
            'incoming_paid_transfers_total',
            'incoming_pending_transfers_total',
            'max_allowed_with_transfers'
        ));
    }

    /**
     * Process the salary payment (with transfer handling) - COMPLETE FIXED VERSION
     */
    /**
     * Process the salary payment (with transfer handling) - COMPLETE FIXED VERSION
     */
    /**
     * Process the salary payment (with transfer handling) - COMPLETE FIXED VERSION
     */
    /**
     * Process the salary payment (with transfer handling) - COMPLETE FIXED VERSION
     */
    /**
     * Process the salary payment (with transfer handling) - COMPLETE FIXED VERSION
     * تم التعديل: التحويلات الواردة المختارة يتم تحديث حالتها إلى paid
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

        Log::info('=== START processPayment (Fixed Version - Incoming transfers update) ===', [
            'salary_id' => $salary_id,
            'request_data' => $request->all(),
            'user' => Auth::user()->username ?? 'Unknown',
        ]);

        // التحقق من الصلاحيات
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return back()->withErrors(['error' => 'ليس لديك صلاحية للقيام بهذه العملية']);
        }

        // تحقق من البيانات
        $request->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,vodafone_cash',
            'notes' => 'nullable|string|max:500',
            'selected_bonuses' => 'nullable|array',
            'selected_bonuses.*' => 'exists:teacher_adjustments,adjustment_id', // Fixed from 'id' to 'adjustment_id'
            'selected_incoming_transfers' => 'nullable|array',
            'selected_incoming_transfers.*' => 'exists:salary_transfers,transfer_id',
        ]);

        DB::beginTransaction();
        try {
            // ========== 1. الحصول على بيانات الراتب ==========
            $salary = DB::table('salaries')
                ->select('salaries.*', 'teachers.user_id', 'teachers.teacher_name')
                ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
                ->where('salaries.salary_id', $salary_id)
                ->lockForUpdate()
                ->first();

            if (! $salary) {
                return response()->json([
                    'success' => false,
                    'error' => 'سجل الراتب غير موجود',
                ], 404);
            }

            // ========== 2. حساب القيم الفعلية للراتب ==========
            $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);
            $available_payment = $calculatedValues['available_payment'];
            $max_allowed_payment = max($available_payment, $salary->teacher_share);

            // المبالغ المدفوعة بالفعل
            $paid_amount = DB::table('teacher_payments')
                ->where('salary_id', $salary_id)
                ->sum('amount');

            $remaining_amount = max(0, $max_allowed_payment - $paid_amount);

            // ========== 3. جلب التحويلات الصادرة المعلقة (للعلم فقط - لا نحدثها) ==========
            $pendingOutgoingTransfers = DB::table('salary_transfers')
                ->where('source_salary_id', $salary_id)
                ->whereIn('payment_status', ['pending', 'partial'])
                ->get();

            $totalPendingOutgoing = $pendingOutgoingTransfers->sum(function ($transfer) {
                return $transfer->transfer_amount - $transfer->paid_amount;
            });

            // ========== 4. جلب التحويلات الواردة المختارة (نقوم بتحديثها إلى مدفوعة) ==========
            $selectedIncomingTransferIds = $request->input('selected_incoming_transfers', []);
            $totalSelectedIncoming = 0;
            $incomingTransfersToUpdate = collect();

            if (! empty($selectedIncomingTransferIds)) {
                $incomingTransfersToUpdate = DB::table('salary_transfers')
                    ->whereIn('transfer_id', $selectedIncomingTransferIds)
                    ->where('target_teacher_id', $salary->teacher_id)
                    ->whereIn('payment_status', ['pending', 'partial'])
                    ->get();

                $totalSelectedIncoming = $incomingTransfersToUpdate->sum(function ($transfer) {
                    return $transfer->transfer_amount - $transfer->paid_amount;
                });
            }

            // ========== 5. جلب البونصات المحددة ==========
            $selectedBonusIds = $request->input('selected_bonuses', []);
            $totalSelectedBonuses = 0;
            $bonusesToUpdate = collect();

            if (! empty($selectedBonusIds)) {
                $bonusesToUpdate = DB::table('teacher_adjustments')
                    ->whereIn('id', $selectedBonusIds)
                    ->where('teacher_id', $salary->teacher_id)
                    ->where('payment_status', 'pending')
                    ->where('type', 'bonus')
                    ->get();

                $totalSelectedBonuses = $bonusesToUpdate->sum('amount');
            }

            // ========== 6. حساب المبلغ الفعلي المتاح للدفع ==========
            // ملاحظة: التحويلات الواردة لا تزيد المبلغ المتاح للدفع، ولكنها تُحدث فقط
            $availableWithTransfers = $remaining_amount; // بدون إضافة التحويلات الواردة

            $payment_amount = floatval($request->payment_amount);

            // ========== 7. التحقق من صحة المبلغ ==========
            if ($payment_amount > $availableWithTransfers) {
                return back()->withErrors(['payment_amount' => 'المبلغ يتجاوز الحد المسموح به للدفع. الحد الأقصى: '.number_format($availableWithTransfers, 2).' EGP']);
            }

            if ($payment_amount <= 0) {
                return back()->withErrors(['payment_amount' => 'المبلغ يجب أن يكون أكبر من صفر']);
            }

            // ========== 8. تسجيل الدفع في جدول teacher_payments ==========
            $paymentData = [
                'teacher_id' => $salary->teacher_id,
                'salary_id' => $salary_id,
                'amount' => $payment_amount,
                'payment_method' => $request->payment_method,
                'payment_date' => now(),
                'notes' => $request->notes,
                'confirmed_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // إضافة ملاحظة عن البونصات والتحويلات إذا وجدت
            $notes_parts = [];
            if ($totalSelectedBonuses > 0) {
                $notes_parts[] = 'بونص: '.number_format($totalSelectedBonuses, 2).' EGP';
            }
            if ($totalSelectedIncoming > 0) {
                $notes_parts[] = 'تحويلات واردة: '.number_format($totalSelectedIncoming, 2).' EGP (تم تحديث حالتها إلى مدفوعة)';
            }
            if ($totalPendingOutgoing > 0) {
                $notes_parts[] = 'تحويلات صادرة: '.number_format($totalPendingOutgoing, 2).' EGP (خصمت من الراتب - تبقى pending للمستفيد)';
            }

            if (! empty($notes_parts)) {
                $paymentData['notes'] = ($paymentData['notes'] ? $paymentData['notes'].' | ' : '').implode(' | ', $notes_parts);
            }

            \App\Models\TeacherPayment::create($paymentData);

            // ========== 9. تحديث حالة التحويلات الواردة المختارة إلى مدفوعة ==========
            if ($totalSelectedIncoming > 0) {
                foreach ($incomingTransfersToUpdate as $transfer) {
                    $transferRemaining = $transfer->transfer_amount - $transfer->paid_amount;

                    // تحديث التحويل إلى مدفوع بالكامل
                    DB::table('salary_transfers')
                        ->where('transfer_id', $transfer->transfer_id)
                        ->update([
                            'paid_amount' => $transfer->transfer_amount, // دفع كامل المبلغ
                            'payment_status' => 'paid',
                            'updated_at' => now(),
                        ]);

                    Log::info('Incoming transfer marked as paid', [
                        'transfer_id' => $transfer->transfer_id,
                        'amount' => $transfer->transfer_amount,
                    ]);

                    // إنشاء إشعار للمدرس المستفيد (المدرس الحالي)
                    $this->createIncomingTransferPaidNotification($transfer, $salary);
                }
            }

            // ========== 10. تحديث حالة البونصات المحددة ==========
            if (! empty($bonusesToUpdate)) {
                foreach ($bonusesToUpdate as $bonus) {
                    DB::table('teacher_adjustments')
                        ->where('id', $bonus->id)
                        ->update([
                            'payment_status' => 'paid',
                            'payment_date' => now(),
                            'paid_by' => Auth::id(),
                            'updated_at' => now(),
                        ]);
                }
            }

            // ========== 11. حساب المبالغ الجديدة وتحديث حالة الراتب ==========
            $new_paid_amount = $paid_amount + $payment_amount;
            $new_remaining_amount = max(0, $max_allowed_payment - $new_paid_amount);

            $new_status = 'partial';
            if ($new_remaining_amount <= 0.01) {
                $new_status = 'paid';
                $new_remaining_amount = 0;
            }

            DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->update([
                    'status' => $new_status,
                    'updated_at' => now(),
                    'payment_date' => now(),
                    'updated_by' => Auth::id(),
                ]);

            // ========== 12. إرسال إشعار للمدرس ==========
            if ($salary->user_id) {
                $notification_msg = 'تم دفع مبلغ '.number_format($payment_amount, 2).
                    ' جنيه من راتبك لشهر '.date('F Y', strtotime($salary->month.'-01')).
                    '. المتبقي: '.number_format($new_remaining_amount, 2).' جنيه';

                if ($totalSelectedBonuses > 0) {
                    $notification_msg .= ' (بونص: '.number_format($totalSelectedBonuses, 2).' جنيه)';
                }
                if ($totalSelectedIncoming > 0) {
                    $notification_msg .= ' (تم استلام '.number_format($totalSelectedIncoming, 2).' جنيه تحويلات واردة من مدرسين آخرين)';
                }
                if ($totalPendingOutgoing > 0) {
                    $notification_msg .= ' (تم خصم '.number_format($totalPendingOutgoing, 2).' جنيه تحويلات صادرة لمدرسين آخرين)';
                }

                \App\Models\Notification::create([
                    'user_id' => $salary->user_id,
                    'title' => 'دفع راتب',
                    'message' => $notification_msg,
                    'type' => 'salary',
                    'related_id' => $salary_id,
                ]);
            }

            DB::commit();

            // ========== 13. إرجاع النتيجة ==========
            $success_message = 'تمت عملية الدفع بنجاح!';
            if ($totalSelectedBonuses > 0) {
                $success_message .= ' (شمل البونصات المحددة)';
            }
            if ($totalSelectedIncoming > 0) {
                $success_message .= ' (تم تحديث حالة '.number_format($totalSelectedIncoming, 2).' جنيه تحويلات واردة إلى مدفوعة)';
            }
            if ($totalPendingOutgoing > 0) {
                $success_message .= ' (تم خصم '.number_format($totalPendingOutgoing, 2).' جنيه تحويلات صادرة)';
            }

            return redirect()->route('salaries.show', $salary_id)->with('success', $success_message);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Payment transaction failed', [
                'salary_id' => $salary_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'خطأ في عملية الدفع: '.$e->getMessage()]);
        }
    }

    /**
     * دالة مساعدة لإنشاء إشعار للمدرس المستفيد من التحويل الوارد
     */
    private function createIncomingTransferPaidNotification($transfer, $salary)
    {
        try {
            $sourceTeacher = DB::table('teachers')->where('teacher_id', $transfer->source_teacher_id)->first();

            if ($salary->user_id) {
                \App\Models\Notification::create([
                    'user_id' => $salary->user_id,
                    'title' => 'تم استلام تحويل وارد',
                    'message' => 'تم استلام مبلغ '.number_format($transfer->transfer_amount, 2).
                               ' جنيه كتحويل وارد من '.($sourceTeacher->teacher_name ?? 'مدرس').
                               ' وتم إضافته إلى راتبك.',
                    'type' => 'salary_transfer',
                    'related_id' => $transfer->transfer_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error creating incoming transfer notification: '.$e->getMessage());
        }
    }

    /**
     * دالة مساعدة لإنشاء إشعارات دفع التحويلات
     */
    /**
     * دالة مساعدة لإنشاء إشعارات دفع التحويلات
     */
    private function createTransferPaymentNotification($transfer, $amount, $type = 'outgoing')
    {
        try {
            if ($type == 'outgoing') {
                // للمدرس المستفيد
                $targetTeacher = DB::table('teachers')->where('teacher_id', $transfer->target_teacher_id)->first();
                $sourceTeacher = DB::table('teachers')->where('teacher_id', $transfer->source_teacher_id)->first();

                if ($targetTeacher && $targetTeacher->user_id) {
                    \App\Models\Notification::create([
                        'user_id' => $targetTeacher->user_id,
                        'title' => 'تم دفع تحويل',
                        'message' => 'تم دفع مبلغ '.number_format($amount, 2).
                                   ' جنيه من تحويل صادر من '.($sourceTeacher->teacher_name ?? 'مدرس').
                                   ' إلى حسابك.',
                        'type' => 'salary_transfer',
                        'related_id' => $transfer->transfer_id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error creating transfer payment notification: '.$e->getMessage());
        }
    }

    /**
     * Process the salary payment (full or partial).
     */
    /**
     * Process the salary payment (full or partial).
     */
    /**
     * Process the salary payment (full or partial).
     */
    /**
     * Process the salary payment (simplified version).
     */

    /**
     * Show the form for creating a new salary record.
     */
    public function create()
    {
        // Get all teachers
        $teachers = DB::table('teachers')
            ->select('teacher_id', 'teacher_name', 'salary_percentage', 'base_salary')
            ->orderBy('teacher_name')
            ->get();

        // Get all groups with course and teacher info
        $groups = DB::table('groups')
            ->select(
                'groups.group_id',
                'groups.group_name',
                'groups.teacher_id',
                'groups.price',
                'groups.teacher_percentage',
                'courses.course_name',
                'teachers.teacher_name'
            )
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->orderBy('groups.group_name')
            ->get();

        return view('salaries.create', compact('teachers', 'groups'));
    }

    /**
     * Store a newly created salary record.
     */
    /**
     * Store a newly created salary record.
     */
    /**
     * Store a newly created salary record.
     */
    public function store(Request $request)
    {
        Log::info('Starting salary store process', $request->all());

        try {
            // التحقق من الصلاحيات
            if (! Auth::check() || ! Auth::user()->isAdminFull()) {
                return redirect()->back()
                    ->with('error', 'ليس لديك صلاحية لإضافة رواتب')
                    ->withInput();
            }

            $request->validate([
                'teacher_id' => 'required|exists:teachers,teacher_id',
                'group_id' => 'required|exists:groups,group_id',
                'month' => 'required|date_format:Y-m',
                'teacher_percentage' => 'required|numeric|min:0|max:100',
                'group_revenue' => 'required|numeric|min:0',
                'teacher_share' => 'required|numeric|min:0',
                'net_salary' => 'required|numeric',
                'status' => 'required|in:pending,partial,paid',
                'bonuses' => 'nullable|numeric|min:0',
                'deductions' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            Log::info('Validation passed', $request->all());

            // التحقق من عدم تكرار الراتب لنفس المدرس والمجموعة والشهر
            $existing = DB::table('salaries')
                ->where('teacher_id', $request->teacher_id)
                ->where('group_id', $request->group_id)
                ->where('month', $request->month)
                ->exists();

            if ($existing) {
                Log::warning('Salary already exists', [
                    'teacher_id' => $request->teacher_id,
                    'group_id' => $request->group_id,
                    'month' => $request->month,
                ]);

                return redirect()->back()
                    ->with('error', 'تم إضافة راتب لهذا المدرس والمجموعة في هذا الشهر بالفعل')
                    ->withInput();
            }

            DB::beginTransaction();

            // إعداد البيانات للإدخال وفقاً لهيكل الجدول
            $salaryData = [
                'teacher_id' => $request->teacher_id,
                'group_id' => $request->group_id,
                'month' => $request->month,
                'group_revenue' => $request->group_revenue,
                'teacher_share' => $request->teacher_share,
                'bonuses' => $request->bonuses ?? 0,
                'deductions' => $request->deductions ?? 0,
                'net_salary' => $request->net_salary,
                'status' => $request->status,
                'notes' => $request->notes,
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // إضافة payment_date فقط إذا كان status = paid
            if ($request->status === 'paid') {
                $salaryData['payment_date'] = now()->toDateString();
            }

            Log::info('Salary data prepared for insertion', $salaryData);

            // إنشاء سجل الراتب
            $salary = \App\Models\Salary::create($salaryData);
            $salaryId = $salary->salary_id;

            Log::info('Salary record created', ['salary_id' => $salaryId]);

            // إذا كانت الحالة "paid" أو "partial"، قم بإضافة سجل دفع
            if (in_array($request->status, ['paid', 'partial'])) {
                $paymentAmount = $request->status === 'paid' ? $request->net_salary : $request->teacher_share;

                \App\Models\TeacherPayment::create([
                    'teacher_id' => $request->teacher_id,
                    'salary_id' => $salaryId,
                    'amount' => $paymentAmount,
                    'payment_method' => 'manual_entry',
                    'payment_date' => now(),
                    'notes' => 'إدخال يدوي للراتب - حالة: '.$request->status.($request->notes ? ' - '.$request->notes : ''),
                    'confirmed_by' => Auth::id(),
                ]);

                Log::info('Payment record created', [
                    'salary_id' => $salaryId,
                    'amount' => $paymentAmount,
                    'status' => $request->status,
                ]);
            }

            DB::commit();

            Log::info('Salary creation completed successfully', ['salary_id' => $salaryId]);

            return redirect()->route('salaries.index')
                ->with('success', 'تم إضافة سجل الراتب بنجاح! رقم الراتب: '.$salaryId);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in salary store: '.json_encode($e->errors()));

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating salary record: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return redirect()->back()
                ->with('error', 'خطأ في إضافة سجل الراتب: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get group details for salary calculation.
     */
    /**
     * Check if salary record already exists.
     */
    public function getGroupDetails(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,group_id',
        ]);

        try {
            $group = DB::table('groups')
                ->select(
                    'groups.group_id',
                    'groups.group_name',
                    'groups.price',
                    'groups.teacher_percentage',
                    'groups.teacher_id',
                    DB::raw('(SELECT COUNT(*) FROM student_group WHERE group_id = groups.group_id) as student_count')
                )
                ->where('group_id', $request->group_id)
                ->first();

            return response()->json([
                'success' => true,
                'group' => $group,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching group details: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error fetching group details',
            ], 500);
        }
    }

    /**
     * Check if salary record already exists.
     */
    public function checkExisting(Request $request)
    {
        try {
            $request->validate([
                'teacher_id' => 'required|exists:teachers,teacher_id',
                'group_id' => 'required|exists:groups,group_id',
                'month' => 'required|date_format:Y-m',
            ]);

            $existing = DB::table('salaries')
                ->where('teacher_id', $request->teacher_id)
                ->where('group_id', $request->group_id)
                ->where('month', $request->month)
                ->exists();

            return response()->json([
                'exists' => $existing,
                'message' => $existing ? 'Salary record already exists for this teacher, group, and month.' : 'No existing record found.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking existing salary: '.$e->getMessage());

            return response()->json([
                'exists' => false,
                'error' => 'Error checking existing record',
            ]);
        }
    }

    /**
     * AJAX method to get groups by teacher
     */
    public function getGroupsByTeacher(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'teacher_id' => 'required|exists:teachers,teacher_id',
            ]);

            Log::info('Fetching groups for teacher:', ['teacher_id' => $request->teacher_id]);

            // Get active groups for the teacher
            $groups = DB::table('groups')
                ->select(
                    'groups.group_id',
                    'groups.group_name',
                    'groups.price',
                    'groups.teacher_percentage',
                    'courses.course_name',
                    'teachers.teacher_name'
                )
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->where('groups.teacher_id', $request->teacher_id)
                // ->where('groups.status', 'active')
                ->orderBy('groups.group_name')
                ->get();

            Log::info('Groups found:', ['count' => $groups->count()]);

            // Return empty array if no groups found instead of error
            return response()->json([
                'success' => true,
                'groups' => $groups,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching groups by teacher: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Error fetching groups: '.$e->getMessage(),
                'groups' => [],
            ], 500);
        }
    }

    /**
     * AJAX method to get group details for salary calculation
     */
    public function getGroupDetailsForSalary(Request $request)
    {
        try {
            $request->validate([
                'group_id' => 'required|exists:groups,group_id',
                'month' => 'required|date_format:Y-m',
            ]);

            $group_id = $request->group_id;
            $month = $request->month;

            Log::info('Getting group details for salary calculation', [
                'group_id' => $group_id,
                'month' => $month,
            ]);

            // الحصول على بيانات المجموعة
            $group = DB::table('groups')
                ->select(
                    'groups.*',
                    'teachers.teacher_id',
                    'teachers.teacher_name',
                    'teachers.salary_percentage',
                    'courses.course_name'
                )
                ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->where('groups.group_id', $group_id)
                ->first();

            if (! $group) {
                return response()->json([
                    'success' => false,
                    'error' => 'Group not found',
                ], 404);
            }

            // حساب عدد الطلاب في المجموعة
            $student_count = (int) DB::table('student_group')
                ->where('group_id', $group_id)
                ->count();

            // الحصول على جميع الفواتير
            $invoices = DB::table('invoices')
                ->where('group_id', $group_id)
                ->get();

            // حساب المدفوعات والخصومات
            $total_amount = (float) $invoices->sum('amount');
            $total_paid = (float) $invoices->whereIn('status', ['paid', 'partial'])->sum('amount_paid');
            $total_discounts = (float) $invoices->sum('discount_amount');

            // استخدام نفس الصيغة المستخدمة في calculateFinalCorrectSalaryValues
            $teacher = DB::table('teachers')->where('teacher_id', $group->teacher_id)->first();
            $current_percentage = (float) ($group->teacher_percentage ?? ($teacher->salary_percentage ?? 0));

            // ************* التصحيح الأساسي هنا *************
            // 1. حساب إيرادات المجموعة = عدد الطلاب × سعر المجموعة
            $group_price = (float) ($group->price ?? 0);
            $group_revenue = $student_count * $group_price;

            // 2. حصة المدرس = إيرادات المجموعة × نسبة المدرس
            // لا نطرح الخصومات لأن الخصومات تكون من حساب المركز، وليس من حصة المدرس
            $teacher_share = $group_revenue * ($current_percentage / 100);

            // 3. المبلغ المتاح للدفع = إجمالي المدفوعات × نسبة المدرس
            $available_payment = $total_paid * ($current_percentage / 100);

            // 4. الراتب الصافي = حصة المدرس (بدون خصومات، الخصومات تكون في ميزانية المركز)
            $net_salary = $teacher_share;

            // إضافة تسجيل للقيم لحل المشكلة
            Log::info('Salary calculation values - CORRECTED', [
                'group_id' => $group_id,
                'student_count' => $student_count,
                'group_price' => $group_price,
                'group_revenue' => $group_revenue,
                'current_percentage' => $current_percentage,
                'teacher_share_calculated' => $teacher_share,
                'formula' => 'teacher_share = group_revenue × percentage',
                'total_discounts' => $total_discounts,
                'note' => 'Discounts not subtracted from teacher share',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'teacher_id' => $group->teacher_id,
                    'teacher_name' => $group->teacher_name,
                    'group_name' => $group->group_name,
                    'course_name' => $group->course_name,
                    'student_count' => $student_count,
                    'group_price' => $group_price,
                    'group_revenue' => (float) $group_revenue,
                    'total_discounts' => (float) $total_discounts,
                    'total_paid_fees' => (float) $total_paid,
                    'teacher_percentage' => (float) $current_percentage,
                    'teacher_share' => (float) $teacher_share, // <-- 3500 وليس 2250
                    'available_payment' => (float) $available_payment,
                    'net_salary' => (float) $net_salary,
                    'bonuses' => 0,
                    'deductions' => 0,
                    'calculation_note' => 'Teacher share = Group revenue × Teacher percentage (Discounts not deducted)',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting group details for salary: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Error fetching group details: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Process salary calculation.
     */
    public function processCalculation(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'teacher_id' => 'nullable|exists:teachers,teacher_id',
        ]);

        $month = $request->month;
        $teacher_id = $request->teacher_id;

        if ($teacher_id) {
            // Calculate salary for specific teacher based on ACTUAL payments
            $teacher_groups = DB::table('groups')
                ->select('groups.*', 'teachers.teacher_name', 'teachers.salary_percentage', 'teachers.base_salary')
                ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->where('groups.teacher_id', $teacher_id)
                ->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$month])
                ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$month])
                ->get();

            $total_revenue = 0;
            $teacher_share = 0;

            foreach ($teacher_groups as $group) {
                // CORRECTED: Calculate ACTUAL paid fees for this group
                $group_paid_fees = DB::table('invoices')
                    ->where('group_id', $group->group_id)
                    ->whereIn('status', ['paid', 'partial'])
                    ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
                    ->sum('amount_paid');

                $total_revenue += $group_paid_fees;

                // Use group-specific percentage or teacher's default
                $percentage = $group->teacher_percentage ?? $group->salary_percentage ?? 0;
                $teacher_share += ($group_paid_fees * $percentage / 100);
            }

            $base_salary = $teacher_groups->first()->base_salary ?? 0;
            $net_salary = $base_salary + $teacher_share;

            // Insert salary record
            \App\Models\Salary::create([
                'teacher_id' => $teacher_id,
                'month' => $month,
                'group_revenue' => $total_revenue, // ACTUAL revenue
                'teacher_share' => $teacher_share, // Based on ACTUAL payments
                'base_salary' => $base_salary,
                'net_salary' => $net_salary,
                'status' => 'pending',
            ]);

            return redirect()->route('salaries.index')->with('message', 'Salary calculated and saved successfully based on ACTUAL payments!');
        } else {
            // Calculate salaries for all teachers based on ACTUAL payments
            $teachers = DB::table('teachers')->get();

            foreach ($teachers as $teacher) {
                $teacher_groups = DB::table('groups')
                    ->select('groups.*', 'teachers.salary_percentage', 'teachers.base_salary')
                    ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                    ->where('groups.teacher_id', $teacher->teacher_id)
                    ->whereRaw('DATE_FORMAT(groups.start_date, "%Y-%m") <= ?', [$month])
                    ->whereRaw('DATE_FORMAT(groups.end_date, "%Y-%m") >= ?', [$month])
                    ->get();

                if ($teacher_groups->isNotEmpty()) {
                    $total_revenue = 0;
                    $teacher_share = 0;

                    foreach ($teacher_groups as $group) {
                        // CORRECTED: Calculate ACTUAL paid fees
                        $group_paid_fees = DB::table('invoices')
                            ->where('group_id', $group->group_id)
                            ->whereIn('status', ['paid', 'partial'])
                            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
                            ->sum('amount_paid');

                        $total_revenue += $group_paid_fees;

                        $percentage = $group->teacher_percentage ?? $group->salary_percentage ?? 0;
                        $teacher_share += ($group_paid_fees * $percentage / 100);
                    }

                    $base_salary = $teacher->base_salary;
                    $net_salary = $base_salary + $teacher_share;

                    // Check if salary already exists
                    $existing = DB::table('salaries')
                        ->where('teacher_id', $teacher->teacher_id)
                        ->where('month', $month)
                        ->first();

                    if ($existing) {
                        // Update existing record with ACTUAL values
                        DB::table('salaries')
                            ->where('salary_id', $existing->salary_id)
                            ->update([
                                'group_revenue' => $total_revenue,
                                'teacher_share' => $teacher_share,
                                'base_salary' => $base_salary,
                                'net_salary' => $net_salary,
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Insert new record with ACTUAL values
                        \App\Models\Salary::create([
                            'teacher_id' => $teacher->teacher_id,
                            'month' => $month,
                            'group_revenue' => $total_revenue,
                            'teacher_share' => $teacher_share,
                            'base_salary' => $base_salary,
                            'net_salary' => $net_salary,
                            'status' => 'pending',
                        ]);
                    }
                }
            }

            return redirect()->route('salaries.index')->with('message', 'All salaries calculated and saved successfully based on ACTUAL payments!');
        }
    }

    /**
     * Show salary invoice.
     */
    public function showInvoice($salary_id)
    {
        // Get salary details with teacher information
        $salary = DB::table('salaries')
            ->select(
                'salaries.*',
                'teachers.teacher_name',
                'teachers.salary_percentage',
                'teachers.base_salary',
                'teachers.bank_account',
                'teachers.payment_method',
                'teachers.user_id',
                'teacher_users.email as teacher_email',
                'users.username as confirmed_by_name'
            )
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->leftJoin('users as teacher_users', 'teachers.user_id', '=', 'teacher_users.id')
            ->leftJoin('users', 'salaries.updated_by', '=', 'users.id')
            ->where('salaries.salary_id', $salary_id)
            ->first();

        if (! $salary) {
            abort(404, 'Salary record not found');
        }

        // Get payment history for this salary
        $payments = DB::table('teacher_payments')
            ->select('teacher_payments.*', 'users.username as confirmed_by_name')
            ->leftJoin('users', 'teacher_payments.confirmed_by', '=', 'users.id')
            ->where('teacher_payments.salary_id', $salary_id)
            ->orderBy('teacher_payments.payment_date', 'desc')
            ->get();

        return view('salaries.show', [
            'salary' => $salary,
            'payments' => $payments
        ]);
    }

    /**
     * تحويل جزء من الراتب إلى مدرس آخر
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح مع إنشاء راتب للمستفيد)
     */

    /**
     * إنشاء أو تحديث راتب للمدرس المستفيد من التحويل
     */

    /**
     * إنشاء إشعارات للمدرسين المعنيين بالتحويل
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح بالكامل)
     */

    /**
     * إنشاء أو تحديث راتب للمدرس المستفيد من التحويل
     */
    private function createOrUpdateSalaryForBeneficiary($originalSalary, $targetTeacher, $transferAmount)
    {
        $month = $originalSalary->month;
        $targetTeacherId = $targetTeacher->teacher_id;

        // البحث عن راتب موجود للمدرس المستفيد لنفس الشهر والمجموعة
        $existingSalary = DB::table('salaries')
            ->where('teacher_id', $targetTeacherId)
            ->where('month', $month)
            ->where('group_id', $originalSalary->group_id)
            ->first();

        if ($existingSalary) {
            // إذا كان موجوداً، نقوم بتحديثه بإضافة المبلغ المحول
            DB::table('salaries')
                ->where('salary_id', $existingSalary->salary_id)
                ->update([
                    'bonuses' => ($existingSalary->bonuses ?? 0) + $transferAmount,
                    'net_salary' => ($existingSalary->net_salary ?? 0) + $transferAmount,
                    'updated_at' => now(),
                    'updated_by' => Auth::id(),
                ]);

            Log::info('Updated existing salary for beneficiary', [
                'teacher_id' => $targetTeacherId,
                'salary_id' => $existingSalary->salary_id,
                'added_amount' => $transferAmount,
            ]);
        } else {
            // إذا لم يكن موجوداً، نقوم بإنشاء سجل جديد
            $newSalaryData = [
                'teacher_id' => $targetTeacherId,
                'group_id' => $originalSalary->group_id,
                'month' => $month,
                'group_revenue' => 0,
                'teacher_share' => 0,
                'bonuses' => $transferAmount,
                'deductions' => 0,
                'net_salary' => $transferAmount,
                'status' => 'pending',
                'notes' => 'راتب ناتج عن تحويل من المدرس '.$originalSalary->teacher_name,
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $newSalary = \App\Models\Salary::create($newSalaryData);
            $newSalaryId = $newSalary->salary_id;

            Log::info('Created new salary for beneficiary', [
                'teacher_id' => $targetTeacherId,
                'salary_id' => $newSalaryId,
                'amount' => $transferAmount,
            ]);
        }
    }

    /**
     * إنشاء إشعارات للمدرسين المعنيين بالتحويل - دالة واحدة فقط
     */

    /**
     * إنشاء إشعارات للمدرسين المعنيين بالتحويل
     */

    /**
     * التحقق من وجود جدول salary_transfers وإنشائه إذا لم يكن موجوداً
     */
    private function ensureSalaryTransfersTable()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('salary_transfers')) {
            \Illuminate\Support\Facades\Schema::create('salary_transfers', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id('transfer_id');
                $table->unsignedBigInteger('original_salary_id');
                $table->unsignedBigInteger('from_teacher_id');
                $table->unsignedBigInteger('to_teacher_id');
                $table->decimal('transfer_amount', 10, 2);
                $table->string('transfer_type')->default('amount'); // amount or percentage
                $table->decimal('percentage', 5, 2)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('transferred_by');
                $table->timestamps();

                $table->foreign('original_salary_id')->references('salary_id')->on('salaries');
                $table->foreign('from_teacher_id')->references('teacher_id')->on('teachers');
                $table->foreign('to_teacher_id')->references('teacher_id')->on('teachers');
                $table->foreign('transferred_by')->references('id')->on('users');
            });
        }
    }

    /**
     * عرض سجل التحويلات
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (بدون خصم من المصدر ولا إضافة بونص)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح لهيكل الجدول)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (محدث)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (تأثير فوري على الراتب المصدر والمستفيد)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (تأثير فوري مع تسجيل دفعة)
     */

    /**
     * دالة مساعدة لإضافة المبلغ المحول للمدرس المستفيد
     */
    private function addTransferAmountToBeneficiary($sourceSalary, $targetTeacher, $amount, $transferId)
    {
        $month = $sourceSalary->month;
        $group_id = $sourceSalary->group_id;

        // البحث عن راتب موجود للمدرس المستفيد لنفس الشهر والمجموعة
        $existingSalary = DB::table('salaries')
            ->where('teacher_id', $targetTeacher->teacher_id)
            ->where('month', $month)
            ->where('group_id', $group_id)
            ->first();

        if ($existingSalary) {
            // إذا كان موجوداً، نضيف المبلغ المحول كـ Bonus
            $newBonuses = ($existingSalary->bonuses ?? 0) + $amount;
            $newNetSalary = $existingSalary->teacher_share + $newBonuses - ($existingSalary->deductions ?? 0);

            DB::table('salaries')
                ->where('salary_id', $existingSalary->salary_id)
                ->update([
                    'bonuses' => $newBonuses,
                    'net_salary' => $newNetSalary,
                    'updated_at' => now(),
                    'updated_by' => Auth::id(),
                ]);

            Log::info('Updated existing salary for beneficiary with bonus', [
                'teacher_id' => $targetTeacher->teacher_id,
                'salary_id' => $existingSalary->salary_id,
                'added_bonus' => $amount,
            ]);
        } else {
            // إذا لم يكن موجوداً، نقوم بإنشاء سجل جديد للمستفيد
            $newSalaryData = [
                'teacher_id' => $targetTeacher->teacher_id,
                'group_id' => $group_id,
                'month' => $month,
                'group_revenue' => 0,
                'teacher_share' => 0,
                'bonuses' => $amount,
                'deductions' => 0,
                'net_salary' => $amount,
                'status' => 'pending',
                'notes' => 'راتب ناتج عن تحويل من المدرس '.$sourceSalary->teacher_name,
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $newSalary = \App\Models\Salary::create($newSalaryData);
            $newSalaryId = $newSalary->salary_id;

            Log::info('Created new salary for beneficiary', [
                'teacher_id' => $targetTeacher->teacher_id,
                'salary_id' => $newSalaryId,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * دالة مساعدة لإنشاء إشعارات التحويل
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح - الحالة pending وبدون دفعة للمصدر)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح بالكامل)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح - الحالة pending)
     */
    /**
     * تحويل جزء من الراتب إلى مدرس آخر (مصحح - الحالة pending)
     */
    public function transferSalary(Request $request, $salary_id)
    {
        // Resolve UUID/ID
        if (! is_numeric($salary_id)) {
            $record = DB::table('salaries')->where('uuid', $salary_id)->first();
            if ($record) {
                $salary_id = $record->salary_id;
            }
        }

        Log::info('=== START transferSalary (Corrected - Pending Status) ===', [
            'raw_salary_id' => $salary_id,
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
        ]);

        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return back()->withErrors(['error' => 'ليس لديك صلاحية للقيام بهذه العملية']);
        }

        $validator = Validator::make($request->all(), [
            'target_teacher_id' => 'required|exists:teachers,teacher_id',
            'transfer_type' => 'required|in:amount,percentage',
            'transfer_amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();
        try {
            // 1. الحصول على الراتب المصدر
            $sourceSalary = DB::table('salaries')
                ->select('salaries.*', 'teachers.teacher_name', 'teachers.user_id as source_teacher_user_id')
                ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
                ->where('salaries.salary_id', $salary_id)
                ->lockForUpdate()
                ->first();

            if (! $sourceSalary) {
                return back()->withErrors(['error' => 'الراتب غير موجود']);
            }

            if ($sourceSalary->teacher_id == $request->target_teacher_id) {
                return back()->withErrors(['error' => 'لا يمكن التحويل لنفس المدرس']);
            }

            // 2. حساب المبلغ المتبقي في الراتب المصدر
            $paidAmount = DB::table('teacher_payments')
                ->where('salary_id', $salary_id)
                ->sum('amount');

            $remainingAmount = $sourceSalary->teacher_share - $paidAmount;
            $transferAmount = floatval($request->transfer_amount);

            if ($transferAmount > $remainingAmount) {
                return back()->withErrors(['error' => 'المبلغ المحول ('.number_format($transferAmount, 2).') أكبر من المتبقي للدفع: '.number_format($remainingAmount, 2).' EGP']);
            }

            $targetTeacher = DB::table('teachers')
                ->where('teacher_id', $request->target_teacher_id)
                ->first(['teacher_id', 'teacher_name', 'user_id']);

            if (! $targetTeacher) {
                throw new \Exception('المدرس المستفيد غير موجود');
            }

            // 3. إنشاء سجل تحويل جديد بحالة PENDING (وليس paid)
            $transferData = [
                'source_salary_id' => $sourceSalary->salary_id,
                'source_teacher_id' => $sourceSalary->teacher_id,
                'target_teacher_id' => $targetTeacher->teacher_id,
                'transfer_amount' => $transferAmount,
                'original_amount' => $transferAmount,
                'transfer_type' => $request->transfer_type,
                'payment_status' => 'pending', // مهم: تكون pending وليس paid
                'paid_amount' => 0, // لم يتم دفع أي شيء بعد
                'notes' => $request->notes ?: 'تحويل من '.$sourceSalary->teacher_name.' إلى '.$targetTeacher->teacher_name,
                'transferred_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $transfer = \App\Models\SalaryTransfer::create($transferData);
            $transferId = $transfer->transfer_id;

            // 4. إضافة المبلغ للمدرس المستفيد (كرصيد مستحق وليس مدفوع)
            $this->addTransferAmountToBeneficiaryAsPending($sourceSalary, $targetTeacher, $transferAmount, $transferId);

            // 5. إنشاء إشعارات (بأن هناك تحويل pending)
            $this->createTransferNotifications($sourceSalary, $targetTeacher, $transferAmount, $transferId, 'pending');

            DB::commit();

            return back()->with('success', 'تم إنشاء طلب تحويل بمبلغ '.number_format($transferAmount, 2).' جنيه من '. $sourceSalary->teacher_name.' إلى '.$targetTeacher->teacher_name.' بنجاح (في انتظار الدفع)');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in transferSalary: '.$e->getMessage());

            return back()->withErrors(['error' => 'حدث خطأ أثناء إنشاء التحويل: '.$e->getMessage()]);
        }
    }

    /**
     * دالة جديدة لإضافة المبلغ المحول للمدرس المستفيد كـ pending (وليس مدفوع)
     */
    /**
     * دالة جديدة لإضافة المبلغ المحول للمدرس المستفيد كـ pending (وليس مدفوع)
     */
    /**
     * دالة جديدة لإضافة المبلغ المحول للمدرس المستفيد كـ pending (وليس مدفوع)
     */
    private function addTransferAmountToBeneficiaryAsPending($sourceSalary, $targetTeacher, $amount, $transferId)
    {
        $month = $sourceSalary->month;
        $group_id = $sourceSalary->group_id;

        // البحث عن راتب موجود للمدرس المستفيد لنفس الشهر والمجموعة
        $existingSalary = DB::table('salaries')
            ->where('teacher_id', $targetTeacher->teacher_id)
            ->where('month', $month)
            ->where('group_id', $group_id)
            ->first();

        if ($existingSalary) {
            // إذا كان موجوداً، نضيف المبلغ المحول كـ "مستحق من تحويلات"
            $newBonuses = ($existingSalary->bonuses ?? 0) + $amount;
            $newNetSalary = $existingSalary->teacher_share + $newBonuses - ($existingSalary->deductions ?? 0);

            DB::table('salaries')
                ->where('salary_id', $existingSalary->salary_id)
                ->update([
                    'bonuses' => $newBonuses,
                    'net_salary' => $newNetSalary,
                    'updated_at' => now(),
                    'updated_by' => Auth::id(),
                ]);

            Log::info('Updated existing salary for beneficiary with pending transfer', [
                'teacher_id' => $targetTeacher->teacher_id,
                'salary_id' => $existingSalary->salary_id,
                'added_pending_amount' => $amount,
                'transfer_id' => $transferId,
            ]);
        } else {
            // إذا لم يكن موجوداً، نقوم بإنشاء سجل جديد للمستفيد
            $newSalaryData = [
                'teacher_id' => $targetTeacher->teacher_id,
                'group_id' => $group_id,
                'month' => $month,
                'group_revenue' => 0,
                'teacher_share' => 0,
                'bonuses' => $amount,
                'deductions' => 0,
                'net_salary' => $amount,
                'status' => 'pending',
                'notes' => 'راتب ناتج عن تحويل pending من المدرس '.$sourceSalary->teacher_name.' (التحويل رقم: '.$transferId.')',
                'updated_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $newSalary = \App\Models\Salary::create($newSalaryData);
            $newSalaryId = $newSalary->salary_id;

            Log::info('Created new salary for beneficiary with pending transfer', [
                'teacher_id' => $targetTeacher->teacher_id,
                'salary_id' => $newSalaryId,
                'amount' => $amount,
                'transfer_id' => $transferId,
            ]);
        }
    }

    /**
     * دالة جديدة لإضافة المبلغ المحول للمدرس المستفيد كـ pending (وليس مدفوع)
     */

    /**
     * تحديث دالة createTransferNotifications لإضافة حالة pending
     */
    /**
     * إنشاء إشعارات للمدرسين المعنيين بالتحويل
     */
    private function createTransferNotifications($sourceSalary, $targetTeacher, $amount, $transferId, $status = 'pending')
    {
        try {
            $monthName = date('F Y', strtotime($sourceSalary->month.'-01'));
            $statusText = ($status == 'pending') ? 'في انتظار الدفع' : 'تم دفعه';

            // إشعار للمدرس المصدر
            if ($sourceSalary->source_teacher_user_id) {
                \App\Models\Notification::create([
                    'user_id' => $sourceSalary->source_teacher_user_id,
                    'title' => 'تحويل راتب صادر',
                    'message' => 'تم إنشاء طلب تحويل بمبلغ '.number_format($amount, 2).
                               ' جنيه من راتبك لشهر '.$monthName.' إلى '.$targetTeacher->teacher_name.
                               ' ('.$statusText.')',
                    'type' => 'salary_transfer',
                    'related_id' => $transferId,
                ]);
            }

            // إشعار للمدرس المستلم
            if ($targetTeacher->user_id) {
                \App\Models\Notification::create([
                    'user_id' => $targetTeacher->user_id,
                    'title' => 'تحويل راتب وارد',
                    'message' => 'تم إنشاء طلب تحويل بمبلغ '.number_format($amount, 2).
                               ' جنيه من '.$sourceSalary->teacher_name.' (راتب شهر '.$monthName.')'.
                               ' ('.$statusText.')',
                    'type' => 'salary_transfer',
                    'related_id' => $transferId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error creating transfer notifications: '.$e->getMessage());
        }
    }

    /**
     * دالة لدفع مبلغ التحويل للمدرس المستفيد
     */
    /**
     * دالة لدفع مبلغ التحويل للمدرس المستفيد
     */
    /**
     * دالة لدفع مبلغ التحويل للمدرس المستفيد
     */
    /**
     * دالة لدفع مبلغ التحويل للمدرس المستفيد
     */
    /**
     * دالة لدفع مبلغ التحويل للمدرس المستفيد
     */
    /**
     * دالة لدفع مبلغ التحويل للمدرس المستفيد (بدون إنشاء سجل في teacher_payments)
     */
    public function payTransferToBeneficiary(Request $request, $transfer_id)
    {
        Log::info('=== START payTransferToBeneficiary (Updated - No teacher_payments) ===', [
            'transfer_id' => $transfer_id,
            'request_data' => $request->all(),
        ]);

        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية للقيام بهذه العملية');
        }

        $validator = Validator::make($request->all(), [
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,vodafone_cash',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'بيانات غير صحيحة');
        }

        DB::beginTransaction();
        try {
            // 1. الحصول على التحويل
            $transfer = DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                return redirect()->back()->with('error', 'التحويل غير موجود');
            }

            if ($transfer->payment_status === 'paid') {
                return redirect()->back()->with('error', 'هذا التحويل مدفوع بالكامل بالفعل');
            }

            $paymentAmount = floatval($request->payment_amount);
            $remainingTransfer = $transfer->transfer_amount - $transfer->paid_amount;

            if ($paymentAmount > $remainingTransfer) {
                return redirect()->back()->with('error', 'المبلغ المدفوع ('.number_format($paymentAmount, 2).') أكبر من المتبقي من التحويل: '.number_format($remainingTransfer, 2));
            }

            // 2. الحصول على المدرس المستفيد والراتب الخاص به
            $targetTeacher = DB::table('teachers')
                ->where('teacher_id', $transfer->target_teacher_id)
                ->first();

            if (! $targetTeacher) {
                throw new \Exception('المدرس المستفيد غير موجود');
            }

            // 3. البحث عن راتب المدرس المستفيد لهذا الشهر
            $sourceSalary = DB::table('salaries')
                ->where('salary_id', $transfer->source_salary_id)
                ->first();

            if (! $sourceSalary) {
                throw new \Exception('الراتب المصدر غير موجود');
            }

            $targetSalary = DB::table('salaries')
                ->where('teacher_id', $transfer->target_teacher_id)
                ->where('month', $sourceSalary->month)
                ->where('group_id', $sourceSalary->group_id)
                ->first();

            if (! $targetSalary) {
                throw new \Exception('لا يوجد راتب للمدرس المستفيد لهذا الشهر');
            }

            // =================== تم إزالة الجزء الخاص بإدراج teacher_payments ===================

            // 4. تحديث حالة التحويل (بدون إنشاء دفعة في teacher_payments)
            $newPaidAmount = $transfer->paid_amount + $paymentAmount;
            $newStatus = 'partial';

            if ($newPaidAmount >= $transfer->transfer_amount) {
                $newStatus = 'paid';
                $newPaidAmount = $transfer->transfer_amount;
            }

            DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'payment_status' => $newStatus,
                    'updated_at' => now(),
                ]);

            // 5. تحديث حالة راتب المستفيد (اختياري، إذا كنت تريد تتبع المبالغ المدفوعة)
            // يمكنك إضافة منطق هنا إذا كان لديك طريقة لتتبع المبالغ المدفوعة للمستفيد من التحويلات
            // على سبيل المثال، قد يكون لديك حقل `received_from_transfers` في جدول `salaries`
            // أو قد تترك هذا للتقرير المالي الشامل.

            DB::commit();

            // ✅ التوجيه إلى صفحة إدارة الراتب الخاصة بالمدرس المستفيد
            return redirect()->route('teachers.salary_management', ['teacher' => $transfer->target_teacher_id])
                ->with('success', 'تم تحديث حالة التحويل: دفع '.number_format($paymentAmount, 2).' جنيه للمدرس '.$targetTeacher->teacher_name.' بنجاح (بدون إنشاء دفعة جديدة).');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in payTransferToBeneficiary: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء الدفع: '.$e->getMessage());
        }
    }

    /**
     * دالة لدفع مبلغ لمدرس بناءً على تحويل
     */
    public function payTransferAmount(Request $request, $transfer_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,vodafone_cash',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'بيانات غير صحيحة',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 1. الحصول على التحويل
            $transfer = DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                return response()->json([
                    'success' => false,
                    'error' => 'التحويل غير موجود',
                ], 404);
            }

            if ($transfer->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'error' => 'هذا التحويل مدفوع بالكامل',
                ], 422);
            }

            $amount = floatval($request->amount);
            $remainingTransfer = $transfer->transfer_amount - $transfer->paid_amount;

            if ($amount > $remainingTransfer) {
                return response()->json([
                    'success' => false,
                    'error' => 'المبلغ يتجاوز المتبقي من التحويل',
                ], 422);
            }

            // 2. تحديث حالة التحويل
            $newPaidAmount = $transfer->paid_amount + $amount;
            $newStatus = 'partial';

            if ($newPaidAmount >= $transfer->transfer_amount) {
                $newStatus = 'paid';
                $newPaidAmount = $transfer->transfer_amount; // للتأكد من عدم تجاوز المبلغ
            }

            DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'payment_status' => $newStatus,
                    'updated_at' => now(),
                ]);

            // 3. إنشاء سجل دفع للمدرس المستهدف (اختياري)
            // يمكنك إضافة سجل في جدول teacher_payments للمدرس المستهدف إذا أردت

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم دفع '.number_format($amount, 2).' جنيه بنجاح',
                'transfer_id' => $transfer_id,
                'paid_amount' => $newPaidAmount,
                'remaining' => $transfer->transfer_amount - $newPaidAmount,
                'payment_status' => $newStatus,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error in payTransferAmount: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'حدث خطأ أثناء الدفع: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * عرض سجل التحويلات مع حالات الدفع
     */
    /**
     * عرض سجل التحويلات مع حالات الدفع
     */
    public function showTransfers()
    {
        $transfers = DB::table('salary_transfers')
            ->select(
                'salary_transfers.*',
                'source_t.teacher_name as source_teacher_name',
                'target_t.teacher_name as target_teacher_name',
                'users.username as transferred_by_name',
                'salaries.month',
                'salaries.teacher_share as source_teacher_share',
                DB::raw('(SELECT SUM(amount) FROM teacher_payments WHERE teacher_payments.salary_id = salaries.salary_id) as source_paid_amount'),
                DB::raw('(SELECT SUM(amount) FROM teacher_payments WHERE teacher_payments.teacher_id = salary_transfers.target_teacher_id AND teacher_payments.payment_date >= salary_transfers.created_at) as target_received_amount')
            )
            ->join('teachers as source_t', 'salary_transfers.source_teacher_id', '=', 'source_t.teacher_id')
            ->join('teachers as target_t', 'salary_transfers.target_teacher_id', '=', 'target_t.teacher_id')
            ->join('salaries', 'salary_transfers.source_salary_id', '=', 'salaries.salary_id')
            ->join('users', 'salary_transfers.transferred_by', '=', 'users.id')
            ->orderBy('salary_transfers.created_at', 'desc')
            ->get();

        return view('salaries.transfers', compact('transfers'));
    }

    /**
     * الحصول على قائمة المدرسين للتحويل
     */
    public function getTeachersForTransfer(Request $request, $salary_id)
    {
        // Resolve UUID/ID
        if (! is_numeric($salary_id)) {
            $record = DB::table('salaries')->where('uuid', $salary_id)->first();
            if ($record) {
                $salary_id = $record->salary_id;
            }
        }

        try {
            Log::info('getTeachersForTransfer called', ['salary_id' => $salary_id]);

            // التحقق من الصلاحيات
            if (! Auth::check() || ! Auth::user()->isAdminFull()) {
                Log::warning('Unauthorized access attempt');

                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access',
                ], 403);
            }

            // التحقق من وجود الراتب
            $salary = DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->first();

            if (! $salary) {
                Log::warning('Salary not found', ['salary_id' => $salary_id]);

                return response()->json([
                    'success' => false,
                    'error' => 'Salary record not found',
                ], 404);
            }

            // الحصول على جميع المدرسين باستثناء المدرس الحالي
            $teachers = DB::table('teachers')
                ->select('teacher_id', 'teacher_name', 'salary_percentage', 'base_salary')
                ->where('teacher_id', '!=', $salary->teacher_id)
                ->orderBy('teacher_name')
                ->get();

            Log::info('Teachers found', ['count' => $teachers->count()]);

            return response()->json([
                'success' => true,
                'teachers' => $teachers,
                'count' => $teachers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getTeachersForTransfer: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Server error: '.$e->getMessage(),
                'teachers' => [],
            ], 500);
        }
    }

    /**
     * الحصول على اسم المدرس
     */
    private function getTeacherName($teacher_id)
    {
        $teacher = DB::table('teachers')
            ->where('teacher_id', $teacher_id)
            ->first();

        return $teacher ? $teacher->teacher_name : 'Unknown Teacher';
    }

    /**
     * Show the add expense form.
     */
    public function addExpense()
    {
        $expense_types = ['Office Supplies', 'Utilities', 'Marketing', 'Equipment', 'Software', 'Training', 'Travel', 'Other'];

        return view('salaries.add-expense', compact('expense_types'));
    }

    /**
     * Store a new expense.
     */
    public function storeExpense(Request $request)
    {
        $request->validate([
            'expense_type' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:500',
            'expense_date' => 'required|date',
        ]);

        Expense::create([
            'category' => $request->expense_type,
            'amount' => $request->amount,
            'description' => $request->description,
            'expense_date' => $request->expense_date,
            'recorded_by' => Auth::id(),
        ]);

        return redirect()->route('expenses.index')->with('message', 'Expense added successfully!');
    }

    /**
     * Display a listing of expenses.
     */
    public function expenses()
    {
        // Get all expenses with user information
        $expenses = DB::table('expenses')
            ->select('expenses.*', 'users.username as recorded_by_name')
            ->leftJoin('users', 'expenses.recorded_by', '=', 'users.id')
            ->orderBy('expenses.expense_date', 'desc')
            ->orderBy('expenses.created_at', 'desc')
            ->get();

        // Calculate totals
        $total_expenses = $expenses->sum('amount');
        $monthly_expenses = $expenses->filter(function ($expense) {
            return date('Y-m', strtotime($expense->expense_date)) == date('Y-m');
        })->sum('amount');

        // Get expenses by type
        $expenses_by_type = [];
        foreach ($expenses as $expense) {
            $type = $expense->category;
            if (! isset($expenses_by_type[$type])) {
                $expenses_by_type[$type] = 0;
            }
            $expenses_by_type[$type] += $expense->amount;
        }

        // Get expense types for filter
        $expense_types = array_unique($expenses->pluck('category')->toArray());

        return view('salaries.expenses', compact('expenses', 'total_expenses', 'monthly_expenses', 'expenses_by_type', 'expense_types'));
    }

    /**
     * التحقق من تطابق بيانات الدفع
     */
    public function verifyPaymentConsistency($salary_id)
    {
        try {
            $salary = DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->first();

            if (! $salary) {
                return ['success' => false, 'error' => 'Salary not found'];
            }

            // 1. التحقق من المدفوعات في teacher_payments
            $total_teacher_payments = DB::table('teacher_payments')
                ->where('salary_id', $salary_id)
                ->sum('amount');

            // 2. الحصول على الفواتير المرتبطة
            $invoices = DB::table('invoices')
                ->where('group_id', $salary->group_id)
                ->get();

            $total_invoice_amount = $invoices->sum('amount');
            $total_invoice_paid = $invoices->sum('amount_paid');
            $total_invoice_discounts = $invoices->sum('discount_amount');

            // 3. الحصول على عدد الطلاب
            $student_count = DB::table('student_group')
                ->where('group_id', $salary->group_id)
                ->count();

            // 4. حساب الإيرادات المتوقعة
            $group = DB::table('groups')
                ->where('group_id', $salary->group_id)
                ->first();

            $expected_revenue = $student_count * $group->price;
            $teacher_percentage = $group->teacher_percentage ?? 0;
            $expected_teacher_share = ($expected_revenue - $total_invoice_discounts) * ($teacher_percentage / 100);

            return [
                'success' => true,
                'data' => [
                    'salary_info' => [
                        'salary_id' => $salary->salary_id,
                        'teacher_share' => $salary->teacher_share,
                        'paid_amount' => $salary->paid_amount ?? 0,
                        'status' => $salary->status,
                    ],
                    'teacher_payments' => [
                        'total' => $total_teacher_payments,
                        'count' => DB::table('teacher_payments')
                            ->where('salary_id', $salary_id)
                            ->count(),
                    ],
                    'invoices_info' => [
                        'total_invoices' => $invoices->count(),
                        'total_amount' => $total_invoice_amount,
                        'total_paid' => $total_invoice_paid,
                        'total_discounts' => $total_invoice_discounts,
                        'pending_invoices' => $invoices->where('status', 'pending')->count(),
                        'partial_invoices' => $invoices->where('status', 'partial')->count(),
                        'paid_invoices' => $invoices->where('status', 'paid')->count(),
                    ],
                    'calculations' => [
                        'student_count' => $student_count,
                        'group_price' => $group->price,
                        'expected_revenue' => $expected_revenue,
                        'teacher_percentage' => $teacher_percentage,
                        'expected_teacher_share' => $expected_teacher_share,
                        'difference' => $salary->teacher_share - $expected_teacher_share,
                    ],
                    'consistency_check' => [
                        'payments_match' => abs($total_teacher_payments - ($salary->paid_amount ?? 0)) < 0.01,
                        'revenue_match' => abs($salary->group_revenue - $expected_revenue) < 0.01,
                        'teacher_share_match' => abs($salary->teacher_share - $expected_teacher_share) < 0.01,
                        'invoice_status_correct' => $this->checkInvoiceStatusConsistency($invoices),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error verifying payment consistency: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * التحقق من تطابق حالة الفواتير مع المدفوعات
     */
    private function checkInvoiceStatusConsistency($invoices)
    {
        $inconsistent = [];

        foreach ($invoices as $invoice) {
            $expected_status = 'pending';

            if ($invoice->amount_paid >= $invoice->amount) {
                $expected_status = 'paid';
            } elseif ($invoice->amount_paid > 0) {
                $expected_status = 'partial';
            }

            if ($invoice->status !== $expected_status) {
                $inconsistent[] = [
                    'invoice_id' => $invoice->invoice_id,
                    'current_status' => $invoice->status,
                    'expected_status' => $expected_status,
                    'amount' => $invoice->amount,
                    'amount_paid' => $invoice->amount_paid,
                ];
            }
        }

        return [
            'is_consistent' => empty($inconsistent),
            'inconsistent_invoices' => $inconsistent,
        ];
    }

    /**
     * إصلاح بيانات الدفع غير المتطابقة
     */
    public function fixPaymentData($salary_id)
    {
        try {
            DB::beginTransaction();

            $salary = DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->first();

            if (! $salary) {
                return ['success' => false, 'error' => 'Salary not found'];
            }

            // 1. تحديث حالة الفواتير بناءً على amount_paid
            $invoices = DB::table('invoices')
                ->where('group_id', $salary->group_id)
                ->get();

            foreach ($invoices as $invoice) {
                $new_status = 'pending';

                if ($invoice->amount_paid >= $invoice->amount) {
                    $new_status = 'paid';
                } elseif ($invoice->amount_paid > 0) {
                    $new_status = 'partial';
                }

                if ($invoice->status !== $new_status) {
                    DB::table('invoices')
                        ->where('invoice_id', $invoice->invoice_id)
                        ->update(['status' => $new_status]);
                }
            }

            // 2. إعادة حساب الراتب بناءً على الفواتير المحدثة
            $recalculated_values = $this->recalculateSalaryFromInvoices($salary_id);

            // 3. تحديث سجل الراتب
            DB::table('salaries')
                ->where('salary_id', $salary_id)
                ->update([
                    'group_revenue' => $recalculated_values['group_revenue'],
                    'teacher_share' => $recalculated_values['teacher_share'],
                    'net_salary' => $recalculated_values['teacher_share'],
                    'updated_at' => now(),
                ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'تم إصلاح بيانات الدفع بنجاح',
                'updated_values' => $recalculated_values,
            ];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error fixing payment data: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * إعادة حساب الراتب من الفواتير
     */
    private function recalculateSalaryFromInvoices($salary_id)
    {
        $salary = DB::table('salaries')
            ->where('salary_id', $salary_id)
            ->first();

        $group = DB::table('groups')
            ->where('group_id', $salary->group_id)
            ->first();

        // الحصول على بيانات الفواتير
        $invoices = DB::table('invoices')
            ->where('group_id', $salary->group_id)
            ->get();

        // حساب القيم الجديدة
        $student_count = DB::table('student_group')
            ->where('group_id', $salary->group_id)
            ->count();

        $group_revenue = $student_count * $group->price;
        $total_discounts = $invoices->sum('discount_amount');
        $total_paid = $invoices->whereIn('status', ['paid', 'partial'])->sum('amount_paid');

        $teacher = DB::table('teachers')->where('teacher_id', $salary->teacher_id)->first();
        $teacher_percentage = $group->teacher_percentage ?? $teacher->salary_percentage ?? 0;

        $teacher_share = ($group_revenue - $total_discounts) * ($teacher_percentage / 100);

        return [
            'student_count' => $student_count,
            'group_revenue' => $group_revenue,
            'total_discounts' => $total_discounts,
            'total_paid' => $total_paid,
            'teacher_percentage' => $teacher_percentage,
            'teacher_share' => $teacher_share,
        ];
    }

    private function updateInvoiceStatusesForGroup($group_id)
    {
        $invoices = DB::table('invoices')
            ->where('group_id', $group_id)
            ->get();

        foreach ($invoices as $invoice) {
            $new_status = 'pending';

            if ($invoice->amount_paid >= $invoice->amount) {
                $new_status = 'paid';
            } elseif ($invoice->amount_paid > 0) {
                $new_status = 'partial';
            }

            DB::table('invoices')
                ->where('invoice_id', $invoice->invoice_id)
                ->update(['status' => $new_status]);
        }
    }
    // =============================================
    // ⬇️⬇️⬇️ دوال التحويلات الجديدة (Edit, Update, Delete, Confirm) ⬇️⬇️⬇️
    // =============================================

    /**
     * Show the form for editing a transfer.
     *
     * @param  int  $transfer_id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function editTransfer($transfer_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return redirect()->route('salaries.transfers')->with('error', 'ليس لديك صلاحية للقيام بهذه العملية.');
        }

        // جلب بيانات التحويل مع أسماء المدرسين
        $transfer = DB::table('salary_transfers')
            ->select(
                'salary_transfers.*',
                'source_t.teacher_name as source_teacher_name',
                'target_t.teacher_name as target_teacher_name',
                'salaries.month',
                'salaries.group_id'
            )
            ->join('teachers as source_t', 'salary_transfers.source_teacher_id', '=', 'source_t.teacher_id')
            ->join('teachers as target_t', 'salary_transfers.target_teacher_id', '=', 'target_t.teacher_id')
            ->join('salaries', 'salary_transfers.source_salary_id', '=', 'salaries.salary_id')
            ->where('salary_transfers.transfer_id', $transfer_id)
            ->first();

        if (! $transfer) {
            return redirect()->route('salaries.transfers')->with('error', 'التحويل غير موجود.');
        }

        // لا نسمح بتعديل التحويلات المدفوعة بالكامل
        if ($transfer->payment_status === 'paid') {
            return redirect()->route('salaries.transfers')->with('error', 'لا يمكن تعديل تحويل تم دفعه بالكامل.');
        }

        // جلب قائمة المدرسين (باستثناء المصدر) لتحديث المدرس المستفيد إذا لزم الأمر
        $teachers = DB::table('teachers')
            ->select('teacher_id', 'teacher_name')
            ->where('teacher_id', '!=', $transfer->source_teacher_id)
            ->orderBy('teacher_name')
            ->get();

        return view('salaries.edit-transfer', compact('transfer', 'teachers'));
    }

    /**
     * Update the specified transfer.
     *
     * @param  int  $transfer_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTransfer(Request $request, $transfer_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json(['success' => false, 'error' => 'ليس لديك صلاحية.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'target_teacher_id' => 'required|exists:teachers,teacher_id',
            'transfer_amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'بيانات غير صحيحة', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $transfer = DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                return response()->json(['success' => false, 'error' => 'التحويل غير موجود.'], 404);
            }

            if ($transfer->payment_status === 'paid') {
                return response()->json(['success' => false, 'error' => 'لا يمكن تعديل تحويل تم دفعه بالكامل.'], 422);
            }

            // لا يمكن تغيير المدرس المصدر
            if ($transfer->source_teacher_id == $request->target_teacher_id) {
                return response()->json(['success' => false, 'error' => 'لا يمكن تحويل لنفس المدرس.'], 422);
            }

            // التحقق من أن المبلغ المعدل لا يقل عن المبلغ المدفوع بالفعل
            if ($request->transfer_amount < $transfer->paid_amount) {
                return response()->json([
                    'success' => false,
                    'error' => 'لا يمكن تقليل المبلغ الكلي للتحويل إلى أقل من المبلغ المدفوع بالفعل ('.number_format($transfer->paid_amount, 2).' EGP).',
                ], 422);
            }

            // تحديث التحويل
            DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->update([
                    'target_teacher_id' => $request->target_teacher_id,
                    'transfer_amount' => $request->transfer_amount,
                    'original_amount' => $request->transfer_amount, // قد تحتاج لتحديث هذا الحقل أيضًا
                    'notes' => $request->notes,
                    'updated_at' => now(),
                ]);

            // تحديث حالة الدفع بناءً على المبلغ الجديد
            $newPaymentStatus = 'pending';
            if ($transfer->paid_amount >= $request->transfer_amount) {
                $newPaymentStatus = 'paid';
            } elseif ($transfer->paid_amount > 0) {
                $newPaymentStatus = 'partial';
            }

            DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->update(['payment_status' => $newPaymentStatus]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث التحويل بنجاح.',
                'transfer_id' => $transfer_id,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating transfer: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified transfer.
     *
     * @param  int  $transfer_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyTransfer($transfer_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json(['success' => false, 'error' => 'ليس لديك صلاحية.'], 403);
        }

        DB::beginTransaction();
        try {
            $transfer = DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->first();

            if (! $transfer) {
                return response()->json(['success' => false, 'error' => 'التحويل غير موجود.'], 404);
            }

            // لا نسمح بحذف التحويلات المدفوعة
            if ($transfer->payment_status === 'paid') {
                return response()->json(['success' => false, 'error' => 'لا يمكن حذف تحويل تم دفعه بالكامل.'], 422);
            }

            // يمكننا إضافة شرط لمنع الحذف إذا كان هناك مدفوعات جزئية أيضًا، لكننا سنسمح به
            // مع حذف أي إشعارات مرتبطة بهذا التحويل (اختياري)
            DB::table('notifications')
                ->where('type', 'salary_transfer')
                ->where('related_id', $transfer_id)
                ->delete();

            // حذف التحويل
            DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف التحويل بنجاح.',
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting transfer: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    /**
     * Confirm payment for a transfer (Mark as fully paid).
     *
     * @param  int  $transfer_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmTransferPayment(Request $request, $transfer_id)
    {
        if (! Auth::check() || ! Auth::user()->isAdminFull()) {
            return response()->json(['success' => false, 'error' => 'ليس لديك صلاحية.'], 403);
        }

        DB::beginTransaction();
        try {
            $transfer = DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                return response()->json(['success' => false, 'error' => 'التحويل غير موجود.'], 404);
            }

            if ($transfer->payment_status === 'paid') {
                return response()->json(['success' => false, 'error' => 'التحويل مدفوع بالكامل بالفعل.'], 422);
            }

            $remainingAmount = $transfer->transfer_amount - $transfer->paid_amount;

            // تحديث حالة التحويل إلى مدفوع بالكامل
            DB::table('salary_transfers')
                ->where('transfer_id', $transfer_id)
                ->update([
                    'paid_amount' => $transfer->transfer_amount, // تعيين المبلغ المدفوع ليطابق المبلغ الكلي
                    'payment_status' => 'paid',
                    'updated_at' => now(),
                ]);

            // اختياري: إنشاء سجل دفع في جدول teacher_payments للمدرس المستفيد
            // هذا يساعد في تتبع المبالغ المدفوعة فعليًا للمدرسين
            $targetTeacher = DB::table('teachers')->where('teacher_id', $transfer->target_teacher_id)->first();

            // نحتاج لمعرفة salary_id للمدرس المستفيد لهذا الشهر. هذا قد يكون معقدًا.
            // تبسيطًا: نقوم بإنشاء سجل دفع مرتبط بالتحويل نفسه (بدون salary_id)
            // أو نترك هذا الجزء للمراجعة المالية الشهرية.

            // تحديث الإشعارات (اختياري)
            DB::table('notifications')
                ->where('type', 'salary_transfer')
                ->where('related_id', $transfer_id)
                ->update(['is_read' => 1]); // تعليمها كمقروءة

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد دفع التحويل بالكامل بنجاح.',
                'transfer_id' => $transfer_id,
                'amount' => $transfer->transfer_amount,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error confirming transfer payment: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => 'حدث خطأ: '.$e->getMessage()], 500);
        }
    }

    // =============================================
    // ⬆️⬆️⬆️ نهاية دوال التحويلات الجديدة ⬆️⬆️⬆️
    // =============================================

    /**
     * Get notification URLs for salary payment
     */
    public function notify(Request $request, $salary_id)
    {
        // Resolve UUID to integer ID if needed
        if (!is_numeric($salary_id)) {
            $resolved_id = DB::table('salaries')->where('uuid', $salary_id)->value('salary_id');
            if ($resolved_id) $salary_id = $resolved_id;
        }

        $salary = \App\Models\Salary::with('teacher.user.profile')->find($salary_id);
        if (!$salary) {
            return response()->json(['success' => false, 'error' => 'Salary not found'], 404);
        }

        // We need to ensure we have the paid amount from payments table if model doesn't have it
        $payments = DB::table('teacher_payments')->where('salary_id', $salary_id)->get();
        $salary->paid_amount = $payments->sum('amount');

        $notificationService = app(\App\Services\NotificationService::class);
        
        return response()->json([
            'success' => true,
            'whatsapp_url' => $notificationService->getSalaryWhatsAppUrl($salary),
            'email_url' => $notificationService->getSalaryEmailUrl($salary),
            'slip_url' => route('salaries.public_slip', $salary->public_token)
        ]);
    }

    /**
     * Public Salary Slip View
     */
    public function publicSlip($token)
    {
        $salary = \App\Models\Salary::where('public_token', $token)
            ->with(['teacher.user.profile', 'group'])
            ->firstOrFail();

        // Calculate values for display
        $calculatedValues = $this->calculateFinalCorrectSalaryValues($salary);
        $salary->total_paid = DB::table('teacher_payments')->where('salary_id', $salary->salary_id)->sum('amount');
        
        // Detailed breakdown
        $bonuses = DB::table('teacher_adjustments')
            ->where('teacher_id', $salary->teacher_id)
            ->where('salary_id', $salary->salary_id)
            ->where('type', 'bonus')
            ->get();
            
        $deductions = DB::table('teacher_adjustments')
            ->where('teacher_id', $salary->teacher_id)
            ->where('salary_id', $salary->salary_id)
            ->where('type', 'deduction')
            ->get();

        $incomingTransfers = DB::table('salary_transfers')
            ->join('teachers', 'salary_transfers.source_teacher_id', '=', 'teachers.teacher_id')
            ->where('target_teacher_id', $salary->teacher_id)
            ->where('payment_status', 'paid')
            ->get();

        return view('salaries.slip', compact('salary', 'calculatedValues', 'bonuses', 'deductions', 'incomingTransfers'));
    }
}
