<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialService;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $financialService = app(FinancialService::class);
        
        $data = [
            'teachers_count' => DB::table('teachers')->count(),
            'groups_count' => DB::table('groups')->count(),
            'courses_count' => DB::table('courses')->count(),
            'students_count' => DB::table('students')->count(),
            'net_profit' => $financialService->calculateNetProfit(),
            'total_revenue' => $financialService->getTotalRevenue(),
            'total_expenses' => $financialService->getGrossExpenses(), // Inclusive of salaries
            'vault_balance' => $financialService->getVaultBalance(),
            'total_debt' => $financialService->getTotalOutstandingBalance(),
            'total_capital' => $financialService->getTotalCapital(),
            'total_distributions' => $financialService->getTotalProfitDistributions(),
            'vault_withdrawals' => $financialService->getVaultWithdrawals(),
            'monthly_revenue' => $financialService->getFinancialMetrics('month')['revenue'],
            'profit_today' => $financialService->getNetProfitForPeriod('today'),
            'profit_month' => $financialService->getNetProfitForPeriod('month'),
            'profit_year' => $financialService->getNetProfitForPeriod('year'),
            'available_balance' => $financialService->getAvailableBalanceForAdmin(auth()->user()),
            'new_teachers_month' => DB::table('teachers')
                ->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))
                ->count(),
            'total_assignments' => DB::table('assignments')->count(),
            'total_submissions' => DB::table('assignment_submissions')->count(),
            'avg_score' => round(DB::table('assignment_submissions')->whereNotNull('score')->avg('score') ?? 0, 1),
            'attendance_rate' => $this->getAttendanceRate(),
            'total_quizzes' => DB::table('quizzes')->count(),
            'monthly_stats' => $this->getMonthlyStats(),
            'course_distribution' => $this->getCourseDistribution(),
            'recent_payments' => $this->getRecentPayments(),
            'recent_activities' => \App\Models\Activity::with('user.profile')->latest()->limit(10)->get(),
            'yearly_revenue_stats' => $this->getYearlyRevenueStats(),
            'student_status_stats' => $this->getStudentStatusStats(),
            'top_teachers' => $this->getTopTeachers(),
            'task_completion_rates' => $this->getTaskCompletionRates(),
            'weekly_signups' => $this->getWeeklySignups(),
            'weekly_revenue' => $this->getWeeklyRevenue(),
            'weekly_teachers' => $this->getWeeklyTeachers(),
            'weekly_groups' => $this->getWeeklyGroups(),
            'today_enrollments' => DB::table('students')->whereDate('created_at', now()->toDateString())->count(),
            'today_sessions' => DB::table('sessions')->whereDate('session_date', now()->toDateString())->count(),
            'today_leads' => DB::table('enrollment_requests')->whereDate('created_at', now()->toDateString())->count(),
            'pending_grading_count' => DB::table('assignment_submissions')->whereNull('score')->count(),
        ];

        return view('dashboard', $data);
    }

    private function getAttendanceRate()
    {
        $total = DB::table('attendance')->count();
        if ($total === 0) return 0;
        $present = DB::table('attendance')->whereIn('status', ['present', 'late'])->count();
        return round(($present / $total) * 100, 1);
    }

    private function getMonthlyStats()
    {
        $months = collect(range(5, 0))->map(function ($i) {
            return now()->subMonths($i)->format('M Y');
        });

        $stats = DB::table('students')
            ->selectRaw("DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('count', 'month');

        return $months->map(function ($month) use ($stats) {
            return ['month' => $month, 'count' => $stats->get($month, 0)];
        })->toArray();
    }

    private function getYearlyRevenueStats()
    {
        $months = collect(range(11, 0))->map(function ($i) {
            return now()->subMonths($i)->format('b Y'); // %b is short month name in MySQL, 'M Y' in PHP is same e.g. "Mar 2024"
        });

        $stats = DB::table('payments')
            ->selectRaw("DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount) as total")
            ->where('payment_date', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        $series = $months->map(function ($month) use ($stats) {
            // Need to match PHP 'M' (e.g. Mar) with MySQL '%b' (e.g. Mar)
            return (float)$stats->get($month, 0);
        });

        return [
            'labels' => collect(range(11, 0))->map(fn($i) => now()->subMonths($i)->format('M Y'))->toArray(),
            'series' => $series->toArray(),
        ];
    }

    private function getStudentStatusStats()
    {
        $active = DB::table('users')->where('role_id', 3)->where('is_active', 1)->count();
        $inactive = DB::table('users')->where('role_id', 3)->where('is_active', 0)->count();
        $waiting = DB::table('waiting_students')->whereIn('status', ['waiting', 'contacted'])->count();

        return [
            'labels' => ['Active Enrollments', 'Inactive Students', 'Waitlist'],
            'series' => [(int)$active, (int)$inactive, (int)$waiting]
        ];
    }

    private function getTopTeachers()
    {
        return DB::table('teachers as t')
            ->select('u.username as name', DB::raw('COUNT(sg.student_id) as students_count'))
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('groups as g', 't.teacher_id', '=', 'g.teacher_id')
            ->leftJoin('student_group as sg', 'g.group_id', '=', 'sg.group_id')
            ->groupBy('t.teacher_id', 'u.username')
            ->orderByDesc('students_count')
            ->limit(5)
            ->get();
    }

    private function getTaskCompletionRates()
    {
        $total_assignments = DB::table('assignments')->count();
        $total_students = DB::table('students')->count();
        
        $active_submissions = DB::table('assignment_submissions')->where('created_at', '>=', now()->subDays(30))->count();
        $expected_submissions = max(1, $total_assignments * $total_students * 0.1); 
        
        return min(100, round(($active_submissions / $expected_submissions) * 100));
    }

    private function getCourseDistribution()
    {
        $courses = DB::table('courses as c')
            ->select('c.course_name', DB::raw('COUNT(g.group_id) as groups_count'))
            ->leftJoin('groups as g', 'c.course_id', '=', 'g.course_id')
            ->groupBy('c.course_id', 'c.course_name')
            ->orderByDesc('groups_count')
            ->limit(5)
            ->get();

        return [
            'labels' => $courses->pluck('course_name')->toArray(),
            'series' => $courses->pluck('groups_count')->toArray()
        ];
    }

    private function getRecentPayments()
    {
        return DB::table('payments as p')
            ->select('p.amount', 'p.payment_method', 'p.payment_date', 'i.invoice_number', 's.student_name')
            ->join('invoices as i', 'p.invoice_id', '=', 'i.invoice_id')
            ->join('students as s', 'i.student_id', '=', 's.student_id')
            ->orderByDesc('p.payment_date')
            ->limit(5)
            ->get();
    }

    private function getWeeklySignups()
    {
        $days = collect(range(6, 0))->map(function ($i) {
            return now()->subDays($i)->format('D');
        });

        $stats = DB::table('students')
            ->selectRaw("DATE_FORMAT(created_at, '%a') as day, COUNT(*) as count")
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->pluck('count', 'day');

        $series = $days->map(function ($day) use ($stats) {
            return (int)$stats->get($day, 0);
        });

        return [
            'labels' => $days->toArray(),
            'series' => $series->toArray()
        ];
    }

    private function getWeeklyRevenue()
    {
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('D'));
        $stats = DB::table('payments')
            ->selectRaw("DATE_FORMAT(payment_date, '%a') as day, SUM(amount) as total")
            ->where('payment_date', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'labels' => $days->toArray(),
            'series' => $days->map(fn($d) => (float)$stats->get($d, 0))->toArray()
        ];
    }

    private function getWeeklyTeachers()
    {
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('D'));
        $stats = DB::table('teachers')
            ->selectRaw("DATE_FORMAT(created_at, '%a') as day, COUNT(*) as count")
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->pluck('count', 'day');

        return [
            'labels' => $days->toArray(),
            'series' => $days->map(fn($d) => (int)$stats->get($d, 0))->toArray()
        ];
    }

    private function getWeeklyGroups()
    {
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('D'));
        $stats = DB::table('groups')
            ->selectRaw("DATE_FORMAT(created_at, '%a') as day, COUNT(*) as count")
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->pluck('count', 'day');

        return [
            'labels' => $days->toArray(),
            'series' => $days->map(fn($d) => (int)$stats->get($d, 0))->toArray()
        ];
    }
}
