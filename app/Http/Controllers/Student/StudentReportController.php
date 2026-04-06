<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Exports\StudentReportExport;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Rating;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\GroupsFinancialExport;

class StudentReportController extends Controller
{
    /**
     * عرض صفحة تقارير الطلاب الرئيسية
     */
    // في بداية الكلاس، بعد constructor
    public function __construct()
    {
        $this->middleware('auth');
        // Changed from 'permission:view-reports' to 'can:view-reports' to avoid 500 error if permission middleware alias is missing
        $this->middleware('can:view-reports', ['only' => [
            'getGroupsFinancialSummary',
            'getGroupsFinancialDetails',
            'getGroupsFinancialDashboard',
            'getGroupsFinancialReport',
        ]]);
    }

    /**
     * Base query for groups with financial data (unpaid invoices)
     */
    private function getBaseFinancialQuery()
    {
        return Group::select(
            'groups.group_id',
            'groups.group_name',
            'courses.course_name',
            'teachers.teacher_name',
            'groups.start_date',
            'groups.end_date',
            DB::raw('COUNT(DISTINCT student_group.student_id) as student_count'),
            DB::raw('COUNT(DISTINCT CASE WHEN invoices.amount > invoices.amount_paid THEN invoices.student_id END) as unpaid_students_count'),
            DB::raw('COALESCE(SUM(invoices.amount), 0) as total_invoices'),
            DB::raw('COALESCE(SUM(invoices.amount_paid), 0) as total_paid'),
            DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as total_due')
        )
            ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
            ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
            ->leftJoin('invoices', function ($join) {
                $join->on('student_group.student_id', '=', 'invoices.student_id')
                    ->whereColumn('invoices.group_id', 'groups.group_id');
            })
            ->whereNotNull('groups.end_date')
            ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name',
                'teachers.teacher_name', 'groups.start_date', 'groups.end_date');
    }

    public function index()
    {
        $data = $this->getGroupsFinancialData('complete', null, null, null);
        
        return view('reports.detailed.financial_dashboard', [
            'initialData' => $data
        ]);
    }

    /**
     * جلب أفضل 20 طالب حسب الدرجات
     */
    public function topByGrades(Request $request)
    {
        try {
            $date = $request->input('date', now()->format('Y-m-d'));
            $month = $request->input('month', now()->format('Y-m'));
            $year = $request->input('year', now()->format('Y'));

            $students = Rating::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('AVG(ratings.rating_value) as average_rating'),
                DB::raw('COUNT(ratings.rating_id) as total_ratings'),
                'groups.group_name',
                'courses.course_name'
            )
                ->join('students', 'ratings.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('groups', 'ratings.group_id', '=', 'groups.group_id')
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->when($request->input('period'), function ($query, $period) use ($date, $month, $year) {
                    if ($period === 'daily') {
                        $query->whereDate('ratings.rated_at', $date);
                    } elseif ($period === 'monthly') {
                        $query->whereYear('ratings.rated_at', Carbon::parse($month)->year)
                            ->whereMonth('ratings.rated_at', Carbon::parse($month)->month);
                    } elseif ($period === 'yearly') {
                        $query->whereYear('ratings.rated_at', $year);
                    }
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username', 'groups.group_name', 'courses.course_name')
                ->orderBy('average_rating', 'DESC')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $students,
                'period' => $request->input('period', 'overall'),
                'date' => $date,
                'month' => $month,
                'year' => $year,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in topByGrades: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب أفضل 20 طالب حسب الحضور
     */
    public function topByAttendance(Request $request)
    {
        try {
            $date = $request->input('date', now()->format('Y-m-d'));
            $month = $request->input('month', now()->format('Y-m'));
            $year = $request->input('year', now()->format('Y'));

            $students = Attendance::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(CASE WHEN attendance.status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance.attendance_id) as total_sessions'),
                DB::raw('ROUND(COUNT(CASE WHEN attendance.status = "present" THEN 1 END) * 100.0 / COUNT(attendance.attendance_id), 2) as attendance_percentage'),
                'groups.group_name',
                'courses.course_name'
            )
                ->join('students', 'attendance.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->join('sessions', 'attendance.session_id', '=', 'sessions.session_id')
                ->join('groups', 'sessions.group_id', '=', 'groups.group_id')
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->when($request->input('period'), function ($query, $period) use ($date, $month, $year) {
                    if ($period === 'daily') {
                        $query->whereDate('attendance.recorded_at', $date);
                    } elseif ($period === 'monthly') {
                        $query->whereYear('attendance.recorded_at', Carbon::parse($month)->year)
                            ->whereMonth('attendance.recorded_at', Carbon::parse($month)->month);
                    } elseif ($period === 'yearly') {
                        $query->whereYear('attendance.recorded_at', $year);
                    }
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username', 'groups.group_name', 'courses.course_name')
                ->having('total_sessions', '>', 0)
                ->orderBy('attendance_percentage', 'DESC')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $students,
                'period' => $request->input('period', 'overall'),
                'date' => $date,
                'month' => $month,
                'year' => $year,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in topByAttendance: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }
    /**
     * جلب بيانات الجروبات المالية
     */
    // في StudentReportController.php - تحديث دالة getGroupsFinancialData

    /**
     * جلب بيانات الجروبات المالية - النسخة المحسنة
     */
    public function getGroupsFinancialData($period, $date, $month, $year)
    {
        try {
            $today = Carbon::now();
            $data = [];

            // 1. الجروبات المنتهية مع فواتير غير مدفوعة (محدث)
            $expiredWithUnpaid = $this->getBaseFinancialQuery()
                ->havingRaw('total_due > 0')
                ->where('groups.end_date', '<', $today)
                ->orderBy('groups.end_date', 'DESC')
                ->get()
                ->map(function ($group) use ($today) {
                    // الحصول على الطلاب غير الدافعين لهذه المجموعة
                    $unpaidStudents = Student::select(
                        'students.student_id',
                        'students.student_name',
                        'users.username',
                        'users.email',
                        DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as student_due'),
                        DB::raw('COUNT(DISTINCT invoices.invoice_id) as unpaid_invoices_count')
                    )
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->join('invoices', function ($join) use ($group) {
                            $join->on('students.student_id', '=', 'invoices.student_id')
                                ->where('invoices.group_id', $group->group_id)
                                ->whereRaw('invoices.amount > invoices.amount_paid');
                        })
                        ->groupBy('students.student_id', 'students.student_name', 'users.username', 'users.email')
                        ->get();

                    return [
                        'group_id' => $group->group_id,
                        'group_name' => $group->group_name,
                        'course_name' => $group->course_name,
                        'teacher_name' => $group->teacher_name,
                        'start_date' => $group->start_date,
                        'end_date' => $group->end_date,
                        'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                        'days_since_expiry' => Carbon::parse($group->end_date)->diffInDays($today),
                        'student_count' => $group->student_count,
                        'total_invoices' => (float) $group->total_invoices,
                        'total_paid' => (float) $group->total_paid,
                        'total_due' => (float) $group->total_due,
                        'has_unpaid_invoices' => $group->total_due > 0,
                        'unpaid_students' => $unpaidStudents->map(function ($student) {
                            return [
                                'student_id' => $student->student_id,
                                'student_name' => $student->student_name,
                                'username' => $student->username,
                                'email' => $student->email,
                                'amount_due' => (float) $student->student_due,
                                'unpaid_invoices_count' => $student->unpaid_invoices_count,
                            ];
                        }),
                    ];
                });

            // 2. الجروبات التي على وشك الانتهاء مع فواتير غير مدفوعة (30 يوم القادمة) - محدث
            $aboutToExpire = $this->getBaseFinancialQuery()
                ->havingRaw('total_due > 0')
                ->where('groups.end_date', '>=', $today)
                ->where('groups.end_date', '<=', $today->copy()->addDays(30))
                ->orderBy('groups.end_date', 'ASC')
                ->get()
                ->map(function ($group) use ($today) {
                    // الحصول على الطلاب غير الدافعين لهذه المجموعة
                    $unpaidStudents = Student::select(
                        'students.student_id',
                        'students.student_name',
                        'users.username',
                        'users.email',
                        DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as student_due'),
                        DB::raw('COUNT(DISTINCT invoices.invoice_id) as unpaid_invoices_count')
                    )
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->join('invoices', function ($join) use ($group) {
                            $join->on('students.student_id', '=', 'invoices.student_id')
                                ->where('invoices.group_id', $group->group_id)
                                ->whereRaw('invoices.amount > invoices.amount_paid');
                        })
                        ->groupBy('students.student_id', 'students.student_name', 'users.username', 'users.email')
                        ->get();

                    return [
                        'group_id' => $group->group_id,
                        'group_name' => $group->group_name,
                        'course_name' => $group->course_name,
                        'teacher_name' => $group->teacher_name,
                        'start_date' => $group->start_date,
                        'end_date' => $group->end_date,
                        'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                        'days_remaining' => Carbon::parse($group->end_date)->diffInDays($today, false) > 0 ? Carbon::parse($group->end_date)->diffInDays($today, false) : 0,
                        'student_count' => $group->student_count,
                        'total_invoices' => (float) $group->total_invoices,
                        'total_paid' => (float) $group->total_paid,
                        'total_due' => (float) $group->total_due,
                        'has_unpaid_invoices' => $group->total_due > 0,
                        'urgency_level' => Carbon::parse($group->end_date)->diffInDays($today, false) <= 7 ? 'high' :
                                         (Carbon::parse($group->end_date)->diffInDays($today, false) <= 14 ? 'medium' : 'low'),
                        'unpaid_students' => $unpaidStudents->map(function ($student) {
                            return [
                                'student_id' => $student->student_id,
                                'student_name' => $student->student_name,
                                'username' => $student->username,
                                'email' => $student->email,
                                'amount_due' => (float) $student->student_due,
                                'unpaid_invoices_count' => $student->unpaid_invoices_count,
                            ];
                        }),
                    ];
                });

            // 3. الطلاب الذين دفعوا وليس لهم جروبات نشطة - محدث
            $paidStudentsNoActiveGroups = Student::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                'users.email',
                DB::raw('MAX(groups.end_date) as last_group_end'),
                DB::raw('MAX(groups.group_name) as last_group_name'),
                DB::raw('MAX(courses.course_name) as last_course_name'),
                DB::raw('SUM(invoices.amount_paid) as total_paid'),
                DB::raw('COUNT(DISTINCT invoices.invoice_id) as paid_invoices_count')
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('student_group', 'students.student_id', '=', 'student_group.student_id')
                ->leftJoin('groups', function ($join) use ($today) {
                    $join->on('student_group.group_id', '=', 'groups.group_id')
                        ->whereNotNull('groups.end_date')
                        ->where('groups.end_date', '<', $today);
                })
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('invoices', function ($join) {
                    $join->on('students.student_id', '=', 'invoices.student_id')
                        ->whereRaw('invoices.amount <= invoices.amount_paid');
                })
                ->whereDoesntHave('groups', function ($query) use ($today) {
                    $query->where(function ($q) use ($today) {
                        $q->whereNull('groups.end_date')
                            ->orWhere('groups.end_date', '>=', $today);
                    });
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username', 'users.email')
                ->having('total_paid', '>', 0)
                ->orderBy('total_paid', 'DESC')
                ->get()
                ->map(function ($student) {
                    // الحصول على جميع الفواتير المدفوعة
                    $paidInvoices = Invoice::where('student_id', $student->student_id)
                        ->whereRaw('amount <= amount_paid')
                        ->orderBy('created_at', 'DESC')
                        ->get();

                    // الحصول على جميع الجروبات التي انتهت لهذا الطالب
                    $completedGroups = Group::select(
                        'groups.group_id',
                        'groups.group_name',
                        'courses.course_name',
                        'groups.start_date',
                        'groups.end_date'
                    )
                        ->join('student_group', 'groups.group_id', '=', 'student_group.group_id')
                        ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                        ->where('student_group.student_id', $student->student_id)
                        ->whereNotNull('groups.end_date')
                        ->orderBy('groups.end_date', 'DESC')
                        ->get();

                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->username,
                        'email' => $student->email,
                        'last_group_name' => $student->last_group_name,
                        'last_course_name' => $student->last_course_name,
                        'last_group_end' => $student->last_group_end ? Carbon::parse($student->last_group_end)->format('Y-m-d') : null,
                        'total_paid' => (float) $student->total_paid,
                        'paid_invoices_count' => $student->paid_invoices_count,
                        'completed_groups' => $completedGroups->map(function ($group) {
                            return [
                                'group_id' => $group->group_id,
                                'group_name' => $group->group_name,
                                'course_name' => $group->course_name,
                                'start_date' => $group->start_date,
                                'end_date' => $group->end_date,
                                'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                            ];
                        }),
                        'paid_invoices' => $paidInvoices->map(function ($invoice) {
                            return [
                                'invoice_id' => $invoice->invoice_id,
                                'invoice_number' => $invoice->invoice_number,
                                'amount' => (float) $invoice->amount,
                                'amount_paid' => (float) $invoice->amount_paid,
                                'created_at' => $invoice->created_at,
                                'description' => $invoice->description,
                            ];
                        }),
                    ];
                });

            // 4. الإحصائيات الإجمالية
            $stats = [
                'total_expired_with_unpaid' => $expiredWithUnpaid->count(),
                'total_about_to_expire' => $aboutToExpire->count(),
                'total_paid_students_no_active' => $paidStudentsNoActiveGroups->count(),
                'total_unpaid_amount_expired' => $expiredWithUnpaid->sum('total_due'),
                'total_unpaid_amount_about_to_expire' => $aboutToExpire->sum('total_due'),
                'total_paid_amount_students' => $paidStudentsNoActiveGroups->sum('total_paid'),
                'total_unpaid_students_expired' => $expiredWithUnpaid->sum(function ($group) {
                    return count($group['unpaid_students'] ?? []);
                }),
                'total_unpaid_students_about_to_expire' => $aboutToExpire->sum(function ($group) {
                    return count($group['unpaid_students'] ?? []);
                }),
            ];

            return [
                'expired_with_unpaid' => $expiredWithUnpaid,
                'about_to_expire' => $aboutToExpire,
                'paid_students_no_active_groups' => $paidStudentsNoActiveGroups,
                'stats' => $stats,
                'today' => $today->format('Y-m-d'),
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            Log::error('Error in getGroupsFinancialData: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'expired_with_unpaid' => collect(),
                'about_to_expire' => collect(),
                'paid_students_no_active_groups' => collect(),
                'stats' => [
                    'total_expired_with_unpaid' => 0,
                    'total_about_to_expire' => 0,
                    'total_paid_students_no_active' => 0,
                    'total_unpaid_amount_expired' => 0,
                    'total_unpaid_amount_about_to_expire' => 0,
                    'total_paid_amount_students' => 0,
                    'total_unpaid_students_expired' => 0,
                    'total_unpaid_students_about_to_expire' => 0,
                ],
                'today' => date('Y-m-d'),
                'generated_at' => date('Y-m-d H:i:s'),
            ];
        }
    }
    // في StudentReportController.php - إضافة هذه الدوال الجديدة

    /**
     * API لجلب البيانات المالية الكاملة
     */
    public function getCompleteFinancialData(Request $request)
    {
        try {
            $today = Carbon::now();

            // استخدام الدالة المحسنة
            $data = $this->getGroupsFinancialData('complete', null, null, null);

            // إذا كانت البيانات فارغة، استخدام بيانات تجريبية مع تفاصيل كاملة
            if ($data['expired_with_unpaid']->isEmpty() &&
                $data['about_to_expire']->isEmpty() &&
                $data['paid_students_no_active_groups']->isEmpty()) {

                // بيانات تجريبية مفصلة
                $data = $this->getMockCompleteFinancialData();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'تم تحميل البيانات المالية الكاملة بنجاح',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getCompleteFinancialData: '.$e->getMessage());

            return response()->json([
                'success' => true,
                'data' => $this->getMockCompleteFinancialData(),
                'message' => 'تم تحميل بيانات تجريبية مع تفاصيل كاملة',
            ]);
        }
    }

    /**
     * بيانات تجريبية كاملة مع تفاصيل
     */

    // app/Http/Controllers/StudentReportController.php

    /**
     * جلب ملخص الجروبات المالية
     */
    // في StudentReportController.php - إضافة هذه الدوال الجديدة

    /**
     * جلب تفاصيل الجروبات المالية
     */

    /**
     * الحصول على تفاصيل الجروبات المنتهية
     */

    /**
     * الحصول على تفاصيل الجروبات القريبة من الانتهاء
     */

    /**
     * الحصول على تفاصيل الطلاب الدافعين بدون جروبات نشطة
     */

    /**
     * بيانات تجريبية للعرض
     */

    /**
     * API لجلب جميع الجروبات المنتهية
     */
    // في StudentReportController.php - إضافة هذه الدوال الجديدة

    /**
     * جلب تفاصيل الجروبات المالية
     */
    public function getGroupsFinancialDetails(Request $request)
    {
        try {
            $type = $request->input('type', 'all');
            $limit = $request->input('limit', 20);

            $data = [];

            if ($type === 'all' || $type === 'expired') {
                $data['expired_groups'] = $this->getExpiredGroupsDetails($limit);
            }

            if ($type === 'all' || $type === 'about_to_expire') {
                $data['about_to_expire'] = $this->getAboutToExpireDetails($limit);
            }

            if ($type === 'all' || $type === 'paid_students') {
                $data['paid_students'] = $this->getPaidStudentsDetails($limit);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getGroupsFinancialDetails: '.$e->getMessage());

            return response()->json([
                'success' => true,
                'data' => $this->getMockFinancialData(),
                'message' => 'تم تحميل بيانات تجريبية للعرض',
            ]);
        }
    }

    /**
     * الحصول على تفاصيل الجروبات المنتهية
     */
    private function getExpiredGroupsDetails($limit = 20)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        return $this->getBaseFinancialQuery()
            ->havingRaw('total_due > 0')
            ->where('groups.end_date', '<', Carbon::now())
            ->where('groups.end_date', '>=', $thirtyDaysAgo) // آخر 30 يوم فقط
            ->orderBy('total_due', 'DESC')
            ->limit($limit)
            ->get()
            ->map(function ($group) {
                return [
                    'group_id' => $group->group_id,
                    'group_name' => $group->group_name,
                    'course_name' => $group->course_name,
                    'teacher_name' => $group->teacher_name,
                    'start_date' => $group->start_date,
                    'end_date' => $group->end_date,
                    'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                    'days_since_expiry' => Carbon::parse($group->end_date)->diffInDays(Carbon::now()),
                    'student_count' => $group->student_count,
                    'total_due' => (float) $group->total_due,
                ];
            });
    }

    /**
     * الحصول على تفاصيل الجروبات القريبة من الانتهاء
     */
    private function getAboutToExpireDetails($limit = 20)
    {
        $today = Carbon::now();
        $thirtyDaysFromNow = $today->copy()->addDays(30);

        return $this->getBaseFinancialQuery()
            ->havingRaw('total_due > 0')
            ->where('groups.end_date', '>=', $today)
            ->where('groups.end_date', '<=', $thirtyDaysFromNow)
            ->orderBy('groups.end_date', 'ASC')
            ->limit($limit)
            ->get()
            ->map(function ($group) {
                return [
                    'group_id' => $group->group_id,
                    'group_name' => $group->group_name,
                    'course_name' => $group->course_name,
                    'teacher_name' => $group->teacher_name,
                    'start_date' => $group->start_date,
                    'end_date' => $group->end_date,
                    'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                    'days_remaining' => Carbon::now()->diffInDays(Carbon::parse($group->end_date), false),
                    'student_count' => $group->student_count,
                    'total_due' => (float) $group->total_due,
                ];
            });
    }

    /**
     * الحصول على تفاصيل الطلاب الدافعين بدون جروبات نشطة
     */
    private function getPaidStudentsDetails($limit = 20)
    {
        return Student::select(
            'students.student_id',
            'students.student_name',
            'users.username',
            DB::raw('MAX(groups.group_name) as last_group_name'),
            DB::raw('MAX(groups.end_date) as last_group_end'),
            DB::raw('SUM(invoices.amount_paid) as total_paid')
        )
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('student_group', 'students.student_id', '=', 'student_group.student_id')
            ->leftJoin('groups', function ($join) {
                $join->on('student_group.group_id', '=', 'groups.group_id')
                    ->whereNotNull('groups.end_date')
                    ->where('groups.end_date', '<', Carbon::now());
            })
            ->leftJoin('invoices', function ($join) {
                $join->on('students.student_id', '=', 'invoices.student_id')
                    ->whereRaw('invoices.amount <= invoices.amount_paid');
            })
            ->whereDoesntHave('groups', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('groups.end_date')
                        ->orWhere('groups.end_date', '>=', Carbon::now());
                });
            })
            ->groupBy('students.student_id', 'students.student_name', 'users.username')
            ->having('total_paid', '>', 0)
            ->orderBy('last_group_end', 'DESC')
            ->limit($limit)
            ->get()
            ->map(function ($student) {
                return [
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'username' => $student->username,
                    'last_group_name' => $student->last_group_name,
                    'last_group_end' => $student->last_group_end ? Carbon::parse($student->last_group_end)->format('Y-m-d') : null,
                    'total_paid' => (float) $student->total_paid,
                ];
            });
    }

    /**
     * بيانات تجريبية للعرض
     */
    private function getMockFinancialData()
    {
        return [
            'expired_groups' => [
                [
                    'group_id' => 1,
                    'group_name' => 'Web Development - Group A',
                    'course_name' => 'Web Development',
                    'teacher_name' => 'أحمد محمد',
                    'student_count' => 15,
                    'total_due' => 9750.5,
                    'end_date_formatted' => '2024-01-15',
                    'days_since_expiry' => 45,
                ],
                [
                    'group_id' => 2,
                    'group_name' => 'Python Programming - Beginners',
                    'course_name' => 'Python Programming',
                    'teacher_name' => 'سارة أحمد',
                    'student_count' => 20,
                    'total_due' => 12000,
                    'end_date_formatted' => '2024-01-20',
                    'days_since_expiry' => 40,
                ],
            ],
            'about_to_expire' => [
                [
                    'group_id' => 3,
                    'group_name' => 'Mobile Development - Group B',
                    'course_name' => 'Mobile Development',
                    'teacher_name' => 'محمد خالد',
                    'student_count' => 18,
                    'total_due' => 15000,
                    'end_date_formatted' => '2024-02-15',
                    'days_remaining' => 14,
                ],
            ],
            'paid_students' => [
                [
                    'student_id' => 101,
                    'student_name' => 'محمد أحمد',
                    'username' => 'mohamed2024',
                    'last_group_name' => 'Web Development',
                    'last_group_end' => '2024-01-31',
                    'total_paid' => 10400.01,
                ],
                [
                    'student_id' => 102,
                    'student_name' => 'فاطمة علي',
                    'username' => 'fatima2023',
                    'last_group_name' => 'Data Science',
                    'last_group_end' => '2024-01-25',
                    'total_paid' => 8500,
                ],
            ],
        ];
    }

    /**
     * API لجلب جميع الجروبات المنتهية
     */
    // إضافة هذه الدوال إلى StudentReportController

    /**
     * جلب الجروبات المنتهية مع تفاصيل الطلاب غير الدافعين
     */
    public function getExpiredGroupsWithUnpaidInvoices(Request $request)
    {
        try {
            $today = Carbon::now();

            // Use the centralized base query
            $groups = $this->getBaseFinancialQuery()
                ->havingRaw('total_due > 0')
                ->where('groups.end_date', '<', $today)
                ->orderBy('groups.end_date', 'DESC')
                ->get()
                ->map(function ($group) use ($today) {
                    // Get detailed unpaid students for this group
                    $unpaidStudents = Student::select(
                        'students.student_id',
                        'students.student_name',
                        'users.username',
                        'users.email',
                        DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as student_due'),
                        DB::raw('COUNT(invoices.invoice_id) as unpaid_invoices_count')
                    )
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->join('invoices', function ($join) use ($group) {
                            $join->on('students.student_id', '=', 'invoices.student_id')
                                ->where('invoices.group_id', $group->group_id)
                                ->whereRaw('invoices.amount > invoices.amount_paid');
                        })
                        ->groupBy('students.student_id', 'students.student_name', 'users.username', 'users.email')
                        ->get();

                    return [
                        'group_id' => $group->group_id,
                        'group_name' => $group->group_name,
                        'course_name' => $group->course_name,
                        'teacher_name' => $group->teacher_name,
                        'start_date' => $group->start_date,
                        'end_date' => $group->end_date,
                        'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                        'days_since_expiry' => Carbon::parse($group->end_date)->diffInDays($today),
                        'student_count' => $group->student_count,
                        'unpaid_students_count' => $unpaidStudents->count(), // More accurate count
                        'total_due' => (float) $group->total_due,
                        'unpaid_students' => $unpaidStudents->map(function ($student) {
                            return [
                                'student_id' => $student->student_id,
                                'student_name' => $student->student_name,
                                'username' => $student->username,
                                'email' => $student->email,
                                'amount_due' => (float) $student->student_due,
                                'unpaid_invoices_count' => $student->unpaid_invoices_count,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'groups' => $groups,
                    'total_groups' => $groups->count(),
                    'total_due' => $groups->sum('total_due'),
                    'total_unpaid_students' => $groups->sum('unpaid_students_count'),
                    'today' => $today->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getExpiredGroupsWithUnpaidInvoices: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب الطلاب الذين دفعوا وليس لهم جروبات نشطة
     */
    public function getPaidStudentsWithoutActiveGroups(Request $request)
    {
        try {
            $today = Carbon::now();

            // الحصول على الطلاب الذين ليس لهم جروبات نشطة
            $students = Student::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                'users.email',
                DB::raw('MAX(groups.end_date) as last_group_end'),
                DB::raw('MAX(groups.group_name) as last_group_name'),
                DB::raw('MAX(courses.course_name) as last_course_name'),
                DB::raw('SUM(invoices.amount_paid) as total_paid'),
                DB::raw('COUNT(DISTINCT invoices.invoice_id) as paid_invoices_count'),
                DB::raw('GROUP_CONCAT(DISTINCT groups.group_name SEPARATOR ", ") as all_groups_attended')
            )
                ->join('users', 'students.user_id', '=', 'users.id')

            // الحصول على الجروبات التي انتهت (لا جروبات نشطة حالياً)
                ->leftJoin('student_group', 'students.student_id', '=', 'student_group.student_id')
                ->leftJoin('groups', function ($join) use ($today) {
                    $join->on('student_group.group_id', '=', 'groups.group_id')
                        ->whereNotNull('groups.end_date')
                        ->where('groups.end_date', '<', $today);
                })
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')

            // الحصول على الفواتير المدفوعة بالكامل
                ->leftJoin('invoices', function ($join) {
                    $join->on('students.student_id', '=', 'invoices.student_id')
                        ->whereRaw('invoices.amount <= invoices.amount_paid');
                })

            // التأكد من عدم وجود جروبات نشطة حالياً
                ->whereDoesntHave('groups', function ($query) use ($today) {
                    $query->where(function ($q) use ($today) {
                        $q->whereNull('groups.end_date')
                            ->orWhere('groups.end_date', '>=', $today);
                    });
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username', 'users.email')
                ->having('total_paid', '>', 0)
                ->orderBy('total_paid', 'DESC')
                ->get()
                ->map(function ($student) {
                    // الحصول على جميع الجروبات التي انتهت لهذا الطالب
                    $completedGroups = Group::select(
                        'groups.group_id',
                        'groups.group_name',
                        'courses.course_name',
                        'groups.start_date',
                        'groups.end_date'
                    )
                        ->join('student_group', 'groups.group_id', '=', 'student_group.group_id')
                        ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                        ->where('student_group.student_id', $student->student_id)
                        ->whereNotNull('groups.end_date')
                        ->orderBy('groups.end_date', 'DESC')
                        ->get();

                    // الحصول على جميع الفواتير المدفوعة
                    $paidInvoices = Invoice::where('student_id', $student->student_id)
                        ->whereRaw('amount <= amount_paid')
                        ->orderBy('created_at', 'DESC')
                        ->get();

                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->username,
                        'email' => $student->email,
                        'last_group_name' => $student->last_group_name,
                        'last_course_name' => $student->last_course_name,
                        'last_group_end' => $student->last_group_end ? Carbon::parse($student->last_group_end)->format('Y-m-d') : null,
                        'total_paid' => (float) $student->total_paid,
                        'paid_invoices_count' => $student->paid_invoices_count,
                        'completed_groups' => $completedGroups->map(function ($group) {
                            return [
                                'group_id' => $group->group_id,
                                'group_name' => $group->group_name,
                                'course_name' => $group->course_name,
                                'start_date' => $group->start_date,
                                'end_date' => $group->end_date,
                                'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                            ];
                        }),
                        'paid_invoices' => $paidInvoices->map(function ($invoice) {
                            return [
                                'invoice_id' => $invoice->invoice_id,
                                'invoice_number' => $invoice->invoice_number,
                                'amount' => (float) $invoice->amount,
                                'amount_paid' => (float) $invoice->amount_paid,
                                'created_at' => $invoice->created_at,
                                'description' => $invoice->description,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'students' => $students,
                    'total_students' => $students->count(),
                    'total_paid' => $students->sum('total_paid'),
                    'today' => $today->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getPaidStudentsWithoutActiveGroups: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات',
            ], 500);
        }
    }

    /**
     * جلب الجروبات القريبة من الانتهاء مع فواتير غير مدفوعة
     */
    public function getAboutToExpireGroupsWithUnpaid(Request $request)
    {
        try {
            $today = Carbon::now();
            $thirtyDaysFromNow = $today->copy()->addDays(30);

            // Use the centralized base query
            $groups = $this->getBaseFinancialQuery()
                ->havingRaw('total_due > 0')
                ->where('groups.end_date', '>=', $today)
                ->where('groups.end_date', '<=', $thirtyDaysFromNow)
                ->orderBy('groups.end_date', 'ASC')
                ->get()
                ->map(function ($group) use ($today) {
                    $unpaidStudents = Student::select(
                        'students.student_id',
                        'students.student_name',
                        'users.username',
                        'users.email',
                        DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as student_due'),
                        DB::raw('COUNT(invoices.invoice_id) as unpaid_invoices_count')
                    )
                        ->join('users', 'students.user_id', '=', 'users.id')
                        ->join('invoices', function ($join) use ($group) {
                            $join->on('students.student_id', '=', 'invoices.student_id')
                                ->where('invoices.group_id', $group->group_id)
                                ->whereRaw('invoices.amount > invoices.amount_paid');
                        })
                        ->groupBy('students.student_id', 'students.student_name', 'users.username', 'users.email')
                        ->get();

                    $daysRemaining = Carbon::parse($group->end_date)->diffInDays($today);
                    // Note: diffInDays gives absolute difference.
                    // Since it's about to expire (future), target - today = positive.
                    $daysRemaining = Carbon::now()->diffInDays(Carbon::parse($group->end_date), false);
                    if ($daysRemaining < 0) {
                        $daysRemaining = 0;
                    } // Should not happen given the query

                    return [
                        'group_id' => $group->group_id,
                        'group_name' => $group->group_name,
                        'course_name' => $group->course_name,
                        'teacher_name' => $group->teacher_name,
                        'start_date' => $group->start_date,
                        'end_date' => $group->end_date,
                        'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                        'days_remaining' => (int) $daysRemaining,
                        'student_count' => $group->student_count,
                        'unpaid_students_count' => $unpaidStudents->count(),
                        'total_due' => (float) $group->total_due,
                        'urgency_level' => $daysRemaining <= 7 ? 'high' :
                                         ($daysRemaining <= 14 ? 'medium' : 'low'),
                        'unpaid_students' => $unpaidStudents->map(function ($student) {
                            return [
                                'student_id' => $student->student_id,
                                'student_name' => $student->student_name,
                                'username' => $student->username,
                                'email' => $student->email,
                                'amount_due' => (float) $student->student_due,
                                'unpaid_invoices_count' => $student->unpaid_invoices_count,
                            ];
                        }),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'groups' => $groups,
                    'total_groups' => $groups->count(),
                    'total_due' => $groups->sum('total_due'),
                    'total_unpaid_students' => $groups->sum('unpaid_students_count'),
                    'high_urgency_groups' => $groups->where('urgency_level', 'high')->count(),
                    'medium_urgency_groups' => $groups->where('urgency_level', 'medium')->count(),
                    'low_urgency_groups' => $groups->where('urgency_level', 'low')->count(),
                    'today' => $today->format('Y-m-d'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getAboutToExpireGroupsWithUnpaid: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getAllExpiredGroups(Request $request)
    {
        try {
            $limit = $request->input('limit', 100);
            $page = $request->input('page', 1);

            $groups = Group::select(
                'groups.group_id',
                'groups.group_name',
                'courses.course_name',
                'teachers.teacher_name',
                'groups.start_date',
                'groups.end_date',
                DB::raw('DATEDIFF(CURDATE(), groups.end_date) as days_since_expiry'),
                DB::raw('COUNT(DISTINCT student_group.student_id) as student_count'),
                DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as total_due')
            )
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
                ->leftJoin('invoices', function ($join) {
                    $join->on('student_group.student_id', '=', 'invoices.student_id')
                        ->where('invoices.group_id', DB::raw('groups.group_id'));
                })
                ->whereNotNull('groups.end_date')
                ->where('groups.end_date', '<', Carbon::now())
                ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name',
                    'teachers.teacher_name', 'groups.start_date', 'groups.end_date')
                ->having('total_due', '>', 0)
                ->orderBy('groups.end_date', 'DESC')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => [
                    'groups' => $groups->items(),
                    'pagination' => [
                        'total' => $groups->total(),
                        'per_page' => $groups->perPage(),
                        'current_page' => $groups->currentPage(),
                        'last_page' => $groups->lastPage(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getAllExpiredGroups: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات',
            ], 500);
        }
    }

    // في StudentReportController - إضافة أو تحديث هذه المسارات

    /**
     * الحصول على ملخص البيانات المالية للجروبات (للأداء المتكرر)
     */
    public function getGroupsFinancialSummary(Request $request)
    {
        try {
            $user = auth()->user();

            // التحقق من الصلاحيات
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'غير مصرح به'], 401);
            }

            // التحقق من صلاحيات المستخدم
            $canViewReports = $user->isAdminFull() || $user->isAdminPartial() || $user->isSecretary();

            if (! $canViewReports) {
                return response()->json([
                    'success' => false,
                    'message' => 'ليس لديك صلاحيات لعرض التقارير المالية',
                ], 403);
            }

            $today = Carbon::now();
            $thirtyDaysFromNow = $today->copy()->addDays(30);

            // 1. الجروبات المنتهية مع فواتير غير مدفوعة
            $expiredStats = $this->getBaseFinancialQuery()
                ->havingRaw('total_due > 0')
                ->where('groups.end_date', '<', $today)
                ->get();

            $expiredCount = $expiredStats->count();
            $expiredDue = $expiredStats->sum('total_due');

            // 2. الجروبات التي على وشك الانتهاء مع فواتير غير مدفوعة
            $aboutToExpireStats = $this->getBaseFinancialQuery()
                ->havingRaw('total_due > 0')
                ->where('groups.end_date', '>=', $today)
                ->where('groups.end_date', '<=', $thirtyDaysFromNow)
                ->get();

            $aboutToExpireCount = $aboutToExpireStats->count();
            $aboutToExpireDue = $aboutToExpireStats->sum('total_due');

            // 3. الطلاب الذين دفعوا وليس لهم جروبات نشطة
            $studentsPaidNoActive = Student::select(
                DB::raw('COUNT(DISTINCT students.student_id) as student_count'),
                DB::raw('COALESCE(SUM(invoices.amount_paid), 0) as total_paid')
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('invoices', 'students.student_id', '=', 'invoices.student_id')
                ->whereDoesntHave('groups', function ($query) use ($today) {
                    $query->where(function ($q) use ($today) {
                        $q->whereNull('groups.end_date')
                            ->orWhere('groups.end_date', '>=', $today);
                    });
                })
                ->whereHas('invoices')
                ->first();

            $data = [
                'expired_groups_with_unpaid' => [
                    'count' => $expiredCount,
                    'total_due' => (float) $expiredDue,
                ],
                'about_to_expire_with_unpaid' => [
                    'count' => $aboutToExpireCount,
                    'total_due' => (float) $aboutToExpireDue,
                ],
                'students_paid_no_active_groups' => [
                    'count' => $studentsPaidNoActive->student_count ?? 0,
                    'total_paid' => (float) ($studentsPaidNoActive->total_paid ?? 0),
                ],
                'today' => $today->format('Y-m-d'),
                'timestamp' => $today->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'تم تحميل البيانات بنجاح',
            ]);

        } catch (\Throwable $e) {
            \Log::error('Error in getGroupsFinancialSummary: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => true,
                'data' => [
                    'expired_groups_with_unpaid' => ['count' => 0, 'total_due' => 0],
                    'about_to_expire_with_unpaid' => ['count' => 0, 'total_due' => 0],
                    'students_paid_no_active_groups' => ['count' => 0, 'total_paid' => 0],
                    'today' => date('Y-m-d'),
                ],
                'message' => 'تم تحميل بيانات افتراضية',
            ]);
        }
    }
    // في StudentReportController.php - إضافة هذه الدوال

    /**
     * Endpoint for the overall report
     */
    public function overallReport(Request $request)
    {
        try {
            $data = $this->getOverallReportData();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in overallReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التقرير الشامل: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * دالة مساعدة لجلب بيانات التقرير الكلي
     */
    private function getOverallReportData()
    {
        return [
            'summary' => $this->getOverallSummary(),
            'top_by_grades_all_time' => $this->getTopByGradesAllTime(),
            'top_by_attendance_all_time' => $this->getTopByAttendanceAllTime(),
            'students_with_no_debts' => $this->getStudentsWithNoDebtsAllTime(),
            'students_with_discounts' => $this->getStudentsWithDiscountsAllTime(),
            'best_performing_courses' => $this->getBestPerformingCourses(),
            'students_by_status' => $this->getStudentsByStatus(),
            'revenue_trends' => $this->getRevenueTrends(),
            'attendance_trends' => $this->getAttendanceTrends(),
            'enrollment_trends' => $this->getEnrollmentTrends(),
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * تحديث دوال التقارير لإضافة البيانات المالية
     */
    public function dailyReport(Request $request)
    {
        try {
            $date = $request->input('date', now()->format('Y-m-d'));

            $report = [
                'date' => $date,
                'summary' => $this->getDailySummary($date),
                'top_by_grades' => $this->getTopByGradesDaily($date),
                'top_by_attendance' => $this->getTopByAttendanceDaily($date),
                'students_with_no_debts' => $this->getStudentsWithNoDebtsDaily($date),
                'students_with_discounts' => $this->getStudentsWithDiscountsDaily($date),
                'new_students' => $this->getNewStudentsDaily($date),
                'total_students' => Student::count(),
                'groups_financial' => $this->getGroupsFinancialData('daily', $date, null, null), // أضف هذا
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in dailyReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تصدير تقرير الجروبات المالية
     */
    public function exportGroupsFinancialReport(Request $request)
    {
        try {
            $type = $request->input('type', 'pdf');
            $reportType = $request->input('report_type', 'daily');
            $date = $request->input('date', now()->format('Y-m-d'));

            $data = $this->getGroupsFinancialData($reportType, $date, null, null);

            if ($type === 'pdf') {
                $pdf = Pdf::loadView('reports.students.pdf.groups_financial', [
                    'data' => $data,
                    'type' => $reportType,
                    'date' => $date,
                ]);

                return $pdf->download('groups_financial_report_'.now()->format('Y_m_d').'.pdf');
            } elseif ($type === 'excel') {
                return Excel::download(new GroupsFinancialExport($data), 'groups_financial_report.xlsx');
            }

            return response()->json([
                'success' => false,
                'message' => 'نوع التصدير غير مدعوم',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in exportGroupsFinancialReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في التصدير',
            ], 500);
        }
    }

    /**
     * إرسال تنبيهات للجروبات المنتهية مع فواتير غير مدفوعة
     */
    public function sendGroupsFinancialAlerts()
    {
        try {
            $today = Carbon::now();
            $data = $this->getGroupsFinancialData('daily', $today->format('Y-m-d'), null, null);

            $alerts = [];

            // 1. تنبيهات الجروبات المنتهية مع فواتير غير مدفوعة
            foreach ($data['expired_with_unpaid'] as $group) {
                $alerts[] = [
                    'type' => 'expired_with_unpaid',
                    'group_name' => $group['group_name'],
                    'course_name' => $group['course_name'],
                    'days_since_expiry' => $group['days_since_expiry'],
                    'total_due' => $group['total_due'],
                    'students_count' => $group['student_count'],
                ];
            }

            // 2. تنبيهات الجروبات التي على وشك الانتهاء
            foreach ($data['about_to_expire'] as $group) {
                if ($group['days_remaining'] <= 7) {
                    $alerts[] = [
                        'type' => 'urgent_about_to_expire',
                        'group_name' => $group['group_name'],
                        'course_name' => $group['course_name'],
                        'days_remaining' => $group['days_remaining'],
                        'total_due' => $group['total_due'],
                        'end_date' => $group['end_date_formatted'],
                    ];
                }
            }

            // هنا يمكنك إضافة كود إرسال الإشعارات أو الإيميلات
            // مثال: إرسال إيميل للمسؤول
            if (! empty($alerts)) {
                \Mail::to('admin@example.com')->send(new \App\Mail\GroupsFinancialAlerts($alerts));
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال التنبيهات',
                'alerts_count' => count($alerts),
                'alerts' => $alerts,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in sendGroupsFinancialAlerts: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إرسال التنبيهات',
            ], 500);
        }
    }

    /**
     * جلب قائمة الطلاب في مجموعة مع فواتيرهم
     */
    public function getGroupStudentsWithInvoices($groupId)
    {
        try {
            $group = Group::with([
                'students.user',
                'students.invoices' => function ($query) use ($groupId) {
                    $query->where('group_id', $groupId);
                },
            ])->find($groupId);

            if (! $group) {
                return response()->json([
                    'success' => false,
                    'message' => 'المجموعة غير موجودة',
                ], 404);
            }

            $students = $group->students->map(function ($student) use ($groupId) {
                $groupInvoices = $student->invoices->where('group_id', $groupId);
                $totalInvoices = $groupInvoices->sum('amount');
                $totalPaid = $groupInvoices->sum('amount_paid');
                $totalDue = $totalInvoices - $totalPaid;

                return [
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'username' => $student->user->username ?? '',
                    'invoices_count' => $groupInvoices->count(),
                    'total_invoices' => $totalInvoices,
                    'total_paid' => $totalPaid,
                    'total_due' => $totalDue,
                    'has_unpaid' => $totalDue > 0,
                    'invoices' => $groupInvoices->map(function ($invoice) {
                        return [
                            'invoice_id' => $invoice->invoice_id,
                            'invoice_number' => $invoice->invoice_number,
                            'amount' => $invoice->amount,
                            'amount_paid' => $invoice->amount_paid,
                            'due' => $invoice->amount - $invoice->amount_paid,
                            'status' => $invoice->status,
                            'created_at' => $invoice->created_at,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'group' => [
                    'group_id' => $group->group_id,
                    'group_name' => $group->group_name,
                    'course_name' => $group->course->course_name ?? '',
                    'start_date' => $group->start_date,
                    'end_date' => $group->end_date,
                    'is_expired' => $group->end_date && $group->end_date < Carbon::now(),
                ],
                'students' => $students,
                'summary' => [
                    'total_students' => $students->count(),
                    'students_with_unpaid' => $students->where('has_unpaid', true)->count(),
                    'total_invoices_amount' => $students->sum('total_invoices'),
                    'total_paid_amount' => $students->sum('total_paid'),
                    'total_due_amount' => $students->sum('total_due'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getGroupStudentsWithInvoices: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات',
            ], 500);
        }
    }
    // كرر نفس الشيء للدوال الأخرى: weeklyReport, monthlyReport, annualReport, overallReport

    /**
     * تقرير أسبوعي شامل
     */
    public function weeklyReport(Request $request)
    {
        try {
            $week = $request->input('week');
            $year = $request->input('year', now()->year);
            $weekNumber = $request->input('week_number');

            // إذا لم يتم تحديد أسبوع محدد، استخدام الأسبوع الحالي
            if (! $week && ! $weekNumber) {
                $weekNumber = Carbon::now()->weekOfYear;
                $year = Carbon::now()->year;
            } elseif ($week) {
                // معالجة تنسيق ISO Week (مثل: 2025-W01)
                if (strpos($week, '-W') !== false) {
                    [$year, $weekNumber] = explode('-W', $week);
                } elseif (strpos($week, 'W') !== false) {
                    [$year, $weekNumber] = explode('W', $week);
                }
            }

            // التأكد من صحة الأرقام
            $year = intval($year);
            $weekNumber = intval($weekNumber);

            if ($year < 2000 || $year > 2100 || $weekNumber < 1 || $weekNumber > 53) {
                $year = Carbon::now()->year;
                $weekNumber = Carbon::now()->weekOfYear;
            }

            $startDate = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek();
            $endDate = Carbon::now()->setISODate($year, $weekNumber)->endOfWeek();

            $report = [
                'week' => "{$year}-W".str_pad($weekNumber, 2, '0', STR_PAD_LEFT),
                'year' => $year,
                'week_number' => $weekNumber,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'summary' => $this->getWeeklySummary($startDate, $endDate),
                'top_by_grades' => $this->getTopByGradesWeekly($startDate, $endDate),
                'top_by_attendance' => $this->getTopByAttendanceWeekly($startDate, $endDate),
                'students_with_no_debts' => $this->getStudentsWithNoDebtsWeekly($startDate, $endDate),
                'students_with_discounts' => $this->getStudentsWithDiscountsWeekly($startDate, $endDate),
                'daily_breakdown' => $this->getDailyBreakdown($startDate, $endDate),
                'new_students' => $this->getNewStudentsWeekly($startDate, $endDate),
            ];

            return response()->json(['success' => true, 'data' => $report]);

        } catch (\Exception $e) {
            Log::error('Error in weeklyReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل التقرير الأسبوعي: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تقرير شهري شامل
     */
    public function monthlyReport(Request $request)
    {
        try {
            $month = $request->input('month', now()->format('Y-m'));
            [$year, $monthNum] = explode('-', $month);

            $startDate = Carbon::create($year, $monthNum, 1)->startOfMonth();
            $endDate = Carbon::create($year, $monthNum, 1)->endOfMonth();

            $report = [
                'month' => $month,
                'year' => $year,
                'month_name' => Carbon::create($year, $monthNum, 1)->format('F'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'summary' => $this->getMonthlySummary($startDate, $endDate),
                'top_by_grades' => $this->getTopByGradesMonthly($startDate, $endDate),
                'top_by_attendance' => $this->getTopByAttendanceMonthly($startDate, $endDate),
                'students_with_no_debts' => $this->getStudentsWithNoDebtsMonthly($startDate, $endDate),
                'students_with_discounts' => $this->getStudentsWithDiscountsMonthly($startDate, $endDate),
                'weekly_breakdown' => $this->getWeeklyBreakdown($startDate, $endDate),
                'new_students' => $this->getNewStudentsMonthly($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in monthlyReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تقرير سنوي شامل
     */
    /**
     * تقرير سنوي شامل
     */
    public function annualReport(Request $request)
    {
        try {
            $year = $request->input('year', now()->format('Y'));

            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate = Carbon::create($year, 12, 31)->endOfYear();

            // الحصول على الطلاب الذين سجلوا في هذه السنة
            $newStudents = Student::whereBetween('enrollment_date', [$startDate, $endDate])->count();

            // الطلاب الذين كانوا نشطين في هذه السنة (حضروا على الأقل مرة واحدة)
            $activeStudents = Student::whereHas('attendances', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('recorded_at', [$startDate, $endDate]);
            })->count();

            $report = [
                'year' => $year,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'summary' => $this->getAnnualSummary($startDate, $endDate, $newStudents, $activeStudents),
                'top_by_grades' => $this->getTopByGradesAnnual($startDate, $endDate),
                'top_by_attendance' => $this->getTopByAttendanceAnnual($startDate, $endDate),
                'students_with_no_debts' => $this->getStudentsWithNoDebtsAnnual($startDate, $endDate),
                'students_with_discounts' => $this->getStudentsWithDiscountsAnnual($startDate, $endDate),
                'monthly_breakdown' => $this->getMonthlyBreakdown($startDate, $endDate),
                'students_by_course' => $this->getStudentsByCourseAnnual($year),
                'new_students' => $this->getNewStudentsAnnual($startDate, $endDate),
                'active_students_count' => $activeStudents,
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in annualReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * الحصول على إحصائيات سريعة للوحة التحكم
     */
    public function getDashboardStats()
    {
        try {
            // Use cache for better performance
            $cacheKey = 'dashboard_stats_'.date('Y-m-d');
            $stats = cache()->remember($cacheKey, 3600, function () {
                return [
                    'total_students' => Student::count(),
                    'active_students' => Student::whereHas('attendances', function ($q) {
                        $q->where('recorded_at', '>=', now()->subDays(30));
                    })->count(),
                    'total_ratings' => Rating::count(),
                    'avg_rating' => Rating::avg('rating_value') ?? 0,
                    'total_attendance' => Attendance::count(),
                    'present_attendance' => Attendance::where('status', 'present')->count(),
                    'total_revenue' => Invoice::sum('amount_paid'),
                    'pending_invoices' => Invoice::whereRaw('amount > amount_paid')->count(),
                ];
            });

            // Calculate percentages
            $stats['attendance_rate'] = $stats['total_attendance'] > 0 ?
                round(($stats['present_attendance'] / $stats['total_attendance']) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard stats error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحميل الإحصائيات',
            ], 500);
        }
    }

    private function getRevenueTrends()
    {
        try {
            $trends = [];
            $currentYear = now()->year;

            for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
                $revenue = Invoice::whereYear('created_at', $year)
                    ->sum('amount_paid');

                $trends[] = [
                    'year' => $year,
                    'revenue' => $revenue,
                    'formatted_revenue' => number_format($revenue, 0, '.', ',').' ج.م',
                    'monthly_avg' => $revenue / 12,
                ];
            }

            return $trends;
        } catch (\Exception $e) {
            Log::error('Error in getRevenueTrends: '.$e->getMessage());

            return [];
        }
    }

    private function getAttendanceTrends()
    {
        try {
            $trends = [];
            $currentYear = now()->year;

            for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
                $totalAttendance = Attendance::whereYear('recorded_at', $year)->count();
                $presentCount = Attendance::whereYear('recorded_at', $year)
                    ->where('status', 'present')->count();

                $attendanceRate = $totalAttendance > 0 ?
                    round(($presentCount / $totalAttendance) * 100, 2) : 0;

                $trends[] = [
                    'year' => $year,
                    'total_attendance' => $totalAttendance,
                    'present_count' => $presentCount,
                    'attendance_rate' => $attendanceRate,
                    'avg_daily_attendance' => $totalAttendance / 365, // متوسط يومي
                ];
            }

            return $trends;
        } catch (\Exception $e) {
            Log::error('Error in getAttendanceTrends: '.$e->getMessage());

            return [];
        }
    }

    // =============================================
    // الدوال المساعدة للتقارير
    // =============================================

    private function getDailySummary($date)
    {
        try {
            $totalAttendance = Attendance::whereDate('recorded_at', $date)->count();
            $presentCount = Attendance::whereDate('recorded_at', $date)->where('status', 'present')->count();
            $absentCount = Attendance::whereDate('recorded_at', $date)->where('status', 'absent')->count();

            $avgRating = Rating::whereDate('rated_at', $date)->avg('rating_value');
            $ratingsCount = Rating::whereDate('rated_at', $date)->count();

            $paymentsReceived = Invoice::whereDate('created_at', $date)->sum('amount_paid');
            $newInvoices = Invoice::whereDate('created_at', $date)->count();

            return [
                'total_attendance' => $totalAttendance,
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'ratings_count' => $ratingsCount,
                'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                'payments_received' => $paymentsReceived,
                'new_invoices' => $newInvoices,
            ];
        } catch (\Exception $e) {
            Log::error('Error in getDailySummary: '.$e->getMessage());

            return [
                'total_attendance' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'ratings_count' => 0,
                'avg_rating' => 0,
                'payments_received' => 0,
                'new_invoices' => 0,
            ];
        }
    }

    private function getWeeklySummary($startDate, $endDate)
    {
        try {
            $totalAttendance = Attendance::whereBetween('recorded_at', [$startDate, $endDate])->count();
            $presentCount = Attendance::whereBetween('recorded_at', [$startDate, $endDate])
                ->where('status', 'present')->count();

            $avgRating = Rating::whereBetween('rated_at', [$startDate, $endDate])->avg('rating_value');
            $ratingsCount = Rating::whereBetween('rated_at', [$startDate, $endDate])->count();

            $paymentsReceived = Invoice::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount_paid');

            $newStudents = Student::whereBetween('enrollment_date', [$startDate, $endDate])->count();

            return [
                'total_attendance' => $totalAttendance,
                'present_count' => $presentCount,
                'absent_count' => $totalAttendance - $presentCount,
                'ratings_count' => $ratingsCount,
                'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                'payments_received' => $paymentsReceived,
                'new_students' => $newStudents,
            ];
        } catch (\Exception $e) {
            Log::error('Error in getWeeklySummary: '.$e->getMessage());

            return [
                'total_attendance' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'ratings_count' => 0,
                'avg_rating' => 0,
                'payments_received' => 0,
                'new_students' => 0,
            ];
        }
    }

    private function getMonthlySummary($startDate, $endDate)
    {
        try {
            $totalAttendance = Attendance::whereBetween('recorded_at', [$startDate, $endDate])->count();
            $presentCount = Attendance::whereBetween('recorded_at', [$startDate, $endDate])
                ->where('status', 'present')->count();

            $avgRating = Rating::whereBetween('rated_at', [$startDate, $endDate])->avg('rating_value');
            $ratingsCount = Rating::whereBetween('rated_at', [$startDate, $endDate])->count();

            $paymentsReceived = Invoice::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount_paid');

            $newStudents = Student::whereBetween('enrollment_date', [$startDate, $endDate])->count();

            return [
                'total_attendance' => $totalAttendance,
                'present_count' => $presentCount,
                'absent_count' => $totalAttendance - $presentCount,
                'ratings_count' => $ratingsCount,
                'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                'payments_received' => $paymentsReceived,
                'new_students' => $newStudents,
            ];
        } catch (\Exception $e) {
            Log::error('Error in getMonthlySummary: '.$e->getMessage());

            return [
                'total_attendance' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'ratings_count' => 0,
                'avg_rating' => 0,
                'payments_received' => 0,
                'new_students' => 0,
            ];
        }
    }

    private function getAnnualSummary($startDate, $endDate, $newStudents = 0, $activeStudents = 0)
    {
        try {
            $totalAttendance = Attendance::whereBetween('recorded_at', [$startDate, $endDate])->count();
            $presentCount = Attendance::whereBetween('recorded_at', [$startDate, $endDate])
                ->where('status', 'present')->count();

            $avgRating = Rating::whereBetween('rated_at', [$startDate, $endDate])->avg('rating_value');
            $ratingsCount = Rating::whereBetween('rated_at', [$startDate, $endDate])->count();

            $paymentsReceived = Invoice::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount_paid');

            // الحصول على عدد الطلاب الذين ليس لهم ديون
            $studentsWithoutDebts = Student::whereDoesntHave('invoices', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->whereRaw('amount > amount_paid');
            })->count();

            $attendanceRate = $totalAttendance > 0 ?
                round(($presentCount / $totalAttendance) * 100, 2) : 0;

            return [
                'total_attendance' => $totalAttendance,
                'present_count' => $presentCount,
                'absent_count' => $totalAttendance - $presentCount,
                'ratings_count' => $ratingsCount,
                'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                'payments_received' => $paymentsReceived,
                'new_students' => $newStudents,
                'active_students' => $activeStudents,
                'students_without_debts' => $studentsWithoutDebts,
                'avg_attendance_rate' => $attendanceRate,
                'avg_monthly_revenue' => $paymentsReceived / 12, // متوسط إيرادات الشهر
            ];
        } catch (\Exception $e) {
            Log::error('Error in getAnnualSummary: '.$e->getMessage());

            return [
                'total_attendance' => 0,
                'present_count' => 0,
                'absent_count' => 0,
                'ratings_count' => 0,
                'avg_rating' => 0,
                'payments_received' => 0,
                'new_students' => 0,
                'active_students' => 0,
                'students_without_debts' => 0,
                'avg_attendance_rate' => 0,
                'avg_monthly_revenue' => 0,
            ];
        }
    }

    private function getOverallSummary()
    {
        try {
            $totalStudents = Student::count();
            $totalRatings = Rating::count();
            $totalAttendance = Attendance::count();
            $presentAttendance = Attendance::where('status', 'present')->count();
            $avgRating = Rating::avg('rating_value');

            $attendanceRate = $totalAttendance > 0 ?
                round(($presentAttendance / $totalAttendance) * 100, 2) : 0;

            $totalRevenue = Invoice::sum('amount_paid');
            // تم التعديل: افترض أن جميع الطلاب نشطين (لا يوجد عمود status)
            $activeStudents = $totalStudents;
            $totalCourses = Group::distinct('course_id')->count('course_id');
            $totalGroups = Group::count();

            return [
                'total_students' => $totalStudents,
                'total_courses' => $totalCourses,
                'total_groups' => $totalGroups,
                'total_ratings' => $totalRatings,
                'total_attendance' => $totalAttendance,
                'avg_rating_all_time' => $avgRating !== null ? (float) $avgRating : 0,
                'avg_attendance_rate' => $attendanceRate,
                'total_revenue' => $totalRevenue,
                'active_students' => $activeStudents,
            ];
        } catch (\Exception $e) {
            Log::error('Error in getOverallSummary: '.$e->getMessage());

            return [
                'total_students' => 0,
                'total_courses' => 0,
                'total_groups' => 0,
                'total_ratings' => 0,
                'total_attendance' => 0,
                'avg_rating_all_time' => 0,
                'avg_attendance_rate' => 0,
                'total_revenue' => 0,
                'active_students' => 0,
            ];
        }
    }

    private function getTopByGradesDaily($date)
    {
        try {
            return Rating::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('AVG(ratings.rating_value) as average_rating'),
                DB::raw('COUNT(ratings.rating_id) as total_ratings')
            )
                ->join('students', 'ratings.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereDate('ratings.rated_at', $date)
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('average_rating', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByGradesDaily: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByGradesWeekly($startDate, $endDate)
    {
        try {
            return Rating::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('AVG(ratings.rating_value) as average_rating'),
                DB::raw('COUNT(ratings.rating_id) as total_ratings')
            )
                ->join('students', 'ratings.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereBetween('ratings.rated_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('average_rating', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByGradesWeekly: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByGradesMonthly($startDate, $endDate)
    {
        try {
            return Rating::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('AVG(ratings.rating_value) as average_rating'),
                DB::raw('COUNT(ratings.rating_id) as total_ratings')
            )
                ->join('students', 'ratings.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereBetween('ratings.rated_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('average_rating', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByGradesMonthly: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByGradesAnnual($startDate, $endDate)
    {
        try {
            return Rating::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('AVG(ratings.rating_value) as average_rating'),
                DB::raw('COUNT(ratings.rating_id) as total_ratings')
            )
                ->join('students', 'ratings.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereBetween('ratings.rated_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('average_rating', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByGradesAnnual: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByAttendanceDaily($date)
    {
        try {
            return Attendance::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(CASE WHEN attendance.status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance.attendance_id) as total_sessions')
            )
                ->join('students', 'attendance.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereDate('attendance.recorded_at', $date)
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('present_count', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByAttendanceDaily: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByAttendanceWeekly($startDate, $endDate)
    {
        try {
            return Attendance::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(CASE WHEN attendance.status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance.attendance_id) as total_sessions')
            )
                ->join('students', 'attendance.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereBetween('attendance.recorded_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('present_count', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByAttendanceWeekly: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByAttendanceMonthly($startDate, $endDate)
    {
        try {
            return Attendance::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(CASE WHEN attendance.status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance.attendance_id) as total_sessions')
            )
                ->join('students', 'attendance.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereBetween('attendance.recorded_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('present_count', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByAttendanceMonthly: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByAttendanceAnnual($startDate, $endDate)
    {
        try {
            return Attendance::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(CASE WHEN attendance.status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance.attendance_id) as total_sessions')
            )
                ->join('students', 'attendance.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereBetween('attendance.recorded_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('present_count', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByAttendanceAnnual: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * فحص البيانات المالية للطلاب
     */
    public function checkFinancialData(Request $request)
    {
        try {
            $date = $request->input('date', now()->format('Y-m-d'));

            $stats = [
                'total_students' => Student::count(),
                'students_with_invoices' => Student::whereHas('invoices')->count(),
                'students_with_payments' => Student::whereHas('invoices.payments')->count(),
                'total_invoices' => Invoice::whereDate('created_at', $date)->count(),
                'total_payments' => Payment::whereDate('payment_date', $date)->sum('amount'),
                'payment_methods' => Payment::whereDate('payment_date', $date)
                    ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                    ->groupBy('payment_method')
                    ->get(),
                'invoice_status' => Invoice::whereDate('created_at', $date)
                    ->select(DB::raw('
                    CASE 
                        WHEN amount_paid >= amount THEN "paid"
                        WHEN amount_paid > 0 THEN "partial"
                        ELSE "unpaid"
                    END as status'),
                        DB::raw('COUNT(*) as count')
                    )
                    ->groupBy('status')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error checking financial data: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function getStudentsWithNoDebtsDaily($date)
    {
        try {
            return Student::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COALESCE(SUM(payments.amount), 0) as total_paid'),
                DB::raw('COUNT(DISTINCT invoices.invoice_id) as total_invoices')
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('invoices', function ($join) use ($date) {
                    $join->on('students.student_id', '=', 'invoices.student_id')
                        ->whereDate('invoices.created_at', $date);
                })
                ->leftJoin('payments', function ($join) use ($date) {
                    $join->on('invoices.invoice_id', '=', 'payments.invoice_id')
                        ->whereDate('payments.payment_date', $date);
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->havingRaw('(SUM(invoices.amount) IS NULL OR SUM(payments.amount) >= SUM(invoices.amount))')
                ->orderBy('total_paid', 'DESC')
                ->limit(20)
                ->get()
                ->map(function ($student) {
                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->username,
                        'total_paid' => (float) $student->total_paid,
                        'total_invoices' => (int) $student->total_invoices,
                        'payment_status' => $student->total_invoices > 0 ?
                            ($student->total_paid > 0 ? 'paid' : 'unpaid') : 'no_invoices',
                    ];
                });
        } catch (\Exception $e) {
            \Log::error('Error in getStudentsWithNoDebtsDaily: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithNoDebtsWeekly($startDate, $endDate)
    {
        try {
            return Student::select(
                'students.student_id',
                'students.student_name',
                'users.username'
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereDoesntHave('invoices', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                        ->whereRaw('amount > amount_paid');
                })
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithNoDebtsWeekly: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithNoDebtsMonthly($startDate, $endDate)
    {
        try {
            return Student::select(
                'students.student_id',
                'students.student_name',
                'users.username'
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereDoesntHave('invoices', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                        ->whereRaw('amount > amount_paid');
                })
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithNoDebtsMonthly: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithNoDebtsAnnual($startDate, $endDate)
    {
        try {
            return Student::select(
                'students.student_id',
                'students.student_name',
                'users.username'
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereDoesntHave('invoices', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                        ->whereRaw('amount > amount_paid');
                })
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithNoDebtsAnnual: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithNoDebtsAllTime()
    {
        try {
            return Student::select(
                'students.student_id',
                'students.student_name',
                'users.username'
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->whereDoesntHave('invoices', function ($q) {
                    $q->whereRaw('amount > amount_paid');
                })
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithNoDebtsAllTime: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithDiscountsDaily($date)
    {
        try {
            return Invoice::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('SUM(invoices.discount_amount) as total_discount')
            )
                ->join('students', 'invoices.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('invoices.discount_amount', '>', 0)
                ->whereDate('invoices.created_at', $date)
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('total_discount', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithDiscountsDaily: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithDiscountsWeekly($startDate, $endDate)
    {
        try {
            return Invoice::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('SUM(invoices.discount_amount) as total_discount')
            )
                ->join('students', 'invoices.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('invoices.discount_amount', '>', 0)
                ->whereBetween('invoices.created_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('total_discount', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithDiscountsWeekly: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithDiscountsMonthly($startDate, $endDate)
    {
        try {
            return Invoice::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('SUM(invoices.discount_amount) as total_discount')
            )
                ->join('students', 'invoices.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('invoices.discount_amount', '>', 0)
                ->whereBetween('invoices.created_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('total_discount', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithDiscountsMonthly: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithDiscountsAnnual($startDate, $endDate)
    {
        try {
            return Invoice::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('SUM(invoices.discount_amount) as total_discount')
            )
                ->join('students', 'invoices.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('invoices.discount_amount', '>', 0)
                ->whereBetween('invoices.created_at', [$startDate, $endDate])
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('total_discount', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithDiscountsAnnual: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsWithDiscountsAllTime()
    {
        try {
            return Invoice::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('SUM(invoices.discount_amount) as total_discount')
            )
                ->join('students', 'invoices.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('invoices.discount_amount', '>', 0)
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('total_discount', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsWithDiscountsAllTime: '.$e->getMessage());

            return collect();
        }
    }

    private function getNewStudentsDaily($date)
    {
        try {
            return Student::whereDate('enrollment_date', $date)
                ->with('user')
                ->limit(20)
                ->get()
                ->map(function ($student) {
                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->user->username ?? '',
                        'enrollment_date' => $student->enrollment_date,
                    ];
                });
        } catch (\Exception $e) {
            Log::error('Error in getNewStudentsDaily: '.$e->getMessage());

            return collect();
        }
    }

    private function getNewStudentsWeekly($startDate, $endDate)
    {
        try {
            return Student::whereBetween('enrollment_date', [$startDate, $endDate])
                ->with('user')
                ->limit(20)
                ->get()
                ->map(function ($student) {
                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->user->username ?? '',
                        'enrollment_date' => $student->enrollment_date,
                    ];
                });
        } catch (\Exception $e) {
            Log::error('Error in getNewStudentsWeekly: '.$e->getMessage());

            return collect();
        }
    }

    private function getNewStudentsMonthly($startDate, $endDate)
    {
        try {
            return Student::whereBetween('enrollment_date', [$startDate, $endDate])
                ->with('user')
                ->limit(20)
                ->get()
                ->map(function ($student) {
                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->user->username ?? '',
                        'enrollment_date' => $student->enrollment_date,
                    ];
                });
        } catch (\Exception $e) {
            Log::error('Error in getNewStudentsMonthly: '.$e->getMessage());

            return collect();
        }
    }

    private function getNewStudentsAnnual($startDate, $endDate)
    {
        try {
            return Student::whereBetween('enrollment_date', [$startDate, $endDate])
                ->with('user')
                ->limit(20)
                ->get()
                ->map(function ($student) {
                    return [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->user->username ?? '',
                        'enrollment_date' => $student->enrollment_date,
                    ];
                });
        } catch (\Exception $e) {
            Log::error('Error in getNewStudentsAnnual: '.$e->getMessage());

            return collect();
        }
    }

    private function getDailyBreakdown($startDate, $endDate)
    {
        try {
            $days = [];
            $currentDate = Carbon::parse($startDate);

            while ($currentDate <= Carbon::parse($endDate)) {
                $dayDate = $currentDate->format('Y-m-d');

                $attendance = Attendance::whereDate('recorded_at', $dayDate)->count();
                $present = Attendance::whereDate('recorded_at', $dayDate)->where('status', 'present')->count();
                $ratings = Rating::whereDate('rated_at', $dayDate)->count();
                $avgRating = Rating::whereDate('rated_at', $dayDate)->avg('rating_value');

                $days[] = [
                    'date' => $dayDate,
                    'day_name' => $currentDate->locale('ar')->dayName,
                    'attendance' => $attendance,
                    'present' => $present,
                    'ratings' => $ratings,
                    'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                ];

                $currentDate->addDay();
            }

            return $days;
        } catch (\Exception $e) {
            Log::error('Error in getDailyBreakdown: '.$e->getMessage());

            return [];
        }
    }

    private function getWeeklyBreakdown($startDate, $endDate)
    {
        try {
            $weeks = [];
            $currentWeek = Carbon::parse($startDate)->startOfWeek();

            while ($currentWeek <= Carbon::parse($endDate)) {
                $weekStart = $currentWeek->format('Y-m-d');
                $weekEnd = $currentWeek->copy()->endOfWeek()->format('Y-m-d');

                $attendance = Attendance::whereBetween('recorded_at', [$weekStart, $weekEnd])->count();
                $present = Attendance::whereBetween('recorded_at', [$weekStart, $weekEnd])->where('status', 'present')->count();
                $ratings = Rating::whereBetween('rated_at', [$weekStart, $weekEnd])->count();
                $avgRating = Rating::whereBetween('rated_at', [$weekStart, $weekEnd])->avg('rating_value');

                $weeks[] = [
                    'week_number' => $currentWeek->weekOfYear,
                    'start_date' => $weekStart,
                    'end_date' => $weekEnd,
                    'attendance' => $attendance,
                    'present' => $present,
                    'ratings' => $ratings,
                    'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                ];

                $currentWeek->addWeek();
            }

            return $weeks;
        } catch (\Exception $e) {
            Log::error('Error in getWeeklyBreakdown: '.$e->getMessage());

            return [];
        }
    }

    private function getMonthlyBreakdown($startDate, $endDate)
    {
        try {
            $months = [];
            $currentMonth = Carbon::parse($startDate)->startOfMonth();

            while ($currentMonth <= Carbon::parse($endDate)) {
                $monthStart = $currentMonth->format('Y-m-d');
                $monthEnd = $currentMonth->copy()->endOfMonth()->format('Y-m-d');

                $attendance = Attendance::whereBetween('recorded_at', [$monthStart, $monthEnd])->count();
                $present = Attendance::whereBetween('recorded_at', [$monthStart, $monthEnd])->where('status', 'present')->count();
                $ratings = Rating::whereBetween('rated_at', [$monthStart, $monthEnd])->count();
                $avgRating = Rating::whereBetween('rated_at', [$monthStart, $monthEnd])->avg('rating_value');
                $newStudents = Student::whereBetween('enrollment_date', [$monthStart, $monthEnd])->count();
                $payments = Invoice::whereBetween('created_at', [$monthStart, $monthEnd])->sum('amount_paid');

                $months[] = [
                    'month' => $currentMonth->month,
                    'month_name' => $currentMonth->locale('ar')->monthName,
                    'year' => $currentMonth->year,
                    'attendance' => $attendance,
                    'present' => $present,
                    'ratings' => $ratings,
                    'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                    'new_students' => $newStudents,
                    'payments' => $payments,
                ];

                $currentMonth->addMonth();
            }

            return $months;
        } catch (\Exception $e) {
            Log::error('Error in getMonthlyBreakdown: '.$e->getMessage());

            return [];
        }
    }

    private function getStudentsByCourseAnnual($year)
    {
        try {
            return Student::select(
                'courses.course_name',
                DB::raw('COUNT(DISTINCT students.student_id) as student_count'),
                DB::raw('AVG(ratings.rating_value) as avg_rating')
            )
                ->join('student_group', 'students.student_id', '=', 'student_group.student_id')
                ->join('groups', 'student_group.group_id', '=', 'groups.group_id')
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('ratings', function ($join) use ($year) {
                    $join->on('students.student_id', '=', 'ratings.student_id')
                        ->whereYear('ratings.rated_at', $year);
                })
                ->whereYear('students.enrollment_date', '<=', $year)
                ->groupBy('courses.course_id', 'courses.course_name')
                ->orderBy('student_count', 'DESC')
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getStudentsByCourseAnnual: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByGradesAllTime()
    {
        try {
            return Rating::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('AVG(ratings.rating_value) as average_rating'),
                DB::raw('COUNT(ratings.rating_id) as total_ratings')
            )
                ->join('students', 'ratings.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('average_rating', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByGradesAllTime: '.$e->getMessage());

            return collect();
        }
    }

    private function getTopByAttendanceAllTime()
    {
        try {
            return Attendance::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(CASE WHEN attendance.status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance.attendance_id) as total_sessions')
            )
                ->join('students', 'attendance.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('present_count', 'DESC')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getTopByAttendanceAllTime: '.$e->getMessage());

            return collect();
        }
    }

    private function getBestPerformingCourses()
    {
        try {
            return Group::select(
                'courses.course_name',
                DB::raw('COUNT(DISTINCT student_group.student_id) as student_count'),
                DB::raw('AVG(ratings.rating_value) as avg_rating')
            )
                ->join('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
                ->leftJoin('ratings', function ($join) {
                    $join->on('student_group.student_id', '=', 'ratings.student_id')
                        ->on('groups.group_id', '=', 'ratings.group_id');
                })
                ->groupBy('courses.course_id', 'courses.course_name')
                ->orderBy('avg_rating', 'DESC')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error in getBestPerformingCourses: '.$e->getMessage());

            return collect();
        }
    }

    private function getStudentsByStatus()
    {
        try {
            $total = Student::count();

            // حساب الطلاب الذين لديهم ديون
            $withDebts = Student::whereHas('invoices', function ($q) {
                $q->whereRaw('amount > amount_paid');
            })->count();

            // الطلاب بدون ديون يعتبرون نشطين
            $active = $total - $withDebts;
            $inactive = 0; // لا يوجد بيانات عن غير النشطين
            $graduated = 0; // لا يوجد بيانات عن المتخرجين

            return [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'with_debts' => $withDebts,
                'graduated' => $graduated,
                'percentage_active' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error in getStudentsByStatus: '.$e->getMessage());

            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'with_debts' => 0,
                'graduated' => 0,
                'percentage_active' => 0,
            ];
        }
    }

    private function getEnrollmentTrends()
    {
        try {
            $trends = [];
            $currentYear = now()->year;

            for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
                $enrollments = Student::whereYear('enrollment_date', $year)->count();

                // لا يوجد بيانات عن التخرج
                $graduations = 0;

                // متوسط التقييم لهذه السنة
                $avgRating = Rating::whereYear('rated_at', $year)->avg('rating_value');

                // الطلاب الجدد في هذه السنة
                $newStudents = Student::whereYear('enrollment_date', $year)->count();

                // نسبة حضور لهذه السنة
                $attendance = Attendance::whereYear('recorded_at', $year)->count();
                $present = Attendance::whereYear('recorded_at', $year)
                    ->where('status', 'present')->count();
                $attendanceRate = $attendance > 0 ? round(($present / $attendance) * 100, 2) : 0;

                $trends[] = [
                    'year' => $year,
                    'enrollments' => $enrollments,
                    'graduations' => $graduations,
                    'avg_rating' => $avgRating !== null ? (float) $avgRating : 0,
                    'new_students' => $newStudents,
                    'attendance_rate' => $attendanceRate,
                    'total_attendance' => $attendance,
                    'present_attendance' => $present,
                ];
            }

            return $trends;
        } catch (\Exception $e) {
            Log::error('Error in getEnrollmentTrends: '.$e->getMessage());

            return [];
        }
    }

    /**
     * جلب تقرير الجروبات مع الحالة المالية للطلاب
     */
    public function getGroupsFinancialReport(Request $request)
    {
        try {
            $today = Carbon::now();
            $thirtyDaysFromNow = $today->copy()->addDays(30);

            // Use base query for consistent calculation
            $results = $this->getBaseFinancialQuery()
                ->addSelect(DB::raw('DATEDIFF(CURDATE(), groups.end_date) as days_since_expiry'))
                ->addSelect(DB::raw('DATEDIFF(groups.end_date, CURDATE()) as days_remaining'))
                ->addSelect(DB::raw("CASE 
                                WHEN groups.end_date < CURDATE() THEN 'منتهية'
                                WHEN groups.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'قرب تنتهي'
                                ELSE 'نشطة'
                            END as group_status"))
                ->whereNotNull('groups.end_date')
                ->where(function ($query) use ($today, $thirtyDaysFromNow) {
                    $query->where('groups.end_date', '<', $today)
                        ->orWhereBetween('groups.end_date', [$today, $thirtyDaysFromNow]);
                })
                ->havingRaw('total_due > 0 OR group_status = "قرب تنتهي"')
                ->orderByRaw('
                    CASE 
                        WHEN groups.end_date < CURDATE() THEN 1
                        WHEN groups.end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 2
                        ELSE 3
                    END,
                    groups.end_date DESC
                ')
                ->get()
                ->map(function ($group) {
                    return [
                        'group_id' => $group->group_id,
                        'group_name' => $group->group_name,
                        'course_name' => $group->course_name,
                        'teacher_name' => $group->teacher_name,
                        'start_date' => $group->start_date,
                        'end_date' => $group->end_date,
                        'end_date_formatted' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
                        'days_since_expiry' => $group->days_since_expiry,
                        'days_remaining' => $group->days_remaining,
                        'student_count' => $group->student_count,
                        'total_invoices' => (float) $group->total_invoices,
                        'total_paid' => (float) $group->total_paid,
                        'total_due' => (float) $group->total_due,
                        'group_status' => $group->group_status,
                        'has_unpaid_invoices' => $group->total_due > 0,
                        'is_expired' => $group->days_since_expiry > 0,
                        'is_about_to_expire' => $group->days_remaining > 0 && $group->days_remaining <= 30,
                    ];
                });

            // Group results by status to match original response structure
            $expiredGroups = $results->where('is_expired', true)->values();
            $aboutToExpire = $results->where('is_about_to_expire', true)->values();
            $expiredWithUnpaid = $results->where('is_expired', true)->where('has_unpaid_invoices', true)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'expired_groups' => $expiredGroups,
                    'about_to_expire' => $aboutToExpire,
                    'expired_with_unpaid' => $expiredWithUnpaid,
                    'summary' => [
                        'total_expired_groups' => $expiredGroups->count(),
                        'total_about_to_expire' => $aboutToExpire->count(),
                        'total_expired_with_unpaid' => $expiredWithUnpaid->count(),
                        'total_unpaid_amount' => $results->where('has_unpaid_invoices', true)->sum('total_due'),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getGroupsFinancialReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل التقرير: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب بيانات الجروبات المالية المطلوبة
     */
    public function getGroupsFinancialDashboard(Request $request)
    {
        try {
            $today = Carbon::now();
            $data = [];

            // 1. الجروبات المنتهية مع فواتير غير مدفوعة
            $expiredGroupsWithUnpaid = Group::select(
                'groups.group_id',
                'groups.group_name',
                'courses.course_name',
                'teachers.teacher_name',
                'groups.start_date',
                'groups.end_date',
                DB::raw('COUNT(DISTINCT student_group.student_id) as student_count'),
                DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as total_due')
            )
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
                ->leftJoin('invoices', function ($join) {
                    $join->on('student_group.student_id', '=', 'invoices.student_id')
                        ->where('invoices.group_id', DB::raw('groups.group_id'));
                })
                ->whereNotNull('groups.end_date')
                ->where('groups.end_date', '<', $today)
                ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name',
                    'teachers.teacher_name', 'groups.start_date', 'groups.end_date')
                ->having('total_due', '>', 0)
                ->orderBy('total_due', 'DESC')
                ->limit(10)
                ->get();

            // 2. الجروبات التي على وشك الانتهاء مع فواتير غير مدفوعة (30 يوم القادمة)
            $aboutToExpireWithUnpaid = Group::select(
                'groups.group_id',
                'groups.group_name',
                'courses.course_name',
                'teachers.teacher_name',
                'groups.start_date',
                'groups.end_date',
                DB::raw('DATEDIFF(groups.end_date, CURDATE()) as days_remaining'),
                DB::raw('COUNT(DISTINCT student_group.student_id) as student_count'),
                DB::raw('COALESCE(SUM(invoices.amount - invoices.amount_paid), 0) as total_due')
            )
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
                ->leftJoin('student_group', 'groups.group_id', '=', 'student_group.group_id')
                ->leftJoin('invoices', function ($join) {
                    $join->on('student_group.student_id', '=', 'invoices.student_id')
                        ->where('invoices.group_id', DB::raw('groups.group_id'));
                })
                ->whereNotNull('groups.end_date')
                ->where('groups.end_date', '>=', $today)
                ->where('groups.end_date', '<=', $today->copy()->addDays(30))
                ->groupBy('groups.group_id', 'groups.group_name', 'courses.course_name',
                    'teachers.teacher_name', 'groups.start_date', 'groups.end_date')
                ->having('total_due', '>', 0)
                ->orderBy('days_remaining', 'ASC')
                ->limit(10)
                ->get();

            // 3. الطلاب الذين جروباتهم انتهت ودفعوا ولكن ليس في جروبات نشطة حالياً
            $studentsPaidNoActiveGroups = Student::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COUNT(DISTINCT groups.group_id) as completed_groups'),
                DB::raw('SUM(invoices.amount_paid) as total_paid'),
                DB::raw('MAX(groups.end_date) as last_group_end_date')
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('student_group', 'students.student_id', '=', 'student_group.student_id')
                ->leftJoin('groups', function ($join) {
                    $join->on('student_group.group_id', '=', 'groups.group_id')
                        ->whereNotNull('groups.end_date')
                        ->where('groups.end_date', '<', Carbon::now());
                })
                ->leftJoin('invoices', function ($join) {
                    $join->on('students.student_id', '=', 'invoices.student_id')
                        ->whereRaw('invoices.amount <= invoices.amount_paid');
                })
                ->whereDoesntHave('groups', function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('groups.end_date')
                            ->orWhere('groups.end_date', '>=', Carbon::now());
                    });
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->having('completed_groups', '>', 0)
                ->orderBy('last_group_end_date', 'DESC')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'expired_groups_with_unpaid' => [
                        'groups' => $expiredGroupsWithUnpaid,
                        'count' => $expiredGroupsWithUnpaid->count(),
                        'total_due' => $expiredGroupsWithUnpaid->sum('total_due'),
                    ],
                    'about_to_expire_with_unpaid' => [
                        'groups' => $aboutToExpireWithUnpaid,
                        'count' => $aboutToExpireWithUnpaid->count(),
                        'total_due' => $aboutToExpireWithUnpaid->sum('total_due'),
                    ],
                    'students_paid_no_active_groups' => [
                        'students' => $studentsPaidNoActiveGroups,
                        'count' => $studentsPaidNoActiveGroups->count(),
                        'total_paid' => $studentsPaidNoActiveGroups->sum('total_paid'),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getGroupsFinancialDashboard: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات',
            ], 500);
        }
    }

    /**
     * جلب تفاصيل مجموعة مع الطلاب غير الدافعين
     */
    public function getGroupUnpaidDetails($groupId)
    {
        try {
            $group = Group::with([
                'students.user',
                'students.invoices' => function ($query) use ($groupId) {
                    $query->where('group_id', $groupId)
                        ->whereRaw('amount > amount_paid');
                },
            ])->find($groupId);

            if (! $group) {
                return response()->json([
                    'success' => false,
                    'message' => 'المجموعة غير موجودة',
                ], 404);
            }

            $unpaidStudents = $group->students->filter(function ($student) use ($groupId) {
                $groupInvoices = $student->invoices->where('group_id', $groupId);
                $totalDue = $groupInvoices->sum(function ($invoice) {
                    return $invoice->amount - $invoice->amount_paid;
                });

                return $totalDue > 0;
            })->map(function ($student) use ($groupId) {
                $groupInvoices = $student->invoices->where('group_id', $groupId);
                $totalDue = $groupInvoices->sum(function ($invoice) {
                    return $invoice->amount - $invoice->amount_paid;
                });

                return [
                    'student_id' => $student->student_id,
                    'student_name' => $student->student_name,
                    'username' => $student->user->username ?? '',
                    'total_due' => $totalDue,
                    'invoices' => $groupInvoices->map(function ($invoice) {
                        return [
                            'invoice_id' => $invoice->invoice_id,
                            'invoice_number' => $invoice->invoice_number,
                            'amount' => $invoice->amount,
                            'amount_paid' => $invoice->amount_paid,
                            'due' => $invoice->amount - $invoice->amount_paid,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'group' => [
                    'group_id' => $group->group_id,
                    'group_name' => $group->group_name,
                    'course_name' => $group->course->course_name ?? '',
                    'end_date' => $group->end_date,
                    'is_expired' => $group->end_date && $group->end_date < Carbon::now(),
                ],
                'unpaid_students' => $unpaidStudents,
                'summary' => [
                    'total_students' => $unpaidStudents->count(),
                    'total_due' => $unpaidStudents->sum('total_due'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getGroupUnpaidDetails: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات',
            ], 500);
        }
    }

    public function enhancedOverallReport(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $user = auth()->user();
            if (! $user) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'يجب تسجيل الدخول أولاً',
                    ], 401);
                }

                return redirect()->route('login');
            }

            // التحقق من أن المستخدم لديه صلاحيات لمشاهدة التقارير
            if (! $user->is_admin && ! $user->is_partial_admin && ! $user->is_secretary) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ليس لديك صلاحيات لعرض التقارير',
                    ], 403);
                }
                abort(403, 'ليس لديك صلاحيات لعرض التقارير');
            }

            // الحصول على البيانات
            $data = $this->getOverallReportData();

            // إذا كان الطلب AJAX، إرجاع JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $data,
                ]);
            }

            // وإلا إرجاع View
            return view('reports.student_overall_enhanced', [
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in enhanced overall report: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ في الخادم',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }

            return back()->withErrors(['error' => 'حدث خطأ في تحميل التقرير']);
        }
    }

    private function getOverallStats()
    {
        try {
            $totalStudents = Student::count();

            // حساب الطلاب النشطين (الذين لديهم أنشطة في آخر 90 يوم)
            $activeStudents = Student::where(function ($query) {
                $query->whereHas('attendances', function ($q) {
                    $q->where('recorded_at', '>=', now()->subDays(90));
                })
                    ->orWhereHas('ratings', function ($q) {
                        $q->where('rated_at', '>=', now()->subDays(90));
                    })
                    ->orWhereHas('invoices', function ($q) {
                        $q->where('created_at', '>=', now()->subDays(90));
                    });
            })->count();

            // نسبة الطلاب النشطين
            $activePercentage = $totalStudents > 0 ?
                round(($activeStudents / $totalStudents) * 100, 2) : 0;

            // إحصائيات الحضور
            $totalAttendance = Attendance::count();
            $presentAttendance = Attendance::where('status', 'present')->count();
            $attendanceRate = $totalAttendance > 0 ?
                round(($presentAttendance / $totalAttendance) * 100, 2) : 0;

            // إحصائيات التقييماتء
            $avgRating = Rating::avg('rating_value');
            $totalRatings = Rating::count();

            // إحصائيات المالية
            $totalRevenue = Invoice::sum('amount_paid');
            $totalDue = Invoice::sum(DB::raw('amount - amount_paid'));

            // إحصائيات الشهر الماضي
            $lastMonthStart = now()->subMonth()->startOfMonth();
            $lastMonthEnd = now()->subMonth()->endOfMonth();

            $newStudentsLastMonth = Student::whereBetween('enrollment_date', [$lastMonthStart, $lastMonthEnd])->count();
            $lastMonthRevenue = Invoice::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->sum('amount_paid');

            // إحصائيات الكورسات والمجموعات
            $totalCourses = DB::table('courses')->count();
            $activeGroups = Group::where('is_active', true)->count();
            $totalGroups = Group::count();

            return [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'active_percentage' => $activePercentage,
                'inactive_students' => $totalStudents - $activeStudents,
                'total_courses' => $totalCourses,
                'total_groups' => $totalGroups,
                'active_groups' => $activeGroups,
                'total_ratings' => $totalRatings,
                'total_attendance' => $totalAttendance,
                'present_attendance' => $presentAttendance,
                'avg_rating_all_time' => $avgRating !== null ? (float) $avgRating : 0,
                'avg_attendance_rate' => $attendanceRate,
                'total_revenue' => $totalRevenue,
                'total_due' => $totalDue,
                'avg_monthly_revenue' => $lastMonthRevenue,
                'new_students_last_month' => $newStudentsLastMonth,
                'completion_date' => now()->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            Log::error('Error in getOverallStats: '.$e->getMessage());

            return [
                'total_students' => 0,
                'active_students' => 0,
                'active_percentage' => 0,
                'inactive_students' => 0,
                'total_courses' => 0,
                'total_groups' => 0,
                'active_groups' => 0,
                'total_ratings' => 0,
                'total_attendance' => 0,
                'present_attendance' => 0,
                'avg_rating_all_time' => 0,
                'avg_attendance_rate' => 0,
                'total_revenue' => 0,
                'total_due' => 0,
                'avg_monthly_revenue' => 0,
                'new_students_last_month' => 0,
                'completion_date' => now()->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * البحث عن الطلاب حسب الاسم أو المعرف
     */
    public function searchStudents(Request $request)
    {
        try {
            $search = trim($request->input('search', ''));

            if (strlen($search) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $students = Student::select(
                'students.student_id as id',
                'students.student_name as name',
                'users.username',
                'students.student_id',
                'students.enrollment_date'
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where(function ($query) use ($search) {
                    $query->where('students.student_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.username', 'LIKE', "%{$search}%")
                        ->orWhere('students.student_id', '=', $search);
                })
                ->orderBy('students.student_name')
                ->limit(50)
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'text' => "{$student->name} ({$student->username}) - ID: {$student->student_id}",
                        'name' => $student->name,
                        'username' => $student->username,
                        'student_id' => $student->student_id,
                        'enrollment_date' => $student->enrollment_date,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);

        } catch (\Exception $e) {
            Log::error('Search students error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في البحث',
            ], 500);
        }
    }

    /**
     * تقرير مفصل لطالب محدد
     */
    private function getStudentAcademicPerformance($studentId, $period, $date, $month, $year)
    {
        $query = Rating::where('student_id', $studentId);

        $this->applyPeriodFilter($query, $period, $date, $month, $year, 'rated_at');

        return $query->select(
            DB::raw('COALESCE(AVG(rating_value), 0) as average_rating'),
            DB::raw('COUNT(rating_id) as total_ratings'),
            DB::raw('COALESCE(MAX(rating_value), 0) as highest_rating'),
            DB::raw('COALESCE(MIN(rating_value), 0) as lowest_rating')
        )->first();
    }

    private function getStudentAttendance($studentId, $period, $date, $month, $year)
    {
        $query = Attendance::where('student_id', $studentId);

        $this->applyPeriodFilter($query, $period, $date, $month, $year, 'recorded_at');

        return $query->select(
            DB::raw('COUNT(CASE WHEN status = "present" THEN 1 END) as present_count'),
            DB::raw('COUNT(attendance_id) as total_sessions'),
            DB::raw('CASE 
                WHEN COUNT(attendance_id) > 0 
                THEN ROUND(COUNT(CASE WHEN status = "present" THEN 1 END) * 100.0 / COUNT(attendance_id), 2)
                ELSE 0 
                END as attendance_percentage')
        )->first();
    }

    /**
     * الحصول على الحالة المالية للطالب مع الفواتير كسجلات منفصلة
     */
    private function getStudentFinancialStatus($studentId, $period, $date, $month, $year)
    {
        try {
            $query = Invoice::where('student_id', $studentId);

            $this->applyPeriodFilter($query, $period, $date, $month, $year, 'created_at');

            // الحصول على الفواتير كسجلات منفصلة
            $invoices = $query->with(['payments' => function ($q) {
                $q->orderBy('payment_date', 'desc');
            }])->orderBy('created_at', 'desc')->get();

            if ($invoices->isEmpty()) {
                return [
                    'invoices' => collect(),
                    'total_paid' => 0,
                    'total_due' => 0,
                    'total_discount' => 0,
                    'paid_invoices' => 0,
                    'unpaid_invoices' => 0,
                    'partial_invoices' => 0,
                    'total_invoices' => 0,
                    'payment_status' => 'no_invoices',
                    'summary' => [
                        'total_amount' => 0,
                        'total_paid' => 0,
                        'total_due' => 0,
                        'total_discount' => 0,
                        'discount_percentage' => 0,
                    ],
                ];
            }

            // معالجة كل فاتورة بشكل منفصل
            $processedInvoices = $invoices->map(function ($invoice) {
                // حساب الخصم بشكل صحيح
                $discountAmount = $this->calculateInvoiceDiscount($invoice);

                // المبلغ النهائي بعد الخصم
                $finalAmount = $invoice->amount - $discountAmount;

                // المتبقي
                $balanceDue = max(0, $finalAmount - $invoice->amount_paid);

                // حالة الدفع
                $paymentStatus = $this->getInvoicePaymentStatus($invoice, $finalAmount);

                // نسبة الخصم
                $discountPercentage = $invoice->amount > 0 ?
                    round(($discountAmount / $invoice->amount) * 100, 2) : 0;

                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'description' => $invoice->description,
                    'amount' => (float) $invoice->amount,
                    'discount_amount' => (float) $discountAmount,
                    'discount_percentage' => $discountPercentage,
                    'final_amount' => (float) $finalAmount,
                    'amount_paid' => (float) $invoice->amount_paid,
                    'balance_due' => (float) $balanceDue,
                    'due_date' => $invoice->due_date,
                    'status' => $invoice->status,
                    'payment_status' => $paymentStatus,
                    'created_at' => $invoice->created_at,
                    'payments' => $invoice->payments->map(function ($payment) {
                        return [
                            'payment_id' => $payment->payment_id,
                            'amount' => (float) $payment->amount,
                            'payment_method' => $payment->payment_method,
                            'payment_date' => $payment->payment_date,
                            'notes' => $payment->notes,
                        ];
                    }),
                ];
            });

            // حساب الإجماليات
            $totalPaid = $invoices->sum('amount_paid');
            $totalAmount = $invoices->sum('amount');
            $totalDiscount = $this->calculateTotalDiscount($invoices);
            $totalFinalAmount = $totalAmount - $totalDiscount;
            $totalDue = max(0, $totalFinalAmount - $totalPaid);

            // عد الفواتير حسب الحالة
            $paidInvoices = $processedInvoices->where('balance_due', 0)->count();
            $unpaidInvoices = $processedInvoices->where('balance_due', '>', 0)
                ->where('amount_paid', 0)
                ->count();
            $partialInvoices = $processedInvoices->where('balance_due', '>', 0)
                ->where('amount_paid', '>', 0)
                ->count();

            // تحديد حالة الدفع العامة
            $overallStatus = $this->getOverallPaymentStatus($totalDue, $totalPaid, $processedInvoices->count());

            return [
                'invoices' => $processedInvoices,
                'total_paid' => (float) $totalPaid,
                'total_due' => (float) $totalDue,
                'total_discount' => (float) $totalDiscount,
                'paid_invoices' => $paidInvoices,
                'unpaid_invoices' => $unpaidInvoices,
                'partial_invoices' => $partialInvoices,
                'total_invoices' => $processedInvoices->count(),
                'payment_status' => $overallStatus,
                'summary' => [
                    'total_amount' => (float) $totalAmount,
                    'total_paid' => (float) $totalPaid,
                    'total_due' => (float) $totalDue,
                    'total_discount' => (float) $totalDiscount,
                    'discount_percentage' => $totalAmount > 0 ?
                        round(($totalDiscount / $totalAmount) * 100, 2) : 0,
                    'final_amount' => (float) $totalFinalAmount,
                    'payment_completion' => $totalFinalAmount > 0 ?
                        round(($totalPaid / $totalFinalAmount) * 100, 2) : 100,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error in getStudentFinancialStatus: '.$e->getMessage());

            return [
                'invoices' => collect(),
                'total_paid' => 0,
                'total_due' => 0,
                'total_discount' => 0,
                'paid_invoices' => 0,
                'unpaid_invoices' => 0,
                'partial_invoices' => 0,
                'total_invoices' => 0,
                'payment_status' => 'error',
                'summary' => [
                    'total_amount' => 0,
                    'total_paid' => 0,
                    'total_due' => 0,
                    'total_discount' => 0,
                    'discount_percentage' => 0,
                    'final_amount' => 0,
                    'payment_completion' => 0,
                ],
            ];
        }
    }

    /**
     * حساب خصم الفاتورة
     */
    private function calculateInvoiceDiscount($invoice)
    {
        // الأولوية لـ discount_amount إذا كان موجوداً
        if ($invoice->discount_amount > 0) {
            return (float) $invoice->discount_amount;
        }

        // إذا كان discount_percent موجوداً
        if ($invoice->discount_percent > 0) {
            return (float) ($invoice->amount * ($invoice->discount_percent / 100));
        }

        return 0;
    }

    /**
     * حساب إجمالي الخصم
     */
    private function calculateTotalDiscount($invoices)
    {
        $totalDiscount = 0;

        foreach ($invoices as $invoice) {
            $totalDiscount += $this->calculateInvoiceDiscount($invoice);
        }

        return $totalDiscount;
    }

    /**
     * تحديد حالة دفع الفاتورة
     */
    private function getInvoicePaymentStatus($invoice, $finalAmount)
    {
        if ($invoice->amount_paid >= $finalAmount) {
            return 'paid';
        } elseif ($invoice->amount_paid > 0) {
            return 'partial';
        } else {
            return 'unpaid';
        }
    }

    /**
     * تحديد الحالة العامة للدفع
     */
    private function getOverallPaymentStatus($totalDue, $totalPaid, $invoiceCount)
    {
        if ($invoiceCount === 0) {
            return 'no_invoices';
        } elseif ($totalDue == 0) {
            return 'paid';
        } elseif ($totalPaid == 0) {
            return 'unpaid';
        } else {
            return 'partial';
        }
    }

    /**
     * Helper: Get student groups
     */
    private function getStudentGroups($studentId)
    {
        return Student::find($studentId)
            ->groups()
            ->select(
                'groups.group_id',
                'groups.group_name',
                'courses.course_name',
                'users.username as teacher_name',
                'groups.start_date',
                'groups.end_date',
                'groups.is_active'
            )
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->leftJoin('users', 'groups.teacher_id', '=', 'users.id')
            ->get();
    }

    /**
     * Helper: Apply period filter to query
     */
    private function applyPeriodFilter($query, $period, $date, $month, $year, $dateColumn)
    {
        switch ($period) {
            case 'daily':
                $query->whereDate($dateColumn, $date);
                break;
            case 'monthly':
                $query->whereYear($dateColumn, Carbon::parse($month)->year)
                    ->whereMonth($dateColumn, Carbon::parse($month)->month);
                break;
            case 'yearly':
                $query->whereYear($dateColumn, $year);
                break;
        }
    }

    /**
     * تقرير مفصل لطالب محدد - محسّن
     */
    public function studentReport($studentId, Request $request)
    {
        try {
            Log::info('Loading enhanced student report for ID: '.$studentId);

            // Validate student exists
            $student = Student::with(['user' => function ($query) {
                $query->select('id', 'username', 'email');
            }])->with(['invoices' => function ($query) use ($request) {
                // Apply period filter to invoices
                $period = $request->input('period', 'all');
                $date = $request->input('date', now()->format('Y-m-d'));
                $month = $request->input('month', now()->format('Y-m'));
                $year = $request->input('year', now()->format('Y'));

                switch ($period) {
                    case 'daily':
                        $query->whereDate('created_at', $date);
                        break;
                    case 'monthly':
                        $query->whereYear('created_at', Carbon::parse($month)->year)
                            ->whereMonth('created_at', Carbon::parse($month)->month);
                        break;
                    case 'yearly':
                        $query->whereYear('created_at', $year);
                        break;
                }

                $query->with(['payments' => function ($q) use ($period, $date, $month, $year) {
                    switch ($period) {
                        case 'daily':
                            $q->whereDate('payment_date', $date);
                            break;
                        case 'monthly':
                            $q->whereYear('payment_date', Carbon::parse($month)->year)
                                ->whereMonth('payment_date', Carbon::parse($month)->month);
                            break;
                        case 'yearly':
                            $q->whereYear('payment_date', $year);
                            break;
                    }
                }])->orderBy('created_at', 'desc');
            }])->select('student_id', 'student_name', 'user_id', 'enrollment_date')
                ->find($studentId);

            if (! $student) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطالب غير موجود',
                ], 404);
            }

            $period = $request->input('period', 'all');
            $date = $request->input('date', now()->format('Y-m-d'));
            $month = $request->input('month', now()->format('Y-m'));
            $year = $request->input('year', now()->format('Y'));

            // Get academic performance with period filter
            $academicQuery = Rating::where('student_id', $studentId);
            $this->applyPeriodFilter($academicQuery, $period, $date, $month, $year, 'rated_at');

            $academicPerformance = $academicQuery->select(
                DB::raw('COALESCE(AVG(rating_value), 0) as average_rating'),
                DB::raw('COUNT(rating_id) as total_ratings'),
                DB::raw('COALESCE(MAX(rating_value), 0) as highest_rating'),
                DB::raw('COALESCE(MIN(rating_value), 0) as lowest_rating')
            )->first();

            // Get attendance with period filter
            $attendanceQuery = Attendance::where('student_id', $studentId);
            $this->applyPeriodFilter($attendanceQuery, $period, $date, $month, $year, 'recorded_at');

            $attendanceRecord = $attendanceQuery->select(
                DB::raw('COUNT(CASE WHEN status = "present" THEN 1 END) as present_count'),
                DB::raw('COUNT(attendance_id) as total_sessions'),
                DB::raw('CASE 
                WHEN COUNT(attendance_id) > 0 
                THEN ROUND(COUNT(CASE WHEN status = "present" THEN 1 END) * 100.0 / COUNT(attendance_id), 2)
                ELSE 0 
                END as attendance_percentage')
            )->first();

            // Calculate financial summary from loaded invoices
            $financialSummary = $this->calculateFinancialSummary($student->invoices);

            // Get group participation
            $groupParticipation = DB::table('student_group')
                ->where('student_group.student_id', $studentId)
                ->join('groups', 'student_group.group_id', '=', 'groups.group_id')
                ->leftJoin('courses', 'groups.course_id', '=', 'courses.course_id')
                ->leftJoin('users', 'groups.teacher_id', '=', 'users.id')
                ->select(
                    'groups.group_id',
                    'groups.group_name',
                    'courses.course_name',
                    'users.username as teacher_name',
                    'groups.start_date',
                    'groups.end_date',
                    DB::raw('CASE WHEN groups.end_date IS NULL OR groups.end_date >= CURDATE() THEN 1 ELSE 0 END as is_active')
                )
                ->get();

            // Prepare period info for display
            $periodInfo = $this->getPeriodInfo($period, $date, $month, $year);

            $report = [
                'student' => $student,
                'academic_performance' => $academicPerformance ?: (object) [
                    'average_rating' => 0,
                    'total_ratings' => 0,
                    'highest_rating' => 0,
                    'lowest_rating' => 0,
                ],
                'attendance_record' => $attendanceRecord ?: (object) [
                    'present_count' => 0,
                    'total_sessions' => 0,
                    'attendance_percentage' => 0,
                ],
                'financial_status' => $financialSummary,
                'group_participation' => $groupParticipation,
                'report_period' => $period,
                'period_info' => $periodInfo,
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);

        } catch (\Exception $e) {
            Log::error('Student report error: '.$e->getMessage(), [
                'student_id' => $studentId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحميل تقرير الطالب: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate financial summary from invoices
     */
    private function calculateFinancialSummary($invoices)
    {
        try {
            $summary = [
                'invoices' => collect(),
                'total_amount' => 0,
                'total_paid' => 0,
                'total_due' => 0,
                'total_discount' => 0,
                'paid_invoices' => 0,
                'unpaid_invoices' => 0,
                'partial_invoices' => 0,
                'total_invoices' => 0,
                'payment_status' => 'no_invoices',
                'payment_completion' => 0,
            ];

            if ($invoices->isEmpty()) {
                return $summary;
            }

            // Process each invoice
            $processedInvoices = $invoices->map(function ($invoice) {
                $discountAmount = $invoice->discount_amount ?: 0;
                $finalAmount = $invoice->amount - $discountAmount;
                $balanceDue = max(0, $finalAmount - $invoice->amount_paid);

                // Determine payment status
                if ($invoice->amount_paid >= $finalAmount) {
                    $paymentStatus = 'paid';
                } elseif ($invoice->amount_paid > 0) {
                    $paymentStatus = 'partial';
                } else {
                    $paymentStatus = 'unpaid';
                }

                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'description' => $invoice->description,
                    'amount' => (float) $invoice->amount,
                    'discount_amount' => (float) $discountAmount,
                    'discount_percentage' => $invoice->amount > 0 ?
                        round(($discountAmount / $invoice->amount) * 100, 2) : 0,
                    'final_amount' => (float) $finalAmount,
                    'amount_paid' => (float) $invoice->amount_paid,
                    'balance_due' => (float) $balanceDue,
                    'due_date' => $invoice->due_date,
                    'status' => $invoice->status,
                    'payment_status' => $paymentStatus,
                    'created_at' => $invoice->created_at,
                    'payments' => $invoice->payments->map(function ($payment) {
                        return [
                            'payment_id' => $payment->payment_id,
                            'amount' => (float) $payment->amount,
                            'payment_method' => $payment->payment_method,
                            'payment_date' => $payment->payment_date,
                            'notes' => $payment->notes,
                        ];
                    }),
                ];
            });

            // Calculate totals
            $summary['total_amount'] = $invoices->sum('amount');
            $summary['total_discount'] = $invoices->sum('discount_amount');
            $summary['total_paid'] = $invoices->sum('amount_paid');
            $summary['final_amount'] = $summary['total_amount'] - $summary['total_discount'];
            $summary['total_due'] = max(0, $summary['final_amount'] - $summary['total_paid']);

            // Count invoices by status
            $summary['paid_invoices'] = $processedInvoices->where('balance_due', 0)->count();
            $summary['unpaid_invoices'] = $processedInvoices->where('payment_status', 'unpaid')->count();
            $summary['partial_invoices'] = $processedInvoices->where('payment_status', 'partial')->count();
            $summary['total_invoices'] = $processedInvoices->count();

            // Determine overall payment status
            if ($summary['total_invoices'] == 0) {
                $summary['payment_status'] = 'no_invoices';
            } elseif ($summary['total_due'] == 0) {
                $summary['payment_status'] = 'paid';
            } elseif ($summary['total_paid'] == 0) {
                $summary['payment_status'] = 'unpaid';
            } else {
                $summary['payment_status'] = 'partial';
            }

            // Calculate payment completion percentage
            if ($summary['final_amount'] > 0) {
                $summary['payment_completion'] = round(($summary['total_paid'] / $summary['final_amount']) * 100, 2);
            } else {
                $summary['payment_completion'] = 100;
            }

            $summary['invoices'] = $processedInvoices;

            return $summary;

        } catch (\Exception $e) {
            Log::error('Error in calculateFinancialSummary: '.$e->getMessage());

            return [
                'invoices' => collect(),
                'total_amount' => 0,
                'total_paid' => 0,
                'total_due' => 0,
                'total_discount' => 0,
                'paid_invoices' => 0,
                'unpaid_invoices' => 0,
                'partial_invoices' => 0,
                'total_invoices' => 0,
                'payment_status' => 'error',
                'payment_completion' => 0,
            ];
        }
    }

    /**
     * Get period information for display
     */
    private function getPeriodInfo($period, $date, $month, $year)
    {
        switch ($period) {
            case 'daily':
                return [
                    'type' => 'يومي',
                    'date' => Carbon::parse($date)->format('Y-m-d'),
                    'display' => Carbon::parse($date)->locale('ar')->isoFormat('dddd، LL'),
                ];
            case 'monthly':
                $dateObj = Carbon::createFromFormat('Y-m', $month);

                return [
                    'type' => 'شهري',
                    'month' => $month,
                    'display' => $dateObj->locale('ar')->monthName.' '.$dateObj->year,
                ];
            case 'yearly':
                return [
                    'type' => 'سنوي',
                    'year' => $year,
                    'display' => 'سنة '.$year,
                ];
            default:
                return [
                    'type' => 'كل الفترة',
                    'display' => 'جميع الفترات',
                ];
        }
    }

    /**
     * جلب الطلاب بدون ديون
     */
    public function getNoDebts(Request $request)
    {
        try {
            $period = $request->input('period', 'all');
            $date = $request->input('date', now()->format('Y-m-d'));
            $month = $request->input('month', now()->format('Y-m'));
            $year = $request->input('year', now()->format('Y'));

            $students = Student::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('COALESCE(SUM(invoices.amount_paid), 0) as total_paid'),
                DB::raw('COUNT(invoices.invoice_id) as total_invoices'),
                DB::raw('CASE 
                WHEN COUNT(invoices.invoice_id) = 0 THEN "no_invoices"
                WHEN SUM(invoices.amount - invoices.amount_paid) <= 0 THEN "paid"
                WHEN SUM(invoices.amount_paid) = 0 THEN "unpaid"
                ELSE "partial"
            END as payment_status')
            )
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('invoices', function ($join) use ($period, $date, $month, $year) {
                    $join->on('students.student_id', '=', 'invoices.student_id');

                    if ($period === 'daily') {
                        $join->whereDate('invoices.created_at', $date);
                    } elseif ($period === 'monthly') {
                        $join->whereYear('invoices.created_at', Carbon::parse($month)->year)
                            ->whereMonth('invoices.created_at', Carbon::parse($month)->month);
                    } elseif ($period === 'yearly') {
                        $join->whereYear('invoices.created_at', $year);
                    }
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->having('payment_status', '=', 'paid')
                ->orHaving('payment_status', '=', 'no_invoices')
                ->orderBy('students.student_name')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getNoDebts: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ',
            ], 500);
        }
    }

    /**
     * جلب الطلاب مع خصومات
     */
    public function getWithDiscounts(Request $request)
    {
        try {
            $period = $request->input('period', 'all');
            $date = $request->input('date', now()->format('Y-m-d'));
            $month = $request->input('month', now()->format('Y-m'));
            $year = $request->input('year', now()->format('Y'));

            $students = Invoice::select(
                'students.student_id',
                'students.student_name',
                'users.username',
                DB::raw('SUM(invoices.discount_amount) as total_discount'),
                DB::raw('ROUND(SUM(invoices.discount_amount) * 100.0 / SUM(invoices.amount), 2) as discount_percentage')
            )
                ->join('students', 'invoices.student_id', '=', 'students.student_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('invoices.discount_amount', '>', 0)
                ->when($period !== 'all', function ($query) use ($period, $date, $month, $year) {
                    if ($period === 'daily') {
                        $query->whereDate('invoices.created_at', $date);
                    } elseif ($period === 'monthly') {
                        $query->whereYear('invoices.created_at', Carbon::parse($month)->year)
                            ->whereMonth('invoices.created_at', Carbon::parse($month)->month);
                    } elseif ($period === 'yearly') {
                        $query->whereYear('invoices.created_at', $year);
                    }
                })
                ->groupBy('students.student_id', 'students.student_name', 'users.username')
                ->orderBy('total_discount', 'DESC')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getWithDiscounts: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ',
            ], 500);
        }
    }

    /**
     * تصدير التقرير
     */
    public function exportReport(Request $request)
    {
        try {
            $type = $request->input('type', 'pdf');
            $reportType = $request->input('report_type', 'daily');

            // جلب البيانات حسب النوع
            $data = [];

            switch ($reportType) {
                case 'daily':
                    $data = $this->dailyReport($request)->getData()->data;
                    break;
                case 'weekly':
                    $data = $this->weeklyReport($request)->getData()->data;
                    break;
                case 'monthly':
                    $data = $this->monthlyReport($request)->getData()->data;
                    break;
                case 'annual':
                    $data = $this->annualReport($request)->getData()->data;
                    break;
                case 'overall':
                    $data = $this->overallReport($request)->getData()->data;
                    break;
            }

            if ($type === 'pdf') {
                $pdf = Pdf::loadView('reports.students.pdf.main_report', [
                    'data' => $data,
                    'type' => $reportType,
                ]);

                return $pdf->download("student_report_{$reportType}_".now()->format('Y_m_d').'.pdf');
            }

            return response()->json([
                'success' => false,
                'message' => 'نوع التصدير غير مدعوم',
            ]);

        } catch (\Exception $e) {
            Log::error('Error in exportReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في التصدير',
            ], 500);
        }
    }

    /**
     * تصدير تقرير الطالب إلى PDF
     */
    public function exportStudentReport($studentId, Request $request)
    {
        try {
            // جلب بيانات تقرير الطالب
            $reportData = $this->studentReport($studentId, $request);

            if (! $reportData->getData()->success) {
                throw new \Exception('فشل في تحميل بيانات التقرير');
            }

            $data = $reportData->getData()->data;

            // تحديد نوع التصدير
            $exportType = $request->input('type', 'pdf');
            $period = $request->input('period', 'all');

            if ($exportType === 'pdf') {
                // استخدام مكتبة PDF (مثل DomPDF أو TCPDF)
                $pdf = Pdf::loadView('reports.students.pdf.student_report', [
                    'report' => $data,
                    'export_date' => now(),
                    'period' => $period,
                ]);

                $studentName = str_replace(' ', '_', $data['student']->student_name);
                $filename = "student_report_{$studentName}_".now()->format('Y_m_d').'.pdf';

                return $pdf->download($filename);

            } elseif ($exportType === 'excel') {
                // تصدير إلى Excel
                $exportData = [
                    'student_info' => [
                        ['المعرف', 'الاسم', 'اسم المستخدم', 'تاريخ التسجيل'],
                        [
                            $data['student']->student_id,
                            $data['student']->student_name,
                            $data['student']->user->username ?? '',
                            $data['student']->enrollment_date,
                        ],
                    ],
                    'academic_performance' => [
                        ['متوسط التقييم', 'إجمالي التقييمات', 'أعلى تقييم', 'أقل تقييم'],
                        [
                            $data['academic_performance']->average_rating ?? 0,
                            $data['academic_performance']->total_ratings ?? 0,
                            $data['academic_performance']->highest_rating ?? 0,
                            $data['academic_performance']->lowest_rating ?? 0,
                        ],
                    ],
                    'attendance' => [
                        ['الحضور', 'إجمالي الجلسات', 'نسبة الحضور %'],
                        [
                            $data['attendance_record']->present_count ?? 0,
                            $data['attendance_record']->total_sessions ?? 0,
                            $data['attendance_record']->attendance_percentage ?? 0,
                        ],
                    ],
                    'financial' => [
                        ['إجمالي المدفوع', 'إجمالي المستحق', 'إجمالي الخصم', 'الفواتير المدفوعة', 'الفواتير غير المدفوعة'],
                        [
                            $data['financial_status']->total_paid ?? 0,
                            $data['financial_status']->total_due ?? 0,
                            $data['financial_status']->total_discount ?? 0,
                            $data['financial_status']->paid_invoices ?? 0,
                            $data['financial_status']->unpaid_invoices ?? 0,
                        ],
                    ],
                ];

                $studentName = str_replace(' ', '_', $data['student']->student_name);
                $filename = "student_report_{$studentName}_".now()->format('Y_m_d').'.xlsx';

                return Excel::download(new StudentReportExport($exportData), $filename);

            } else {
                // تصدير إلى CSV
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="student_report.csv"',
                ];

                $callback = function () use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['تقرير الطالب', $data['student']->student_name]);
                    fputcsv($file, []); // سطر فارغ

                    // معلومات الطالب
                    fputcsv($file, ['معلومات الطالب']);
                    fputcsv($file, ['المعرف', 'الاسم', 'اسم المستخدم', 'تاريخ التسجيل']);
                    fputcsv($file, [
                        $data['student']->student_id,
                        $data['student']->student_name,
                        $data['student']->user->username ?? '',
                        $data['student']->enrollment_date,
                    ]);

                    fputcsv($file, []); // سطر فارغ

                    // الأداء الأكاديمي
                    fputcsv($file, ['الأداء الأكاديمي']);
                    fputcsv($file, ['متوسط التقييم', 'إجمالي التقييمات', 'أعلى تقييم', 'أقل تقييم']);
                    fputcsv($file, [
                        $data['academic_performance']->average_rating ?? 0,
                        $data['academic_performance']->total_ratings ?? 0,
                        $data['academic_performance']->highest_rating ?? 0,
                        $data['academic_performance']->lowest_rating ?? 0,
                    ]);

                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

        } catch (\Exception $e) {
            Log::error('Error in exportStudentReport: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تصدير التقرير: '.$e->getMessage(),
            ], 500);
        }
    }
}
