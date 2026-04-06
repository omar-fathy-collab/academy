<?php

namespace App\Services;

use App\DTOs\ReportFilterDTO;
use App\Repositories\CapitalRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SalaryRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinancialReportService
{
    private $paymentRepo;

    private $expenseRepo;

    private $salaryRepo;

    private $capitalRepo;

    public function __construct(
        PaymentRepository $paymentRepo,
        ExpenseRepository $expenseRepo,
        SalaryRepository $salaryRepo,
        CapitalRepository $capitalRepo
    ) {
        $this->paymentRepo = $paymentRepo;
        $this->expenseRepo = $expenseRepo;
        $this->salaryRepo = $salaryRepo;
        $this->capitalRepo = $capitalRepo;
    }

    public function generateDailyReport(ReportFilterDTO $filter): array
    {
        $date = $filter->date ?? now()->format('Y-m-d');
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        return [
            'date' => $date,
            'revenue' => $this->getDailyRevenue($startDate, $endDate),
            'expenses' => $this->getDailyExpenses($startDate, $endDate),
            'salaries' => $this->getDailySalaries($startDate, $endDate),
            'capital_changes' => $this->getDailyCapitalChanges($startDate, $endDate),
            'summary' => $this->calculateDailySummary($startDate, $endDate),
            'comparison' => $this->getDayComparison($date),
            'suggestions' => $this->getDateSuggestions($startDate, $endDate),
        ];
    }

    private function getDailyRevenue(Carbon $startDate, Carbon $endDate): array
    {
        $payments = $this->paymentRepo->getBetweenDates($startDate, $endDate);

        return [
            'total' => $payments->sum('amount'),
            'by_method' => $payments->groupBy('payment_method')
                ->map(function ($group) {
                    return $group->sum('amount');
                }),
            'transactions' => $payments->take(50),
            'statistics' => [
                'average' => $payments->avg('amount'),
                'count' => $payments->count(),
                'max' => $payments->max('amount'),
                'min' => $payments->min('amount'),
            ],
        ];
    }

    private function getDailyExpenses(Carbon $startDate, Carbon $endDate): array
    {
        $expenses = $this->expenseRepo->getApprovedBetweenDates($startDate, $endDate);

        return [
            'total' => $expenses->sum('amount'),
            'by_category' => $expenses->groupBy('category')
                ->map(function ($group) {
                    return $group->sum('amount');
                }),
            'transactions' => $expenses->take(50),
            'statistics' => [
                'average' => $expenses->avg('amount'),
                'count' => $expenses->count(),
                'pending_count' => $this->expenseRepo->getPendingCount($startDate, $endDate),
            ],
        ];
    }

    private function getDailySalaries(Carbon $startDate, Carbon $endDate): array
    {
        $salaries = $this->salaryRepo->getBetweenDates($startDate, $endDate);

        return [
            'total' => $salaries->sum('net_salary'),
            'transactions' => $salaries->take(50),
            'statistics' => [
                'average' => $salaries->avg('net_salary'),
                'count' => $salaries->count(),
                'teachers_count' => $salaries->unique('teacher_id')->count(),
            ],
        ];
    }

    private function getDailyCapitalChanges(Carbon $startDate, Carbon $endDate): array
    {
        $capital = $this->capitalRepo->getBetweenDates($startDate, $endDate);

        return [
            'additions' => $capital->sum('amount'),
            'withdrawals' => 0, // Assuming no withdrawals table
            'transactions' => $capital->take(50),
            'net_change' => $capital->sum('amount'),
        ];
    }

    private function calculateDailySummary(Carbon $startDate, Carbon $endDate): array
    {
        $revenue = $this->paymentRepo->sumBetweenDates($startDate, $endDate);
        $expenses = $this->expenseRepo->sumApprovedBetweenDates($startDate, $endDate);
        $salaries = $this->salaryRepo->sumBetweenDates($startDate, $endDate);

        $totalOutgoing = $expenses + $salaries;
        $netCashFlow = $revenue - $totalOutgoing;

        return [
            'total_income' => $revenue,
            'total_expenses' => $expenses,
            'total_salaries' => $salaries,
            'total_outgoing' => $totalOutgoing,
            'net_cash_flow' => $netCashFlow,
            'transaction_counts' => [
                'payments' => $this->paymentRepo->countBetweenDates($startDate, $endDate),
                'expenses' => $this->expenseRepo->countBetweenDates($startDate, $endDate),
                'salaries' => $this->salaryRepo->countBetweenDates($startDate, $endDate),
            ],
            'profit_margin' => $revenue > 0 ? ($netCashFlow / $revenue) * 100 : 0,
        ];
    }

    private function getDayComparison(string $date): array
    {
        $currentDay = Carbon::parse($date);
        $previousDay = $currentDay->copy()->subDay();

        $currentRevenue = $this->paymentRepo->sumForDate($currentDay);
        $previousRevenue = $this->paymentRepo->sumForDate($previousDay);

        $change = $previousRevenue > 0
            ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100
            : ($currentRevenue > 0 ? 100 : 0);

        return [
            'previous_day' => $previousDay->format('Y-m-d'),
            'previous_revenue' => $previousRevenue,
            'current_revenue' => $currentRevenue,
            'change_percentage' => round($change, 2),
            'trend' => $change >= 0 ? 'up' : 'down',
        ];
    }

    private function getDateSuggestions(Carbon $startDate, Carbon $endDate): ?array
    {
        $hasData = $this->paymentRepo->countBetweenDates($startDate, $endDate) > 0
            || $this->expenseRepo->countBetweenDates($startDate, $endDate) > 0
            || $this->salaryRepo->countBetweenDates($startDate, $endDate) > 0;

        if ($hasData) {
            return null;
        }

        $latestDates = [
            'payment' => $this->paymentRepo->getLatestDate(),
            'expense' => $this->expenseRepo->getLatestDate(),
            'salary' => $this->salaryRepo->getLatestDate(),
        ];

        $validDates = array_filter($latestDates);

        if (empty($validDates)) {
            return null;
        }

        $latestDate = max($validDates);

        return [
            'suggested_date' => Carbon::parse($latestDate)->format('Y-m-d'),
            'message' => 'لا توجد بيانات للتاريخ المحدد. آخر تاريخ به بيانات: '.Carbon::parse($latestDate)->format('Y-m-d'),
            'data_sources' => array_keys(array_filter($latestDates, function ($date) use ($latestDate) {
                return $date === $latestDate;
            })),
        ];
    }

    public function generateWeeklyReport(ReportFilterDTO $filter): array
    {
        $startDate = Carbon::now()->setISODate($filter->year, $filter->week)->startOfWeek();
        $endDate = $startDate->copy()->endOfWeek();

        return [
            'period' => [
                'week' => $filter->week,
                'year' => $filter->year,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'revenue' => $this->paymentRepo->sumBetweenDates($startDate, $endDate),
            'expenses' => $this->expenseRepo->sumApprovedBetweenDates($startDate, $endDate),
            'salaries' => $this->salaryRepo->sumBetweenDates($startDate, $endDate),
            'daily_breakdown' => $this->getWeeklyDailyBreakdown($startDate),
            'summary' => $this->calculatePeriodSummary($startDate, $endDate),
            'comparison' => $this->getWeekComparison($filter->week, $filter->year),
        ];
    }

    private function getWeeklyDailyBreakdown(Carbon $startDate): array
    {
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->isoFormat('dddd'),
                'revenue' => $this->paymentRepo->sumBetweenDates($dayStart, $dayEnd),
                'expenses' => $this->expenseRepo->sumApprovedBetweenDates($dayStart, $dayEnd),
                'salaries' => $this->salaryRepo->sumBetweenDates($dayStart, $dayEnd),
                'transactions_count' => [
                    'payments' => $this->paymentRepo->countBetweenDates($dayStart, $dayEnd),
                    'expenses' => $this->expenseRepo->countBetweenDates($dayStart, $dayEnd),
                    'salaries' => $this->salaryRepo->countBetweenDates($dayStart, $dayEnd),
                ],
            ];
        }

        return $days;
    }

    private function calculatePeriodSummary(Carbon $startDate, Carbon $endDate): array
    {
        $revenue = $this->paymentRepo->sumBetweenDates($startDate, $endDate);
        $expenses = $this->expenseRepo->sumApprovedBetweenDates($startDate, $endDate);
        $salaries = $this->salaryRepo->sumBetweenDates($startDate, $endDate);

        $totalOutgoing = $expenses + $salaries;
        $netProfit = $revenue - $totalOutgoing;

        return [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'total_salaries' => $salaries,
            'total_outgoing' => $totalOutgoing,
            'net_profit' => $netProfit,
            'profit_margin' => $revenue > 0 ? ($netProfit / $revenue) * 100 : 0,
            'operating_efficiency' => $revenue > 0 ? ($totalOutgoing / $revenue) * 100 : 0,
        ];
    }

    private function getWeekComparison(int $week, int $year): array
    {
        $currentWeek = Carbon::now()->setISODate($year, $week);
        $previousWeek = $currentWeek->copy()->subWeek();

        $currentRevenue = $this->paymentRepo->sumForWeek($week, $year);
        $previousRevenue = $this->paymentRepo->sumForWeek(
            $previousWeek->isoWeek,
            $previousWeek->year
        );

        $change = $previousRevenue > 0
            ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100
            : ($currentRevenue > 0 ? 100 : 0);

        return [
            'previous_week' => $previousWeek->isoWeek,
            'previous_year' => $previousWeek->year,
            'previous_revenue' => $previousRevenue,
            'current_revenue' => $currentRevenue,
            'change_percentage' => round($change, 2),
            'trend' => $change >= 0 ? 'up' : 'down',
        ];
    }

    public function generateMonthlyReport(ReportFilterDTO $filter): array
    {
        $startDate = Carbon::create($filter->year, $filter->month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return [
            'period' => [
                'month' => $filter->month,
                'year' => $filter->year,
                'month_name' => $startDate->translatedFormat('F'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'revenue' => $this->getMonthlyRevenue($startDate, $endDate),
            'expenses' => $this->getMonthlyExpenses($startDate, $endDate),
            'salaries' => $this->getMonthlySalaries($startDate, $endDate),
            'weekly_breakdown' => $this->getMonthlyWeeklyBreakdown($startDate),
            'category_analysis' => $this->getMonthlyCategoryAnalysis($startDate, $endDate),
            'summary' => $this->calculatePeriodSummary($startDate, $endDate),
            'comparison' => $this->getMonthComparison($filter->month, $filter->year),
        ];
    }

    private function getMonthlyRevenue(Carbon $startDate, Carbon $endDate): array
    {
        $payments = $this->paymentRepo->getBetweenDates($startDate, $endDate);

        return [
            'total' => $payments->sum('amount'),
            'by_method' => $payments->groupBy('payment_method')
                ->map(function ($group) {
                    return [
                        'total' => $group->sum('amount'),
                        'count' => $group->count(),
                        'percentage' => $payments->sum('amount') > 0
                            ? ($group->sum('amount') / $payments->sum('amount')) * 100
                            : 0,
                    ];
                }),
            'transactions' => $payments->take(100),
            'daily_average' => $payments->sum('amount') / $startDate->diffInDays($endDate) + 1,
            'peak_day' => $this->findPeakDay($startDate, $endDate, 'payments'),
        ];
    }

    private function getMonthlyExpenses(Carbon $startDate, Carbon $endDate): array
    {
        $expenses = $this->expenseRepo->getApprovedBetweenDates($startDate, $endDate);

        return [
            'total' => $expenses->sum('amount'),
            'by_category' => $expenses->groupBy('category')
                ->map(function ($group) use ($expenses) {
                    return [
                        'total' => $group->sum('amount'),
                        'count' => $group->count(),
                        'percentage' => $expenses->sum('amount') > 0
                            ? ($group->sum('amount') / $expenses->sum('amount')) * 100
                            : 0,
                    ];
                }),
            'transactions' => $expenses->take(100),
            'pending_amount' => $this->expenseRepo->sumPendingBetweenDates($startDate, $endDate),
            'approval_rate' => $this->calculateApprovalRate($startDate, $endDate),
        ];
    }

    private function calculateApprovalRate(Carbon $startDate, Carbon $endDate): float
    {
        $approvedCount = $this->expenseRepo->countApprovedBetweenDates($startDate, $endDate);
        $totalCount = $this->expenseRepo->countBetweenDates($startDate, $endDate);

        return $totalCount > 0 ? ($approvedCount / $totalCount) * 100 : 0;
    }

    private function findPeakDay(Carbon $startDate, Carbon $endDate, string $type): array
    {
        $peakDay = null;
        $peakAmount = 0;

        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayAmount = $this->{"{$type}Repo"}->sumForDate($current);

            if ($dayAmount > $peakAmount) {
                $peakAmount = $dayAmount;
                $peakDay = $current->copy();
            }

            $current->addDay();
        }

        return $peakDay ? [
            'date' => $peakDay->format('Y-m-d'),
            'day_name' => $peakDay->isoFormat('dddd'),
            'amount' => $peakAmount,
        ] : [];
    }

    private function getMonthlyWeeklyBreakdown(Carbon $startDate): array
    {
        $weeks = [];
        $current = $startDate->copy();
        $endDate = $startDate->copy()->endOfMonth();

        $weekNumber = 1;

        while ($current <= $endDate) {
            $weekStart = $current->copy();
            $weekEnd = $current->copy()->addDays(6);

            if ($weekEnd > $endDate) {
                $weekEnd = $endDate;
            }

            $weeks[] = [
                'week_number' => $weekNumber,
                'start_date' => $weekStart->format('Y-m-d'),
                'end_date' => $weekEnd->format('Y-m-d'),
                'revenue' => $this->paymentRepo->sumBetweenDates($weekStart, $weekEnd),
                'expenses' => $this->expenseRepo->sumApprovedBetweenDates($weekStart, $weekEnd),
                'salaries' => $this->salaryRepo->sumBetweenDates($weekStart, $weekEnd),
                'net_profit' => $this->paymentRepo->sumBetweenDates($weekStart, $weekEnd)
                    - ($this->expenseRepo->sumApprovedBetweenDates($weekStart, $weekEnd)
                    + $this->salaryRepo->sumBetweenDates($weekStart, $weekEnd)),
            ];

            $current->addDays(7);
            $weekNumber++;
        }

        return $weeks;
    }

    private function getMonthlyCategoryAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'revenue_sources' => $this->getRevenueSources($startDate, $endDate),
            'expense_categories' => $this->getExpenseCategories($startDate, $endDate),
            'salary_distribution' => $this->getSalaryDistribution($startDate, $endDate),
        ];
    }

    private function getRevenueSources(Carbon $startDate, Carbon $endDate): Collection
    {
        $payments = $this->paymentRepo->getWithInvoiceDetails($startDate, $endDate);

        return $payments->groupBy(function ($payment) {
            return $payment->invoice->group->course->course_name ?? 'Unknown';
        })->map(function ($payments) {
            return [
                'total' => $payments->sum('amount'),
                'count' => $payments->count(),
                'average' => $payments->avg('amount'),
            ];
        })->sortByDesc('total');
    }

    private function getExpenseCategories(Carbon $startDate, Carbon $endDate): Collection
    {
        $expenses = $this->expenseRepo->getApprovedBetweenDates($startDate, $endDate);

        return $expenses->groupBy('category')->map(function ($expenses) {
            return [
                'total' => $expenses->sum('amount'),
                'count' => $expenses->count(),
                'average' => $expenses->avg('amount'),
            ];
        })->sortByDesc('total');
    }

    private function getSalaryDistribution(Carbon $startDate, Carbon $endDate): Collection
    {
        $salaries = $this->salaryRepo->getWithTeacherDetails($startDate, $endDate);

        return $salaries->groupBy('teacher.teacher_name')->map(function ($salaries) {
            return [
                'total' => $salaries->sum('net_salary'),
                'count' => $salaries->count(),
                'average' => $salaries->avg('net_salary'),
            ];
        })->sortByDesc('total');
    }

    public function generateAnnualReport(ReportFilterDTO $filter): array
    {
        $startDate = Carbon::create($filter->year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        return [
            'period' => [
                'year' => $filter->year,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'revenue' => $this->getAnnualRevenue($startDate, $endDate),
            'expenses' => $this->getAnnualExpenses($startDate, $endDate),
            'salaries' => $this->getAnnualSalaries($startDate, $endDate),
            'monthly_breakdown' => $this->getAnnualMonthlyBreakdown($startDate),
            'quarterly_analysis' => $this->getQuarterlyAnalysis($startDate, $endDate),
            'trends' => $this->getAnnualTrends($filter->year),
            'summary' => $this->calculatePeriodSummary($startDate, $endDate),
            'comparison' => $this->getYearComparison($filter->year),
        ];
    }

    private function getAnnualMonthlyBreakdown(Carbon $startDate): array
    {
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $months[] = [
                'month' => $monthStart->format('F'),
                'month_number' => $monthStart->month,
                'revenue' => $this->paymentRepo->sumBetweenDates($monthStart, $monthEnd),
                'expenses' => $this->expenseRepo->sumApprovedBetweenDates($monthStart, $monthEnd),
                'salaries' => $this->salaryRepo->sumBetweenDates($monthStart, $monthEnd),
                'profit' => $this->paymentRepo->sumBetweenDates($monthStart, $monthEnd)
                    - ($this->expenseRepo->sumApprovedBetweenDates($monthStart, $monthEnd)
                    + $this->salaryRepo->sumBetweenDates($monthStart, $monthEnd)),
                'profit_margin' => $this->calculateMonthProfitMargin($monthStart, $monthEnd),
            ];
        }

        return $months;
    }

    private function calculateMonthProfitMargin(Carbon $startDate, Carbon $endDate): float
    {
        $revenue = $this->paymentRepo->sumBetweenDates($startDate, $endDate);
        $expenses = $this->expenseRepo->sumApprovedBetweenDates($startDate, $endDate);
        $salaries = $this->salaryRepo->sumBetweenDates($startDate, $endDate);
        $profit = $revenue - ($expenses + $salaries);

        return $revenue > 0 ? ($profit / $revenue) * 100 : 0;
    }

    private function getQuarterlyAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $quarters = [];

        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $quarterStart = $startDate->copy()->addMonths(($quarter - 1) * 3)->startOfMonth();
            $quarterEnd = $quarterStart->copy()->addMonths(2)->endOfMonth();

            if ($quarterEnd > $endDate) {
                $quarterEnd = $endDate;
            }

            $quarters[] = [
                'quarter' => $quarter,
                'start_date' => $quarterStart->format('Y-m-d'),
                'end_date' => $quarterEnd->format('Y-m-d'),
                'revenue' => $this->paymentRepo->sumBetweenDates($quarterStart, $quarterEnd),
                'expenses' => $this->expenseRepo->sumApprovedBetweenDates($quarterStart, $quarterEnd),
                'salaries' => $this->salaryRepo->sumBetweenDates($quarterStart, $quarterEnd),
                'profit' => $this->paymentRepo->sumBetweenDates($quarterStart, $quarterEnd)
                    - ($this->expenseRepo->sumApprovedBetweenDates($quarterStart, $quarterEnd)
                    + $this->salaryRepo->sumBetweenDates($quarterStart, $quarterEnd)),
            ];
        }

        return $quarters;
    }

    private function getAnnualTrends(int $year): array
    {
        $currentYear = $year;
        $previousYear = $year - 1;

        $currentYearData = $this->getYearlyTrendData($currentYear);
        $previousYearData = $this->getYearlyTrendData($previousYear);

        return [
            'revenue_growth' => $this->calculateGrowthRate(
                $previousYearData['revenue'],
                $currentYearData['revenue']
            ),
            'expense_growth' => $this->calculateGrowthRate(
                $previousYearData['expenses'],
                $currentYearData['expenses']
            ),
            'salary_growth' => $this->calculateGrowthRate(
                $previousYearData['salaries'],
                $currentYearData['salaries']
            ),
            'profit_growth' => $this->calculateGrowthRate(
                $previousYearData['profit'],
                $currentYearData['profit']
            ),
        ];
    }

    private function getYearlyTrendData(int $year): array
    {
        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        $revenue = $this->paymentRepo->sumBetweenDates($startDate, $endDate);
        $expenses = $this->expenseRepo->sumApprovedBetweenDates($startDate, $endDate);
        $salaries = $this->salaryRepo->sumBetweenDates($startDate, $endDate);
        $profit = $revenue - ($expenses + $salaries);

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'salaries' => $salaries,
            'profit' => $profit,
        ];
    }

    private function calculateGrowthRate(float $previous, float $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / abs($previous)) * 100;
    }

    public function generateOverallReport(ReportFilterDTO $filter): array
    {
        $firstRecord = $this->getFirstTransactionDate();
        $startDate = $firstRecord ? Carbon::parse($firstRecord) : now()->startOfYear();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'duration_days' => $startDate->diffInDays(now()),
            ],
            'totals' => $this->getOverallTotals(),
            'profit_loss' => $this->calculateOverallProfitLoss(),
            'capital_summary' => $this->getCapitalSummary(),
            'debt_summary' => $this->getDebtSummary(),
            'financial_ratios' => $this->calculateFinancialRatios(),
            'trends' => $this->getOverallTrends(),
            'period_analysis' => $this->getPeriodAnalysis($startDate),
        ];
    }

    private function getFirstTransactionDate(): ?string
    {
        $dates = [
            $this->paymentRepo->getEarliestDate(),
            $this->expenseRepo->getEarliestDate(),
            $this->salaryRepo->getEarliestDate(),
        ];

        $validDates = array_filter($dates);

        return empty($validDates) ? null : min($validDates);
    }

    private function getOverallTotals(): array
    {
        return [
            'total_revenue' => $this->paymentRepo->getTotalSum(),
            'total_expenses' => $this->expenseRepo->getTotalApprovedSum(),
            'total_salaries' => $this->salaryRepo->getTotalSum(),
            'total_transactions' => [
                'payments' => $this->paymentRepo->getTotalCount(),
                'expenses' => $this->expenseRepo->getTotalCount(),
                'salaries' => $this->salaryRepo->getTotalCount(),
            ],
            'average_transaction' => [
                'payment' => $this->paymentRepo->getAverageAmount(),
                'expense' => $this->expenseRepo->getAverageAmount(),
                'salary' => $this->salaryRepo->getAverageAmount(),
            ],
        ];
    }

    private function calculateOverallProfitLoss(): array
    {
        $revenue = $this->paymentRepo->getTotalSum();
        $expenses = $this->expenseRepo->getTotalApprovedSum();
        $salaries = $this->salaryRepo->getTotalSum();

        $totalCosts = $expenses + $salaries;
        $netProfit = $revenue - $totalCosts;
        $profitMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

        return [
            'gross_revenue' => $revenue,
            'total_costs' => $totalCosts,
            'net_profit' => $netProfit,
            'profit_margin' => round($profitMargin, 2),
            'break_even_point' => $this->calculateBreakEvenPoint($revenue, $totalCosts),
            'roi' => $this->calculateROI($revenue, $totalCosts),
        ];
    }

    private function calculateBreakEvenPoint(float $revenue, float $costs): float
    {
        return $revenue > 0 ? ($costs / $revenue) * 100 : 0;
    }

    private function calculateROI(float $revenue, float $costs): float
    {
        return $costs > 0 ? (($revenue - $costs) / $costs) * 100 : 0;
    }

    private function getCapitalSummary(): array
    {
        $capital = $this->capitalRepo->getAll();

        return [
            'total_additions' => $capital->sum('amount'),
            'total_withdrawals' => 0, // Assuming no withdrawals
            'net_capital' => $capital->sum('amount'),
            'transactions_count' => $capital->count(),
            'largest_addition' => $capital->max('amount'),
            'average_addition' => $capital->avg('amount'),
        ];
    }

    private function getDebtSummary(): array
    {
        // Assuming you have Invoice model with debt calculation
        $invoices = \App\Models\Invoice::all();

        $totalDebt = $invoices->sum(function ($invoice) {
            return max(0, $invoice->amount - $invoice->amount_paid);
        });

        $debtorsCount = $invoices->filter(function ($invoice) {
            return $invoice->amount_paid < $invoice->amount;
        })->count();

        return [
            'total_debt' => $totalDebt,
            'debtors_count' => $debtorsCount,
            'average_debt' => $debtorsCount > 0 ? $totalDebt / $debtorsCount : 0,
            'debt_to_revenue_ratio' => $this->paymentRepo->getTotalSum() > 0
                ? ($totalDebt / $this->paymentRepo->getTotalSum()) * 100
                : 0,
        ];
    }

    private function calculateFinancialRatios(): array
    {
        $revenue = $this->paymentRepo->getTotalSum();
        $expenses = $this->expenseRepo->getTotalApprovedSum();
        $salaries = $this->salaryRepo->getTotalSum();
        $totalCosts = $expenses + $salaries;
        $netProfit = $revenue - $totalCosts;

        return [
            'expense_to_revenue_ratio' => $revenue > 0 ? ($expenses / $revenue) * 100 : 0,
            'salary_to_revenue_ratio' => $revenue > 0 ? ($salaries / $revenue) * 100 : 0,
            'profit_margin' => $revenue > 0 ? ($netProfit / $revenue) * 100 : 0,
            'operating_efficiency' => $revenue > 0 ? ($totalCosts / $revenue) * 100 : 0,
            'current_ratio' => $this->calculateCurrentRatio(),
            'quick_ratio' => $this->calculateQuickRatio(),
        ];
    }

    private function calculateCurrentRatio(): float
    {
        // Simplified current ratio calculation
        // In a real system, you would fetch actual assets and liabilities
        $assets = $this->paymentRepo->getTotalSum() + $this->capitalRepo->getTotalSum();
        $liabilities = $this->getDebtSummary()['total_debt'];

        return $liabilities > 0 ? $assets / $liabilities : 0;
    }

    private function calculateQuickRatio(): float
    {
        // Simplified quick ratio (acid-test ratio)
        $quickAssets = $this->paymentRepo->getTotalSum(); // Assuming payments are liquid
        $currentLiabilities = $this->getDebtSummary()['total_debt'];

        return $currentLiabilities > 0 ? $quickAssets / $currentLiabilities : 0;
    }

    private function getOverallTrends(): array
    {
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        $currentData = $this->getYearlyTrendData($currentYear);
        $previousData = $this->getYearlyTrendData($previousYear);

        return [
            'revenue_trend' => $this->calculateTrend($previousData['revenue'], $currentData['revenue']),
            'expense_trend' => $this->calculateTrend($previousData['expenses'], $currentData['expenses']),
            'profit_trend' => $this->calculateTrend($previousData['profit'], $currentData['profit']),
            'growth_rate' => $this->calculateCAGR($previousData['revenue'], $currentData['revenue']),
            'monthly_average' => [
                'revenue' => $currentData['revenue'] / 12,
                'expenses' => $currentData['expenses'] / 12,
                'profit' => $currentData['profit'] / 12,
            ],
        ];
    }

    private function calculateTrend(float $previous, float $current): string
    {
        if ($previous == 0) {
            return $current > 0 ? 'rising' : 'stable';
        }

        $change = (($current - $previous) / abs($previous)) * 100;

        if ($change > 10) {
            return 'strongly_rising';
        } elseif ($change > 0) {
            return 'rising';
        } elseif ($change > -10) {
            return 'stable';
        } else {
            return 'declining';
        }
    }

    private function calculateCAGR(float $beginningValue, float $endingValue, int $years = 1): float
    {
        if ($beginningValue == 0) {
            return 0;
        }

        return pow(($endingValue / $beginningValue), (1 / $years)) - 1;
    }

    private function getPeriodAnalysis(Carbon $startDate): array
    {
        $months = $startDate->diffInMonths(now());

        if ($months == 0) {
            $months = 1;
        }

        $revenue = $this->paymentRepo->getTotalSum();
        $expenses = $this->expenseRepo->getTotalApprovedSum();
        $salaries = $this->salaryRepo->getTotalSum();

        return [
            'period_months' => $months,
            'monthly_averages' => [
                'revenue' => $revenue / $months,
                'expenses' => $expenses / $months,
                'salaries' => $salaries / $months,
                'profit' => ($revenue - ($expenses + $salaries)) / $months,
            ],
            'best_month' => $this->findBestMonth($startDate),
            'worst_month' => $this->findWorstMonth($startDate),
            'seasonality' => $this->analyzeSeasonality($startDate),
        ];
    }

    private function findBestMonth(Carbon $startDate): array
    {
        $bestMonth = null;
        $bestProfit = 0;

        $current = $startDate->copy();

        while ($current <= now()) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $revenue = $this->paymentRepo->sumBetweenDates($monthStart, $monthEnd);
            $expenses = $this->expenseRepo->sumApprovedBetweenDates($monthStart, $monthEnd);
            $salaries = $this->salaryRepo->sumBetweenDates($monthStart, $monthEnd);
            $profit = $revenue - ($expenses + $salaries);

            if ($profit > $bestProfit) {
                $bestProfit = $profit;
                $bestMonth = $current->copy();
            }

            $current->addMonth();
        }

        return $bestMonth ? [
            'month' => $bestMonth->format('F Y'),
            'profit' => $bestProfit,
            'revenue' => $this->paymentRepo->sumBetweenDates(
                $bestMonth->copy()->startOfMonth(),
                $bestMonth->copy()->endOfMonth()
            ),
        ] : [];
    }

    private function findWorstMonth(Carbon $startDate): array
    {
        $worstMonth = null;
        $worstProfit = PHP_FLOAT_MAX;

        $current = $startDate->copy();

        while ($current <= now()) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $revenue = $this->paymentRepo->sumBetweenDates($monthStart, $monthEnd);
            $expenses = $this->expenseRepo->sumApprovedBetweenDates($monthStart, $monthEnd);
            $salaries = $this->salaryRepo->sumBetweenDates($monthStart, $monthEnd);
            $profit = $revenue - ($expenses + $salaries);

            if ($profit < $worstProfit) {
                $worstProfit = $profit;
                $worstMonth = $current->copy();
            }

            $current->addMonth();
        }

        return $worstMonth ? [
            'month' => $worstMonth->format('F Y'),
            'profit' => $worstProfit,
            'revenue' => $this->paymentRepo->sumBetweenDates(
                $worstMonth->copy()->startOfMonth(),
                $worstMonth->copy()->endOfMonth()
            ),
        ] : [];
    }

    private function analyzeSeasonality(Carbon $startDate): array
    {
        $seasonality = [];
        $monthlyProfits = [];

        $current = $startDate->copy();

        while ($current <= now()) {
            $monthNumber = $current->month;

            if (! isset($monthlyProfits[$monthNumber])) {
                $monthlyProfits[$monthNumber] = [];
            }

            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $revenue = $this->paymentRepo->sumBetweenDates($monthStart, $monthEnd);
            $expenses = $this->expenseRepo->sumApprovedBetweenDates($monthStart, $monthEnd);
            $salaries = $this->salaryRepo->sumBetweenDates($monthStart, $monthEnd);
            $profit = $revenue - ($expenses + $salaries);

            $monthlyProfits[$monthNumber][] = $profit;

            $current->addMonth();
        }

        foreach ($monthlyProfits as $month => $profits) {
            if (count($profits) > 0) {
                $seasonality[$month] = [
                    'month_name' => Carbon::create()->month($month)->format('F'),
                    'average_profit' => array_sum($profits) / count($profits),
                    'profit_count' => count($profits),
                    'trend' => $this->analyzeProfitTrend($profits),
                ];
            }
        }

        return $seasonality;
    }

    private function analyzeProfitTrend(array $profits): string
    {
        if (count($profits) < 2) {
            return 'insufficient_data';
        }

        $first = $profits[0];
        $last = $profits[count($profits) - 1];

        if ($last > $first * 1.2) {
            return 'improving';
        } elseif ($last < $first * 0.8) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    public function prepareExportData(ReportFilterDTO $filter): array
    {
        $reportData = $this->generateReport($filter->type, $filter);

        return [
            'report_data' => $reportData,
            'export_info' => [
                'exported_at' => now()->toIso8601String(),
                'exported_by' => auth()->user()->name ?? 'System',
                'filters' => $filter->toArray(),
                'format' => 'excel',
                'version' => '1.0',
            ],
        ];
    }

    private function generateReport(string $type, ReportFilterDTO $filter): array
    {
        return match ($type) {
            'daily' => $this->generateDailyReport($filter),
            'weekly' => $this->generateWeeklyReport($filter),
            'monthly' => $this->generateMonthlyReport($filter),
            'annual' => $this->generateAnnualReport($filter),
            'overall' => $this->generateOverallReport($filter),
            default => throw new \InvalidArgumentException("Invalid report type: {$type}")
        };
    }
}
