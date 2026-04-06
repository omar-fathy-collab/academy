<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Expense;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware(function ($request, $next) {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();
            if (! $user || ! $user->hasRoleName('admin')) {
                return redirect('/unauthorized');
            }

            return $next($request);
        });
    }

    /**
     * صفحة التقارير المالية الرئيسية
     */
    public function index(Request $request)
    {
        // الفلاتر الأساسية
        $period = $request->get('period', 'today'); // today, yesterday, this_week, this_month, last_month, custom
        $date = $request->get('date', date('Y-m-d'));
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        // تحديد التاريخ بناء على الفترة
        $dateRange = $this->calculateDateRange($period, $date, $start_date, $end_date);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // جلب البيانات حسب نوع التقرير
        $reportType = $request->get('report_type', 'summary');

        switch ($reportType) {
            case 'daily_details':
                $data = $this->getDailyDetailedReport($startDate, $endDate);
                break;

            case 'payments':
                $data = $this->getPaymentsReport($startDate, $endDate);
                break;

            case 'invoices':
                $data = $this->getInvoicesReport($startDate, $endDate);
                break;

            case 'expenses':
                $data = $this->getExpensesReport($startDate, $endDate);
                break;

            case 'salaries':
                $data = $this->getSalariesReport($startDate, $endDate);
                break;

            case 'summary':
            default:
                $data = $this->getSummaryReport($startDate, $endDate);
                break;
        }

        // إحصائيات سريعة
        $quickStats = $this->getQuickStats($startDate, $endDate);

        return view('reports.financial', [
            'period' => $period,
            'date' => $date,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'reportType' => $reportType,
            'data' => $data,
            'quickStats' => $quickStats,
        ]);
    }

    /**
     * حساب النطاق الزمني بناء على الفترة
     */
    private function calculateDateRange($period, $date, $customStart = null, $customEnd = null)
    {
        $today = Carbon::today();
        $carbonDate = Carbon::parse($date);

        switch ($period) {
            case 'today':
                $start = $today->copy()->startOfDay();
                $end = $today->copy()->endOfDay();
                break;

            case 'yesterday':
                $start = $today->copy()->subDay()->startOfDay();
                $end = $today->copy()->subDay()->endOfDay();
                break;

            case 'this_week':
                $start = $today->copy()->startOfWeek();
                $end = $today->copy()->endOfWeek();
                break;

            case 'last_week':
                $start = $today->copy()->subWeek()->startOfWeek();
                $end = $today->copy()->subWeek()->endOfWeek();
                break;

            case 'this_month':
                $start = $today->copy()->startOfMonth();
                $end = $today->copy()->endOfMonth();
                break;

            case 'last_month':
                $start = $today->copy()->subMonth()->startOfMonth();
                $end = $today->copy()->subMonth()->endOfMonth();
                break;

            case 'this_year':
                $start = $today->copy()->startOfYear();
                $end = $today->copy()->endOfYear();
                break;

            case 'custom':
                $start = $customStart ? Carbon::parse($customStart)->startOfDay() : $today->copy()->startOfMonth();
                $end = $customEnd ? Carbon::parse($customEnd)->endOfDay() : $today->copy()->endOfMonth();
                break;

            default:
                $start = $carbonDate->copy()->startOfDay();
                $end = $carbonDate->copy()->endOfDay();
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'period' => $period,
        ];
    }

    /**
     * تقرير الملخص
     */
    private function getSummaryReport($startDate, $endDate)
    {
        $data = [];

        // الإيرادات من المدفوعات
        $payments = Payment::whereBetween('payment_date', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('DATE(payment_date) as date')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $totalPayments = $payments->sum('total');
        $paymentCount = $payments->sum('count');

        // الإيرادات من الفواتير (المدفوعة)
        $invoices = Invoice::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('DATE(created_at) as date')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $totalInvoices = $invoices->sum('total');
        $invoiceCount = $invoices->sum('count');

        // المصاريف
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('DATE(expense_date) as date')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $totalExpenses = $expenses->sum('total');
        $expenseCount = $expenses->sum('count');

        // الرواتب
        $salaries = Salary::whereBetween('payment_date', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(teacher_share) as total'),
                DB::raw('COUNT(*) as count'),
                DB::raw('DATE(payment_date) as date')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $totalSalaries = $salaries->sum('total');
        $salaryCount = $salaries->sum('count');

        // ======= استخدام FinancialService للحصول على الأرقام المالية دقيقة =======
        $financialService = app(\App\Services\FinancialService::class);

        $totalRevenue = $financialService->getTotalRevenue($startDate, $endDate);
        $totalExpenses = $financialService->getTotalExpenses($startDate, $endDate);
        $totalSalaries = $financialService->getTotalTeacherPayments($startDate, $endDate);
        $totalDeductions = $financialService->getTotalDeductions($startDate, $endDate);

        $totalCosts = $totalExpenses + $totalSalaries;
        $netProfit = $financialService->calculateNetProfit($startDate, $endDate);
        $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_payments' => $totalRevenue, // في هذا النظام Revenue هو Payments
                'payment_count' => $payments->sum('count'),
                'total_invoices' => Invoice::whereBetween('created_at', [$startDate, $endDate])->where('status', 'paid')->sum('amount'),
                'invoice_count' => Invoice::whereBetween('created_at', [$startDate, $endDate])->where('status', 'paid')->count(),
                'total_expenses' => $totalExpenses,
                'expense_count' => $expenses->sum('count'),
                'total_salaries' => $totalSalaries,
                'salary_count' => $salaries->sum('count'),
                'total_costs' => $totalCosts,
                'net_profit' => $netProfit,
                'profit_margin' => $profitMargin,
                'total_deductions' => $totalDeductions,
            ],
            'daily_breakdown' => [
                'payments' => $payments,
                'invoices' => $invoices,
                'expenses' => $expenses,
                'salaries' => $salaries,
            ],
            'top_courses' => $this->getTopCourses($startDate, $endDate),
            'top_groups' => $this->getTopGroups($startDate, $endDate),
        ];
    }

    /**
     * تقرير المدفوعات التفصيلي
     */
    private function getPaymentsReport($startDate, $endDate)
    {
        $payments = Payment::select(
            'payments.payment_id',
            'payments.amount',
            'payments.payment_date',
            'payments.payment_method',
            'payments.status',
            'invoices.invoice_number',
            'students.student_id',
            'users.username as student_name',
            'groups.group_name',
            'courses.course_name'
        )
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.invoice_id')
            ->join('students', 'invoices.student_id', '=', 'students.student_id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('groups', 'invoices.group_id', '=', 'groups.group_id')
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->orderBy('payments.payment_date', 'desc')
            ->get();

        $summary = [
            'total_amount' => $payments->sum('amount'),
            'count' => $payments->count(),
            'by_method' => $payments->groupBy('payment_method')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                ];
            }),
            'by_status' => $payments->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                ];
            }),
        ];

        return [
            'payments' => $payments,
            'summary' => $summary,
        ];
    }

    /**
     * تقرير الفواتير
     */
    private function getInvoicesReport($startDate, $endDate)
    {
        $invoices = Invoice::select(
            'invoices.invoice_id',
            'invoices.invoice_number',
            'invoices.amount',
            'invoices.amount_paid',
            'invoices.discount_amount',
            'invoices.status',
            'invoices.due_date',
            'invoices.created_at',
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

        $summary = [
            'total_amount' => $invoices->sum('amount'),
            'total_paid' => $invoices->sum('amount_paid'),
            'total_discount' => $invoices->sum('discount_amount'),
            'total_balance' => $invoices->sum('amount') - $invoices->sum('amount_paid'),
            'count' => $invoices->count(),
            'by_status' => $invoices->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                    'paid' => $group->sum('amount_paid'),
                ];
            }),
        ];

        return [
            'invoices' => $invoices,
            'summary' => $summary,
        ];
    }

    /**
     * تقرير المصاريف
     */
    private function getExpensesReport($startDate, $endDate)
    {
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->orderBy('expense_date', 'desc')
            ->get();

        $summary = [
            'total_amount' => $expenses->sum('amount'),
            'count' => $expenses->count(),
            'by_category' => $expenses->groupBy('category')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                ];
            }),
            'approved_vs_pending' => [
                'approved' => $expenses->where('is_approved', 1)->sum('amount'),
                'pending' => $expenses->where('is_approved', 0)->sum('amount'),
            ],
        ];

        return [
            'expenses' => $expenses,
            'summary' => $summary,
        ];
    }

    /**
     * تقرير الرواتب
     */
    private function getSalariesReport($startDate, $endDate)
    {
        $salaries = Salary::select(
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

        $summary = [
            'total_teacher_share' => $salaries->sum('teacher_share'),
            'total_net_salary' => $salaries->sum('net_salary'),
            'total_deductions' => $salaries->sum('deductions'),
            'total_bonuses' => $salaries->sum('bonuses'),
            'count' => $salaries->count(),
            'by_status' => $salaries->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('teacher_share'),
                ];
            }),
            'by_teacher' => $salaries->groupBy('teacher_name')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('teacher_share'),
                ];
            }),
        ];

        return [
            'salaries' => $salaries,
            'summary' => $summary,
        ];
    }

    /**
     * تقرير يومي تفصيلي
     */
    private function getDailyDetailedReport($startDate, $endDate)
    {
        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            // المدفوعات
            $payments = Payment::whereBetween('payment_date', [$dayStart, $dayEnd])
                ->sum('amount');

            // الفواتير المدفوعة
            $invoices = Invoice::whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'paid')
                ->sum('amount');

            // المصاريف
            $expenses = Expense::whereBetween('expense_date', [$dayStart, $dayEnd])
                ->sum('amount');

            // الرواتب
            $salaries = Salary::whereBetween('payment_date', [$dayStart, $dayEnd])
                ->sum('teacher_share');

            // الحسابات
            $totalRevenue = $payments;
            $totalCosts = $expenses + $salaries;
            $netProfit = $totalRevenue - $totalCosts;

            $data[] = [
                'date' => $currentDate->format('Y-m-d'),
                'display_date' => $currentDate->format('d/m/Y'),
                'day_name' => $this->getArabicDay($currentDate->format('l')),
                'payments' => $payments,
                'invoices' => $invoices,
                'expenses' => $expenses,
                'salaries' => $salaries,
                'total_revenue' => $totalRevenue,
                'total_costs' => $totalCosts,
                'net_profit' => $netProfit,
                'profit_class' => $netProfit >= 0 ? 'text-success' : 'text-danger',
                'details' => [
                    'payments_count' => Payment::whereBetween('payment_date', [$dayStart, $dayEnd])->count(),
                    'invoices_count' => Invoice::whereBetween('created_at', [$dayStart, $dayEnd])->where('status', 'paid')->count(),
                    'expenses_count' => Expense::whereBetween('expense_date', [$dayStart, $dayEnd])->count(),
                    'salaries_count' => Salary::whereBetween('payment_date', [$dayStart, $dayEnd])->count(),
                ],
            ];

            $currentDate->addDay();
        }

        return [
            'daily_data' => $data,
            'summary' => $this->calculateDailySummary($data),
        ];
    }

    /**
     * إحصائيات سريعة
     */
    private function getQuickStats($startDate, $endDate)
    {
        // اليوم
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        // الأسبوع
        $weekStart = Carbon::today()->startOfWeek();
        $weekEnd = Carbon::today()->endOfWeek();

        // الشهر
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        return [
            'today' => [
                'revenue' => Payment::whereBetween('payment_date', [$todayStart, $todayEnd])->sum('amount'),
                'expenses' => Expense::whereBetween('expense_date', [$todayStart, $todayEnd])->sum('amount'),
                'salaries' => Salary::whereBetween('payment_date', [$todayStart, $todayEnd])->sum('teacher_share'),
                'profit' => Payment::whereBetween('payment_date', [$todayStart, $todayEnd])->sum('amount') -
                           (Expense::whereBetween('expense_date', [$todayStart, $todayEnd])->sum('amount') +
                            Salary::whereBetween('payment_date', [$todayStart, $todayEnd])->sum('teacher_share')),
            ],
            'this_week' => [
                'revenue' => Payment::whereBetween('payment_date', [$weekStart, $weekEnd])->sum('amount'),
                'expenses' => Expense::whereBetween('expense_date', [$weekStart, $weekEnd])->sum('amount'),
                'salaries' => Salary::whereBetween('payment_date', [$weekStart, $weekEnd])->sum('teacher_share'),
                'profit' => Payment::whereBetween('payment_date', [$weekStart, $weekEnd])->sum('amount') -
                           (Expense::whereBetween('expense_date', [$weekStart, $weekEnd])->sum('amount') +
                            Salary::whereBetween('payment_date', [$weekStart, $weekEnd])->sum('teacher_share')),
            ],
            'this_month' => [
                'revenue' => Payment::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount'),
                'expenses' => Expense::whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount'),
                'salaries' => Salary::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('teacher_share'),
                'profit' => Payment::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount') -
                           (Expense::whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount') +
                            Salary::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('teacher_share')),
            ],
        ];
    }

    /**
     * أفضل الكورسات إيراداً
     */
    private function getTopCourses($startDate, $endDate, $limit = 5)
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
            ->limit($limit)
            ->get();
    }

    /**
     * أفضل الجروبات إيراداً
     */
    private function getTopGroups($startDate, $endDate, $limit = 5)
    {
        return Group::select(
            'groups.group_id',
            'groups.group_name',
            'courses.course_name',
            DB::raw('SUM(payments.amount) as revenue'),
            DB::raw('COUNT(DISTINCT payments.payment_id) as payment_count'),
            DB::raw('COUNT(DISTINCT invoices.student_id) as student_count')
        )
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->join('invoices', 'groups.group_id', '=', 'invoices.group_id')
            ->join('payments', 'invoices.invoice_id', '=', 'payments.invoice_id')
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /**
     * حساب ملخص البيانات اليومية
     */
    private function calculateDailySummary($dailyData)
    {
        return [
            'total_revenue' => collect($dailyData)->sum('total_revenue'),
            'total_costs' => collect($dailyData)->sum('total_costs'),
            'net_profit' => collect($dailyData)->sum('net_profit'),
            'days_count' => count($dailyData),
            'profitable_days' => collect($dailyData)->where('net_profit', '>', 0)->count(),
            'loss_days' => collect($dailyData)->where('net_profit', '<', 0)->count(),
            'best_day' => collect($dailyData)->sortByDesc('net_profit')->first(),
            'worst_day' => collect($dailyData)->sortBy('net_profit')->first(),
        ];
    }

    /**
     * تحويل اليوم للإنجليزية للعربية
     */
    private function getArabicDay($englishDay)
    {
        $days = [
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];

        return $days[$englishDay] ?? $englishDay;
    }

    /**
     * تصدير التقرير لـ Excel
     */
    public function exportExcel(Request $request)
    {
        $period = $request->get('period', 'today');
        $date = $request->get('date', date('Y-m-d'));
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $reportType = $request->get('report_type', 'summary');

        $dateRange = $this->calculateDateRange($period, $date, $start_date, $end_date);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // TODO: Implement Excel export
        // يمكنك استخدام maatwebsite/excel هنا

        return back()->with('success', 'سيتم إضافة التصدير لـ Excel قريباً');
    }

    /**
     * API للحصول على البيانات (للتحديث الديناميكي)
     */
    public function getData(Request $request)
    {
        try {
            $period = $request->get('period', 'today');
            $date = $request->get('date', date('Y-m-d'));
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            $reportType = $request->get('report_type', 'summary');

            $dateRange = $this->calculateDateRange($period, $date, $start_date, $end_date);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            switch ($reportType) {
                case 'daily_details':
                    $data = $this->getDailyDetailedReport($startDate, $endDate);
                    break;

                case 'payments':
                    $data = $this->getPaymentsReport($startDate, $endDate);
                    break;

                case 'invoices':
                    $data = $this->getInvoicesReport($startDate, $endDate);
                    break;

                case 'expenses':
                    $data = $this->getExpensesReport($startDate, $endDate);
                    break;

                case 'salaries':
                    $data = $this->getSalariesReport($startDate, $endDate);
                    break;

                case 'summary':
                default:
                    $data = $this->getSummaryReport($startDate, $endDate);
                    break;
            }

            $quickStats = $this->getQuickStats($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $data,
                'quickStats' => $quickStats,
                'dateRange' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'period' => $dateRange['period'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in financial reports API: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات',
            ], 500);
        }
    }
}
