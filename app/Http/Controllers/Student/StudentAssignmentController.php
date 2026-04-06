<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StudentAssignmentController extends Controller
{
    /**
     * عرض صفحة تسليم الواجب
     */
    public function submitAssignment(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user->isStudent()) {
                return redirect()->route('dashboard')->with('error', 'Unauthorized access');
            }

            $student = $user->student;
            if (! $student) {
                return redirect()->route('student.dashboard')->with('error', 'Student profile not found.');
            }

            $assignmentId = $request->query('assignment_id');

            if (! $assignmentId) {
                return redirect()->route('student.my_assignments')->with('error', 'Assignment ID is required.');
            }

            // جلب بيانات الواجب مع التحقق من تسجيل الطالب في المجموعة
            $assignment = DB::table('assignments as a')
                ->join('groups as g', 'a.group_id', '=', 'g.group_id')
                ->join('student_group as gs', 'g.group_id', '=', 'gs.group_id')
                ->where('a.assignment_id', $assignmentId)
                ->where('gs.student_id', $student->student_id)
                ->select('a.*', 'g.group_name')
                ->first();

            if (! $assignment) {
                return redirect()->route('student.my_assignments')->with('error', 'Assignment not found or you are not enrolled in this group.');
            }

            // جلب التقديم السابق إن وجد
            $submission = DB::table('assignment_submissions')
                ->where('assignment_id', $assignmentId)
                ->where('student_id', $student->student_id)
                ->first();

            return view('student.assignments.submit', [
                'assignment' => $assignment,
                'submission' => $submission
            ]);

        } catch (\Exception $e) {
            Log::error('Submit assignment page error: '.$e->getMessage());

            return redirect()->route('student.dashboard')->with('error', 'An error occurred while loading the assignment.');
        }
    }

    /**
     * معالجة تسليم الواجب
     */
    public function processSubmission(Request $request)
    {
        // بداية transaction
        DB::beginTransaction();

        try {
            // تحقق من صحة البيانات
            $validated = $request->validate([
                'assignment_id' => 'required|exists:assignments,assignment_id',
                'files.*' => 'required|file|mimes:pdf,doc,docx,txt,png,jpg,jpeg,gif,zip,rar,7z|max:15360',
                'user_file' => 'nullable|file|mimes:pdf,doc,docx,txt,png,jjpg,jpeg,gif,zip,rar,7z|max:15360',
                'message' => 'nullable|string|max:2000',
            ]);

            $user = Auth::user();
            if (! $user->isStudent()) {
                return redirect()->back()->with('error', 'Unauthorized access');
            }

            $student = $user->student;
            if (! $student) {
                return redirect()->back()->with('error', 'Student profile not found.');
            }

            $assignmentId = $request->assignment_id;

            // التحقق من تسجيل الطالب في المجموعة
            $assignment = DB::table('assignments as a')
                ->join('student_group as gs', 'a.group_id', '=', 'gs.group_id')
                ->where('a.assignment_id', $assignmentId)
                ->where('gs.student_id', $student->student_id)
                ->select('a.*')
                ->first();

            if (! $assignment) {
                return redirect()->back()->with('error', 'You are not enrolled in this assignment\'s group.');
            }

            $filePaths = [];

            // معالجة الملفات الرئيسية
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    if ($file->isValid()) {
                        $filename = 'assignment_'.$assignmentId.'_'.$student->student_id.'_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                        $path = $file->storeAs('assignments/submissions', $filename, 'public');
                        $filePaths[] = [
                            'name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'size' => $file->getSize(),
                            'type' => $file->getClientMimeType(),
                        ];
                    }
                }
            }

            // معالجة الملف الشخصي الإضافي
            $userFileData = null;
            if ($request->hasFile('user_file') && $request->file('user_file')->isValid()) {
                $userFile = $request->file('user_file');
                $userFilename = 'personal_'.$assignmentId.'_'.$student->student_id.'_'.time().'_'.uniqid().'.'.$userFile->getClientOriginalExtension();
                $userFilePath = $userFile->storeAs('assignments/submissions/personal', $userFilename, 'public');
                $userFileData = [
                    'name' => $userFile->getClientOriginalName(),
                    'path' => $userFilePath,
                    'size' => $userFile->getSize(),
                    'type' => $userFile->getClientMimeType(),
                ];
            }

            // بيانات التقديم
            $submissionData = [
                'assignment_id' => $assignmentId,
                'student_id' => $student->student_id,
                'file_path' => json_encode($filePaths),
                'user_file' => $userFileData ? json_encode($userFileData) : null,
                'feedback' => $request->message,
                'submission_date' => now(),
                'updated_at' => now(),
            ];

            // التحقق من وجود تقديم سابق
            $existingSubmission = DB::table('assignment_submissions')
                ->where('assignment_id', $assignmentId)
                ->where('student_id', $student->student_id)
                ->first();

            if ($existingSubmission) {
                // حذف الملفات القديمة
                $this->deleteOldFiles($existingSubmission);

                // تحديث التقديم الحالي
                AssignmentSubmission::where('submission_id', $existingSubmission->submission_id)
                    ->update($submissionData);

                $message = 'Assignment updated successfully!';
            } else {
                // تقديم جديد
                AssignmentSubmission::create($submissionData);
                $message = 'Assignment submitted successfully!';
            }

            DB::commit();

            return redirect()->route('student.my_assignments')->with('success', $message);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assignment submission error: '.$e->getMessage());
            Log::error('Assignment submission data: ', $request->all());

            return redirect()->back()->with('error', 'Failed to submit assignment. Please try again.')->withInput();
        }
    }

    /**
     * حذف الملفات القديمة
     */
    private function deleteOldFiles($submission)
    {
        try {
            // حذف الملفات الرئيسية
            if ($submission->file_path) {
                $files = json_decode($submission->file_path, true);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (isset($file['path']) && Storage::disk('public')->exists($file['path'])) {
                            Storage::disk('public')->delete($file['path']);
                        }
                    }
                }
            }

            // حذف الملف الشخصي
            if ($submission->user_file) {
                $userFile = json_decode($submission->user_file, true);
                if (is_array($userFile) && isset($userFile['path']) && Storage::disk('public')->exists($userFile['path'])) {
                    Storage::disk('public')->delete($userFile['path']);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error deleting old files: '.$e->getMessage());
        }
    }

    /**
     * عرض الواجبات الخاصة بالطالب
     */
    public function index()
    {
        $user = Auth::user();
        if (! $user->isStudent()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        try {
            $student = $user->student;

            if (! $student) {
                return redirect()->route('student.dashboard')->with('error', 'Student profile not found.');
            }

            // إصلاح الـ Query - جلب الأسايمنتس من المجموعات المسجل فيها الطالب
            $assignments = DB::table('assignments as a')
                ->join('groups as g', 'a.group_id', '=', 'g.group_id')
                ->join('student_group as gs', 'g.group_id', '=', 'gs.group_id')
                ->leftJoin('courses as c', 'g.course_id', '=', 'c.course_id')
                ->leftJoin('assignment_submissions as sub', function ($join) use ($student) {
                    $join->on('a.assignment_id', '=', 'sub.assignment_id')
                        ->where('sub.student_id', '=', $student->student_id);
                })
                ->where('gs.student_id', $student->student_id)
                ->select(
                    'a.assignment_id',
                    'a.title',
                    'a.description',
                    'a.due_date',
                    'a.created_at',
                    'a.teacher_file',
                    'g.group_name',
                    'c.course_name',
                    'sub.submission_date',
                    'sub.score',
                    'sub.feedback',
                    'sub.graded_at',
                    'sub.file_path',
                    DB::raw('CASE WHEN sub.submission_id IS NOT NULL THEN "Submitted" ELSE "Not Submitted" END as status')
                )
                ->orderBy('a.due_date', 'asc')
                ->get();

            return view('student.assignments.index', [
                'assignments' => $assignments
            ]);

        } catch (\Exception $e) {
            Log::error('My assignments error: '.$e->getMessage());
            return redirect()->route('student.dashboard')->with('error', 'An error occurred while loading your assignments.');
        }
    }
}
