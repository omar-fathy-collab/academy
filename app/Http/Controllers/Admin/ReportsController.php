<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Expense;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Salary;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAdjustment; // أضف هذا السطر أيضاً
use Carbon\Carbon; // أضف هذا السطر
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->isAdmin()) {
                return redirect('/unauthorized');
            }

            return $next($request);
        });
    }

    /**
     * Display the main reports dashboard
     */
    public function index()
    {
        // Get revenue data for the last 12 months
        $revenueData = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as month, SUM(amount) as amount')
            ->whereRaw('payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Get groups data
        $groupsData = Group::select('groups.group_name as name')
            ->selectRaw('COUNT(student_group.student_id) as students')
            ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
            ->groupBy('groups.group_id', 'groups.group_name')
            ->orderByDesc('students')
            ->limit(5)
            ->get();

        // Calculate stats
        $totalRevenue = Payment::whereRaw('payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->sum('amount');
        $totalExpenses = Expense::whereRaw('expense_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->sum('amount');
        $totalStudents = Student::count();
        $activeStudents = Student::whereHas('groups', function ($q) {
            $q->where('end_date', '>=', now());
        })->count();
        $averageScore = QuizAttempt::avg('score') ?? 0;
        $attendanceRate = DB::table('attendance')
            ->whereRaw('recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->selectRaw('AVG(CASE WHEN status IN ("present", "late") THEN 1 ELSE 0 END) * 100 as rate')
            ->first()->rate ?? 0;

        // Get monthly stats for the last 12 months
        $monthlyStats = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $monthlyStats->push([
                'month' => $date->format('M Y'),
                'revenue' => Payment::whereBetween('payment_date', [$startOfMonth, $endOfMonth])->sum('amount'),
                'expenses' => Expense::whereBetween('expense_date', [$startOfMonth, $endOfMonth])->sum('amount'),
                'enrollments' => DB::table('student_group')
                    ->whereBetween('enrollment_date', [$startOfMonth, $endOfMonth])
                    ->count(),
                'attendance_rate' => DB::table('attendance')
                    ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
                    ->selectRaw('AVG(CASE WHEN status IN ("present", "late") THEN 1 ELSE 0 END) * 100 as rate')
                    ->first()->rate ?? 0,
                'success_rate' => QuizAttempt::whereBetween('start_time', [$startOfMonth, $endOfMonth])
                    ->where('status', 'graded')
                    ->where('score', '>=', 60)
                    ->count() / max(1, QuizAttempt::whereBetween('start_time', [$startOfMonth, $endOfMonth])
                    ->where('status', 'graded')
                    ->count()) * 100,
            ]);
        }

        return view('reports.index', [
            'revenueData' => $revenueData,
            'groupsData' => $groupsData,
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'totalStudents' => $totalStudents,
            'activeStudents' => $activeStudents,
            'averageScore' => $averageScore,
            'attendanceRate' => $attendanceRate,
            'monthlyStats' => $monthlyStats
        ]);
    }

    /**
     * Filter reports data based on date range and type
     */
    public function filter(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $type = $request->input('report_type');

        // Get filtered revenue data
        $revenueData = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as month, SUM(amount) as amount')
            ->when($startDate, fn ($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('payment_date', '<=', $endDate))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Get filtered groups data
        $groupsData = Group::select('groups.group_name as name')
            ->selectRaw('COUNT(student_group.student_id) as students')
            ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
            ->when($startDate, fn ($q) => $q->where('student_group.enrollment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('student_group.enrollment_date', '<=', $endDate))
            ->groupBy('groups.group_id', 'groups.group_name')
            ->orderByDesc('students')
            ->limit(5)
            ->get();

        // Calculate filtered stats
        $totalRevenue = Payment::when($startDate, fn ($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('payment_date', '<=', $endDate))
            ->sum('amount');

        $totalStudents = Student::when($startDate, function ($q) use ($startDate) {
            $q->whereHas('groups', fn ($sq) => $sq->where('enrollment_date', '>=', $startDate));
        })->when($endDate, function ($q) use ($endDate) {
            $q->whereHas('groups', fn ($sq) => $sq->where('enrollment_date', '<=', $endDate));
        })->count();

        $averageScore = QuizAttempt::when($startDate, fn ($q) => $q->where('start_time', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('start_time', '<=', $endDate))
            ->avg('score') ?? 0;

        $attendanceRate = DB::table('attendance')
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('date', '<=', $endDate))
            ->avg('status') * 100 ?? 0;

        // Get expenses data
        $expensesData = Expense::selectRaw('DATE_FORMAT(expense_date, "%Y-%m") as month, SUM(amount) as amount')
            ->when($startDate, fn ($q) => $q->where('expense_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('expense_date', '<=', $endDate))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'stats' => [
                'totalRevenue' => $totalRevenue,
                'totalStudents' => $totalStudents,
                'averageScore' => $averageScore,
                'attendanceRate' => $attendanceRate,
            ],
            'revenue' => [
                'labels' => $revenueData->pluck('month')->map(function ($month) {
                    return Carbon::createFromFormat('Y-m', $month)->format('M Y');
                }),
                'data' => $revenueData->pluck('amount'),
            ],
            'expenses' => [
                'data' => $expensesData->pluck('amount'),
            ],
            'groups' => [
                'labels' => $groupsData->pluck('name'),
                'data' => $groupsData->pluck('students'),
            ],
        ]);
    }

    /**
     * Display financial reports
     */
    private function getDetailedInvoices($startDate, $endDate)
    {
        return Invoice::select(
            'invoices.invoice_id',
            'invoices.invoice_number',
            'invoices.amount',
            'invoices.amount_paid',
            'invoices.discount_amount',
            'invoices.status',
            'invoices.due_date',
            'students.student_id',
            'users.username as student_name',
            'groups.group_name',
            'courses.course_name'
        )
            ->join('students', 'invoices.student_id', '=', 'students.student_id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('groups', 'invoices.group_id', '=', 'groups.group_id')
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->orderBy('invoices.created_at', 'desc')
            ->get();
    }

    public function financial(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $reportType = $request->get('report_type', 'summary');
        $date = $request->get('date', date('Y-m-d'));

        // Determine date range based on period
        [$startDate, $endDate] = $this->getDateRangeByPeriod($period, $date, $request);

        // Initialize data arrays
        $dailyData = [];
        $weeklyData = [];
        $monthlyData = [];
        $detailedData = [];

        // Get data based on report type
        switch ($reportType) {
            case 'daily':
                $dailyData = $this->getDailyFinancialData($startDate, $endDate);
                break;

            case 'weekly':
                $weeklyData = $this->getWeeklyFinancialData($startDate, $endDate);
                break;

            case 'monthly':
                $monthlyData = $this->getMonthlyFinancialData($startDate, $endDate);
                break;

            case 'detailed':
                $detailedData = $this->getDetailedFinancialReport($startDate, $endDate);
                break;

            case 'summary':
            default:
                // Get all data for summary
                $dailyData = $this->getDailyFinancialData($startDate, $endDate);
                $weeklyData = $this->getWeeklyFinancialData($startDate, $endDate);
                $monthlyData = $this->getMonthlyFinancialData($startDate, $endDate);
                break;
        }

        // Get summary statistics
        $summary = $this->getFinancialSummary($startDate, $endDate);

        // Get top revenue sources
        $topCourses = $this->getTopRevenueSources($startDate, $endDate);

        return view('reports.financial', [
            'period' => $period,
            'reportType' => $reportType,
            'date' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dailyData' => $dailyData,
            'weeklyData' => $weeklyData,
            'monthlyData' => $monthlyData,
            'detailedData' => $detailedData,
            'summary' => $summary,
            'topCourses' => $topCourses
        ]);
    }

    private function getDateRangeByPeriod($period, $date, $request = null)
    {
        $carbonDate = Carbon::parse($date);

        switch ($period) {
            case 'daily':
                $startDate = $carbonDate->copy()->startOfDay();
                $endDate = $carbonDate->copy()->endOfDay();
                break;

            case 'weekly':
                $startDate = $carbonDate->copy()->startOfWeek();
                $endDate = $carbonDate->copy()->endOfWeek();
                break;

            case 'monthly':
                $startDate = $carbonDate->copy()->startOfMonth();
                $endDate = $carbonDate->copy()->endOfMonth();
                break;

            case 'yearly':
                $startDate = $carbonDate->copy()->startOfYear();
                $endDate = $carbonDate->copy()->endOfYear();
                break;

            case 'custom':
                // تأكد من وجود الـ request ووجود التواريخ
                if ($request && $request->has(['start_date', 'end_date'])) {
                    $startDate = Carbon::parse($request->get('start_date'))->startOfDay();
                    $endDate = Carbon::parse($request->get('end_date'))->endOfDay();
                } else {
                    // إذا لم توجد تواريخ مخصصة، استخدم الشهر الحالي
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                }
                break;

            default:
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
        }

        return [$startDate, $endDate];
    }

    private function getMonthlyFinancialData($startDate, $endDate)
    {
        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $monthStart = $currentDate->copy()->startOfMonth();
            $monthEnd = $currentDate->copy()->endOfMonth();

            // Revenue from payments
            $revenue = Payment::whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount');

            // Revenue from invoices
            $invoiceRevenue = Invoice::whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('status', 'paid')
                ->sum('amount');

            // Expenses
            $expenses = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])
                ->sum('amount');

            // Teacher salaries
            $salaries = Salary::whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('teacher_share');

            // Teacher adjustments
            $adjustments = TeacherAdjustment::whereBetween('adjustment_date', [$monthStart, $monthEnd])
                ->sum('amount');

            // Calculate totals
            $totalRevenue = $revenue + $invoiceRevenue;
            $totalExpenses = $expenses + $salaries;
            $netProfit = $totalRevenue - $totalExpenses;

            $data[] = [
                'month' => $currentDate->format('Y-m'),
                'display_month' => $currentDate->format('M Y'),
                'revenue' => $revenue,
                'invoice_revenue' => $invoiceRevenue,
                'total_revenue' => $totalRevenue,
                'expenses' => $expenses,
                'salaries' => $salaries,
                'adjustments' => $adjustments,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'days_in_month' => $currentDate->daysInMonth,
            ];

            $currentDate->addMonth();
        }

        return $data;
    }

    private function getDetailedFinancialReport($startDate, $endDate)
    {
        return [
            'payments' => $this->getDetailedPayments($startDate, $endDate),
            'invoices' => $this->getDetailedInvoices($startDate, $endDate),
            'expenses' => $this->getDetailedExpenses($startDate, $endDate),
            'salaries' => $this->getDetailedSalaries($startDate, $endDate),
            'adjustments' => $this->getDetailedAdjustments($startDate, $endDate),
        ];
    }

    private function getDetailedPayments($startDate, $endDate)
    {
        return Payment::select(
            'payments.payment_id',
            'payments.amount',
            'payments.payment_date',
            'payments.payment_method',
            'payments.status',
            'invoices.invoice_number',
            'students.student_id',
            'users.username as student_name'
        )
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.invoice_id')
            ->join('students', 'invoices.student_id', '=', 'students.student_id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->orderBy('payments.payment_date', 'desc')
            ->get();
    }

    private function getDetailedSalaries($startDate, $endDate)
    {
        return Salary::select(
            'salaries.salary_id',
            'salaries.teacher_share',
            'salaries.net_salary',
            'salaries.deductions',
            'salaries.bonuses',
            'salaries.status',
            'salaries.payment_date',
            'teachers.teacher_name',
            'groups.group_name',
            'courses.course_name'
        )
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id')
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->whereBetween('salaries.payment_date', [$startDate, $endDate])
            ->orderBy('salaries.payment_date', 'desc')
            ->get();
    }

    private function getDetailedAdjustments($startDate, $endDate)
    {
        return TeacherAdjustment::select(
            'teacher_adjustments.id',
            'teacher_adjustments.description',
            'teacher_adjustments.amount',
            'teacher_adjustments.type',
            'teacher_adjustments.adjustment_date',
            'teachers.teacher_name'
        )
            ->join('teachers', 'teacher_adjustments.teacher_id', '=', 'teachers.teacher_id')
            ->whereBetween('teacher_adjustments.adjustment_date', [$startDate, $endDate])
            ->orderBy('teacher_adjustments.adjustment_date', 'desc')
            ->get();
    }

    private function getFinancialSummary($startDate, $endDate)
    {
        // Total revenue from payments
        $totalRevenue = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        // Total revenue from invoices
        $totalInvoiceRevenue = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('amount');

        // Total expenses
        $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // Total salaries
        $totalSalaries = Salary::whereBetween('payment_date', [$startDate, $endDate])
            ->sum('teacher_share');

        // Total adjustments
        $totalAdjustments = TeacherAdjustment::whereBetween('adjustment_date', [$startDate, $endDate])
            ->sum('amount');

        // Calculate totals
        $grandTotalRevenue = $totalRevenue + $totalInvoiceRevenue;
        $grandTotalExpenses = $totalExpenses + $totalSalaries;
        $netProfit = $grandTotalRevenue - $grandTotalExpenses;
        $profitMargin = $grandTotalRevenue > 0 ? ($netProfit / $grandTotalRevenue) * 100 : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_invoice_revenue' => $totalInvoiceRevenue,
            'grand_total_revenue' => $grandTotalRevenue,
            'total_expenses' => $totalExpenses,
            'total_salaries' => $totalSalaries,
            'total_adjustments' => $totalAdjustments,
            'grand_total_expenses' => $grandTotalExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
            'period_days' => $startDate->diffInDays($endDate) + 1,
        ];
    }

    private function getTopRevenueSources($startDate, $endDate)
    {
        return Course::select(
            'courses.course_id',
            'courses.course_name',
            DB::raw('SUM(payments.amount) as revenue'),
            DB::raw('COUNT(DISTINCT payments.payment_id) as payment_count')
        )
            ->join('groups', 'courses.course_id', '=', 'groups.course_id')
            ->join('invoices', 'groups.group_id', '=', 'invoices.group_id')
            ->join('payments', 'invoices.invoice_id', '=', 'payments.invoice_id')
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->groupBy('courses.course_id', 'courses.course_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
    }

    public function exportFinancialExcel(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $reportType = $request->get('report_type', 'summary');
        $date = $request->get('date', date('Y-m-d'));

        [$startDate, $endDate] = $this->getDateRangeByPeriod($period, $date);

        $data = [];
        $fileName = '';

        switch ($reportType) {
            case 'daily':
                $data = $this->getDailyFinancialData($startDate, $endDate);
                $fileName = 'daily_financial_report_'.$startDate->format('Y_m_d').'_to_'.$endDate->format('Y_m_d').'.xlsx';
                break;

            case 'weekly':
                $data = $this->getWeeklyFinancialData($startDate, $endDate);
                $fileName = 'weekly_financial_report_'.$startDate->format('Y_m_d').'_to_'.$endDate->format('Y_m_d').'.xlsx';
                break;

            case 'monthly':
                $data = $this->getMonthlyFinancialData($startDate, $endDate);
                $fileName = 'monthly_financial_report_'.$startDate->format('Y_m').'_to_'.$endDate->format('Y_m').'.xlsx';
                break;

            case 'detailed':
                $detailedData = $this->getDetailedFinancialReport($startDate, $endDate);
                $data = $this->formatDetailedDataForExcel($detailedData);
                $fileName = 'detailed_financial_report_'.$startDate->format('Y_m_d').'_to_'.$endDate->format('Y_m_d').'.xlsx';
                break;
        }

        return Excel::download(new class($data, $reportType) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
        {
            protected $data;

            protected $reportType;

            public function __construct($data, $reportType)
            {
                $this->data = $data;
                $this->reportType = $reportType;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                if ($this->reportType === 'daily') {
                    return ['Date', 'Day', 'Revenue', 'Invoice Revenue', 'Total Revenue', 'Expenses', 'Salaries', 'Adjustments', 'Total Expenses', 'Net Profit'];
                } elseif ($this->reportType === 'weekly') {
                    return ['Week', 'Week Start', 'Week End', 'Revenue', 'Invoice Revenue', 'Total Revenue', 'Expenses', 'Salaries', 'Adjustments', 'Total Expenses', 'Net Profit'];
                } elseif ($this->reportType === 'monthly') {
                    return ['Month', 'Revenue', 'Invoice Revenue', 'Total Revenue', 'Expenses', 'Salaries', 'Adjustments', 'Total Expenses', 'Net Profit', 'Days in Month'];
                } else {
                    return array_keys(reset($this->data));
                }
            }
        }, $fileName);
    }

    private function formatDetailedDataForExcel($detailedData)
    {
        $formattedData = [];

        // Add payments
        foreach ($detailedData['payments'] as $payment) {
            $formattedData[] = [
                'Type' => 'Payment',
                'ID' => $payment->payment_id,
                'Date' => $payment->payment_date,
                'Amount' => $payment->amount,
                'Method' => $payment->payment_method,
                'Status' => $payment->status,
                'Invoice' => $payment->invoice_number,
                'Student' => $payment->student_name,
            ];
        }

        // Add invoices
        foreach ($detailedData['invoices'] as $invoice) {
            $formattedData[] = [
                'Type' => 'Invoice',
                'ID' => $invoice->invoice_id,
                'Number' => $invoice->invoice_number,
                'Amount' => $invoice->amount,
                'Paid' => $invoice->amount_paid,
                'Discount' => $invoice->discount_amount,
                'Status' => $invoice->status,
                'Due Date' => $invoice->due_date,
                'Student' => $invoice->student_name,
                'Group' => $invoice->group_name,
                'Course' => $invoice->course_name,
            ];
        }

        // Add expenses
        foreach ($detailedData['expenses'] as $expense) {
            $formattedData[] = [
                'Type' => 'Expense',
                'ID' => $expense->expense_id,
                'Date' => $expense->expense_date,
                'Category' => $expense->category,
                'Description' => $expense->description,
                'Amount' => $expense->amount,
                'Approved' => $expense->is_approved ? 'Yes' : 'No',
            ];
        }

        // Add salaries
        foreach ($detailedData['salaries'] as $salary) {
            $formattedData[] = [
                'Type' => 'Salary',
                'ID' => $salary->salary_id,
                'Date' => $salary->payment_date,
                'Teacher' => $salary->teacher_name,
                'Share' => $salary->teacher_share,
                'Net Salary' => $salary->net_salary,
                'Deductions' => $salary->deductions,
                'Bonuses' => $salary->bonuses,
                'Status' => $salary->status,
                'Group' => $salary->group_name,
                'Course' => $salary->course_name,
            ];
        }

        // Add adjustments
        foreach ($detailedData['adjustments'] as $adjustment) {
            $formattedData[] = [
                'Type' => 'Adjustment',
                'ID' => $adjustment->id,
                'Date' => $adjustment->adjustment_date,
                'Teacher' => $adjustment->teacher_name,
                'Description' => $adjustment->description,
                'Amount' => $adjustment->amount,
                'Adjustment Type' => $adjustment->type,
            ];
        }

        return $formattedData;
    }

    private function getDetailedExpenses($startDate, $endDate)
    {
        return Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    private function getWeeklyFinancialData($startDate, $endDate)
    {
        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $weekStart = $currentDate->copy()->startOfWeek();
            $weekEnd = $currentDate->copy()->endOfWeek();

            // Revenue from payments
            $revenue = Payment::whereBetween('payment_date', [$weekStart, $weekEnd])
                ->sum('amount');

            // Revenue from invoices
            $invoiceRevenue = Invoice::whereBetween('created_at', [$weekStart, $weekEnd])
                ->where('status', 'paid')
                ->sum('amount');

            // Expenses
            $expenses = Expense::whereBetween('expense_date', [$weekStart, $weekEnd])
                ->sum('amount');

            // Teacher salaries
            $salaries = Salary::whereBetween('payment_date', [$weekStart, $weekEnd])
                ->sum('teacher_share');

            // Teacher adjustments
            $adjustments = TeacherAdjustment::whereBetween('adjustment_date', [$weekStart, $weekEnd])
                ->sum('amount');

            // Calculate totals
            $totalRevenue = $revenue + $invoiceRevenue;
            $totalExpenses = $expenses + $salaries;
            $netProfit = $totalRevenue - $totalExpenses;

            $data[] = [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'display_week' => $weekStart->format('d M').' - '.$weekEnd->format('d M Y'),
                'revenue' => $revenue,
                'invoice_revenue' => $invoiceRevenue,
                'total_revenue' => $totalRevenue,
                'expenses' => $expenses,
                'salaries' => $salaries,
                'adjustments' => $adjustments,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'week_number' => $weekStart->weekOfYear,
            ];

            $currentDate->addWeek();
        }

        return $data;
    }

    private function getDailyFinancialData($startDate, $endDate)
    {
        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            // Revenue from payments
            $revenue = Payment::whereBetween('payment_date', [$dayStart, $dayEnd])
                ->sum('amount');

            // Revenue from invoices (if needed)
            $invoiceRevenue = Invoice::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'paid')
                ->sum('amount');

            // Expenses
            $expenses = Expense::whereBetween('expense_date', [$dayStart, $dayEnd])
                ->sum('amount');

            // Teacher salaries
            $salaries = Salary::whereBetween('payment_date', [$dayStart, $dayEnd])
                ->sum('teacher_share');

            // Teacher adjustments (bonuses/deductions)
            $adjustments = TeacherAdjustment::whereBetween('adjustment_date', [$dayStart, $dayEnd])
                ->sum('amount');

            // Calculate totals
            $totalRevenue = $revenue + $invoiceRevenue;
            $totalExpenses = $expenses + $salaries;
            $netProfit = $totalRevenue - $totalExpenses;

            $data[] = [
                'date' => $currentDate->format('Y-m-d'),
                'display_date' => $currentDate->format('d M Y'),
                'revenue' => $revenue,
                'invoice_revenue' => $invoiceRevenue,
                'total_revenue' => $totalRevenue,
                'expenses' => $expenses,
                'salaries' => $salaries,
                'adjustments' => $adjustments,
                'total_expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'day' => $currentDate->format('l'),
            ];

            $currentDate->addDay();
        }

        return $data;
    }

    /**
     * Display students reports
     */
    public function students(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get student enrollment trends
        $enrollmentTrends = $this->getEnrollmentTrends($startDate, $endDate, $period);

        // Get top performing students with their user info
        // Get top performing students with their user info
        $topStudents = Student::select(
            'students.student_id',
            'students.user_id',
            'users.username as student_name',
            DB::raw('AVG(quiz_attempts.score) as avg_score'),
            DB::raw('COUNT(DISTINCT quiz_attempts.quiz_id) as quizzes_taken')
        )
            ->join('users', 'students.user_id', '=', 'users.id')
            ->join('quiz_attempts', 'students.student_id', '=', 'quiz_attempts.student_id')
            ->groupBy('students.student_id', 'students.user_id', 'users.username')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get();

        // Attach attendance summary (percentage) for each top student within the selected date range
        foreach ($topStudents as $student) {
            $presentCount = DB::table('attendance')
                ->where('student_id', $student->student_id)
                ->whereBetween('recorded_at', [$startDate, $endDate])
                ->where('status', 'present')
                ->count();

            $totalCount = DB::table('attendance')
                ->where('student_id', $student->student_id)
                ->whereBetween('recorded_at', [$startDate, $endDate])
                ->count();

            $student->attendance_percentage = $totalCount > 0 ? round(($presentCount / $totalCount) * 100, 1) : null;
        }

        // Get popular groups
        $popularGroups = Group::select(
            'groups.group_id',
            'groups.group_name',
            'courses.course_name',
            'teachers.teacher_name',
            DB::raw('COUNT(student_group.student_id) as student_count')
        )
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->join('student_group', 'groups.group_id', '=', 'student_group.group_id')
            ->whereBetween('student_group.enrollment_date', [$startDate, $endDate])
            ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name', 'teachers.teacher_name')
            ->orderByDesc('student_count')
            ->limit(5)
            ->get();

        return view('reports.students', [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'enrollmentTrends' => $enrollmentTrends,
            'topStudents' => $topStudents,
            'popularGroups' => $popularGroups,
        ]);
    }

    /**
     * Display quizzes reports
     */
    public function quizzes(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get quiz participation stats
        $quizStats = Quiz::select(
            'quizzes.quiz_id',
            'quizzes.title',
            'sessions.topic',
            'groups.group_name',
            DB::raw('COUNT(DISTINCT quiz_attempts.student_id) as participants'),
            DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_score')
        )
            ->leftJoin('sessions', 'quizzes.session_id', '=', 'sessions.session_id')
            ->leftJoin('groups', 'sessions.group_id', '=', 'groups.group_id')
            ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                    ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
            })
            ->groupBy('quizzes.quiz_id', 'quizzes.title', 'sessions.topic', 'groups.group_name')
            ->orderBy('groups.group_name', 'asc')
            ->orderBy('sessions.topic', 'asc')
            ->orderBy('quizzes.title', 'asc')
            ->get();

        return view('reports.quizzes', [
            'quizStats' => $quizStats,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display attendance reports
     */
    public function attendance(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get attendance data
        $attendanceData = DB::table('attendance')
            ->selectRaw('DATE_FORMAT(recorded_at, "%Y-%m") as period, AVG(status) * 100 as attendance_rate')
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.generic', [
            'data' => $attendanceData,
            'title' => 'Attendance Report',
            'label' => 'Attendance Rate',
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Display performance reports
     */
    public function performance(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get performance data
        $performanceData = QuizAttempt::selectRaw('DATE_FORMAT(start_time, "%Y-%m") as period, AVG(score) as avg_score')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.generic', [
            'data' => $performanceData,
            'title' => 'Academic Performance Report',
            'label' => 'Average Score',
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Display enrollment reports
     */
    public function enrollment(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get enrollment data
        $enrollmentData = DB::table('student_group')
            ->selectRaw('DATE_FORMAT(enrollment_date, "%Y-%m") as period, COUNT(*) as enrollments')
            ->whereBetween('enrollment_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.generic', [
            'data' => $enrollmentData,
            'title' => 'Enrollment Report',
            'label' => 'New Enrollments',
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Display groups reports
     */
    public function groups(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get groups data with student counts
        $groupsData = Group::select(
            'groups.group_name',
            'courses.course_name',
            DB::raw('COUNT(student_group.student_id) as student_count')
        )
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                $join->on('groups.group_id', '=', 'student_group.group_id')
                    ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
            })
            ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name')
            ->orderByDesc('student_count')
            ->get();

        return view('reports.detailed.groups_report', [
            'groupsData' => $groupsData,
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Display certificates reports
     */
    public function certificates(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get certificates data (placeholder - assuming certificates table exists)
        $certificatesData = DB::table('certificates')
            ->selectRaw('DATE_FORMAT(issue_date, "%Y-%m") as period, COUNT(*) as certificates_count')
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.detailed.generic_stat_report', [
            'data' => $certificatesData,
            'title' => 'Certificates Issued Report',
            'label' => 'Certificates',
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Display teachers reports
     */
    public function teachers(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get teachers data
        $teachersData = Teacher::select(
            'teachers.teacher_name',
            DB::raw('COUNT(DISTINCT groups.group_id) as groups_count'),
            DB::raw('COUNT(DISTINCT student_group.student_id) as students_count'),
            DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_performance')
        )
            ->leftJoin('groups', 'teachers.teacher_id', '=', 'groups.teacher_id')
            ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                $join->on('groups.group_id', '=', 'student_group.group_id')
                    ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
            })
            ->leftJoin('sessions', 'groups.group_id', '=', 'sessions.group_id')
            ->leftJoin('quizzes', 'sessions.session_id', '=', 'quizzes.session_id')
            ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                    ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
            })
            ->groupBy('teachers.teacher_id', 'teachers.teacher_name')
            ->orderByDesc('students_count')
            ->get();

        return view('reports.detailed.teachers_report', [
            'teachersData' => $teachersData,
            'period' => $period,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Display revenue reports
     */
    public function revenue(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get revenue data
        $revenueData = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as period, SUM(amount) as total_revenue')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.revenue', [
            'revenueData' => $revenueData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function getDetailedFinancialData(Request $request)
    {
        try {
            $month = $request->input('month', date('Y-m'));
            $year = $request->input('year', date('Y'));

            // Parse dates for filtering
            $startDate = Carbon::parse("{$year}-{$month}-01")->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // 1. Total payments from students (all time and selected month)
            $totalPaymentsAllTime = Payment::sum('amount');
            $totalPaymentsThisMonth = Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount');

            // 2. Total payments to teachers (all time and selected month)
            $totalTeacherPaymentsAllTime = Salary::sum('teacher_share');
            $totalTeacherPaymentsThisMonth = Salary::whereBetween('payment_date', [$startDate, $endDate])->sum('teacher_share');

            // 3. Total expenses (all time and selected month)
            $totalExpensesAllTime = Expense::sum('amount');
            $totalExpensesThisMonth = Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');

            // 4. Monthly breakdown for the selected period
            $monthlyBreakdown = $this->getMonthlyBreakdown($year);

            // 5. Detailed payment breakdown by student groups
            $paymentBreakdown = $this->getPaymentBreakdown($startDate, $endDate);

            // 6. Teacher payment details
            $teacherPayments = $this->getTeacherPaymentDetails($startDate, $endDate);

            // 7. Expense breakdown by category
            $expenseBreakdown = $this->getExpenseBreakdown($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'totals' => [
                        'student_payments' => [
                            'all_time' => $totalPaymentsAllTime,
                            'this_month' => $totalPaymentsThisMonth,
                        ],
                        'teacher_payments' => [
                            'all_time' => $totalTeacherPaymentsAllTime,
                            'this_month' => $totalTeacherPaymentsThisMonth,
                        ],
                        'expenses' => [
                            'all_time' => $totalExpensesAllTime,
                            'this_month' => $totalExpensesThisMonth,
                        ],
                        'net_profit' => [
                            'all_time' => $totalPaymentsAllTime - $totalTeacherPaymentsAllTime - $totalExpensesAllTime,
                            'this_month' => $totalPaymentsThisMonth - $totalTeacherPaymentsThisMonth - $totalExpensesThisMonth,
                        ],
                    ],
                    'monthly_breakdown' => $monthlyBreakdown,
                    'payment_breakdown' => $paymentBreakdown,
                    'teacher_payments' => $teacherPayments,
                    'expense_breakdown' => $expenseBreakdown,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting detailed financial data: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get financial data',
            ], 500);
        }
    }

    private function getMonthlyBreakdown($year)
    {
        $months = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthStart = Carbon::create($year, $i, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $studentPayments = Payment::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount');
            $teacherPayments = Salary::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('teacher_share');
            $expenses = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount');

            $months[] = [
                'month' => $monthStart->format('M Y'),
                'student_payments' => $studentPayments,
                'teacher_payments' => $teacherPayments,
                'expenses' => $expenses,
                'net_profit' => $studentPayments - $teacherPayments - $expenses,
            ];
        }

        return $months;
    }

    private function getPaymentBreakdown($startDate, $endDate)
    {
        return Payment::select(
            'payments.amount',
            'students.student_id',
            'users.username as student_name',
            'groups.group_name',
            'courses.course_name',
            'payments.payment_date'
        )
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.invoice_id')
            ->join('students', 'invoices.student_id', '=', 'students.student_id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('groups', 'invoices.group_id', '=', 'groups.group_id')
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->orderBy('payments.payment_date', 'desc')
            ->get();
    }

    private function getTeacherPaymentDetails($startDate, $endDate)
    {
        return Salary::select(
            'salaries.teacher_share',
            'salaries.net_salary',
            'salaries.payment_date',
            'teachers.teacher_name',
            'groups.group_name',
            'courses.course_name'
        )
            ->join('teachers', 'salaries.teacher_id', '=', 'teachers.teacher_id')
            ->leftJoin('groups', 'salaries.group_id', '=', 'groups.group_id')
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->whereBetween('salaries.payment_date', [$startDate, $endDate])
            ->orderBy('salaries.payment_date', 'desc')
            ->get();
    }

    private function getExpenseBreakdown($startDate, $endDate)
    {
        return Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    /**
     * Display expenses reports
     */
    public function expenses(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get expenses data
        $expensesData = Expense::selectRaw('DATE_FORMAT(expense_date, "%Y-%m") as period, SUM(amount) as total_expenses')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.expenses', [
            'expensesData' => $expensesData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display profit reports
     */
    public function profit(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get profit data (revenue - expenses)
        $profitData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $periodStart = $currentDate->copy()->startOfMonth();
            $periodEnd = $currentDate->copy()->endOfMonth();

            $revenue = Payment::whereBetween('payment_date', [$periodStart, $periodEnd])->sum('amount');
            $expenses = Expense::whereBetween('expense_date', [$periodStart, $periodEnd])->sum('amount');
            $salaries = Salary::whereBetween('created_at', [$periodStart, $periodEnd])->sum('teacher_share');
            $totalExpenses = $expenses + $salaries;
            $profit = $revenue - $totalExpenses;

            $profitData[] = [
                'period' => $currentDate->format('M Y'),
                'revenue' => $revenue,
                'expenses' => $totalExpenses,
                'profit' => $profit,
            ];

            $currentDate->addMonth();
        }

        return view('reports.profit', [
            'profitData' => $profitData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display courses reports
     */
    public function courses(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get courses data with completion rates and average scores
        $coursesData = Course::select(
            'courses.course_name',
            'courses.course_id',
            DB::raw('COUNT(DISTINCT groups.group_id) as groups_count'),
            DB::raw('COUNT(DISTINCT student_group.student_id) as enrollments'),
            DB::raw('SUM(payments.amount) as revenue'),
            DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_score'),
            DB::raw('ROUND((COUNT(DISTINCT CASE WHEN quiz_attempts.score >= 60 THEN student_group.student_id END) / NULLIF(COUNT(DISTINCT student_group.student_id), 0)) * 100, 2) as completion_rate')
        )
            ->leftJoin('groups', 'courses.course_id', '=', 'groups.course_id')
            ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                $join->on('groups.group_id', '=', 'student_group.group_id')
                    ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
            })
            ->leftJoin('invoices', 'groups.group_id', '=', 'invoices.group_id')
            ->leftJoin('payments', 'invoices.invoice_id', '=', 'payments.invoice_id')
            ->leftJoin('sessions', 'groups.group_id', '=', 'sessions.group_id')
            ->leftJoin('quizzes', 'sessions.session_id', '=', 'quizzes.session_id')
            ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                    ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
            })
            ->groupBy('courses.course_id', 'courses.course_name')
            ->orderByDesc('revenue')
            ->get();

        return view('reports.courses', [
            'coursesData' => $coursesData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display analytics reports
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get comprehensive analytics data
        $analyticsData = [
            'total_students' => Student::count(),
            'active_students' => Student::whereHas('groups', function ($q) {
                $q->where('end_date', '>=', now());
            })->count(),
            'total_groups' => Group::count(),
            'total_courses' => Course::count(),
            'total_teachers' => Teacher::count(),
            'average_quiz_score' => QuizAttempt::avg('score') ?? 0,
            'attendance_rate' => DB::table('attendance')->whereBetween('recorded_at', [$startDate, $endDate])->avg('status') * 100 ?? 0,
            'total_revenue' => Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount'),
            'total_expenses' => Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount'),
        ];

        return view('reports.analytics', [
            'analyticsData' => $analyticsData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display progress reports
     */
    public function progress(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get progress data
        $progressData = Student::select(
            'students.student_id',
            'users.username as student_name',
            DB::raw('COUNT(DISTINCT quiz_attempts.quiz_id) as quizzes_completed'),
            DB::raw('AVG(quiz_attempts.score) as average_score'),
            DB::raw('MAX(quiz_attempts.start_time) as last_activity')
        )
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('quiz_attempts', 'students.student_id', '=', 'quiz_attempts.student_id')
            ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate])
            ->groupBy('students.student_id', 'users.username')
            ->orderByDesc('average_score')
            ->get();

        return view('reports.progress', [
            'progressData' => $progressData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Display feedback reports
     */
    public function feedback(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->get('end_date', Carbon::now()));

        // Get feedback data (placeholder - assuming feedback table exists)
        $feedbackData = DB::table('feedback')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, COUNT(*) as feedback_count, AVG(rating) as average_rating')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return view('reports.feedback', [
            'feedbackData' => $feedbackData,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * Export report data to Excel
     */
    public function exportExcel(Request $request, $type)
    {
        try {
            // 1. Validate request
            if (! in_array($type, ['financial', 'students', 'quizzes', 'attendance', 'performance', 'groups', 'courses', 'revenue', 'teachers', 'expenses', 'profit'])) {
                throw new \Exception("Invalid report type: {$type}");
            }

            // 2. Get dates
            $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfMonth()->toDateString()));
            $endDate = Carbon::parse($request->input('end_date', Carbon::now()->toDateString()));

            // 3. Get data
            $data = [];
            $fileName = '';

            switch ($type) {
                case 'financial':
                    $data = $this->getFinancialExportData($startDate, $endDate);
                    $fileName = 'financial_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'students':
                    $data = $this->getStudentsExportData($startDate, $endDate);
                    $fileName = 'students_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'quizzes':
                    $data = Quiz::select(
                        'quizzes.title',
                        'groups.group_name',
                        'sessions.topic',
                        DB::raw('COUNT(DISTINCT quiz_attempts.student_id) as participants'),
                        DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_score')
                    )
                        ->leftJoin('sessions', 'quizzes.session_id', '=', 'sessions.session_id')
                        ->leftJoin('groups', 'sessions.group_id', '=', 'groups.group_id')
                        ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                            $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                                ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
                        })
                        ->groupBy('quizzes.title', 'groups.group_name', 'sessions.topic')
                        ->get()
                        ->map(function ($quiz) {
                            return [
                                'Quiz Name' => $quiz->title,
                                'Group' => $quiz->group_name ?: 'N/A',
                                'Topic' => $quiz->topic ?: 'N/A',
                                'Participants' => $quiz->participants ?: 0,
                                'Average Score' => number_format($quiz->avg_score ?: 0, 2).'%',
                            ];
                        })
                        ->toArray();
                    $fileName = 'quiz_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'attendance':
                    $data = DB::table('attendance')
                        ->selectRaw('DATE_FORMAT(recorded_at, "%Y-%m") as period, AVG(status) * 100 as attendance_rate')
                        ->whereBetween('recorded_at', [$startDate, $endDate])
                        ->groupBy('period')
                        ->orderBy('period')
                        ->get()
                        ->map(function ($record) {
                            return [
                                'Period' => Carbon::createFromFormat('Y-m', $record->period)->format('M Y'),
                                'Attendance Rate' => number_format($record->attendance_rate, 2).'%',
                            ];
                        })
                        ->toArray();
                    $fileName = 'attendance_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'performance':
                    $data = QuizAttempt::selectRaw('DATE_FORMAT(start_time, "%Y-%m") as period, AVG(score) as avg_score')
                        ->whereBetween('start_time', [$startDate, $endDate])
                        ->groupBy('period')
                        ->orderBy('period')
                        ->get()
                        ->map(function ($record) {
                            return [
                                'Period' => Carbon::createFromFormat('Y-m', $record->period)->format('M Y'),
                                'Average Score' => number_format($record->avg_score ?: 0, 2).'%',
                            ];
                        })
                        ->toArray();
                    $fileName = 'performance_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'groups':
                    $data = Group::select(
                        'groups.group_name',
                        'courses.course_name',
                        DB::raw('COUNT(student_group.student_id) as student_count')
                    )
                        ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                        ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                            $join->on('groups.group_id', '=', 'student_group.group_id')
                                ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
                        })
                        ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name')
                        ->orderByDesc('student_count')
                        ->get()
                        ->map(function ($group) {
                            return [
                                'Group Name' => $group->group_name,
                                'Course' => $group->course_name,
                                'Student Count' => $group->student_count ?: 0,
                            ];
                        })
                        ->toArray();
                    $fileName = 'groups_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'courses':
                    $data = Course::select(
                        'courses.course_name',
                        'courses.course_id',
                        DB::raw('COUNT(DISTINCT groups.group_id) as groups_count'),
                        DB::raw('COUNT(DISTINCT student_group.student_id) as enrollments'),
                        DB::raw('SUM(payments.amount) as revenue'),
                        DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_score'),
                        DB::raw('ROUND((COUNT(DISTINCT CASE WHEN quiz_attempts.score >= 60 THEN student_group.student_id END) / NULLIF(COUNT(DISTINCT student_group.student_id), 0)) * 100, 2) as completion_rate')
                    )
                        ->leftJoin('groups', 'courses.course_id', '=', 'groups.course_id')
                        ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                            $join->on('groups.group_id', '=', 'student_group.group_id')
                                ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
                        })
                        ->leftJoin('invoices', 'groups.group_id', '=', 'invoices.group_id')
                        ->leftJoin('payments', 'invoices.invoice_id', '=', 'payments.invoice_id')
                        ->leftJoin('sessions', 'groups.group_id', '=', 'sessions.group_id')
                        ->leftJoin('quizzes', 'sessions.session_id', '=', 'quizzes.session_id')
                        ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                            $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                                ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
                        })
                        ->groupBy('courses.course_id', 'courses.course_name')
                        ->orderByDesc('revenue')
                        ->get()
                        ->map(function ($course) {
                            return [
                                'Course Name' => $course->course_name,
                                'Groups Count' => $course->groups_count ?: 0,
                                'Enrollments' => $course->enrollments ?: 0,
                                'Revenue' => number_format($course->revenue ?: 0, 2),
                                'Average Score' => number_format($course->avg_score ?: 0, 2).'%',
                                'Completion Rate' => number_format($course->completion_rate ?: 0, 2).'%',
                            ];
                        })
                        ->toArray();
                    $fileName = 'courses_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'revenue':
                    $data = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as period, SUM(amount) as total_revenue')
                        ->whereBetween('payment_date', [$startDate, $endDate])
                        ->groupBy('period')
                        ->orderBy('period')
                        ->get()
                        ->map(function ($record) {
                            return [
                                'Period' => Carbon::createFromFormat('Y-m', $record->period)->format('M Y'),
                                'Total Revenue' => number_format($record->total_revenue, 2),
                            ];
                        })
                        ->toArray();
                    $fileName = 'revenue_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'teachers':
                    $data = Teacher::select(
                        'teachers.teacher_name',
                        DB::raw('COUNT(DISTINCT groups.group_id) as groups_count'),
                        DB::raw('COUNT(DISTINCT student_group.student_id) as students_count'),
                        DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_performance')
                    )
                        ->leftJoin('groups', 'teachers.teacher_id', '=', 'groups.teacher_id')
                        ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                            $join->on('groups.group_id', '=', 'student_group.group_id')
                                ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
                        })
                        ->leftJoin('sessions', 'groups.group_id', '=', 'sessions.group_id')
                        ->leftJoin('quizzes', 'sessions.session_id', '=', 'quizzes.session_id')
                        ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                            $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                                ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
                        })
                        ->groupBy('teachers.teacher_id', 'teachers.teacher_name')
                        ->orderByDesc('students_count')
                        ->get()
                        ->map(function ($teacher) {
                            return [
                                'Teacher Name' => $teacher->teacher_name,
                                'Groups Count' => $teacher->groups_count ?: 0,
                                'Students Count' => $teacher->students_count ?: 0,
                                'Average Performance' => number_format($teacher->avg_performance ?: 0, 2).'%',
                            ];
                        })
                        ->toArray();
                    $fileName = 'teachers_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'expenses':
                    $data = Expense::selectRaw('DATE_FORMAT(expense_date, "%Y-%m") as period, SUM(amount) as total_expenses')
                        ->whereBetween('expense_date', [$startDate, $endDate])
                        ->groupBy('period')
                        ->orderBy('period')
                        ->get()
                        ->map(function ($record) {
                            return [
                                'Period' => Carbon::createFromFormat('Y-m', $record->period)->format('M Y'),
                                'Total Expenses' => number_format($record->total_expenses, 2),
                            ];
                        })
                        ->toArray();
                    $fileName = 'expenses_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;

                case 'profit':
                    $profitData = [];
                    $currentDate = $startDate->copy();
                    while ($currentDate <= $endDate) {
                        $periodStart = $currentDate->copy()->startOfMonth();
                        $periodEnd = $currentDate->copy()->endOfMonth();

                        $revenue = Payment::whereBetween('payment_date', [$periodStart, $periodEnd])->sum('amount');
                        $expenses = Expense::whereBetween('expense_date', [$periodStart, $periodEnd])->sum('amount');
                        $salaries = Salary::whereBetween('created_at', [$periodStart, $periodEnd])->sum('teacher_share');
                        $totalExpenses = $expenses + $salaries;
                        $profit = $revenue - $totalExpenses;

                        $profitData[] = [
                            'Period' => $currentDate->format('M Y'),
                            'Revenue' => number_format($revenue, 2),
                            'Expenses' => number_format($totalExpenses, 2),
                            'Profit' => number_format($profit, 2),
                        ];

                        $currentDate->addMonth();
                    }
                    $data = $profitData;
                    $fileName = 'profit_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';
                    break;
            }

            if (empty($data)) {
                throw new \Exception('No data available for the selected period');
            }

            // 4. Create Excel file
            return Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings
            {
                protected $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function headings(): array
                {
                    return array_keys(reset($this->data));
                }
            }, $fileName);
        } catch (\Exception $e) {
            Log::error('Excel export error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to generate Excel report: '.$e->getMessage());
        }
    }

    /**
     * Export report data to PowerPoint
     */
    public function exportPowerPoint(Request $request, $type)
    {
        try {
            Log::info('Starting PowerPoint export', [
                'type' => $type,
                'request_data' => $request->all(),
            ]);

            // Parse dates
            $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()->toDateString()));
            $endDate = Carbon::parse($request->get('end_date', Carbon::now()->toDateString()));

            Log::info('Dates parsed', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // Create new presentation
            $ppt = new PhpPresentation;

            // Remove default slide if exists
            if ($ppt->getSlideCount() > 0) {
                $ppt->removeSlideByIndex(0);
            }

            // Create slides based on report type
            switch ($type) {
                case 'financial':
                    $this->createFinancialSlides($ppt, $startDate, $endDate);
                    break;

                case 'students':
                    $this->createStudentSlides($ppt, $startDate, $endDate);
                    break;

                case 'quizzes':
                    $this->createQuizSlides($ppt, $startDate, $endDate);
                    break;

                case 'attendance':
                    $this->createAttendanceSlides($ppt, $startDate, $endDate);
                    break;

                case 'performance':
                    $this->createPerformanceSlides($ppt, $startDate, $endDate);
                    break;

                case 'groups':
                    $this->createGroupsSlides($ppt, $startDate, $endDate);
                    break;

                case 'teachers':
                    $this->createTeachersSlides($ppt, $startDate, $endDate);
                    break;

                case 'revenue':
                    $this->createRevenueSlides($ppt, $startDate, $endDate);
                    break;

                case 'expenses':
                    $this->createExpensesSlides($ppt, $startDate, $endDate);
                    break;

                case 'profit':
                    $this->createProfitSlides($ppt, $startDate, $endDate);
                    break;

                default:
                    throw new \Exception("Invalid report type: {$type}");
            }

            // Verify slides were created
            if ($ppt->getSlideCount() === 0) {
                throw new \Exception("No slides were created for the {$type} report");
            }

            // Save to file
            $writer = IOFactory::createWriter($ppt, 'PowerPoint2007');
            $temp_file = tempnam(sys_get_temp_dir(), 'ppt');
            $writer->save($temp_file);

            if (! file_exists($temp_file) || filesize($temp_file) === 0) {
                throw new \Exception('Failed to create PowerPoint file');
            }

            Log::info('PowerPoint file created', [
                'temp_file' => $temp_file,
                'file_exists' => file_exists($temp_file),
                'file_size' => file_exists($temp_file) ? filesize($temp_file) : 0,
            ]);

            return response()
                ->download($temp_file, "{$type}_report_".date('Y-m-d_H-i-s').'.pptx')
                ->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('PowerPoint export error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($temp_file) && file_exists($temp_file)) {
                @unlink($temp_file);
            }

            return back()->with('error', 'Failed to generate PowerPoint report: '.$e->getMessage());
        }
    }

    /**
     * Get monthly statistics for the dashboard
     */
    private function getMonthlyStats()
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months->push([
                'month' => $date->format('M Y'),
                'revenue' => Payment::whereMonth('payment_date', $date->month)
                    ->whereYear('payment_date', $date->year)
                    ->sum('amount'),
                'expenses' => Expense::whereMonth('expense_date', $date->month)
                    ->whereYear('expense_date', $date->year)
                    ->sum('amount'),
                'new_students' => Student::whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
            ]);
        }

        return $months;
    }

    /**
     * Get revenue data for the specified period
     */
    private function getRevenueData($startDate, $endDate, $period)
    {
        $query = Payment::selectRaw('
            DATE_FORMAT(payment_date, ?) as period,
            SUM(amount) as total
        ', [$this->getPeriodFormat($period)])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period');

        return $query->get()->pluck('total', 'period')->toArray();
    }

    /**
     * Get expense data for the specified period
     */
    private function getExpenseData($startDate, $endDate, $period)
    {
        $query = Expense::selectRaw('
            DATE_FORMAT(expense_date, ?) as period,
            SUM(amount) as total
        ', [$this->getPeriodFormat($period)])
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period');

        return $query->get()->pluck('total', 'period')->toArray();
    }

    /**
     * Calculate profit data from revenue and expenses
     */
    private function calculateProfit($revenue, $expenses)
    {
        $profit = [];
        foreach ($revenue as $period => $amount) {
            $profit[$period] = $amount - ($expenses[$period] ?? 0);
        }

        return $profit;
    }

    /**
     * Get enrollment trends for the specified period
     */
    private function getEnrollmentTrends($startDate, $endDate, $period)
    {
        return DB::table('student_group')
            ->selectRaw('
                DATE_FORMAT(enrollment_date, ?) as period,
                COUNT(*) as total
            ', [$this->getPeriodFormat($period)])
            ->whereBetween('enrollment_date', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->pluck('total', 'period')
            ->toArray();
    }

    /**
     * Get SQL date format string based on period type
     */
    private function getPeriodFormat($period)
    {
        switch ($period) {
            case 'daily':
                return '%Y-%m-%d';
            case 'weekly':
                return '%x-W%v';
            case 'monthly':
                return '%Y-%m';
            case 'yearly':
                return '%Y';
            default:
                return '%Y-%m';
        }
    }

    /**
     * Get financial data for export
     */
    private function getFinancialExportData($startDate, $endDate)
    {
        $financialData = [];

        // Get monthly data between start and end dates
        $currentDate = Carbon::parse($startDate)->startOfMonth();
        $endDate = Carbon::parse($endDate)->endOfMonth();

        while ($currentDate->lte($endDate)) {
            $monthStart = $currentDate->copy()->startOfMonth();
            $monthEnd = $currentDate->copy()->endOfMonth();

            // Calculate revenue (payments received)
            $revenue = Payment::whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount');

            // Calculate expenses (including salaries)
            $expenses = Expense::whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount');
            $salaries = Salary::whereBetween('created_at', [$monthStart, $monthEnd])->sum('teacher_share');
            $totalExpenses = $expenses + $salaries;

            // Calculate profit/loss
            $profitLoss = $revenue - $totalExpenses;

            $financialData[] = [
                'Period' => $currentDate->format('F Y'),
                'Revenue' => $revenue,
                'Expenses' => $totalExpenses,
                'Profit/Loss' => $profitLoss,
            ];

            $currentDate->addMonth();
        }

        return $financialData;
    }

    /**
     * Get students data for export
     */
    private function getStudentsExportData($startDate, $endDate)
    {
        $studentsData = [];
        $students = Student::with(['groups.course', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        foreach ($students as $student) {
            $groups = $student->groups;

            // Groups that have ended are considered completed
            $completedGroups = $groups->filter(function ($group) {
                return $group->end_date && $group->end_date < now();
            })->count();

            // Groups that haven't ended yet are ongoing
            $ongoingGroups = $groups->filter(function ($group) {
                return ! $group->end_date || $group->end_date >= now();
            })->count();

            $studentsData[] = [
                'Student Name' => $student->user->name,
                'Email' => $student->user->email,
                'Join Date' => $student->created_at->format('Y-m-d'),
                'Completed Groups' => $completedGroups,
                'Ongoing Groups' => $ongoingGroups,
                'Total Groups' => $completedGroups + $ongoingGroups,
            ];
        }

        return $studentsData;
    }

    /**
     * Get quizzes data for export
     */
    private function getQuizzesExportData($startDate, $endDate)
    {
        Log::info('Getting quiz export data', ['start_date' => $startDate, 'end_date' => $endDate]);

        $quizzes = Quiz::select(
            'quizzes.quiz_id',
            'quizzes.title',
            'groups.group_name',
            'sessions.topic',
            DB::raw('COUNT(DISTINCT quiz_attempts.student_id) as participants'),
            DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_score')
        )
            ->leftJoin('sessions', 'quizzes.session_id', '=', 'sessions.session_id')
            ->leftJoin('groups', 'sessions.group_id', '=', 'groups.group_id')
            ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                    ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
            })
            ->groupBy('quizzes.quiz_id', 'quizzes.title', 'sessions.topic', 'groups.group_name')
            ->orderBy('groups.group_name', 'asc')
            ->orderBy('sessions.topic', 'asc')
            ->orderBy('quizzes.title', 'asc')
            ->get();

        Log::info('Quiz data retrieved', ['count' => $quizzes->count()]);

        $data = $quizzes->map(function ($quiz) {
            return [
                'title' => $quiz->title,
                'group_name' => $quiz->group_name ?: 'N/A',
                'topic' => $quiz->topic ?: 'N/A',
                'participants' => $quiz->participants ?: 0,
                'avg_score' => $quiz->avg_score ?: 0,
            ];
        })->toArray();

        Log::info('Quiz data transformed', ['data_count' => count($data)]);

        return $data;
    }

    /**
     * Create financial slides for PowerPoint
     */
    private function createFinancialSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get financial data
            $revenueData = Payment::whereBetween('payment_date', [$startDate, $endDate])
                ->select(DB::raw('DATE_FORMAT(payment_date, "%Y-%m") as period'), DB::raw('SUM(amount) as total'))
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('total', 'period')
                ->toArray();

            $expenseData = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->select(DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as period'), DB::raw('SUM(amount) as total'))
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('total', 'period')
                ->toArray();

            $salaryData = Salary::whereBetween('created_at', [$startDate, $endDate])
                ->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as period'), DB::raw('SUM(teacher_share) as total'))
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('total', 'period')
                ->toArray();

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Financial Performance Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Financial Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $totalRevenue = array_sum($revenueData);
            $totalExpenses = array_sum($expenseData) + array_sum($salaryData);
            $netProfit = $totalRevenue - $totalExpenses;
            $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

            $summaryData = [
                ['Total Revenue:', number_format($totalRevenue, 2)],
                ['Total Expenses:', number_format($totalExpenses, 2)],
                ['Net Profit:', number_format($netProfit, 2)],
                ['Profit Margin:', number_format($profitMargin, 2).'%'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(300)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create monthly data slides
            $dataSlide = null;
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $dataSlide = $ppt->createSlide();
            $this->createTableRow($dataSlide, ['Period', 'Revenue', 'Expenses', 'Net Profit'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            $periods = array_unique(array_merge(array_keys($revenueData), array_keys($expenseData)));
            sort($periods);

            foreach ($periods as $period) {
                $revenue = $revenueData[$period] ?? 0;
                $expenses = ($expenseData[$period] ?? 0) + ($salaryData[$period] ?? 0);
                $profit = $revenue - $expenses;

                $this->createTableRow(
                    $dataSlide,
                    [
                        Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                        number_format($revenue, 2),
                        number_format($expenses, 2),
                        number_format($profit, 2),
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    // Recreate headers on new slide
                    $this->createTableRow($dataSlide, ['Period', 'Revenue', 'Expenses', 'Net Profit'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating financial slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create student slides for PowerPoint
     */
    public function getFinancialData(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly');
            $reportType = $request->get('report_type', 'summary');
            $date = $request->get('date', date('Y-m-d'));

            [$startDate, $endDate] = $this->getDateRangeByPeriod($period, $date);

            $data = [];

            switch ($reportType) {
                case 'daily':
                    $data = $this->getDailyFinancialData($startDate, $endDate);
                    break;

                case 'weekly':
                    $data = $this->getWeeklyFinancialData($startDate, $endDate);
                    break;

                case 'monthly':
                    $data = $this->getMonthlyFinancialData($startDate, $endDate);
                    break;

                case 'detailed':
                    $data = $this->getDetailedFinancialReport($startDate, $endDate);
                    break;

                case 'summary':
                    $data = [
                        'daily' => $this->getDailyFinancialData($startDate, $endDate),
                        'weekly' => $this->getWeeklyFinancialData($startDate, $endDate),
                        'monthly' => $this->getMonthlyFinancialData($startDate, $endDate),
                        'summary' => $this->getFinancialSummary($startDate, $endDate),
                    ];
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $period,
                'report_type' => $reportType,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting financial data: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get financial data',
            ], 500);
        }
    }

    private function createStudentSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get enrollment trends
            $enrollmentTrends = DB::table('student_group')
                ->selectRaw('
                    DATE_FORMAT(enrollment_date, "%Y-%m") as period,
                    COUNT(*) as total
                ')
                ->whereBetween('enrollment_date', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('total', 'period')
                ->toArray();

            // 2. Get top performing students
            // Get top performing students with their user info
            $topStudents = Student::select(
                'students.student_id',
                'students.user_id',
                'users.username as student_name',
                DB::raw('AVG(quiz_attempts.score) as avg_score'),
                DB::raw('COUNT(DISTINCT quiz_attempts.quiz_id) as quizzes_taken')
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->join('quiz_attempts', 'students.student_id', '=', 'quiz_attempts.student_id')
                ->groupBy('students.student_id', 'students.user_id', 'users.username')
                ->orderByDesc('avg_score')
                ->limit(10)
                ->get();

            // 3. Get popular groups
            $popularGroups = Group::select(
                'groups.group_id',
                'groups.group_name',
                'courses.course_name',
                'teachers.teacher_name',
                DB::raw('COUNT(student_group.student_id) as student_count')
            )
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->join('student_group', 'groups.group_id', '=', 'student_group.group_id')
                ->whereBetween('student_group.enrollment_date', [$startDate, $endDate])
                ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name', 'teachers.teacher_name')
                ->orderByDesc('student_count')
                ->limit(5)
                ->get();

            // 4. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Student Performance Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 5. Create enrollment trends slide
            $trendsSlide = $ppt->createSlide();

            // Add trends title
            $trendsTitle = $trendsSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $trendsTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $trendsTitle->createTextRun('Monthly Enrollment Trends');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add enrollment data
            $rowY = 100;
            $rowHeight = 40;

            $this->createTableRow($trendsSlide, ['Period', 'New Enrollments'], 50, $rowY, true);
            $rowY += $rowHeight;

            foreach ($enrollmentTrends as $period => $total) {
                $this->createTableRow(
                    $trendsSlide,
                    [
                        Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                        (string) $total,
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                if ($rowY > 500) {
                    $trendsSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($trendsSlide, ['Period', 'New Enrollments'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            // 6. Create top students slide
            $studentsSlide = $ppt->createSlide();

            // Add students title
            $studentsTitle = $studentsSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $studentsTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $studentsTitle->createTextRun('Top Performing Students');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add students data
            $rowY = 100;
            $this->createTableRow($studentsSlide, ['Student Name', 'Quizzes Taken', 'Average Score'], 50, $rowY, true);
            $rowY += $rowHeight;

            foreach ($topStudents as $student) {
                $this->createTableRow(
                    $studentsSlide,
                    [
                        $student->student_name,
                        (string) $student->quizzes_taken,
                        number_format($student->avg_score, 2).'%',
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;
            }

            // 7. Create popular groups slide
            $groupsSlide = $ppt->createSlide();

            // Add groups title
            $groupsTitle = $groupsSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $groupsTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $groupsTitle->createTextRun('Most Popular Groups');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add groups data
            $rowY = 100;
            $this->createTableRow($groupsSlide, ['Group', 'Course', 'Teacher', 'Students'], 50, $rowY, true);
            $rowY += $rowHeight;

            foreach ($popularGroups as $group) {
                $this->createTableRow(
                    $groupsSlide,
                    [
                        $group->group_name,
                        $group->course_name,
                        $group->teacher_name ?: 'N/A',
                        (string) $group->student_count,
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating student slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create quiz slides for PowerPoint
     */
    /**
     * Create table row in PowerPoint slide
     */
    private function createTableRow($slide, $data, $x, $y, $isHeader = false)
    {
        $columnWidths = [200, 250, 150, 150]; // Total width = 750
        $rowHeight = 30;

        foreach ($data as $index => $text) {
            $shape = $slide->createRichTextShape()
                ->setHeight($rowHeight)
                ->setWidth($columnWidths[$index])
                ->setOffsetX($x)
                ->setOffsetY($y);

            $shape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $textRun = $shape->createTextRun((string) $text);

            if ($isHeader) {
                $textRun->getFont()
                    ->setBold(true)
                    ->setSize(12)
                    ->setColor(new Color('FF000000'));

                $shape->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new Color('FFE7E6E6'));
            } else {
                $textRun->getFont()
                    ->setSize(11)
                    ->setColor(new Color('FF000000'));
            }

            $x += $columnWidths[$index];
        }
    }

    /**
     * Create quiz slides for PowerPoint
     */
    private function createQuizSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get quiz data
            $quizzes = Quiz::select(
                'quizzes.quiz_id',
                'quizzes.title',
                'groups.group_name',
                'sessions.topic',
                DB::raw('COUNT(DISTINCT quiz_attempts.student_id) as participants'),
                DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_score')
            )
                ->leftJoin('sessions', 'quizzes.session_id', '=', 'sessions.session_id')
                ->leftJoin('groups', 'sessions.group_id', '=', 'groups.group_id')
                ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                    $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                        ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
                })
                ->groupBy('quizzes.quiz_id', 'quizzes.title', 'sessions.topic', 'groups.group_name')
                ->orderBy('groups.group_name', 'asc')
                ->orderBy('sessions.topic', 'asc')
                ->orderBy('quizzes.title', 'asc')
                ->get();

            if ($quizzes->isEmpty()) {
                throw new \Exception('No quiz data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Quiz Performance Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create data slides
            $currentGroup = null;
            $dataSlide = null;
            $rowY = 100;
            $rowHeight = 40;

            foreach ($quizzes as $quiz) {
                // Create new slide for each group
                if ($currentGroup !== $quiz->group_name) {
                    $currentGroup = $quiz->group_name;
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;

                    // Add group header
                    $groupShape = $dataSlide->createRichTextShape()
                        ->setHeight(50)
                        ->setWidth(800)
                        ->setOffsetX(50)
                        ->setOffsetY(30);

                    $groupShape->getActiveParagraph()->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $groupText = $groupShape->createTextRun("Group: {$quiz->group_name}");
                    $groupText->getFont()
                        ->setBold(true)
                        ->setSize(20)
                        ->setColor(new Color('FF000000'));

                    // Add table headers
                    $this->createTableRow($dataSlide, ['Topic', 'Quiz Title', 'Participants', 'Avg Score'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }

                // Add quiz data row
                $this->createTableRow(
                    $dataSlide,
                    [
                        $quiz->topic ?? 'N/A',
                        $quiz->title,
                        $quiz->participants,
                        $quiz->avg_score.'%',
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    // Recreate headers on new slide
                    $this->createTableRow($dataSlide, ['Topic', 'Quiz Title', 'Participants', 'Avg Score'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating quiz slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create attendance slides for PowerPoint
     */
    private function createAttendanceSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get attendance data
            $attendanceData = DB::table('attendance')
                ->selectRaw('DATE_FORMAT(recorded_at, "%Y-%m") as period, AVG(status) * 100 as attendance_rate')
                ->whereBetween('recorded_at', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('attendance_rate', 'period')
                ->toArray();

            if (empty($attendanceData)) {
                throw new \Exception('No attendance data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Attendance Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Attendance Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $overallAttendance = array_sum($attendanceData) / count($attendanceData);
            $bestMonth = array_keys($attendanceData, max($attendanceData))[0] ?? '';
            $worstMonth = array_keys($attendanceData, min($attendanceData))[0] ?? '';

            $summaryData = [
                ['Overall Attendance Rate:', number_format($overallAttendance, 2).'%'],
                ['Best Month:', $bestMonth ? Carbon::createFromFormat('Y-m', $bestMonth)->format('M Y') : 'N/A'],
                ['Worst Month:', $worstMonth ? Carbon::createFromFormat('Y-m', $worstMonth)->format('M Y') : 'N/A'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(250)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(350)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create monthly data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Period', 'Attendance Rate'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($attendanceData as $period => $rate) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                        number_format($rate, 2).'%',
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Period', 'Attendance Rate'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating attendance slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create performance slides for PowerPoint
     */
    private function createPerformanceSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get performance data
            $performanceData = QuizAttempt::selectRaw('DATE_FORMAT(start_time, "%Y-%m") as period, AVG(score) as avg_score')
                ->whereBetween('start_time', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('avg_score', 'period')
                ->toArray();

            if (empty($performanceData)) {
                throw new \Exception('No performance data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Performance Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Performance Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $overallScore = array_sum($performanceData) / count($performanceData);
            $bestMonth = array_keys($performanceData, max($performanceData))[0] ?? '';
            $worstMonth = array_keys($performanceData, min($performanceData))[0] ?? '';

            $summaryData = [
                ['Overall Average Score:', number_format($overallScore, 2).'%'],
                ['Best Month:', $bestMonth ? Carbon::createFromFormat('Y-m', $bestMonth)->format('M Y') : 'N/A'],
                ['Worst Month:', $worstMonth ? Carbon::createFromFormat('Y-m', $worstMonth)->format('M Y') : 'N/A'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(250)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(350)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create monthly data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Period', 'Average Score'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($performanceData as $period => $score) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                        number_format($score ?: 0, 2).'%',
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Period', 'Average Score'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating performance slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create groups slides for PowerPoint
     */
    private function createGroupsSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get groups data
            $groupsData = Group::select(
                'groups.group_name',
                'courses.course_name',
                DB::raw('COUNT(student_group.student_id) as student_count')
            )
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                    $join->on('groups.group_id', '=', 'student_group.group_id')
                        ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
                })
                ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name')
                ->orderByDesc('student_count')
                ->get();

            if ($groupsData->isEmpty()) {
                throw new \Exception('No groups data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Groups Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Groups Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $totalGroups = $groupsData->count();
            $totalStudents = $groupsData->sum('student_count');
            $avgStudentsPerGroup = $totalGroups > 0 ? $totalStudents / $totalGroups : 0;
            $largestGroup = $groupsData->first();

            $summaryData = [
                ['Total Groups:', (string) $totalGroups],
                ['Total Students:', (string) $totalStudents],
                ['Average Students per Group:', number_format($avgStudentsPerGroup, 1)],
                ['Largest Group:', $largestGroup ? $largestGroup->group_name.' ('.$largestGroup->student_count.' students)' : 'N/A'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(250)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(300)
                    ->setOffsetX(350)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create groups data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Group Name', 'Course', 'Student Count'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($groupsData as $group) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        $group->group_name,
                        $group->course_name,
                        (string) $group->student_count,
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Group Name', 'Course', 'Student Count'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating groups slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create teachers slides for PowerPoint
     */
    private function createTeachersSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get teachers data
            $teachersData = Teacher::select(
                'teachers.teacher_name',
                DB::raw('COUNT(DISTINCT groups.group_id) as groups_count'),
                DB::raw('COUNT(DISTINCT student_group.student_id) as students_count'),
                DB::raw('ROUND(AVG(CASE WHEN quiz_attempts.score IS NOT NULL THEN quiz_attempts.score ELSE 0 END), 2) as avg_performance')
            )
                ->leftJoin('groups', 'teachers.teacher_id', '=', 'groups.teacher_id')
                ->leftJoin('student_group', function ($join) use ($startDate, $endDate) {
                    $join->on('groups.group_id', '=', 'student_group.group_id')
                        ->whereBetween('student_group.enrollment_date', [$startDate, $endDate]);
                })
                ->leftJoin('sessions', 'groups.group_id', '=', 'sessions.group_id')
                ->leftJoin('quizzes', 'sessions.session_id', '=', 'quizzes.session_id')
                ->leftJoin('quiz_attempts', function ($join) use ($startDate, $endDate) {
                    $join->on('quizzes.quiz_id', '=', 'quiz_attempts.quiz_id')
                        ->whereBetween('quiz_attempts.start_time', [$startDate, $endDate]);
                })
                ->groupBy('teachers.teacher_id', 'teachers.teacher_name')
                ->orderByDesc('students_count')
                ->get();

            if ($teachersData->isEmpty()) {
                throw new \Exception('No teachers data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Teachers Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Teachers Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $totalTeachers = $teachersData->count();
            $totalGroups = $teachersData->sum('groups_count');
            $totalStudents = $teachersData->sum('students_count');
            $avgPerformance = $teachersData->avg('avg_performance');

            $summaryData = [
                ['Total Teachers:', (string) $totalTeachers],
                ['Total Groups:', (string) $totalGroups],
                ['Total Students:', (string) $totalStudents],
                ['Average Performance:', number_format($avgPerformance, 2).'%'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(300)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create teachers data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Teacher Name', 'Groups', 'Students', 'Avg Performance'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($teachersData as $teacher) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        $teacher->teacher_name,
                        (string) $teacher->groups_count,
                        (string) $teacher->students_count,
                        number_format($teacher->avg_performance, 2).'%',
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Teacher Name', 'Groups', 'Students', 'Avg Performance'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating teachers slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create revenue slides for PowerPoint
     */
    private function createRevenueSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get revenue data
            $revenueData = Payment::selectRaw('DATE_FORMAT(payment_date, "%Y-%m") as period, SUM(amount) as total_revenue')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('total_revenue', 'period')
                ->toArray();

            if (empty($revenueData)) {
                throw new \Exception('No revenue data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Revenue Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Revenue Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $totalRevenue = array_sum($revenueData);
            $avgMonthlyRevenue = count($revenueData) > 0 ? $totalRevenue / count($revenueData) : 0;
            $bestMonth = array_keys($revenueData, max($revenueData))[0] ?? '';
            $worstMonth = array_keys($revenueData, min($revenueData))[0] ?? '';

            $summaryData = [
                ['Total Revenue:', number_format($totalRevenue, 2)],
                ['Average Monthly Revenue:', number_format($avgMonthlyRevenue, 2)],
                ['Best Month:', $bestMonth ? Carbon::createFromFormat('Y-m', $bestMonth)->format('M Y') : 'N/A'],
                ['Worst Month:', $worstMonth ? Carbon::createFromFormat('Y-m', $worstMonth)->format('M Y') : 'N/A'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(220)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(320)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create monthly data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Period', 'Revenue'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($revenueData as $period => $revenue) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                        number_format($revenue, 2),
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Period', 'Revenue'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating revenue slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create expenses slides for PowerPoint
     */
    private function createExpensesSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get expenses data
            $expensesData = Expense::selectRaw('DATE_FORMAT(expense_date, "%Y-%m") as period, SUM(amount) as total_expenses')
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->pluck('total_expenses', 'period')
                ->toArray();

            if (empty($expensesData)) {
                throw new \Exception('No expenses data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Expenses Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Expenses Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $totalExpenses = array_sum($expensesData);
            $avgMonthlyExpenses = count($expensesData) > 0 ? $totalExpenses / count($expensesData) : 0;
            $highestMonth = array_keys($expensesData, max($expensesData))[0] ?? '';
            $lowestMonth = array_keys($expensesData, min($expensesData))[0] ?? '';

            $summaryData = [
                ['Total Expenses:', number_format($totalExpenses, 2)],
                ['Average Monthly Expenses:', number_format($avgMonthlyExpenses, 2)],
                ['Highest Month:', $highestMonth ? Carbon::createFromFormat('Y-m', $highestMonth)->format('M Y') : 'N/A'],
                ['Lowest Month:', $lowestMonth ? Carbon::createFromFormat('Y-m', $lowestMonth)->format('M Y') : 'N/A'],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(220)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(320)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create monthly data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Period', 'Expenses'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($expensesData as $period => $expenses) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                        number_format($expenses, 2),
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Period', 'Expenses'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating expenses slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Create profit slides for PowerPoint
     */
    private function createProfitSlides($ppt, $startDate, $endDate)
    {
        try {
            // 1. Get profit data
            $profitData = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $periodStart = $currentDate->copy()->startOfMonth();
                $periodEnd = $currentDate->copy()->endOfMonth();

                $revenue = Payment::whereBetween('payment_date', [$periodStart, $periodEnd])->sum('amount');
                $expenses = Expense::whereBetween('expense_date', [$periodStart, $periodEnd])->sum('amount');
                $salaries = Salary::whereBetween('created_at', [$periodStart, $periodEnd])->sum('teacher_share');
                $totalExpenses = $expenses + $salaries;
                $profit = $revenue - $totalExpenses;

                $profitData[$currentDate->format('Y-m')] = [
                    'period' => $currentDate->format('M Y'),
                    'revenue' => $revenue,
                    'expenses' => $totalExpenses,
                    'profit' => $profit,
                ];

                $currentDate->addMonth();
            }

            if (empty($profitData)) {
                throw new \Exception('No profit data available for the selected period');
            }

            // 2. Create title slide
            $titleSlide = $ppt->createSlide();

            // Add title
            $titleShape = $titleSlide->createRichTextShape()
                ->setHeight(100)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(50);

            $titleShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $textRun = $titleShape->createTextRun('Profit Report');
            $textRun->getFont()
                ->setBold(true)
                ->setSize(28)
                ->setColor(new Color('FF000000'));

            // Add date range
            $dateShape = $titleSlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(150);

            $dateShape->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $dateRange = $dateShape->createTextRun(
                'Period: '.$startDate->format('M d, Y').' - '.$endDate->format('M d, Y')
            );
            $dateRange->getFont()
                ->setSize(14)
                ->setColor(new Color('FF666666'));

            // 3. Create summary slide
            $summarySlide = $ppt->createSlide();

            // Add summary title
            $summaryTitle = $summarySlide->createRichTextShape()
                ->setHeight(50)
                ->setWidth(600)
                ->setOffsetX(100)
                ->setOffsetY(30);

            $summaryTitle->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $titleRun = $summaryTitle->createTextRun('Profit Summary');
            $titleRun->getFont()
                ->setBold(true)
                ->setSize(24)
                ->setColor(new Color('FF000000'));

            // Add summary data
            $totalRevenue = array_sum(array_column($profitData, 'revenue'));
            $totalExpenses = array_sum(array_column($profitData, 'expenses'));
            $totalProfit = array_sum(array_column($profitData, 'profit'));
            $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

            $profitableMonths = count(array_filter($profitData, fn ($data) => $data['profit'] > 0));
            $totalMonths = count($profitData);

            $summaryData = [
                ['Total Revenue:', number_format($totalRevenue, 2)],
                ['Total Expenses:', number_format($totalExpenses, 2)],
                ['Net Profit:', number_format($totalProfit, 2)],
                ['Profit Margin:', number_format($profitMargin, 2).'%'],
                ['Profitable Months:', $profitableMonths.'/'.$totalMonths],
            ];

            $rowY = 100;
            foreach ($summaryData as $row) {
                $labelShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(100)
                    ->setOffsetY($rowY);

                $labelRun = $labelShape->createTextRun($row[0]);
                $labelRun->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $valueShape = $summarySlide->createRichTextShape()
                    ->setHeight(30)
                    ->setWidth(200)
                    ->setOffsetX(300)
                    ->setOffsetY($rowY);

                $valueRun = $valueShape->createTextRun($row[1]);
                $valueRun->getFont()
                    ->setSize(14)
                    ->setColor(new Color('FF000000'));

                $rowY += 40;
            }

            // 4. Create monthly data slide
            $dataSlide = $ppt->createSlide();
            $rowY = 100;
            $rowHeight = 40;

            // Add headers
            $this->createTableRow($dataSlide, ['Period', 'Revenue', 'Expenses', 'Profit'], 50, $rowY, true);
            $rowY += $rowHeight;

            // Add data rows
            foreach ($profitData as $data) {
                $this->createTableRow(
                    $dataSlide,
                    [
                        $data['period'],
                        number_format($data['revenue'], 2),
                        number_format($data['expenses'], 2),
                        number_format($data['profit'], 2),
                    ],
                    50,
                    $rowY,
                    false
                );
                $rowY += $rowHeight;

                // Create new slide if we're running out of space
                if ($rowY > 500) {
                    $dataSlide = $ppt->createSlide();
                    $rowY = 100;
                    $this->createTableRow($dataSlide, ['Period', 'Revenue', 'Expenses', 'Profit'], 50, $rowY, true);
                    $rowY += $rowHeight;
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating profit slides: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Export all charts as images
     */
    public function exportAllCharts(Request $request)
    {
        try {
            Log::info('Starting export all charts as images', ['request_data' => $request->all()]);

            // Get dates
            $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfMonth()->toDateString()));
            $endDate = Carbon::parse($request->input('end_date', Carbon::now()->toDateString()));

            Log::info('Dates parsed', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // Create temporary directory for images
            $temp_dir = sys_get_temp_dir().'/charts_'.uniqid();
            if (! mkdir($temp_dir, 0755, true)) {
                throw new \Exception('Failed to create temporary directory');
            }

            // Generate images for each report type
            $this->createFinancialImage($temp_dir, $startDate, $endDate);
            $this->createStudentImage($temp_dir, $startDate, $endDate);
            $this->createQuizImage($temp_dir, $startDate, $endDate);
            $this->createAttendanceImage($temp_dir, $startDate, $endDate);
            $this->createPerformanceImage($temp_dir, $startDate, $endDate);
            $this->createGroupsImage($temp_dir, $startDate, $endDate);
            $this->createTeachersImage($temp_dir, $startDate, $endDate);
            $this->createRevenueImage($temp_dir, $startDate, $endDate);
            $this->createExpensesImage($temp_dir, $startDate, $endDate);
            $this->createProfitImage($temp_dir, $startDate, $endDate);

            // Create ZIP file
            $zip_file = tempnam(sys_get_temp_dir(), 'charts_zip');
            $zip = new \ZipArchive;
            if ($zip->open($zip_file, \ZipArchive::CREATE) !== true) {
                throw new \Exception('Failed to create ZIP file');
            }

            // Add images to ZIP
            $files = glob($temp_dir.'/*.png');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            // Clean up temporary directory
            array_map('unlink', glob($temp_dir.'/*.png'));
            rmdir($temp_dir);

            if (! file_exists($zip_file) || filesize($zip_file) === 0) {
                throw new \Exception('Failed to create ZIP file with images');
            }

            Log::info('Charts images ZIP created', [
                'zip_file' => $zip_file,
                'file_exists' => file_exists($zip_file),
                'file_size' => filesize($zip_file),
                'image_count' => count($files),
            ]);

            return response()
                ->download($zip_file, 'charts_images_'.date('Y-m-d_H-i-s').'.zip')
                ->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Export all charts error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clean up on error
            if (isset($temp_dir) && is_dir($temp_dir)) {
                array_map('unlink', glob($temp_dir.'/*.png'));
                rmdir($temp_dir);
            }
            if (isset($zip_file) && file_exists($zip_file)) {
                @unlink($zip_file);
            }

            return back()->with('error', 'Failed to generate charts images: '.$e->getMessage());
        }
    }
}
