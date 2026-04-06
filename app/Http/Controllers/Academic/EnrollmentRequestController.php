<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\EnrollmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        
        $requests = EnrollmentRequest::with(['user', 'course'])
            ->when($status, function($query, $status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $allStudents = \App\Models\Student::with('user')->select(['student_id', 'user_id'])->get(['*']);
        $allCourses = \App\Models\Course::orderBy('course_name', 'asc')->select(['course_id', 'course_name', 'is_free'])->get(['*']);

        return view('admin.enrollment_requests.index', [
            'requests' => $requests,
            'filters' => [
                'status' => $status
            ],
            'allStudents' => $allStudents,
            'allCourses' => $allCourses
        ]);
    }

    public function update(Request $request, $id)
    {
        $enrollRequest = EnrollmentRequest::findOrFail($id);
        $request->validate([
            'status' => 'required|in:pending,approved,rejected,paid',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $enrollRequest->update([
                'status' => $request->status,
                'notes' => $request->notes ?? $enrollRequest->notes
            ]);

            if ($request->status === 'approved' || $request->status === 'paid') {
                $student = \App\Models\Student::where('user_id', $enrollRequest->user_id)->select(['student_id', 'user_id'])->first(['*']);
                if ($student) {
                    DB::table('student_course')->updateOrInsert([
                        'student_id' => $student->student_id,
                        'course_id' => $enrollRequest->course_id
                    ], [
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();
            return back()->with('success', 'تم تحديث حالة الطلب بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء تحديث الطلب.');
        }
    }

    public function destroy($id)
    {
        $enrollRequest = EnrollmentRequest::findOrFail($id);
        $enrollRequest->delete();
        return back()->with('success', 'تم حذف الطلب بنجاح.');
    }

    public function manualEnroll(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,student_id',
            'course_id' => 'required|exists:courses,course_id',
            'notes' => 'nullable|string'
        ]);

        $student = \App\Models\Student::findOrFail($request->student_id);
        
        DB::beginTransaction();
        try {
            // Add to student_course
            DB::table('student_course')->updateOrInsert([
                'student_id' => $student->student_id,
                'course_id' => $request->course_id
            ], [
                'updated_at' => now(),
                'created_at' => DB::raw('IFNULL(created_at, NOW())')
            ]);

            // Create a "paid" enrollment request record for history
            EnrollmentRequest::create([
                'user_id' => $student->user_id,
                'course_id' => $request->course_id,
                'status' => 'paid',
                'amount' => \App\Models\Course::select(['course_id', 'price'])->find($request->course_id, ['*'])->price ?? 0,
                'notes' => 'إضافة يدوية من الإدارة: ' . ($request->notes ?? '')
            ]);

            DB::commit();
            return back()->with('success', 'تم تفعيل الكورس للطالب بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء تفعيل الكورس.');
        }
    }
}
