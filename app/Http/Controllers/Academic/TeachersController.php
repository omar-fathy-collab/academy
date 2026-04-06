<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Finance\TeacherSalariesController;
use App\Models\Teacher;

use App\Models\Profile;
use Illuminate\Support\Facades\Hash;

class TeachersController extends Controller
{
    public function index()
    {
        return view('teachers.index');
    }

    public function create()
    {
        return view('teachers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'teacher_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8',
            'hire_date' => 'required|date',
        ]);

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'username' => str_replace(' ', '.', strtolower($request->teacher_name)) . rand(10, 99),
                'email' => $request->email,
                'pass' => Hash::make($request->password),
                'role_id' => 2, // Teacher
                'is_active' => true,
            ]);

            // Create teacher
            Teacher::create([
                'user_id' => $user->id,
                'teacher_name' => $request->teacher_name,
                'hire_date' => $request->hire_date,
            ]);

            // Create profile
            Profile::create([
                'user_id' => $user->id,
                'nickname' => $request->teacher_name,
            ]);

            DB::commit();

            return redirect()->route('teachers.index')->with('success', 'Teacher added successfully');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error adding teacher: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error adding teacher: ' . $e->getMessage())->withInput();
        }
    }

    public function getStats()
    {
        $total = DB::table('teachers')->count();
        $active = DB::table('teachers as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('u.is_active', true)
            ->count();
            
        return response()->json([
            'total' => $total,
            'active' => $active,
        ]);
    }

    public function show($id)
    {
        // Get teacher basic info
        $teacher = DB::table('teachers as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('profile as p', 'u.id', '=', 'p.user_id')
            ->where('t.teacher_id', $id)
            ->select('t.*', 'u.email', 'u.is_active', 'p.nickname', 'p.phone_number', 'p.address', 'p.profile_picture_url')
            ->first();

        // Check if teacher exists
        if (empty($teacher)) {
            abort(404, 'Teacher not found');
        }

        // Rest of your code remains the same...
        $groups = DB::table('groups as g')
            ->join('courses as c', 'g.course_id', '=', 'c.course_id')
            ->leftJoin('student_group as sg', 'g.group_id', '=', 'sg.group_id')
            ->where('g.teacher_id', $id)
            ->select(
                'g.group_id', 'g.group_name', 'g.course_id', 'g.teacher_id', 'g.schedule', 'g.start_date', 'g.end_date', 'g.created_at', 'g.updated_at', 'c.course_name',
                DB::raw('COUNT(sg.student_id) as student_count'),
                DB::raw('(SELECT COUNT(*) FROM sessions WHERE group_id = g.group_id) as session_count')
            )
            ->groupBy('g.group_id', 'g.group_name', 'g.course_id', 'g.teacher_id', 'g.schedule', 'g.start_date', 'g.end_date', 'g.created_at', 'g.updated_at', 'c.course_name')
            ->orderBy('g.start_date', 'DESC')
            ->get();

        $sessions = DB::table('sessions as s')
            ->join('groups as g', 's.group_id', '=', 'g.group_id')
            ->join('courses as c', 'g.course_id', '=', 'c.course_id')
            ->leftJoin('attendance as a', 's.session_id', '=', 'a.session_id')
            ->where('g.teacher_id', $id)
            ->select(
                's.session_id', 's.group_id', 's.session_date', 's.start_time', 's.end_time', 's.topic', 's.notes', 's.created_at', 's.updated_at',
                'g.group_name', 'c.course_name',
                DB::raw('COUNT(a.attendance_id) as attendance_count'),
                DB::raw("SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw("SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count"),
                DB::raw("SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count")
            )
            ->groupBy('s.session_id', 's.group_id', 's.session_date', 's.start_time', 's.end_time', 's.topic', 's.notes', 's.created_at', 's.updated_at', 'g.group_name', 'c.course_name')
            ->orderBy('s.session_date', 'DESC')
            ->orderBy('s.start_time', 'DESC')
            ->get();

        $assignments = DB::table('assignments as a')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->join('courses as c', 'g.course_id', '=', 'c.course_id')
            ->leftJoin('assignment_submissions as asub', 'a.assignment_id', '=', 'asub.assignment_id')
            ->whereIn('a.created_by', function ($query) use ($id) {
                $query->select('user_id')->from('teachers')->where('teacher_id', $id);
            })
            ->select(
                'a.assignment_id', 'a.title', 'a.description', 'a.due_date', 'a.max_score', 'a.teacher_file', 'a.group_id', 'a.created_by', 'a.created_at', 'a.updated_at',
                'g.group_name', 'c.course_name',
                DB::raw('COUNT(asub.submission_id) as submission_count'),
                DB::raw('AVG(asub.score) as avg_score')
            )
            ->groupBy('a.assignment_id', 'a.title', 'a.description', 'a.due_date', 'a.max_score', 'a.teacher_file', 'a.group_id', 'a.created_by', 'a.created_at', 'a.updated_at', 'g.group_name', 'c.course_name')
            ->orderBy('a.due_date', 'DESC')
            ->get();

        $ratings = DB::table('ratings as r')
            ->join('sessions as s', 'r.session_id', '=', 's.session_id')
            ->join('groups as g', 's.group_id', '=', 'g.group_id')
            ->join('courses as c', 'g.course_id', '=', 'c.course_id')
            ->join('students as st', 'r.student_id', '=', 'st.student_id')
            ->where('g.teacher_id', $id)
            ->select('r.*', 's.session_date', 's.topic', 'g.group_name', 'c.course_name', 'st.student_name', 'r.rated_at', 'r.comments')
            ->orderBy('r.rated_at', 'DESC')
            ->get();

        // Check if rating result exists
        $ratingResult = DB::table('ratings as r')
            ->join('sessions as s', 'r.session_id', '=', 's.session_id')
            ->join('groups as g', 's.group_id', '=', 'g.group_id')
            ->where('g.teacher_id', $id)
            ->selectRaw('AVG(rating_value) as avg_rating, COUNT(*) as rating_count')
            ->first();
            
        $avg_rating = $ratingResult && $ratingResult->avg_rating ? round($ratingResult->avg_rating, 1) : 'N/A';
        $rating_count = $ratingResult ? $ratingResult->rating_count : 0;

        return view('teachers.show', [
            'teacher' => $teacher,
            'groups' => $groups,
            'sessions' => $sessions,
            'assignments' => $assignments,
            'ratings' => $ratings,
            'avg_rating' => $avg_rating,
            'rating_count' => $rating_count
        ]);
    }

    public function fetchTeachers(Request $request)
    {
        $page = $request->get('page', 1);
        $search = $request->get('search', '');
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $q = DB::table('teachers as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('groups as g', 't.teacher_id', '=', 'g.teacher_id');

        $countQ = DB::table('teachers as t')
            ->join('users as u', 't.user_id', '=', 'u.id');

        if (! empty($search)) {
            $searchFn = function ($query) use ($search) {
                $query->where('t.teacher_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('u.email', 'LIKE', '%'.$search.'%');
            };
            $q->where($searchFn);
            $countQ->where($searchFn);
        }

        $total = $countQ->count();

        $teachers = $q->select(
                't.teacher_id',
                't.teacher_name',
                'u.email',
                't.hire_date',
                DB::raw('COUNT(DISTINCT g.group_id) as group_count')
            )
            ->groupBy('t.teacher_id', 't.teacher_name', 'u.email', 't.hire_date')
            ->orderBy('t.teacher_name', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return response()->json([
            'teachers' => $teachers,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function getTeacher($id)
    {
        $teacher = DB::table('teachers as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('t.teacher_id', $id)
            ->select('t.teacher_id', 't.teacher_name', 't.hire_date', 't.user_id', 'u.email')
            ->first();

        if (empty($teacher)) {
            return response()->json(['error' => 'Teacher not found'], 404);
        }

        return response()->json($teacher);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'teacher_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'hire_date' => 'required|date',
        ]);

        try {
            DB::table('teachers')->where('teacher_id', $id)->update([
                'teacher_name' => $request->teacher_name,
                'hire_date' => $request->hire_date,
            ]);

            return redirect()->route('teachers.index')->with('success', 'Teacher updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error updating teacher: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $teacher = DB::table('teachers')->where('teacher_id', $id)->first();
            if (!$teacher) {
                return $request->expectsJson() 
                    ? response()->json(['error' => 'Teacher not found'], 404)
                    : redirect()->route('teachers.index')->with('error', 'Teacher not found');
            }

            $userId = $teacher->user_id;

            // Delete associations first (Audit logs, etc.) if they exist and are not cascaded
            // For now, focusing on User and Teacher
            DB::table('teachers')->where('teacher_id', $id)->delete();

            if ($userId) {
                // Delete profile and user
                DB::table('profile')->where('user_id', $userId)->delete();
                DB::table('users')->where('id', $userId)->delete();
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Teacher deleted successfully']);
            }

            return redirect()->route('teachers.index')->with('success', 'Teacher deleted successfully');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting teacher: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Error deleting teacher: ' . $e->getMessage());
        }
    }

    public function dashboard()
    {
        $teacherId = Auth::id();

        // Get teacher details
        $teacher = \App\Models\Teacher::with(['user'])->where('user_id', $teacherId)->first();

        if (!$teacher) {
            // Check if user is an admin - if so, redirect them to the standard dashboard
            if (Auth::user()->isAdminFull() || Auth::user()->isAdminPartial()) {
                return redirect()->route('dashboard')->with('error', 'Instructor profile not found for this user.');
            }
            
            // Otherwise, follow standard 404 behavior or redirect
            abort(404, 'Instructor record not found.');
        }


        // ✅ **الحل: استخدام TeacherSalariesController للحصول على البيانات**
        $salaryController = new TeacherSalariesController;
        $salaryData = $salaryController->getTeacherSalaryData($teacher->teacher_id, '0');

        $salaryRecords = $salaryData['salaries'] ?? [];

        // ✅ **الحل: تمرير $salaries للـ View (يمكن استخدام $salaryRecords بدلاً منه)**
        $salaries = collect($salaryRecords); // تحويل المصفوفة إلى Collection

        // ✅ **الحل: حساب الإجماليات**
        $totalEarned = $salaryData['total_paid_amount'] ?? 0;
        $totalTeacherShare = $salaryData['total_teacher_share'] ?? 0;
        $totalAvailablePayment = $salaryData['total_available_payment'] ?? 0;
        $totalRemaining = $salaryData['total_remaining'] ?? 0;
        $totalBonuses = $salaryData['total_bonuses'] ?? 0;
        $totalDeductions = $salaryData['total_deductions'] ?? 0;
        $netAdjustments = $salaryData['net_adjustments'] ?? 0;

        // ✅ **الحل: حساب المبالغ المتاحة**
        $availableToEarn = max(0, $totalAvailablePayment - $totalEarned);

        // ✅ **الحل: إجمالي المدفوعات (بما فيهم التعديلات)**
        $totalPaidWithAdjustments = $totalEarned + $totalBonuses - $totalDeductions;

        // ✅ **الحل: حساب Salary Summary**
        $paidCount = count(array_filter($salaryRecords, fn ($r) => $r['status'] === 'paid'));
        $pendingCount = count(array_filter($salaryRecords, fn ($r) => $r['status'] === 'pending'));
        $partialCount = count(array_filter($salaryRecords, fn ($r) => $r['status'] === 'partial'));

        $salarySummary = (object) [
            'total_records' => count($salaryRecords),
            'total_paid' => $totalEarned,
            'total_pending' => array_sum(array_column(array_filter($salaryRecords, fn ($r) => $r['status'] === 'pending'), 'teacher_share')),
            'total_partial' => array_sum(array_column(array_filter($salaryRecords, fn ($r) => $r['status'] === 'partial'), 'paid_amount')),
        ];

        // ✅ **الحل: جلب المجموعات مع تحديث بياناتها**
        $groups = \App\Models\Group::with('course')
            ->withCount('students')
            ->where('teacher_id', $teacher->teacher_id)
            ->orderBy('start_date', 'desc')
            ->get();

        foreach ($groups as $group) {
            // البحث عن سجل الراتب لهذه المجموعة
            $groupSalaryRecord = collect($salaryRecords)->firstWhere('group_id', $group->group_id);

            if ($groupSalaryRecord) {
                $group->group_revenue = $groupSalaryRecord['group_revenue'];
                $group->teacher_share = $groupSalaryRecord['teacher_share'];
                $group->teacher_percentage = $groupSalaryRecord['teacher_percentage'];
                $group->available_payment = $groupSalaryRecord['available_payment'];
                $group->paid_amount = $groupSalaryRecord['paid_amount'];
                $group->remaining = $groupSalaryRecord['remaining'];
                $group->salary_status = $groupSalaryRecord['status'];
            } else {
                // إذا لم يكن هناك سجل راتب، حساب القيم الافتراضية
                $group->group_revenue = 0;
                $group->teacher_share = 0;
                $group->teacher_percentage = $group->teacher_percentage ?? 40;
                $group->available_payment = 0;
                $group->paid_amount = 0;
                $group->remaining = 0;
                $group->salary_status = 'pending';
            }
        }

        // ✅ **الحل: جلب Assignments**
        $assignments = DB::table('assignments as a')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->leftJoin('sessions as s', 'a.session_id', '=', 's.session_id')
            ->leftJoin('assignment_submissions as sub', 'a.assignment_id', '=', 'sub.assignment_id')
            ->select(
                'a.assignment_id',
                'a.title',
                'a.due_date',
                'a.session_id',
                'g.group_name',
                's.topic as session_topic',
                's.session_date',
                DB::raw('COUNT(DISTINCT sub.submission_id) as total_submissions'),
                DB::raw('SUM(CASE WHEN sub.score IS NULL AND sub.submission_id IS NOT NULL THEN 1 ELSE 0 END) as pending_grading')
            )
            ->where('g.teacher_id', $teacher->teacher_id)
            ->groupBy([
                'a.assignment_id',
                'a.title',
                'a.due_date',
                'a.session_id',
                'g.group_name',
                's.topic',
                's.session_date',
            ])
            ->orderBy('a.due_date', 'desc')
            ->limit(5)
            ->get();

        // ✅ **الحل: جلب Submissions**
        $submissions = DB::table('assignment_submissions as s')
            ->join('assignments as a', 's.assignment_id', '=', 'a.assignment_id')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->join('students as st', 's.student_id', '=', 'st.student_id')
            ->leftJoin('sessions as sess', 'a.session_id', '=', 'sess.session_id')
            ->select(
                's.submission_id',
                's.assignment_id',
                'a.title as assignment_title',
                'g.group_name',
                'st.student_name',
                'st.student_id',
                's.submission_date',
                's.file_path',
                's.score',
                's.feedback',
                's.graded_at',
                'a.session_id',
                'sess.topic as session_topic',
                'sess.session_date'
            )
            ->where('g.teacher_id', $teacher->teacher_id)
            ->orderBy('s.submission_date', 'desc')
            ->limit(20)
            ->get();

        // ✅ **الحل: جلب Notifications**
        $notifications = DB::table('notifications')
            ->where('user_id', $teacherId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('teachers.dashboard', [
            'teacher' => $teacher,
            'groups' => $groups,
            'salaryRecords' => $salaryRecords,
            'salaries' => $salaries,
            'totalEarned' => $totalEarned,
            'availableToEarn' => $availableToEarn,
            'salarySummary' => $salarySummary,
            'assignments' => $assignments,
            'submissions' => $submissions,
            'notifications' => $notifications,
            'totalPaidWithAdjustments' => $totalPaidWithAdjustments,
            'totalBonuses' => $totalBonuses,
            'totalDeductions' => $totalDeductions,
            'netAdjustments' => $netAdjustments,
            'totalTeacherShare' => $totalTeacherShare,
            'totalRemaining' => $totalRemaining
        ]);
    }

    private function getTeacherSalaryDataForDashboard($teacher_id)
    {
        // الحصول على سجلات الرواتب
        $salaryRecordsQuery = DB::table('salaries')
            ->join('groups', 'salaries.group_id', '=', 'groups.group_id')
            ->where('salaries.teacher_id', $teacher_id)
            ->where('salaries.teacher_share', '>', 0);

        $salaryRecords = $salaryRecordsQuery
            ->select(
                'salaries.*',
                'groups.group_name',
                'groups.end_date as group_end_date',
                'groups.schedule',
                'groups.price as group_price',
                'groups.teacher_percentage'
            )
            ->get()
            ->unique(function ($item) {
                return $item->group_id.'_'.$item->month;
            });

        $processedRecords = [];
        $totalGroupRevenue = 0;
        $totalTeacherShare = 0;
        $totalAvailablePayment = 0;
        $totalPaidAmount = 0;
        $totalRemaining = 0;

        foreach ($salaryRecords as $salary) {
            // حساب القيم الأساسية
            $calculatedValues = $this->calculateSalaryValuesForDashboard($salary);

            $studentCount = DB::table('student_group')
                ->where('group_id', $salary->group_id)
                ->count();

            $paidAmount = DB::table('teacher_payments')
                ->where('salary_id', $salary->salary_id)
                ->sum('amount');

            $remaining = max(0, $calculatedValues['available_payment'] - $paidAmount);

            $status = 'pending';
            if ($calculatedValues['available_payment'] <= 0) {
                $status = 'pending';
            } elseif ($paidAmount >= $calculatedValues['available_payment']) {
                $status = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
            }

            $record = [
                'group_id' => $salary->group_id,
                'group_name' => $salary->group_name,
                'group_end_date' => $salary->group_end_date,
                'schedule' => $salary->schedule,
                'month' => $salary->month,
                'student_count' => $studentCount,
                'group_price' => $salary->group_price,
                'group_revenue' => $calculatedValues['revenue'],
                'teacher_share' => $calculatedValues['teacher_share'],
                'teacher_percentage' => $calculatedValues['actual_percentage'],
                'available_payment' => $calculatedValues['available_payment'],
                'paid_amount' => $paidAmount,
                'remaining' => max(0, $remaining),
                'status' => $status,
                'salary_id' => $salary->salary_id,
            ];

            $processedRecords[] = $record;

            // حساب الإجماليات
            $totalGroupRevenue += $calculatedValues['revenue'];
            $totalTeacherShare += $calculatedValues['teacher_share'];
            $totalAvailablePayment += $calculatedValues['available_payment'];
            $totalPaidAmount += $paidAmount;
            $totalRemaining += max(0, $remaining);
        }

        // الحصول على التعديلات
        $adjustments = DB::table('teacher_adjustments')
            ->where('teacher_id', $teacher_id)
            ->get();

        $totalBonuses = $adjustments->where('type', 'bonus')->sum('amount');
        $totalDeductions = $adjustments->where('type', 'deduction')->sum('amount');
        $netAdjustments = $totalBonuses - $totalDeductions;

        return [
            'salary_records' => $processedRecords,
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
     * حساب قيم الراتب للمجموعة
     */
    private function calculateSalaryValuesForDashboard($salary)
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
                    'actual_percentage' => 0,
                ];
            }

            // حساب عدد الطلاب
            $student_count = DB::table('student_group')
                ->where('group_id', $group->group_id)
                ->count();

            // الإيرادات: عدد الطلاب × سعر المجموعة
            $group_revenue = $student_count * $group->price;

            // الفواتير المدفوعة
            $total_paid_fees = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->sum('amount_paid');

            // نسبة المدرس
            $teacher = DB::table('teachers')->where('teacher_id', $salary->teacher_id)->first();
            $current_percentage = $group->teacher_percentage ?? ($teacher->salary_percentage ?? 40);

            // حصة المدرس: الإيرادات × النسبة
            $teacher_share = $group_revenue * ($current_percentage / 100);

            // المبلغ المتاح: المبالغ المدفوعة فعلياً × النسبة
            $available_payment = $total_paid_fees * ($current_percentage / 100);

            return [
                'revenue' => $group_revenue,
                'teacher_share' => $teacher_share,
                'available_payment' => $available_payment,
                'actual_percentage' => $current_percentage,
            ];

        } catch (\Exception $e) {
            Log::error('Error in calculateSalaryValuesForDashboard: '.$e->getMessage());

            return [
                'revenue' => 0,
                'teacher_share' => 0,
                'available_payment' => 0,
                'actual_percentage' => 40,
            ];
        }
    }

    public function edit($id)
    {
        // Get teacher basic info
        $teacher = DB::table('teachers as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('profile as p', 'u.id', '=', 'p.user_id')
            ->where('t.teacher_id', $id)
            ->select('t.*', 'u.email', 'u.is_active', 'p.nickname', 'p.phone_number', 'p.address', 'p.profile_picture_url')
            ->first();

        if (!$teacher) {
            abort(404, 'Teacher not found');
        }

        return view('teachers.edit', [
            'teacher' => $teacher
        ]);
    }
}
