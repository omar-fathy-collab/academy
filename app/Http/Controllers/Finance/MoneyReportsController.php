<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoneyReportsController extends Controller
{
    /**
     * عرض صفحة التقارير المالية الرئيسية
     */
    public function index()
    {
        return view('reports.money.index');
    }

    public function getReportByType($type, Request $request)
    {
        try {
            \Log::info("Fetching report type: {$type}", $request->all());

            switch ($type) {
                case 'daily':
                    return $this->dailyReport($request);
                case 'weekly':
                    return $this->weeklyReport($request);
                case 'monthly':
                    return $this->monthlyReport($request);
                case 'annual':
                    return $this->annualReport($request);
                case 'overall':
                    return $this->overallReport($request);
                default:
                    return response()->json([
                        'error' => 'Invalid report type',
                        'message' => 'Report type must be: daily, weekly, monthly, annual, or overall',
                    ], 400);
            }
        } catch (\Exception $e) {
            \Log::error('Error in getReportByType: '.$e->getMessage());

            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**ن
     * التحقق من وجود الجداول المطلوبة في قاعدة البيانات
     */
    private function checkTablesExist()
    {
        $requiredTables = ['payments', 'expenses', 'salaries'];
        $missingTables = [];
        $existingTables = [];

        try {
            $tables = DB::select('SHOW TABLES');
            $existingTableNames = array_map(function ($table) {
                return array_values((array) $table)[0];
            }, $tables);

            foreach ($requiredTables as $table) {
                if (in_array($table, $existingTableNames)) {
                    $existingTables[] = $table;
                } else {
                    $missingTables[] = $table;
                }
            }

            $capitalFound = in_array('capital', $existingTableNames) || in_array('capital_additions', $existingTableNames);
            if ($capitalFound) {
                $existingTables[] = in_array('capital_additions', $existingTableNames) ? 'capital_additions' : 'capital';
            } else {
                $missingTables[] = 'capital';
            }

            return [
                'success' => empty($missingTables),
                'existing_tables' => $existingTables,
                'missing_tables' => $missingTables,
                'total_tables' => count($existingTableNames),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'existing_tables' => [],
                'missing_tables' => array_merge($requiredTables, ['capital']),
            ];
        }
    }

    /**
     * جلب البيانات المالية اليومية
     */
    public function dailyReport(Request $request)
    {
        try {
            $date = $request->get('date', date('Y-m-d'));

            if (! strtotime($date)) {
                $date = date('Y-m-d');
            }

            $startDate = Carbon::parse($date)->startOfDay();
            $endDate = Carbon::parse($date)->endOfDay();

            // جلب الإيرادات اليومية
            $revenueData = $this->getDailyRevenue($startDate, $endDate);

            // جلب المصروفات اليومية
            $expensesData = $this->getDailyExpenses($startDate, $endDate);

            // جلب الرواتب اليومية
            $salariesData = $this->getDailySalaries($startDate, $endDate);

            // حساب صافي التدفق النقدي
            $netCashFlow = $revenueData['total'] - ($expensesData['total'] + $salariesData['total']);

            // تحويل البيانات لـ JSON صحيح
            $byMethod = $revenueData['by_method'] ?? [];
            $byCategory = $expensesData['by_category'] ?? [];

            $data = [
                'success' => true,
                'report_type' => 'daily',
                'date' => $date,
                'revenue' => [
                    'total' => $revenueData['total'] ?? 0,
                    'count' => count($revenueData['transactions'] ?? []),
                    'avg' => count($revenueData['transactions'] ?? []) > 0
                        ? round($revenueData['total'] / count($revenueData['transactions']), 2)
                        : 0,
                    'by_method' => is_array($byMethod) ? $byMethod : $byMethod->toArray(),
                    'transactions' => $revenueData['transactions'] ?? [],
                ],
                'expenses' => [
                    'total' => $expensesData['total'] ?? 0,
                    'by_category' => is_array($byCategory) ? $byCategory : $byCategory->toArray(),
                    'transactions' => $expensesData['transactions'] ?? [],
                ],
                'salaries' => [
                    'total' => $salariesData['total'] ?? 0,
                    'transactions' => $salariesData['transactions'] ?? [],
                ],
                'summary' => [
                    'net_cash_flow' => $netCashFlow,
                ],
                'previous' => [
                    'revenue' => $this->getPreviousDayRevenue($date),
                ],
            ];

            return response()->json($data);

        } catch (\Exception $e) {
            \Log::error('Error in dailyReport: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load daily report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب إيرادات اليوم السابق للمقارنة
     */
    private function getPreviousDayRevenue($currentDate)
    {
        try {
            $previousDate = Carbon::parse($currentDate)->subDay();
            $startDate = $previousDate->startOfDay();
            $endDate = $previousDate->endOfDay();

            return Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * جلب البيانات المالية الأسبوعية
     */
    public function weeklyReport(Request $request)
    {
        try {
            $week = $request->get('week', date('W'));
            $year = $request->get('year', date('Y'));

            \Log::info("Weekly report requested - Year: {$year}, Week: {$week}");

            // تأكد من أن الأسبوع هو رقم صحيح
            $week = (int) $week;
            $year = (int) $year;

            // حساب تواريخ الأسبوع
            $startDate = Carbon::create($year, 1, 1);
            $startDate->setISODate($year, $week)->startOfWeek();
            $endDate = $startDate->copy()->endOfWeek();

            \Log::info("Week dates - Start: {$startDate->format('Y-m-d')}, End: {$endDate->format('Y-m-d')}");

            // جلب التوزيع اليومي باستخدام الوظيفة المحسنة
            $dailyBreakdown = $this->getWeeklyDailyBreakdownEnhanced($startDate);

            // جلب إجماليات الأداء باستخدام استعلامات مباشرة
            $totalRevenue = Payment::whereBetween('payment_date', [$startDate, $endDate])
                ->sum('amount') ?? 0;

            $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->where('is_approved', 1)
                ->sum('amount') ?? 0;

            $totalSalaries = Salary::whereBetween('payment_date', [$startDate, $endDate])
                ->sum('net_salary') ?? 0;

            \Log::info("Weekly totals - Revenue: {$totalRevenue}, Expenses: {$totalExpenses}, Salaries: {$totalSalaries}");

            // حساب إيرادات الأسبوع السابق
            $previousWeekRevenue = $this->getPreviousWeekRevenue($year, $week);
            $percentageChange = $previousWeekRevenue > 0 ?
                (($totalRevenue - $previousWeekRevenue) / $previousWeekRevenue * 100) : 0;

            $data = [
                'success' => true,
                'week' => $week,
                'year' => $year,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'revenue' => (float) $totalRevenue,
                'expenses' => (float) $totalExpenses,
                'salaries' => (float) $totalSalaries,
                'daily_breakdown' => $dailyBreakdown,
                'summary' => [
                    'net_profit' => (float) ($totalRevenue - ($totalExpenses + $totalSalaries)),
                ],
                'previous' => [
                    'revenue' => (float) $previousWeekRevenue,
                ],
                'percentage_change' => round($percentageChange, 2),
            ];

            // تسجيل البيانات للتحقق
            \Log::info('Weekly report data:', [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'daily_count' => count($dailyBreakdown),
                'percentage_change' => $percentageChange,
            ]);

            return response()->json($data);

        } catch (\Exception $e) {
            \Log::error('Error in weeklyReport: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error' => 'Failed to load weekly report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب إيرادات الأسبوع السابق
     */
    private function getPreviousWeekRevenue($year, $week)
    {
        try {
            $prevWeek = $week - 1;
            $prevYear = $year;

            if ($prevWeek < 1) {
                $prevWeek = 52;
                $prevYear = $year - 1;
            }

            \Log::info("Previous week calculation - Year: {$prevYear}, Week: {$prevWeek}");

            $startDate = Carbon::create($prevYear, 1, 1);
            $startDate->setISODate($prevYear, $prevWeek)->startOfWeek();
            $endDate = $startDate->copy()->endOfWeek();

            \Log::info("Previous week dates - Start: {$startDate->format('Y-m-d')}, End: {$endDate->format('Y-m-d')}");

            $previousRevenue = Payment::whereBetween('payment_date', [$startDate, $endDate])
                ->sum('amount') ?? 0;

            \Log::info("Previous week revenue: {$previousRevenue}");

            return $previousRevenue;
        } catch (\Exception $e) {
            \Log::error('Error in getPreviousWeekRevenue: '.$e->getMessage());

            return 0;
        }
    }

    private function getWeeklyDailyBreakdownEnhanced($startDate)
    {
        try {
            $days = [];

            // التأكد من أن startDate هو أول يوم في الأسبوع
            $startDate->startOfWeek();

            for ($i = 0; $i < 7; $i++) {
                $currentDate = $startDate->copy()->addDays($i);
                $dayStart = $currentDate->copy()->startOfDay();
                $dayEnd = $currentDate->copy()->endOfDay();

                // تسجيل الاستعلام للتحقق
                \Log::debug('Querying day: '.$dayStart->format('Y-m-d'));

                // جلب الإيرادات
                $revenue = Payment::whereBetween('payment_date', [$dayStart, $dayEnd])
                    ->sum('amount') ?? 0;

                // جلب المصروفات
                $expenses = Expense::whereBetween('expense_date', [$dayStart, $dayEnd])
                    ->where('is_approved', 1)
                    ->sum('amount') ?? 0;

                // جلب الرواتب
                $salaries = Salary::whereBetween('payment_date', [$dayStart, $dayEnd])
                    ->sum('net_salary') ?? 0;

                // حساب صافي اليوم
                $net = $revenue - ($expenses + $salaries);

                $days[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day_name' => $currentDate->format('l'),
                    'day_short' => $currentDate->format('D'),
                    'revenue' => (float) $revenue,
                    'expenses' => (float) $expenses,
                    'salaries' => (float) $salaries,
                    'net' => (float) $net,
                ];

                // تسجيل بيانات اليوم للتحقق
                \Log::debug('Day data: '.json_encode($days[$i]));
            }

            return $days;
        } catch (\Exception $e) {
            \Log::error('Error in getWeeklyDailyBreakdownEnhanced: '.$e->getMessage());

            return [];
        }
    }

    /**
     * جلب البيانات المالية الشهرية
     */
    public function monthlyReport(Request $request)
    {
        try {
            $month = $request->get('month', date('m'));
            $year = $request->get('year', date('Y'));

            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // جلب الإيرادات الشهرية
            $totalRevenue = $this->getPeriodRevenue($startDate, $endDate);

            // جلب المصروفات الشهرية
            $totalExpenses = $this->getPeriodExpenses($startDate, $endDate);

            // جلب الرواتب الشهرية
            $totalSalaries = $this->getPeriodSalaries($startDate, $endDate);

            // جلب التوزيع الأسبوعي
            $weeklyBreakdown = $this->getMonthlyWeeklyBreakdown($startDate);

            // حساب النسبة المئوية للتغير
            $previousRevenue = $this->getPreviousMonthRevenue($year, $month);
            $percentageChange = $previousRevenue > 0 ?
                (($totalRevenue - $previousRevenue) / $previousRevenue * 100) : 0;

            // التأكد من أن البيانات غير فارغة
            $categoryBreakdown = $this->getMonthlyCategoryBreakdown($startDate, $endDate);
            if (empty($categoryBreakdown)) {
                $categoryBreakdown = [
                    ['category' => 'غير مصنف', 'total' => 0],
                ];
            }

            $data = [
                'month' => (int) $month,
                'year' => (int) $year,
                'month_name' => $startDate->translatedFormat('F'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'revenue' => [
                    'total' => (float) $totalRevenue,
                ],
                'expenses' => [
                    'total' => (float) $totalExpenses,
                ],
                'salaries' => [
                    'total' => (float) $totalSalaries,
                ],
                'weekly_breakdown' => $weeklyBreakdown,
                'category_breakdown' => $categoryBreakdown,
                'summary' => [
                    'net_cash_flow' => $totalRevenue - ($totalExpenses + $totalSalaries),
                ],
                'previous' => [
                    'revenue' => (float) $previousRevenue,
                ],
                'percentage_change' => round($percentageChange, 2),
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error in monthlyReport: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to load monthly report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب إيرادات الشهر السابق
     */
    private function getPreviousMonthRevenue($year, $month)
    {
        try {
            $prevMonth = $month - 1;
            $prevYear = $year;

            if ($prevMonth < 1) {
                $prevMonth = 12;
                $prevYear = $year - 1;
            }

            $startDate = Carbon::create($prevYear, $prevMonth, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            return Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * جلب البيانات المالية السنوية
     */
    public function annualReport(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = $startDate->copy()->endOfYear();

        // جلب التوزيع الشهري
        $monthlyBreakdown = $this->getAnnualMonthlyBreakdown($startDate);

        // حساب الإجماليات من التوزيع الشهري
        $totalRevenue = collect($monthlyBreakdown)->sum('revenue');
        $totalExpenses = collect($monthlyBreakdown)->sum('expenses');
        $totalSalaries = collect($monthlyBreakdown)->sum('salaries');

        $data = [
            'year' => $year,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'revenue' => [
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'total' => $totalExpenses,
            ],
            'salaries' => [
                'total' => $totalSalaries,
            ],
            'monthly_breakdown' => $monthlyBreakdown,
            'category_breakdown' => $this->getAnnualCategoryBreakdown($startDate, $endDate),
            'summary' => [
                'net_profit' => $totalRevenue - ($totalExpenses + $totalSalaries),
            ],
            'previous' => [
                'revenue' => $this->getPreviousYearRevenue($year),
            ],
            'meta' => [
                'page' => 1,
                'total_pages' => 1,
            ],
        ];

        return response()->json($data);
    }

    /**
     * جلب إيرادات السنة السابقة
     */
    private function getPreviousYearRevenue($year)
    {
        try {
            $prevYear = $year - 1;
            $startDate = Carbon::create($prevYear, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();

            return Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * جلب التقرير المالي الكلي
     */
    public function overallReport(Request $request)
    {
        try {
            $tablesExist = $this->checkTablesExist();

            // إذا لم تكن الجداول موجودة، استخدم بيانات افتراضية بدلاً من إرجاع صفر
            if (! $tablesExist['success']) {
                \Log::warning('Some tables are missing: '.json_encode($tablesExist));
            }

            // إجمالي الإيرادات من جدول payments - استخدم SUM مع فحص NULL
            $totalRevenue = DB::table('invoices')->sum('amount_paid') ?? 0;

            // إجمالي المصروفات من جدول expenses - مع التحقق من الحقل الصحيح
            $totalExpenses = DB::table('expenses')
                ->where('is_approved', 1)
                ->sum('amount') ?? 0;
            $base_salaries = DB::table('teacher_payments')->sum('amount');

            $approved_adjustments = DB::table('teacher_adjustments')->sum('amount');

            // ✅ إجمالي ما تم دفعه للمعلمين يشمل الرواتب + التعديلات المدفوعة
            $total_salaries = floatval($base_salaries) + floatval($approved_adjustments);

            // إجمالي الرواتب من جدول salaries
            $totalSalaries = $total_salaries;

            // إجمالي رأس المال - تحقق من اسم الجدول الصحيح
            $totalCapital = 0;
            if (in_array('capital_additions', $tablesExist['existing_tables'])) {
                $totalCapital = DB::table('capital_additions')->sum('amount') ?? 0;
            } elseif (in_array('capital', $tablesExist['existing_tables'])) {
                $totalCapital = DB::table('capital')->sum('amount') ?? 0;
            }
            $withdrawls = DB::table('admin_withdrawals')
                ->where('status', 'completed')
                ->sum('amount') ?? 0;
            // حساب إجمالي التكاليف
            $totalCosts = $totalExpenses + $totalSalaries + $withdrawls;
            // حساب صافي الربح (الإيرادات - التكاليف)
            $therevenue = $totalRevenue + $totalCapital;
            $netProfit = $therevenue - $totalCosts;

            // حساب هامش الربح (بعد التحقق من عدم القسمة على صفر)
            $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

            // حساب النسب المالية
            $expenseToRevenueRatio = $totalRevenue > 0 ? ($totalExpenses / $totalRevenue) * 100 : 0;
            $salaryToRevenueRatio = $totalRevenue > 0 ? ($totalSalaries / $totalRevenue) * 100 : 0;

            // حساب صافي التدفق النقدي (بما في ذلك رأس المال)
            $netCashFlow = $netProfit + $totalCapital;

            // الحصول على أول وآخر تاريخ للبيانات
            $firstPayment = DB::table('payments')->orderBy('payment_date', 'asc')->first();
            $lastPayment = DB::table('payments')->orderBy('payment_date', 'desc')->first();

            $startDate = $firstPayment ? $firstPayment->payment_date : now()->format('Y-m-d');
            $endDate = $lastPayment ? $lastPayment->payment_date : now()->format('Y-m-d');

            $data = [
                'report_type' => 'Overall Financial Report',
                'period' => 'From inception to present',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_revenue' => (float) $totalRevenue,
                'total_expenses' => (float) $totalExpenses,
                'total_salaries' => (float) $totalSalaries,
                'total_capital' => (float) $totalCapital,
                'total_costs' => (float) $totalCosts,
                'profit_loss' => [
                    'gross_revenue' => (float) $totalRevenue,
                    'total_costs' => (float) $totalCosts,
                    'net_profit' => (float) $netProfit,
                    'profit_margin' => (float) $profitMargin,
                    'net_cash_flow_including_capital' => (float) $netCashFlow,
                ],
                'financial_ratios' => [
                    'expense_to_revenue_ratio' => (float) $expenseToRevenueRatio,
                    'salary_to_revenue_ratio' => (float) $salaryToRevenueRatio,
                    'profit_margin' => (float) $profitMargin,
                ],
                'meta' => [
                    'tables_status' => $tablesExist,
                    'calculation_method' => 'Standard calculation',
                    'currency' => 'EGP',
                ],
            ];

            \Log::info('Overall Report Generated:', [
                'revenue' => $totalRevenue,
                'expenses' => $totalExpenses,
                'salaries' => $totalSalaries,
                'capital' => $totalCapital,
                'net_profit' => $netProfit,
            ]);

            return response()->json($data);

        } catch (\Exception $e) {
            \Log::error('Error in overallReport: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to generate overall report',
                'message' => $e->getMessage(),
                'data' => [
                    'total_revenue' => 0,
                    'total_expenses' => 0,
                    'total_salaries' => 0,
                    'total_capital' => 0,
                    'total_costs' => 0,
                    'profit_loss' => [
                        'gross_revenue' => 0,
                        'total_costs' => 0,
                        'net_profit' => 0,
                        'profit_margin' => 0,
                        'net_cash_flow_including_capital' => 0,
                    ],
                    'financial_ratios' => [
                        'expense_to_revenue_ratio' => 0,
                        'salary_to_revenue_ratio' => 0,
                        'profit_margin' => 0,
                    ],
                    'meta' => [
                        'error' => true,
                        'message' => $e->getMessage(),
                    ],
                ],
            ], 500);
        }
    }
    // ==================== وظائف مساعدة محسنة ====================

    private function getDailyRevenue($startDate, $endDate)
    {
        try {
            // إجمالي الإيرادات
            $total = Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount') ?? 0;

            // الإيرادات حسب طريقة الدفع
            $byMethod = Payment::whereBetween('payment_date', [$startDate, $endDate])
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get()
                ->map(function ($item) {
                    return [
                        'payment_method' => $item->payment_method ?? 'غير معروف',
                        'total' => (float) ($item->total ?? 0),
                    ];
                });

            // المعاملات التفصيلية - إزالة الـ limit تماماً
            $transactions = Payment::whereBetween('payment_date', [$startDate, $endDate])
                ->with(['invoice' => function ($query) {
                    $query->with(['student.user', 'group']);
                }])
                ->orderBy('payment_date', 'desc')
                // تم إزالة limit هنا ↓
                ->get()
                ->map(function ($payment) {
                    // التحقق من payment_date
                    $paymentDate = $payment->payment_date;

                    if (is_string($paymentDate)) {
                        try {
                            $paymentDate = Carbon::parse($paymentDate);
                        } catch (\Exception $e) {
                            $paymentDate = null;
                        }
                    }

                    $formattedDate = $paymentDate instanceof Carbon
                        ? $paymentDate->format('Y-m-d H:i:s')
                        : (is_string($paymentDate) ? $paymentDate : null);

                    return [
                        'id' => $payment->payment_id,
                        'reference' => $payment->payment_reference,
                        'amount' => (float) $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'status' => $payment->status ?? 'paid',
                        'created_at' => $formattedDate,
                        'invoice' => $payment->invoice ? [
                            'id' => $payment->invoice->invoice_id,
                            'student' => $payment->invoice->student ? [
                                'user' => [
                                    'name' => $payment->invoice->student->user->name ?? 'غير معروف',
                                ],
                            ] : null,
                            'group' => $payment->invoice->group ? [
                                'group_name' => $payment->invoice->group->group_name,
                            ] : null,
                        ] : null,
                    ];
                })
                ->toArray();

            return [
                'total' => (float) $total,
                'by_method' => $byMethod,
                'transactions' => $transactions,
            ];
        } catch (\Exception $e) {
            \Log::error('Error in getDailyRevenue: '.$e->getMessage());

            return [
                'total' => 0,
                'by_method' => collect([]),
                'transactions' => [],
            ];
        }
    }

    private function getDailyExpenses($startDate, $endDate)
    {
        try {
            // إجمالي المصروفات
            $total = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->where('is_approved', 1)
                ->sum('amount') ?? 0;

            // المصروفات حسب الفئة
            $byCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->where('is_approved', 1)
                ->select('category', DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category ?? 'غير مصنف',
                        'total' => (float) ($item->total ?? 0),
                    ];
                });

            // المعاملات التفصيلية - إزالة limit
            $transactions = Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->where('is_approved', 1)
                ->orderBy('expense_date', 'desc')
                // تم إزالة limit هنا ↓
                ->get()
                ->map(function ($expense) {
                    // التحقق والتأكد من أن expense_date هو كائن Carbon
                    $expenseDate = $expense->expense_date;

                    // إذا كان نصًا، قم بتحويله إلى كائن Carbon
                    if (is_string($expenseDate)) {
                        try {
                            $expenseDate = Carbon::parse($expenseDate);
                        } catch (\Exception $e) {
                            $expenseDate = null;
                        }
                    }

                    // تنسيق التاريخ فقط إذا كان كائن Carbon صالح
                    $formattedDate = $expenseDate instanceof Carbon
                        ? $expenseDate->format('Y-m-d')
                        : (is_string($expenseDate) ? $expenseDate : null);

                    return [
                        'id' => $expense->expense_id,
                        'category' => $expense->category ?? 'غير مصنف',
                        'description' => $expense->description ?? 'لا يوجد وصف',
                        'amount' => (float) $expense->amount,
                        'status' => 'paid',
                        'date' => $formattedDate,
                        'created_at' => $formattedDate ? $formattedDate.' 00:00:00' : null,
                    ];
                })
                ->toArray();

            return [
                'total' => (float) $total,
                'by_category' => $byCategory,
                'transactions' => $transactions,
            ];
        } catch (\Exception $e) {
            \Log::error('Error in getDailyExpenses: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return [
                'total' => 0,
                'by_category' => collect([]),
                'transactions' => [],
            ];
        }
    }

    private function getDailySalaries($startDate, $endDate)
    {
        try {
            // إجمالي المدفوعات من جدول teacher_payments باستخدام DB facade
            $total = DB::table('teacher_payments')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->sum('amount') ?? 0;

            // المعاملات التفصيلية باستخدام query builder
            $payments = DB::table('teacher_payments')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->orderBy('payment_date', 'desc')
                ->get();

            $transactions = [];

            foreach ($payments as $payment) {
                // الحصول على اسم المعلم
                $teacher = DB::table('teachers')
                    ->where('teacher_id', $payment->teacher_id)
                    ->first();

                // الحصول على معلومات المجموعة إذا كان هناك salary_id
                $groupName = 'غير محدد';
                if ($payment->salary_id) {
                    $salary = DB::table('salaries')
                        ->where('salary_id', $payment->salary_id)
                        ->first();

                    if ($salary && $salary->group_id) {
                        $group = DB::table('groups')
                            ->where('group_id', $salary->group_id)
                            ->first();
                        $groupName = $group->group_name ?? 'غير محدد';
                    }
                }

                // تنسيق التاريخ
                $formattedDate = null;
                if ($payment->payment_date) {
                    try {
                        $formattedDate = Carbon::parse($payment->payment_date)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $formattedDate = substr($payment->payment_date, 0, 10);
                    }
                }

                $transactions[] = [
                    'id' => $payment->payment_id,
                    'teacher' => [
                        'teacher_name' => $teacher->teacher_name ?? 'غير معروف',
                    ],
                    'group' => [
                        'group_name' => $groupName,
                    ],
                    'net_salary' => (float) $payment->amount,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method ?? 'غير محدد',
                    'paid_at' => $formattedDate,
                    'date' => $formattedDate,
                    'notes' => $payment->notes,
                    'receipt_image' => $payment->receipt_image,
                ];
            }

            return [
                'total' => (float) $total,
                'transactions' => $transactions,
            ];
        } catch (\Exception $e) {
            \Log::error('Error in getDailySalaries: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return [
                'total' => 0,
                'transactions' => [],
            ];
        }
    }

    private function getPeriodRevenue($startDate, $endDate)
    {
        return Payment::whereBetween('payment_date', [$startDate, $endDate])->sum('amount') ?? 0;
    }

    private function getPeriodExpenses($startDate, $endDate)
    {
        return Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where('is_approved', 1)
            ->sum('amount') ?? 0;
    }

    private function getPeriodSalaries($startDate, $endDate)
    {
        return Salary::whereBetween('payment_date', [$startDate, $endDate])
            ->sum('net_salary') ?? 0;
    }

    private function getWeeklyDailyBreakdown($startDate)
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayStart = $date->startOfDay();
            $dayEnd = $date->endOfDay();

            $revenue = Payment::whereBetween('payment_date', [$dayStart, $dayEnd])->sum('amount') ?? 0;
            $expenses = Expense::whereBetween('expense_date', [$dayStart, $dayEnd])
                ->where('is_approved', 1)
                ->sum('amount') ?? 0;

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'revenue' => $revenue,
                'expenses' => $expenses,
            ];
        }

        return $days;
    }

    private function getMonthlyWeeklyBreakdown($startDate)
    {
        try {
            $weeks = [];
            $current = $startDate->copy();
            $endDate = $startDate->copy()->endOfMonth();

            $weekNumber = 1;
            while ($current <= $endDate) {
                $weekStart = $current->copy()->startOfDay();
                $weekEnd = $current->copy()->addDays(6)->endOfDay();
                if ($weekEnd > $endDate) {
                    $weekEnd = $endDate->copy()->endOfDay();
                }

                $revenue = Payment::whereBetween('payment_date', [$weekStart, $weekEnd])->sum('amount') ?? 0;
                $expenses = Expense::whereBetween('expense_date', [$weekStart, $weekEnd])
                    ->where('is_approved', 1)
                    ->sum('amount') ?? 0;

                $net = $revenue - $expenses;

                $weeks[] = [
                    'week_label' => 'أسبوع '.$weekNumber,
                    'week' => $weekNumber,
                    'start_date' => $weekStart->format('Y-m-d'),
                    'end_date' => $weekEnd->format('Y-m-d'),
                    'revenue' => (float) $revenue,
                    'expenses' => (float) $expenses,
                    'net' => (float) $net,
                ];

                $current->addDays(7);
                $weekNumber++;
            }

            return $weeks;
        } catch (\Exception $e) {
            \Log::error('Error in getMonthlyWeeklyBreakdown: '.$e->getMessage());

            return [];
        }
    }

    private function getAnnualMonthlyBreakdown($startDate)
    {
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $revenue = Payment::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount') ?? 0;
            $expenses = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])
                ->where('is_approved', 1)
                ->sum('amount') ?? 0;
            $salaries = Salary::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('net_salary') ?? 0;

            $months[] = [
                'month_label' => $monthStart->format('F'),
                'month' => $monthStart->format('m'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'salaries' => $salaries,
            ];
        }

        return $months;
    }

    private function getMonthlyCategoryBreakdown($startDate, $endDate)
    {
        try {
            return Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->where('is_approved', 1)
                ->select('category', DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category,
                        'total' => $item->total,
                    ];
                });
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getAnnualCategoryBreakdown($startDate, $endDate)
    {
        try {
            return Expense::whereBetween('expense_date', [$startDate, $endDate])
                ->where('is_approved', 1)
                ->select('category', DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category,
                        'total' => $item->total,
                    ];
                });
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Return a normalized transaction detail for frontend.
     */
    public function transactionDetail(Request $request)
    {
        $type = $request->get('type', 'payment');
        $id = $request->get('id');

        if (! $id) {
            return response()->json(['error' => 'Missing id'], 400);
        }

        try {
            switch ($type) {
                case 'expense':
                    $model = Expense::find($id);
                    break;
                case 'salary':
                    $model = Salary::find($id);
                    break;
                default:
                    $model = Payment::find($id);
                    break;
            }

            if (! $model) {
                return response()->json(['error' => 'Not found'], 404);
            }

            $tx = [
                'id' => $id,
                'type' => $type,
                'amount' => $model->amount ?? ($model->net_salary ?? 0),
                'reference' => $model->payment_reference ?? $model->reference ?? $model->invoice_number ?? null,
                'status' => $model->status ?? null,
                'date' => $model->payment_date ?? $model->expense_date ?? $model->created_at ?? null,
                'note' => $model->note ?? null,
                'items' => $model->items ?? [],
                'attachments' => $model->attachments ?? [],
                'payment_method' => $model->payment_method ?? null,
                'transaction_reference' => $model->transaction_reference ?? null,
                'updated_by' => $model->updated_by ?? null,
                'updated_at' => $model->updated_at ?? null,
            ];

            return response()->json(['transaction' => $tx]);
        } catch (\Exception $e) {
            \Log::error('Error fetching transaction detail: '.$e->getMessage());

            return response()->json(['error' => 'Failed to fetch transaction'], 500);
        }
    }

    /**
     * Change a transaction status
     */
    public function changeTransactionStatus(Request $request)
    {
        $this->validate($request, [
            'type' => 'required|string',
            'id' => 'required',
            'status' => 'required|string',
        ]);

        $type = $request->input('type');
        $id = $request->input('id');
        $status = $request->input('status');

        try {
            switch ($type) {
                case 'expense':
                    $model = Expense::find($id);
                    break;
                case 'salary':
                    $model = Salary::find($id);
                    break;
                default:
                    $model = Payment::find($id);
                    break;
            }

            if (! $model) {
                return response()->json(['success' => false, 'error' => 'Not found'], 404);
            }

            $model->status = $status;
            $model->save();

            return response()->json(['success' => true, 'status' => $status]);
        } catch (\Exception $e) {
            \Log::error('Error changing transaction status: '.$e->getMessage());

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * تصدير التقرير
     */
    public function exportExcel(Request $request)
    {
        $type = $request->get('type', 'daily');

        switch ($type) {
            case 'daily':
                $data = $this->dailyReport($request);
                break;
            case 'weekly':
                $data = $this->weeklyReport($request);
                break;
            case 'monthly':
                $data = $this->monthlyReport($request);
                break;
            case 'annual':
                $data = $this->annualReport($request);
                break;
            case 'overall':
                $data = $this->overallReport($request);
                break;
            default:
                $data = ['message' => 'Invalid report type'];
        }

        return response()->json(['message' => 'Export functionality to be implemented', 'data' => $data]);
    }
}
