<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\StudentCourseSelection;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentRegistrationReportController extends Controller
{
    /**
     * عرض تقرير التسجيلات
     */
    public function index(Request $request)
    {
        $query = User::where('role_id', Role::STUDENT_ID) // طلاب فقط
            ->with(['profile', 'student', 'student.courseSelections.course'])
            ->latest();

        // فلترة حسب الكورس
        if ($request->has('course_id') && $request->course_id != '') {
            $courseId = $request->course_id;
            $query->whereHas('student.courseSelections', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        // فلترة حسب تاريخ التسجيل
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // بحث
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('nickname', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        $registrations = $query->paginate(20);

        // إحصائيات
        $stats = [
            'total' => User::where('role_id', Role::STUDENT_ID)->count(),
            'active' => User::where('role_id', Role::STUDENT_ID)->where('is_active', 1)->count(),
            'pending' => User::where('role_id', Role::STUDENT_ID)->where('is_active', 0)->count(),
            'today' => User::where('role_id', Role::STUDENT_ID)->whereDate('created_at', today())->count(),
            'this_week' => User::where('role_id', Role::STUDENT_ID)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => User::where('role_id', Role::STUDENT_ID)->whereMonth('created_at', now()->month)->count(),
        ];

        // جلب الكورسات للفلترة
        $courses = \App\Models\Course::all();

        return view('reports.student_registrations.index', [
            'registrations' => $registrations,
            'stats' => $stats,
            'courses' => $courses,
            'filters' => $request->only(['course_id', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * تصدير التقرير إلى Excel
     */
    public function exportExcel(Request $request)
    {
        $query = User::where('role_id', Role::STUDENT_ID)
            ->with(['profile', 'student.courseSelections.course'])
            ->latest();

        // تطبيق نفس الفلاتر
        if ($request->has('course_id') && $request->course_id != '') {
            $courseId = $request->course_id;
            $query->whereHas('student.courseSelections', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        $registrations = $query->get();

        // إنشاء ملف Excel
        return \Excel::download(new \App\Exports\StudentRegistrationsExport($registrations), 'student_registrations_'.date('Y-m-d').'.xlsx');
    }

    /**
     * عرض تفاصيل التسجيل
     */
    public function show($id)
    {
        $user = User::with([
            'profile',
            'student',
            'student.courseSelections.course',
            'student.courseSelections.selector.profile',
        ])->findOrFail($id);

        // جلب تاريخ التسجيل
        $registrationDate = $user->created_at;

        // جلب الكورسات المتاحة لتعديل الاختيار
        $courses = \App\Models\Course::all();

        return view('reports.student_registrations.details', [
            'user' => $user,
            'registrationDate' => $registrationDate,
            'courses' => $courses,
        ]);
    }

    /**
     * تحديث اختيار الكورس للطالب
     */
    public function updateCourseSelection(Request $request, $id)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,course_id',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = User::with('student')->findOrFail($id);

        DB::beginTransaction();
        try {
            // إنشاء سجل جديد لاختيار الكورس
            StudentCourseSelection::create([
                'student_id' => $user->student->student_id,
                'course_id' => $request->course_id,
                'selection_type' => 'updated',
                'notes' => $request->notes ?? 'تم التحديث من قبل الإدارة',
                'selected_by' => auth()->id(),
            ]);

            // تحديث بيانات الطالب
            $user->student->update([
                'notes' => ($user->student->notes ? $user->student->notes."\n" : '').
                          'تم تحديث اختيار الكورس إلى: '.
                          \App\Models\Course::find($request->course_id)->course_name.
                          ' - '.now()->format('Y-m-d H:i'),
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'تم تحديث اختيار الكورس للطالب بنجاح');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء التحديث: '.$e->getMessage());
        }
    }

    /**
     * إحصائيات الكورسات المطلوبة
     */
    public function courseStatistics()
    {
        $courseStats = StudentCourseSelection::selectRaw('
            course_id,
            COUNT(*) as total_selections,
            COUNT(CASE WHEN selection_type = "initial" THEN 1 END) as initial_selections,
            COUNT(CASE WHEN selection_type = "updated" THEN 1 END) as updated_selections
        ')
            ->groupBy('course_id')
            ->with('course')
            ->get();

        return view('reports.student_registrations.course_stats', [
            'courseStats' => $courseStats
        ]);
    }
}
