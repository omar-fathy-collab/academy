<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AdminVault;
use App\Models\AdminWithdrawal;
use App\Models\CapitalAddition;
use App\Models\ProfitDistribution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AdminVaultController extends Controller
{
    /**
     * عرض لوحة تحكم الخزائن
     */
    public function index()
    {
        // التحقق من صلاحية المستخدم (سوبر أدمن أو أدمن جزئي)
        $user = \Auth::user();
        if (! $user) {
            return redirect('unauthorized');
        }

        // التحقق إذا كان المستخدم سوبر أدمن أو أدمن جزئي
        $isSuperAdmin = $user->isAdminFull(); // افترض أن لديك هذه الدالة
        $isPartialAdmin = $user->isAdminPartial();

        if (! ($isSuperAdmin || $isPartialAdmin)) {
            return redirect('unauthorized');
        }

        try {
            // ============ قسم حساب الأرباح والخزائن باستخدام FinancialService ============
            $financialService = app(\App\Services\FinancialService::class);

            // حساب صافي الربح
            $netProfit = $financialService->calculateNetProfit();

            // حساب المسحوبات الموافق عليها فقط (من جميع الخزائن)
            $totalApprovedWithdrawals = DB::table('admin_withdrawals')
                ->whereIn('status', ['approved', 'completed'])
                ->sum('amount');

            // حساب إجمالي المسحوبات المعلقة فقط
            $totalPendingWithdrawals = DB::table('admin_withdrawals')
                ->where('status', 'pending')
                ->sum('amount');

            // Net Profit للعرض
            $displayNetProfit = $netProfit;

            // حساب حصة الربح المتاحة (الربح الإجمالي - إجمالي ما تم سحبه بالفعل)
            $netProfitAvailable = max($netProfit - $totalApprovedWithdrawals, 0);
            $netProfitForDistribution = max($netProfit - $totalApprovedWithdrawals - $totalPendingWithdrawals, 0);

            // 2. حساب رأس المال
            $totalCapital = $financialService->getTotalCapital();

            // 3. جلب جميع الخزائن (فقط للسوبر أدمن)
            $vaults = collect();
            $totalBalance = 0;
            $totalEarned = 0;
            $calculatedCapital = 0;
            $profitDistribution = [];
            $recentWithdrawals = collect();
            $eligibleAdmins = collect();

            if ($isSuperAdmin) {
                $vaults = AdminVault::with(['user', 'capitalAdditions'])
                    ->get()
                    ->map(function ($vault) use ($netProfit) {
                        // حساب رأس المال الحقيقي من الاضافات
                        $actualCapital = $vault->capitalAdditions->sum('amount');

                        // حساب حصة المستخدم من صافي الربح
                        $profitShare = ($netProfit * $vault->profit_percentage) / 100;

                        // ✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅
                        // ✅ حساب إجمالي مسحوبات هذا المستخدم الموافق عليها فقط
                        // ✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅
                        $userApprovedWithdrawals = DB::table('admin_withdrawals')
                            ->where('user_id', $vault->user_id)
                            ->whereIn('status', ['approved', 'completed'])
                            ->sum('amount');

                        // ✅ تحديث total_withdrawn في الخزينة ليكون فقط المسحوبات الموافق عليها
                        $vault->total_withdrawn = $userApprovedWithdrawals;

                        // حساب الرصيد الحقيقي (رأس المال + الأرباح - السحوبات الموافق عليها)
                        $vault->balance = $actualCapital + $profitShare - $vault->total_withdrawn;
                        $vault->actual_capital = $actualCapital;
                        $vault->profit_share = $profitShare;
                        $vault->available_balance = max($profitShare - $vault->total_withdrawn, 0);

                        return $vault;
                    })
                    ->sortByDesc('balance');

                // 4. حساب رأس المال المحسوب من الخزائن
                $calculatedCapital = $vaults->sum('actual_capital');

                // 5. حساب الفرق بين رأس المال الحقيقي والمحسوب
                $capitalDiscrepancy = $totalCapital - $calculatedCapital;

                // إخفاء تحذير الفرق إذا كان صغيراً جداً
                if (abs($capitalDiscrepancy) < 0.01) {
                    $capitalDiscrepancy = 0;
                }

                // 6. حساب توزيع صافي الربح
                $profitDistribution = $this->calculateProfitDistribution($netProfit, $vaults);

                // 7. جلب سجلات السحب الأخيرة
                $recentWithdrawals = AdminWithdrawal::with(['user', 'approver'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                // 8. حساب إجماليات
                $totalBalance = $vaults->sum('balance');
                $totalEarned = $vaults->sum('total_earned');
                $totalWithdrawn = $vaults->sum('total_withdrawn');

                // 9. جلب الأدمن المؤهلين
                $eligibleAdmins = User::whereHas('adminType', function ($q) {
                    $q->where('name', 'full')
                        ->orWhere('name', 'partial');
                })
                    ->whereDoesntHave('adminVault')
                    ->get();
            }

            // ============ قسم إحصائيات Dashboard ============

            // Get counts for dashboard
            $students_count = DB::table('students')->count();
            $teachers_count = DB::table('teachers')->count();
            $groups_count = DB::table('groups')->count();
            $courses_count = DB::table('courses')->count();
            $total_assignments = DB::table('assignments')->count();
            $total_submissions = DB::table('assignment_submissions')->count();
            $graded_submissions = DB::table('assignment_submissions')->whereNotNull('score')->count();
            $avg_score = DB::table('assignment_submissions')->whereNotNull('score')->avg('score');
            $total_sessions = DB::table('sessions')->count();
            $total_attendance = DB::table('attendance')->count();
            $present_count = DB::table('attendance')->where('status', 'present')->count();
            $attendance_rate = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100, 1) : 0;
            $total_ratings = DB::table('ratings')->count();
            $avg_rating = DB::table('ratings')->avg('rating_value');

            // ✅ حساب إجمالي الإيرادات
            $total_revenue = $financialService->getTotalRevenue();

            $pending_invoices = DB::table('invoices')->where('status', '!=', 'paid')->count();
            $total_invoices = DB::table('invoices')->count();
            $total_quizzes = DB::table('quizzes')->count();
            $quiz_attempts = DB::table('quiz_attempts')->whereIn('status', ['completed', 'graded'])->count();

            // ✅ تحديث vault_withdrawals ليحتوي فقط المسحوبات الموافق عليها
            $vault_withdrawals = $totalApprovedWithdrawals;

            $monthly_stats = DB::select("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM (SELECT created_at FROM students UNION ALL SELECT created_at FROM teachers UNION ALL SELECT created_at FROM `groups` UNION ALL SELECT created_at FROM assignments UNION ALL SELECT created_at FROM invoices) all_records WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month");

            $course_distribution = DB::select('SELECT c.course_name, COUNT(g.group_id) as group_count, COUNT(DISTINCT sg.student_id) as student_count FROM courses c LEFT JOIN `groups` g ON c.course_id = g.course_id LEFT JOIN student_group sg ON g.group_id = sg.group_id GROUP BY c.course_id, c.course_name ORDER BY group_count DESC');

            $revenue_stats = DB::select("SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total_amount FROM payments WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month");

            /*
# Academy System Stabilization & RBAC Expansion Walkthrough

We have resolved critical system errors and expanded the Role-Based Access Control (RBAC) system with four new specialized roles.

## Changes Made

### 1. RBAC Expansion (New Roles)
- **New Roles Created**:
  - **Financial Manager**: Access to Billing, Salaries, Expenses, and Vault.
  - **Student Manager**: Access to Student Hub, Study Groups, and Timetables.
  - **Academic Manager**: Access to Courses, Instructors, Timetables, and Certificates.
  - **Admin Assistant**: Access to Users, Roles, Rooms, Activity Logs, and Settings.
- **Seeder**: Created `NewRolesSeeder.php` to define these roles and their granular permissions.
- **UI Logic**: Updated `AuthenticatedLayout.jsx` with strict `hasPermission` checks for every sidebar link, ensuring users only see what their role allows.

### 2. Resolved "activities.rollback" Route Error (Ziggy)
- **Issue**: Clicking "Rollback" in Activity Logs triggered a Ziggy error: `route 'activities.rollback' is not in the route list`.
- **Fix**: Added the missing POST route to `routes/admin.php`.
- **File**: `routes/admin.php`

### 3. Developed Resilient Teacher Dashboard
- **Issue**: Admin users without teacher records encountered 404 errors when accessing the teacher dashboard.
- **Fix**: Used a safe `first()` check in `TeachersController@dashboard` and implemented a redirect for multi-role admins.
- **File**: `app/Http/Controllers/TeachersController.php`

### 4. Stabilized Admin Vault & Financials
- **Vault Metrics**: Fixed "Undefined variable" errors by standardizing all financial metrics via `FinancialService`.
- **Withdrawal Actions**: Fixed 404/419 errors in withdrawal management by switching to Axios and proper Laravel routing.
- **Files**: `AdminVaultController.php`, `Index.jsx`, `MyWithdrawals.jsx`

### 5. Video Progress & Tracking Fixes
- **Issue**: Crashes occurred when tracking progress for videos using UUID identifiers.
- **Fix**: Standardized UUID lookups using the `Video` model.
- **File**: `app/Http/Controllers/VideoProgressController.php`

## Verification Results

### Automated Tests
- **Seeder Execution**: `php artisan db:seed --class=NewRolesSeeder` completed successfully.
- **Route Cache**: Verified `activities.rollback` is now part of the application route list.

### Manual Verification
- **Sidebar Visibility**: Verified that restricted roles (e.g., Student Manager) no longer see the "Financials" or "System Admin" sections.
- **Activity Rollback**: Verified the "Rollback" button no longer throws a Ziggy error.
- **Vault Actions**: Confirmed withdrawal approvals update the balance correctly via the new Axios-based implementation.

---

> [!TIP]
> To assign the new roles to existing users, go to the **Users** management section or use the `roles.index` page.

> [!IMPORTANT]
> The `admin` and `super-admin` roles still retain full access to all system features.
*/
            $recent_ratings = DB::select("SELECT r.rating_id, s.student_name, g.group_name, c.course_name, sc.subcourse_name, r.rating_value, r.comments, r.rated_at, r.rating_type, u.username as rated_by, CASE WHEN sc.subcourse_name IS NOT NULL THEN CONCAT(c.course_name, ' - ', sc.subcourse_name) ELSE c.course_name END as full_course_name FROM ratings r JOIN students s ON r.student_id = s.student_id JOIN `groups` g ON r.group_id = g.group_id JOIN courses c ON g.course_id = c.course_id LEFT JOIN subcourses sc ON g.subcourse_id = sc.subcourse_id JOIN users u ON r.rated_by = u.id ORDER BY r.rated_at DESC LIMIT 5");

            // Define missing variables for calculations below
            $approved_expenses = $financialService->getTotalExpenses();
            $base_salaries = $financialService->getTotalTeacherPayments();
            $approved_adjustments = DB::table('teacher_adjustments')->sum('amount');

            // ✅ إجمالي المصاريف
            $total_expenses = $approved_expenses;

            // ✅ إجمالي ما تم دفعه للمعلمين
            $total_salaries = $base_salaries + $approved_adjustments;

            $signed_teacher_payments = -1 * $base_salaries;
            $signed_total_expenses   = -1 * $total_expenses;

            $assignments = DB::select("
            SELECT a.assignment_id, a.title, g.group_name, c.course_name, sc.subcourse_name,
                   COUNT(s.submission_id) AS submissions_count,
                   AVG(s.score) AS avg_score,
                   MAX(s.score) AS max_score,
                   MIN(s.score) AS min_score,
                   CASE WHEN sc.subcourse_name IS NOT NULL
                        THEN CONCAT(c.course_name, ' - ', sc.subcourse_name)
                        ELSE c.course_name
                   END AS full_course_name
            FROM assignments a
            JOIN `groups` g ON a.group_id = g.group_id
            JOIN courses c ON g.course_id = c.course_id
            LEFT JOIN subcourses sc ON g.subcourse_id = sc.subcourse_id
            LEFT JOIN assignment_submissions s ON a.assignment_id = s.assignment_id
            GROUP BY a.assignment_id, a.title, g.group_name, c.course_name, sc.subcourse_name, full_course_name
            ORDER BY a.due_date DESC
            LIMIT 5
        ");

            $approved_adjustments = DB::table('teacher_adjustments')->sum('amount');

            // ✅ إجمالي المصاريف
            $total_expenses = floatval($approved_expenses);

            // ✅ إجمالي ما تم دفعه للمعلمين
            $total_salaries = floatval($base_salaries) + floatval($approved_adjustments);

            $signed_teacher_payments = -1 * floatval($base_salaries);
            $signed_total_expenses = -1 * floatval($total_expenses);

            // ======== بيانات الخزينة الشخصية للمستخدم الحالي ========
            $userVaultDetails = null;
            if ($user && ($isSuperAdmin || $isPartialAdmin)) {
                $userVault = DB::table('admin_vaults')->where('user_id', $user->id)->first();

                if ($userVault) {
                    // حساب رأس المال الحقيقي للمستخدم
                    $userActualCapital = DB::table('capital_additions')
                        ->where('added_by', $user->id)
                        ->sum('amount');

                    // حساب حصة المستخدم من صافي الربح - باستخدام نفس netProfit
                    $userProfitShare = ($netProfit * $userVault->profit_percentage) / 100;

                    // ✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅
                    // ✅ حساب مسحوبات المستخدم الموافق عليها فقط
                    // ✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅✅
                    $userApprovedWithdrawals = DB::table('admin_withdrawals')
                        ->where('user_id', $user->id)
                        ->whereIn('status', ['approved', 'completed'])
                        ->sum('amount');

                    // ✅ تحديث total_withdrawn في تفاصيل المستخدم
                    $userVault->total_withdrawn = $userApprovedWithdrawals;

                    // حساب الرصيد المتاح للسحب
                    $userAvailableBalance = max($userProfitShare - $userVault->total_withdrawn, 0);

                    $userVaultDetails = [
                        'actual_capital' => $userActualCapital,
                        'profit_percentage' => $userVault->profit_percentage,
                        'profit_share' => $userProfitShare,
                        'available_balance' => $userAvailableBalance,
                        'total_earned' => $userVault->total_earned,
                        'total_withdrawn' => $userVault->total_withdrawn,
                        'has_pending_profit' => ($userProfitShare > $userVault->total_withdrawn),
                    ];
                }
            }

            // ======== إحصائيات إضافية ========
            $new_students_month = DB::table('students')
                ->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))
                ->count();

            $new_teachers_month = DB::table('teachers')
                ->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))
                ->count();

            $monthly_revenue = DB::table('payments')
                ->whereMonth('payment_date', date('m'))
                ->whereYear('payment_date', date('Y'))
                ->sum('amount');

            // ✅ هنا كان الخطأ - المتغير $total_revenue الآن معرف
            $payment_completion_rate = $total_invoices > 0 ?
                round(($total_revenue / ($total_revenue + DB::table('invoices')->where('status', '!=', 'paid')->sum('amount'))) * 100, 1) : 0;

            // ======== تحضير البيانات للعرض ========
            // إذا كان المستخدم سوبر أدمن، عرض صفحة الخزائن
            if ($isSuperAdmin) {
                return view('admin-vault.index', [
                    'vaults' => $vaults->values(),
                    'recentWithdrawals' => $recentWithdrawals,
                    'totalBalance' => $totalBalance,
                    'totalEarned' => $totalEarned,
                    'totalWithdrawn' => $totalWithdrawn ?? 0,
                    'totalCapital' => $totalCapital,
                    'calculatedCapital' => $calculatedCapital,
                    'capitalDiscrepancy' => $capitalDiscrepancy ?? 0,
                    'netProfit' => $netProfit,
                    'netProfitAvailable' => $netProfitAvailable,
                    'netProfitForDistribution' => $netProfitForDistribution,
                    'profitDistribution' => $profitDistribution,
                    'eligibleAdmins' => $eligibleAdmins,
                    'totalApprovedWithdrawals' => $totalApprovedWithdrawals,
                    'totalPendingWithdrawals' => $totalPendingWithdrawals,
                    'userVaultDetails' => $userVaultDetails,
                ]);
            }
            // إذا كان المستخدم أدمن جزئي، عرض Dashboard العادي
            else {
                return redirect()->route('dashboard');
            }

        } catch (\Exception $e) {
            if ($isSuperAdmin) {
                return view('admin-vault.setup', [
                    'error' => $e->getMessage(),
                ]);
            } else {
                return redirect()->back()->withErrors(['error' => 'حدث خطأ: '.$e->getMessage()]);
            }
        }
    }

    /**
     * حساب صافي الربح الفعلي - نفس طريقة DashboardController
     */
    /**
     * حساب صافي الربح الفعلي - أصبح public للاستخدام في الـ Views
     */
    /**
     * حساب صافي الربح الفعلي - أصبح public للاستخدام في الـ Views
     */
    public function calculateNetProfit()
    {
        return app(\App\Services\FinancialService::class)->calculateNetProfit();
    }

    /**
     * حساب توزيع صافي الربح
     */
    private function calculateProfitDistribution($netProfit, $vaults)
    {
        $distribution = [];

        if ($vaults->count() > 0 && $netProfit > 0) {
            $totalPercentage = $vaults->sum('profit_percentage');

            if ($totalPercentage > 0) {
                foreach ($vaults as $vault) {
                    // حساب حصة كل أدمن من صافي الربح
                    $profitShare = ($netProfit * $vault->profit_percentage) / $totalPercentage;
                    $distribution[$vault->id] = [
                        'admin_name' => $vault->user->username,
                        'percentage' => $vault->profit_percentage,
                        'share' => $profitShare,
                        'current_balance' => $vault->getAvailableBalanceAttribute(),
                    ];
                }
            }
        }

        return $distribution;
    }

    private function isSuperAdmin($user)
    {
        if (! $user) {
            return false;
        }

        if ($user->adminType) {
            return strtolower($user->adminType->name) === 'full';
        }

        return DB::table('users')
            ->where('users.id', $user->id)
            ->where('users.admin_type_id', 1)
            ->exists();
    }

    private function isAdminEligible($user)
    {
        if (! $user) {
            return false;
        }

        if ($user->adminType) {
            $typeName = strtolower($user->adminType->name);

            return $typeName === 'full' || $typeName === 'partial';
        }

        return false;
    }

    /**
     * طلب سحب من الخزينة
     */
    /**
     * عرض تفاصيل سحب معين
     */
    /**
     * إلغاء سحب مكتمل وإرجاع الأموال
     */
    public function cancelCompletedWithdrawal(Request $request, $id)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $withdrawal = AdminWithdrawal::findOrFail($id);

        // التحقق أن السحب مكتمل
        if ($withdrawal->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed withdrawals can be canceled',
            ]);
        }

        // التحقق من البيانات
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'refund_method' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            // 1. إرجاع الأموال إلى الخزينة
            $vault = $withdrawal->vault;
            $oldWithdrawn = $vault->total_withdrawn;
            $vault->total_withdrawn = max($oldWithdrawn - $withdrawal->amount, 0);
            $vault->save();

            // 2. تحديث حالة السحب
            $withdrawal->status = 'canceled';

            // 3. إضافة ملاحظات الإلغاء
            $existingNotes = $withdrawal->notes ?? '';
            $cancelNotes = "\n\n[CANCELED ".now()->format('Y-m-d H:i').']';
            $cancelNotes .= "\nReason: ".$request->reason;
            $cancelNotes .= "\nRefund Method: ".$request->refund_method;
            $cancelNotes .= "\nCanceled By: ".$user->username;
            $cancelNotes .= "\nAmount Refunded: $".number_format($withdrawal->amount, 2);
            $cancelNotes .= "\nPrevious total_withdrawn: $".number_format($oldWithdrawn, 2);
            $cancelNotes .= "\nNew total_withdrawn: $".number_format($vault->total_withdrawn, 2);

            $withdrawal->notes = $existingNotes.$cancelNotes;
            $withdrawal->canceled_at = now();
            $withdrawal->canceled_by = $user->id;
            $withdrawal->save();

            // 4. تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'subject_type' => get_class($withdrawal),
                'subject_id' => $withdrawal->id,
                'action' => 'cancel_completed_withdrawal',
                'description' => 'Canceled completed withdrawal ID: '.$id.' for $'.$withdrawal->amount.' - Reason: '.$request->reason,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 5. تسجيل المعاملة في سجل المعاملات (إن وجد)
                Activity::create([
                    'user_id' => auth()->id(),
                    'subject_type' => 'App\Models\AdminWithdrawal',
                    'subject_id' => $withdrawal->id,
                    'action' => 'withdrawal_refund',
                    'description' => 'Refund for canceled withdrawal #'.$withdrawal->id,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal canceled successfully. $'.number_format($withdrawal->amount, 2).' has been refunded.',
                'withdrawal' => $withdrawal,
                'vault' => [
                    'old_withdrawn' => $oldWithdrawn,
                    'new_withdrawn' => $vault->total_withdrawn,
                    'available_balance' => $vault->getAvailableBalanceAttribute(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function withdrawalDetails($id)
    {
        $user = \Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $withdrawal = AdminWithdrawal::with(['user', 'approver', 'vault'])
            ->findOrFail($id);

        // التحقق من الصلاحيات
        $isOwner = ($withdrawal->user_id == $user->id);
        $isSuperAdmin = $this->isSuperAdmin($user);

        if (! $isOwner && ! $isSuperAdmin) {
            return response()->json(['success' => false, 'message' => 'Access denied'], 403);
        }

        $details = [
            'id' => $withdrawal->id,
            'amount' => $withdrawal->amount,
            'status' => $withdrawal->status,
            'notes' => $withdrawal->notes,
            'receipt_method' => $withdrawal->receipt_method,
            'receipt_details' => $withdrawal->receipt_details,
            'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
            'approved_at' => $withdrawal->approved_at ? $withdrawal->approved_at->format('Y-m-d H:i:s') : null,
            'completed_at' => $withdrawal->completed_at ? $withdrawal->completed_at->format('Y-m-d H:i:s') : null,
            'approver_name' => $withdrawal->approver ? $withdrawal->approver->username : null,
            'user_name' => $withdrawal->user ? $withdrawal->user->username : null,
            'vault_percentage' => $withdrawal->vault ? $withdrawal->vault->profit_percentage : null,
        ];

        return response()->json([
            'success' => true,
            'withdrawal' => $details,
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isAdminEligible($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $vault = $user->adminVault;
        if (! $vault) {
            return response()->json(['success' => false, 'message' => 'No vault found'], 404);
        }

        // حساب الرصيد المتاح لحظياً باستخدام نفس حساب Dashboard
        $netProfit = $this->calculateNetProfit();
        $profitShare = ($netProfit * $vault->profit_percentage) / 100;
        $availableBalance = max($profitShare - $vault->total_withdrawn, 0);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:'.$availableBalance,
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            $withdrawal = AdminWithdrawal::create([
                'user_id' => $user->id,
                'vault_id' => $vault->id,
                'amount' => $request->amount,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'available_balance' => $availableBalance,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحديث طلب سحب
     */
    public function updateWithdrawal(Request $request, $id)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isAdminEligible($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $withdrawal = AdminWithdrawal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $withdrawal) {
            return response()->json(['success' => false, 'message' => 'Withdrawal not found'], 404);
        }

        // التحقق أن الطلب لا يزال pending
        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a withdrawal that is already processed',
            ]);
        }

        $vault = $user->adminVault;
        // حساب الرصيد المتاح باستخدام نفس حساب Dashboard
        $netProfit = $this->calculateNetProfit();
        $profitShare = ($netProfit * $vault->profit_percentage) / 100;
        $availableBalance = $profitShare - $vault->total_withdrawn + $withdrawal->amount;
        $maxAmount = max($availableBalance, 0);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:'.$maxAmount,
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            $oldAmount = $withdrawal->amount;
            $newAmount = $request->amount;

            $withdrawal->amount = $newAmount;
            $withdrawal->notes = $request->notes;
            $withdrawal->save();

            // تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'subject_type' => get_class($withdrawal),
                'subject_id' => $withdrawal->id,
                'action' => 'update_withdrawal',
                'description' => 'Updated withdrawal from $'.$oldAmount.' to $'.$newAmount,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request updated successfully',
                'withdrawal' => $withdrawal,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف طلب سحب
     */
    public function deleteWithdrawal(Request $request, $id)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isAdminEligible($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $withdrawal = AdminWithdrawal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $withdrawal) {
            return response()->json(['success' => false, 'message' => 'Withdrawal not found'], 404);
        }

        // التحقق أن الطلب لا يزال pending
        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a withdrawal that is already processed',
            ]);
        }

        try {
            DB::beginTransaction();

            // تسجيل النشاط قبل الحذف
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'subject_type' => get_class($withdrawal),
                'subject_id' => $withdrawal->id,
                'action' => 'delete_withdrawal',
                'description' => 'Deleted withdrawal request: $'.$withdrawal->amount,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $withdrawal->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء خزينة جديدة
     */
    public function store(Request $request)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'profit_percentage' => 'required|numeric|min:0|max:100',
            'initial_balance' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            $vault = AdminVault::create([
                'user_id' => $request->user_id,
                'profit_percentage' => $request->profit_percentage,
                'is_active' => true,
                'total_earned' => 0,
                'total_withdrawn' => 0,
            ]);

            // إذا كان هناك رصيد ابتدائي، إضافة كرأس مال
            if ($request->initial_balance && $request->initial_balance > 0) {
                \App\Models\CapitalAddition::create([
                    'amount' => $request->initial_balance,
                    'description' => 'Initial vault balance',
                    'added_by' => $request->user_id,
                    'addition_date' => now(),
                ]);
            }

            // تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => auth()->id(),
                'subject_type' => get_class($vault),
                'subject_id' => $vault->id,
                'action' => 'create_vault',
                'description' => 'Created vault for user ID: '.$request->user_id.' with '.$request->profit_percentage.'%',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vault created successfully',
                'vault' => $vault,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إضافة رأس مال للخزينة
     */
    public function addCapitalToVault(Request $request)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'vault_id' => 'required|exists:admin_vaults,id',
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            \App\Models\CapitalAddition::create([
                'amount' => $request->amount,
                'description' => $request->description ?? 'Manual capital adjustment',
                'added_by' => $request->user_id,
                'addition_date' => now(),
            ]);

            // تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => auth()->id(),
                'action' => 'add_capital_to_vault',
                'description' => 'Added $'.$request->amount.' capital to vault ID: '.$request->vault_id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Capital added to vault successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تصحيح توزيع رأس المال
     */
    public function redistributeCapital()
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // 1. حساب إجمالي رأس المال
            $totalCapital = DB::table('capital_additions')->sum('amount');

            // 2. جلب جميع الخزائن النشطة
            $vaults = AdminVault::where('is_active', true)->get();
            $vaultCount = $vaults->count();

            if ($vaultCount == 0) {
                throw new \Exception('No active vaults found');
            }

            // 3. تقسيم رأس المال بالتساوي على جميع الخزائن
            $capitalPerVault = $totalCapital / $vaultCount;

            // 4. حذف جميع إضافات رأس المال السابقة
            \App\Models\CapitalAddition::truncate();

            // 5. إضافة رأس المال بالتساوي لكل خزينة
            foreach ($vaults as $vault) {
                CapitalAddition::create([
                    'amount' => $capitalPerVault,
                    'description' => 'Fair capital redistribution',
                    'added_by' => $vault->user_id,
                    'addition_date' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Capital redistributed: $'.number_format($totalCapital, 2).
                             " equally among $vaultCount vaults ($".
                             number_format($capitalPerVault, 2).' each)',
                'total_capital' => $totalCapital,
                'vault_count' => $vaultCount,
                'capital_per_vault' => $capitalPerVault,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * توزيع الأرباح
     */
    public function distributeProfit(Request $request)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'total_net_profit' => 'required|numeric|min:0',
            'distribution_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            // استخدام نفس الحساب الموجود في Dashboard
            $totalProfit = $this->calculateNetProfit();
            $distributionDate = $request->distribution_date;
            $vaults = AdminVault::where('is_active', true)->get();

            $totalPercentage = $vaults->sum('profit_percentage');

            if ($totalPercentage == 0) {
                throw new \Exception('Total percentage of all vaults is 0');
            }

            $distributionDetails = [];
            $totalDistributed = 0;

            foreach ($vaults as $vault) {
                $profitAmount = ($totalProfit * $vault->profit_percentage) / 100;

                if ($profitAmount > 0) {
                    // إضافة الربح للخزينة
                    $vault->total_earned += $profitAmount;
                    $vault->save();

                    $totalDistributed += $profitAmount;

                    $distributionDetails[$vault->user_id] = [
                        'admin_name' => $vault->user->username,
                        'profit_percentage' => $vault->profit_percentage,
                        'amount_received' => $profitAmount,
                        'new_total_earned' => $vault->total_earned,
                    ];
                }
            }

            // حفظ سجل التوزيع
            $distribution = ProfitDistribution::create([
                'total_net_profit' => $totalProfit,
                'distribution_date' => $distributionDate,
                'distribution_details' => json_encode($distributionDetails),
                'distributed_by' => auth()->id(),
            ]);

            // تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => auth()->id(),
                'subject_type' => get_class($distribution),
                'subject_id' => $distribution->id,
                'action' => 'profit_distribution',
                'description' => 'Distributed profit: $'.number_format($totalProfit, 2).' to '.$vaults->count().' vaults',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profit distributed successfully',
                'total_distributed' => $totalDistributed,
                'vaults_count' => $vaults->count(),
                'distribution' => $distribution,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تعديل النسب بشكل جماعي
     */
    public function updatePercentages(Request $request)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'percentages' => 'required|array',
            'percentages.*.vault_id' => 'required|exists:admin_vaults,id',
            'percentages.*.percentage' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            DB::beginTransaction();

            $totalPercentage = 0;
            $updatedCount = 0;

            foreach ($request->percentages as $item) {
                $vault = \App\Models\AdminVault::where('id', '=', $item['vault_id'])->firstOrFail();
                
                // The original code had `if (!$vault) continue;` but with `firstOrFail`,
                // an exception would be thrown if not found, making this check redundant.
                // Keeping it for faithfulness to the original structure, though it won't be reached.
                if (!$vault) continue; 

                $oldPercentage = $vault->profit_percentage;

                if ($oldPercentage != $item['percentage']) {
                    $vault->profit_percentage = $item['percentage'];
                    $vault->save();
                    $updatedCount++;


                    \App\Models\Activity::create([
                        'user_id' => auth()->id(),
                        'subject_type' => get_class($vault),
                        'subject_id' => $vault->id,
                        'action' => 'update_vault_percentage',
                        'description' => 'Updated percentage for '.$vault->user->username.
                                        ' from '.$oldPercentage.'% to '.$item['percentage'].'%',
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }

                $totalPercentage += $item['percentage'];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Updated '.$updatedCount.' vault percentages',
                'total_percentage' => $totalPercentage,
                'updated_count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * الموافقة على طلب سحب
     */
    /**
     * الموافقة على طلب سحب
     */
    public function approveWithdrawal(Request $request, $id)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // استخدم AdminWithdrawal بدلاً من Withdrawal
            $withdrawal = AdminWithdrawal::findOrFail($id);

            // التحقق من أن السحب قيد الانتظار
            if ($withdrawal->status != 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal is not pending',
                ]);
            }

            // التحقق من رصيد المستخدم صاحب الطلب
            $userVault = AdminVault::where('user_id', $withdrawal->user_id)->first();

            if (! $userVault) {
                return response()->json([
                    'success' => false,
                    'message' => 'User vault not found',
                ]);
            }

            // حساب الرصيد المتاح للمستخدم صاحب الطلب باستخدام نفس حساب Dashboard
            $userNetProfit = $this->calculateNetProfit();
            $userProfitShare = ($userNetProfit * $userVault->profit_percentage) / 100;
            $userAvailableBalance = max($userProfitShare - $userVault->total_withdrawn, 0);

            if ($userAvailableBalance < $withdrawal->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has insufficient available balance',
                ]);
            }

            DB::beginTransaction();

            // تحديث حالة السحب
            $withdrawal->status = 'approved';
            $withdrawal->approved_by = auth()->id();
            $withdrawal->approved_at = now();

            if ($request->has('notes') && $request->notes) {
                $withdrawal->notes = $request->notes.($withdrawal->notes ? "\n".$withdrawal->notes : '');
            }

            $withdrawal->save();

            // تحديث إجمالي المسحوبات للمستخدم
            $userVault->total_withdrawn += $withdrawal->amount;
            $userVault->save();

            // تسجيل في النشاطات
            \App\Models\Activity::create([
                'user_id' => auth()->id(),
                'subject_type' => 'App\Models\AdminWithdrawal',
                'subject_id' => $withdrawal->id,
                'action' => 'withdrawal_approved',
                'description' => 'Approved withdrawal of $'.$withdrawal->amount.' for user '.$withdrawal->user->username,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal approved successfully',
                'withdrawal' => $withdrawal,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * رفض طلب سحب
     */
    public function rejectWithdrawal(Request $request, $id)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $withdrawal = AdminWithdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request already processed',
            ]);
        }

        try {
            $withdrawal->reject($request->notes);

            // تسجيل النشاط
            Activity::create([
                'user_id' => auth()->id(),
                'subject_type' => get_class($withdrawal),
                'subject_id' => $withdrawal->id,
                'action' => 'reject_withdrawal',
                'description' => 'Rejected withdrawal ID: '.$id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal rejected successfully',
                'withdrawal' => $withdrawal,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تأكيد استلام الأموال
     */
    public function completeWithdrawal(Request $request, $id)
    {
        $user = \Auth::user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $withdrawal = AdminWithdrawal::findOrFail($id);

        // التحقق أن المستخدم هو صاحب الطلب
        if ($withdrawal->user_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'You can only complete your own withdrawals'], 403);
        }

        // التحقق أن الطلب موافق عليه
        if ($withdrawal->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal must be approved before completing',
            ]);
        }

        try {
            DB::beginTransaction();

            // تحديث حالة السحب إلى مكتمل
            $withdrawal->status = 'completed';

            // إضافة تفاصيل الاستلام
            $withdrawal->receipt_method = $request->receipt_method;
            $withdrawal->receipt_details = $request->receipt_details;
            $withdrawal->completed_at = now();
            $withdrawal->save();

            // تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => $user->id,
                'subject_type' => get_class($withdrawal),
                'subject_id' => $withdrawal->id,
                'action' => 'complete_withdrawal',
                'description' => 'Marked withdrawal as completed: $'.$withdrawal->amount.' via '.$request->receipt_method,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal marked as completed successfully',
                'withdrawal' => $withdrawal,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحديث نسبة الربح لخزينة
     */
    public function updatePercentage(Request $request, $id)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $vault = AdminVault::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'profit_percentage' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        try {
            $oldPercentage = $vault->profit_percentage;
            $vault->profit_percentage = $request->profit_percentage;
            $vault->save();

            // تسجيل النشاط
            \App\Models\Activity::create([
                'user_id' => auth()->id(),
                'subject_type' => get_class($vault),
                'subject_id' => $vault->id,
                'action' => 'update_vault_percentage',
                'description' => 'Updated percentage from '.$oldPercentage.'% to '.$request->profit_percentage.'%',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Percentage updated successfully',
                'vault' => $vault,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * عرض سجلات السحب للمستخدم الحالي
     */
    /**
     * عرض سجلات السحب للمستخدم الحالي
     */
    // في دالة myWithdrawals()
    // في دالة myWithdrawals()
    public function myWithdrawals()
    {
        $user = \Auth::user();
        if (! $user || ! $this->isAdminEligible($user)) {
            return redirect('unauthorized');
        }

        $this->ensureVaultExists($user);
        $vault = $user->adminVault;

        if (! $vault) {
            return view('admin_vault.no_vault');
        }

        // 1. حساب صافي الربح (لحظي) - باستخدام نفس حساب Dashboard
        $netProfit = $this->calculateNetProfit();

        // 2. حصة المستخدم (لحظي)
        $pendingProfitShare = ($netProfit * $vault->profit_percentage) / 100;

        // 3. الربح المتاح للسحب (لحظي)
        $availableBalance = max($pendingProfitShare - $vault->total_withdrawn, 0);

        // 4. رأس المال الحقيقي
        $actualCapital = $vault->getActualCapitalAttribute();

        // 5. هل هناك ربح معلق؟
        $hasPendingProfit = ($pendingProfitShare > $vault->total_withdrawn);

        // 6. الربح الذي يمكن توزيعه (للسوبر أدمن فقط)
        $pendingDistribution = $pendingProfitShare;

        // جلب السحوبات
        if ($this->isSuperAdmin($user)) {
            $withdrawals = AdminWithdrawal::with(['user', 'approver'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $withdrawals = AdminWithdrawal::where('user_id', $user->id)
                ->with(['user', 'approver'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        return view('admin_vault.my_withdrawals', [
            'withdrawals' => $withdrawals,
            'vault' => $vault,
            'netProfit' => $netProfit,
            'pendingProfitShare' => $pendingProfitShare,
            'availableBalance' => $availableBalance,
            'actualCapital' => $actualCapital,
            'hasPendingProfit' => $hasPendingProfit,
            'pendingDistribution' => $pendingDistribution,
        ]);
    }

    /**
     * جلب إحصائيات الخزينة
     */
    public function getVaultStats()
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $vaults = AdminVault::all();

            $totalCapital = $vaults->sum(function ($v) {
                return $v->getActualCapitalAttribute();
            });

            // حساب الرصيد المتاح باستخدام نفس حساب Dashboard
            $netProfit = $this->calculateNetProfit();
            $totalAvailableBalance = 0;

            foreach ($vaults as $vault) {
                $profitShare = ($netProfit * $vault->profit_percentage) / 100;
                $availableBalance = max($profitShare - $vault->total_withdrawn, 0);
                $totalAvailableBalance += $availableBalance;
            }

            $stats = [
                'total_vaults' => $vaults->count(),
                'active_vaults' => $vaults->where('is_active', true)->count(),
                'total_capital' => $totalCapital,
                'total_available_balance' => $totalAvailableBalance,
                'total_earned' => $vaults->sum('total_earned'),
                'total_withdrawn' => $vaults->sum('total_withdrawn'),
                'pending_withdrawals' => AdminWithdrawal::where('status', 'pending')->count(),
                'total_withdrawal_requests' => AdminWithdrawal::count(),
                'recent_distributions' => ProfitDistribution::count(),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * الموافقة على سحوبات متعددة
     */
    public function bulkApprove(Request $request)
    {
        $user = \Auth::user();
        if (! $user || ! $this->isSuperAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'withdrawal_ids' => 'required|array',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ]);
        }

        $withdrawalIds = json_decode($request->withdrawal_ids, true);
        $approvedCount = 0;
        $failedCount = 0;
        $messages = [];

        try {
            DB::beginTransaction();

            foreach ($withdrawalIds as $id) {
                $withdrawal = AdminWithdrawal::find($id);

                if (! $withdrawal) {
                    $failedCount++;
                    $messages[] = "Withdrawal ID {$id}: Not found";

                    continue;
                }

                if ($withdrawal->status !== 'pending') {
                    $failedCount++;
                    $messages[] = "Withdrawal ID {$id}: Already processed";

                    continue;
                }

                try {
                    $vault = $withdrawal->vault;

                    // التحقق من الرصيد المتاح باستخدام نفس حساب Dashboard
                    $netProfit = $this->calculateNetProfit();
                    $profitShare = ($netProfit * $vault->profit_percentage) / 100;
                    $availableBalance = max($profitShare - $vault->total_withdrawn, 0);

                    if ($availableBalance < $withdrawal->amount) {
                        throw new \Exception('Insufficient balance');
                    }

                    if ($vault instanceof \App\Models\AdminVault) {
                        $vault->total_withdrawn += $withdrawal->amount;
                        $vault->save();
                    }


                    $withdrawal->status = 'approved';
                    $withdrawal->approved_by = auth()->id();
                    $withdrawal->approved_at = now();
                    $withdrawal->save();

                    $approvedCount++;

                    Activity::create([
                        'user_id' => auth()->id(),
                        'subject_type' => get_class($withdrawal),
                        'subject_id' => $withdrawal->id,
                        'action' => 'bulk_approve_withdrawal',
                        'description' => 'Approved withdrawal ID: '.$id.' for $'.$withdrawal->amount,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                } catch (\Exception $e) {
                    $failedCount++;
                    $messages[] = "Withdrawal ID {$id}: ".$e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Approved {$approvedCount} withdrawals, failed: {$failedCount}",
                'approved_count' => $approvedCount,
                'failed_count' => $failedCount,
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء خزينة تلقائياً إذا لم تكن موجودة
     */
    private function ensureVaultExists($user)
    {
        if (! $user->adminVault) {
            // إذا كان المستخدم أدمن ولم يخ خزينة، ننشئ له واحدة
            if ($this->isAdminEligible($user)) {
                // النسبة الافتراضية
                $defaultPercentage = 0;

                if (strtolower($user->adminType->name) === 'full') {
                    $defaultPercentage = 10; // مثال: 10% للسوبر أدمن
                } elseif (strtolower($user->adminType->name) === 'partial') {
                    $defaultPercentage = 5; // مثال: 5% للأدمن العادي
                }

                AdminVault::create([
                    'user_id' => $user->id,
                    'profit_percentage' => $defaultPercentage,
                    'is_active' => true,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]);

                // نجدد العلاقة
                $user->load('adminVault');
            }
        }
    }
}
