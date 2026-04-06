<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Notification;
use App\Notifications\StudentCertificateNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    /**
     * Resolve the best instructor name for a certificate.
     * Priority:
     * 1. certificate->group->teacher->teacher_name
     * 2. certificate->group->teacher->user->name
     * 3. teacher->teacher (legacy)
     * 4. find a teacher from user's groups that matches the certificate course
     */
    private function resolveInstructorName(Certificate $certificate)
    {
        // If group->teacher is present, prefer teacher_name then user->name
        if ($certificate->group && $certificate->group->teacher) {
            $t = $certificate->group->teacher;

            return $t->teacher_name ?? ($t->user->name ?? ($t->teacher ?? null));
        }

        // Try to find a teacher from user's groups for the same course
        if ($certificate->user && $certificate->user->student) {
            $studentGroups = $certificate->user->student->groups ?? collect();

            // Prefer group with same course
            foreach ($studentGroups as $g) {
                if ($certificate->course && $g->course_id == $certificate->course->course_id && $g->teacher) {
                    $t = $g->teacher;

                    return $t->teacher_name ?? ($t->user->name ?? ($t->teacher ?? null));
                }
            }

            // Otherwise pick first group's teacher
            foreach ($studentGroups as $g) {
                if ($g->teacher) {
                    $t = $g->teacher;

                    return $t->teacher_name ?? ($t->user->name ?? ($t->teacher ?? null));
                }
            }
        }

        return null;
    }

    public function index()
    {
        $certificates = Certificate::with(['user.profile', 'user.student.groups', 'course', 'group.teacher.user', 'issuer'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // attach instructor_name to each certificate in the paginator
        $certificates->getCollection()->transform(function ($certificate) {
            $certificate->instructor_name = $this->resolveInstructorName($certificate);

            return $certificate;
        });

        $requests = \App\Models\CertificateRequest::with(['user.profile', 'user.student.groups', 'course', 'group.teacher.user'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // attach instructor_name to certificate requests
        $requests->transform(function ($request) {
            if ($request->group || $request->user) {
                // create a temporary certificate-like object to reuse the resolver
                $temp = new \App\Models\Certificate($request->toArray());
                $temp->setRelation('user', $request->user);
                $temp->setRelation('group', $request->group);
                $request->instructor_name = $this->resolveInstructorName($temp);
            }

            return $request;
        });

        return view('certificates.admin.index', compact('certificates', 'requests'));

    }

    public function createForAdmin()
    {
        // eager load student and their groups to avoid N+1 when rendering student groups in the view
        $students = User::where('role_id', Role::STUDENT_ID)->with(['student.groups.course'])->get();
        $groups = Group::with(['course', 'teacher'])->get();

        return view('certificates.admin.create', compact('students', 'groups'));
    }

    /**
     * Show the form for a teacher to award a badge to a student in any of their groups.
     */
    public function teacherCreate()
    {
        $user = auth()->user();
        $teacher = $user->teacher;
        if (! $teacher) {
            abort(403, 'Only teachers can access this page.');
        }

        // load groups taught by this teacher and eager load students and course
        $groups = Group::where('teacher_id', $teacher->teacher_id)
            ->with(['students.user.profile', 'course'])
            ->get();

        return view('certificates.teacher.create', compact('groups'));
    }

    /**
     * Store a badge awarded by a teacher.
     */
    public function teacherStore(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,group_id',
            'issue_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $user = auth()->user();
        $teacher = $user->teacher;
        if (! $teacher) {
            abort(403, 'Only teachers can perform this action.');
        }

        $group = Group::find($request->group_id);
        if (! $group || $group->teacher_id != $teacher->teacher_id) {
            abort(403, 'You can only award badges to students in your groups.');
        }

        $studentUser = User::find($request->user_id);
        if (! $studentUser || ! $studentUser->student) {
            abort(422, 'Selected user is not a student.');
        }

        // ensure the student belongs to the selected group (qualify column to avoid ambiguous column error)
        $belongs = $group->students()
            ->where('student_group.student_id', $studentUser->student->student_id)
            ->exists();
        if (! $belongs) {
            abort(403, 'Selected student is not part of the chosen group.');
        }

        $issueDate = $request->issue_date ? $request->issue_date : now();

        $certificate = Certificate::create([
            'user_id' => $studentUser->id,
            'certificate_type' => 'individual',
            'course_id' => $group->course_id ?? null,
            'group_id' => $group->group_id,
            'issued_by' => auth()->id(),
            'certificate_number' => 'BADGE-'.strtoupper(Str::random(8)),
            'issue_date' => $issueDate,
            'attendance_percentage' => null,
            'quiz_average' => null,
            'final_rating' => null,
            'status' => 'issued',
            'remarks' => $request->remarks,
        ]);

        // Notify the student about the badge (wrap in try/catch to avoid failing the request)
        try {
            $certificate->load(['user']);
            if ($certificate->user) {
                $certificate->user->notify(new StudentCertificateNotification($certificate));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notification for teacher-awarded badge '.$certificate->id.': '.$e->getMessage());

            try {
                Notification::create([
                    'user_id' => $certificate->user->id,
                    'title' => 'تم منحك بادج جديد',
                    'message' => 'تم منحك بادج جديد برقم: '.($certificate->certificate_number ?? ''),
                    'type' => 'badge',
                    'related_id' => $certificate->id,
                    'is_read' => false,
                ]);
            } catch (\Exception $ex) {
                Log::error('Failed to persist fallback notification for badge '.$certificate->id.': '.$ex->getMessage());
            }
        }

        // Optionally: generate PDF or notifications later

        return redirect()->route('teacher.certificates.index')
            ->with('success', 'Badge awarded successfully.');
    }

    /**
     * List badges issued by the authenticated teacher.
     */
    public function teacherIndex()
    {
        $user = auth()->user();
        $teacher = $user->teacher;
        if (! $teacher) {
            abort(403, 'Only teachers can access this page.');
        }

        // certificates where issued_by equals this teacher's user id
        $certificates = Certificate::with(['user.profile', 'group.course', 'course'])
            ->where('issued_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('certificates.teacher.index', compact('certificates'));
    }

    public function storeForAdmin(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'nullable|exists:courses,course_id',
            'group_id' => 'nullable|exists:groups,group_id',
            'issue_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        $user = User::find($request->user_id);

        // Determine certificate type server-side to avoid accidental client-side defaults.
        // If a group_id is provided, this is a group_completion certificate; otherwise individual.
        $certificateType = $request->group_id ? 'group_completion' : 'individual';

        // Calculate performance metrics if group/course provided
        $attendancePercentage = null;
        $quizAverage = null;
        $finalRating = null;

        if ($request->group_id) {
            $group = Group::find($request->group_id);

            // Calculate attendance percentage
            $totalSessions = $group->sessions()->count();
            $attendedSessions = $user->student->attendances()
                ->whereHas('session', function ($q) use ($group) {
                    $q->where('group_id', $group->group_id);
                })
                ->where('status', 'present')
                ->count();

            $attendancePercentage = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

            // Calculate quiz average
            $quizzes = $group->sessions()->with('quizzes')->get()->flatMap(function ($session) {
                return $session->quizzes;
            });
            $totalScore = 0;
            $quizCount = 0;

            foreach ($quizzes as $quiz) {
                $attempt = $quiz->attempts()->where('student_id', $user->student->student_id)->first();
                if ($attempt && $attempt->score !== null) {
                    $totalScore += $attempt->score;
                    $quizCount++;
                }
            }

            $quizAverage = $quizCount > 0 ? $totalScore / $quizCount : null;

            // Get final rating (average). Clamp to 0..5 just in case stored ratings are out of range.
            $finalRating = $user->student->ratings()
                ->where('group_id', $group->group_id)
                ->avg('rating_value');
            if ($finalRating !== null) {
                $finalRating = (float) $finalRating;
                if ($finalRating < 0) {
                    $finalRating = 0;
                }
                if ($finalRating > 5) {
                    $finalRating = 5;
                }
            }
        }

        try {
            DB::beginTransaction();

            $certificate = Certificate::create([
                'user_id' => $request->user_id,
                'certificate_type' => $certificateType,
                'course_id' => $request->course_id,
                'group_id' => $request->group_id,
                'issued_by' => Auth::id(),
                'certificate_number' => 'CERT-'.strtoupper(Str::random(8)),
                'issue_date' => $request->issue_date,
                'attendance_percentage' => $attendancePercentage,
                'quiz_average' => $quizAverage,
                'final_rating' => $finalRating,
                'status' => 'issued', // Create as issued directly
                'remarks' => $request->remarks,
            ]);
            DB::commit();

            // Generate PDF after committing the transaction so PDF failures do not rollback
            try {
                $this->generatePDF($certificate);
            } catch (\Exception $e) {
                Log::error('PDF generation failed after creating certificate '.$certificate->id.': '.$e->getMessage());
            }

            // Send notification to the student (explicit in case observer didn't fire)
            try {
                $certificate->load(['user']);
                if ($certificate->user) {
                    $certificate->user->notify(new StudentCertificateNotification($certificate));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notification after creating certificate '.$certificate->id.': '.$e->getMessage());
            }
            Log::info('Certificate created successfully for user: '.$request->user_id);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create certificate: '.$e->getMessage());

            return redirect()->back()->with('error', 'Failed to create certificate. Please try again.');
        }

        return redirect()->route('certificates.edit', $certificate->id)
            ->with('success', 'Certificate created successfully. Please review and finalize.');
    }

    /**
     * ✅ تحسين دالة previewDesign لجعلها أكثر مرونة
     */
    public function previewDesign(Certificate $certificate, $design)
    {
        $user = auth()->user();
        // Check if user owns this certificate or is admin/instructor
        if (auth()->id() !== $certificate->user_id && ! $user->isAdmin() && ! $user->isTeacher()) {
            abort(403, 'Unauthorized');
        }

        $certificate->load(['user', 'course', 'group.teacher.user', 'issuer']);
        $certificate->instructor_name = $this->resolveInstructorName($certificate);

        // ✅ جعل الدالة أكثر مرونة - استخدام التصميم المطلوب مباشرة
        $allowedDesigns = ['individual', 'group_completion'];

        if (! in_array($design, $allowedDesigns)) {
            abort(404, 'Template design not found');
        }

        // تحديد القالب بناءً على التصميم المطلوب
        $component = "Certificates/DesignPreview";

        return view('certificates.design-preview', [
            'certificate' => $certificate,
            'design' => $design
        ]);
    }

    /**
     * ✅ دالة Download المحسنة
     */
    /**
     * ✅ دالة Download المحسنة والمنقحة
     */
    public function download(Certificate $certificate)
    {
        $certificate->load(['user', 'course', 'group.teacher.user', 'issuer']);
        $certificate->instructor_name = $this->resolveInstructorName($certificate);

        // استخدام certificate_type لتحديد القالب
        $template = $certificate->certificate_type == 'group_completion'
            ? 'certificates.templates.group_completion'
            : 'certificates.templates.individual';

        if (! view()->exists($template)) {
            Log::error("Certificate template not found: {$template}");

            return back()->with('error', 'Certificate template not found.');
        }

        try {
            $pdf = Pdf::loadView($template, compact('certificate'))
                ->setPaper('a4', 'landscape');

            $filename = $certificate->certificate_number.'_certificate.pdf';

            // ✅ استخدام طريقة download البسيطة
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PDF generation failed for certificate '.$certificate->id.': '.$e->getMessage());

            return back()->with('error', 'Certificate PDF could not be generated. Please try again.');
        }
    }

    public function generateGroup(Request $request, ?Group $group = null)
    {
        // Resolve group either from route-model binding or POST 'group_id'
        if (! $group) {
            $groupId = $request->input('group_id');
            $group = Group::find($groupId);
        }

        if (! $group) {
            return redirect()->back()->with('error', 'Selected group not found.');
        }

        $students = $group->students;

        Log::info('generateGroup called without conditions', [
            'group_id' => $group->group_id,
            'student_count' => $students->count(),
        ]);

        $created = 0;
        $skippedNoUser = 0;
        $failed = 0;

        foreach ($students as $student) {
            // Ensure we have an associated user for this student
            if (empty($student->user_id)) {
                Log::warning('Skipping student without user_id in group generation', [
                    'group_id' => $group->group_id,
                    'student_id' => $student->student_id ?? null,
                ]);
                $skippedNoUser++;

                continue;
            }

            // ✅ إزالة جميع الشروط - إنشاء الشهادة تلقائياً
            $attendanceCount = $student->attendances()
                ->whereHas('session', function ($q) use ($group) {
                    $q->where('group_id', $group->group_id);
                })
                ->where('status', 'present')
                ->count();

            $totalSessions = $group->sessions()->count();
            $attendancePercentage = $totalSessions > 0 ? ($attendanceCount / $totalSessions) * 100 : 100; // Default to 100% if no sessions

            // Calculate quiz average
            $quizAverage = null;
            $finalRating = null;

            $quizzes = $group->sessions()->with('quizzes')->get()->flatMap(function ($session) {
                return $session->quizzes;
            });

            $totalScore = 0;
            $quizCount = 0;

            foreach ($quizzes as $quiz) {
                $attempt = $quiz->attempts()->where('student_id', $student->student_id)->first();
                if ($attempt && $attempt->score !== null) {
                    $totalScore += $attempt->score;
                    $quizCount++;
                }
            }

            $quizAverage = $quizCount > 0 ? $totalScore / $quizCount : 100; // Default to 100 if no quizzes

            // Get final rating
            $finalRating = $student->ratings()
                ->where('group_id', $group->group_id)
                ->avg('rating_value');

            if ($finalRating !== null) {
                $finalRating = (float) $finalRating;
                if ($finalRating < 0) {
                    $finalRating = 0;
                }
                if ($finalRating > 5) {
                    $finalRating = 5;
                }
            } else {
                $finalRating = 5; // Default to 5 if no ratings
            }

            try {
                DB::beginTransaction();

                $certificate = Certificate::create([
                    'user_id' => $student->user_id,
                    'certificate_type' => 'group_completion',
                    'course_id' => $group->course_id,
                    'group_id' => $group->group_id,
                    'issued_by' => Auth::id(),
                    'certificate_number' => 'CERT-'.strtoupper(Str::random(8)),
                    'issue_date' => now(),
                    'attendance_percentage' => $attendancePercentage,
                    'quiz_average' => $quizAverage,
                    'final_rating' => $finalRating,
                    'status' => 'issued',
                ]);

                // Generate PDF
                $this->generatePDF($certificate);

                DB::commit();

                // Notify the student
                try {
                    $certificate->load(['user']);
                    if ($certificate->user) {
                        $certificate->user->notify(new StudentCertificateNotification($certificate));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send notification for group certificate '.$certificate->id.': '.$e->getMessage());

                    // Fallback notification
                    try {
                        if ($certificate->user) {
                            Notification::create([
                                'user_id' => $certificate->user->id,
                                'title' => 'New Certificate Issued',
                                'message' => 'A new certificate has been issued with number: '.($certificate->certificate_number ?? ''),
                                'type' => 'certificate',
                                'related_id' => $certificate->id,
                                'is_read' => false,
                            ]);
                        }
                    } catch (\Exception $ex) {
                        Log::error('Failed to persist fallback notification: '.$ex->getMessage());
                    }
                }

                Log::info('Certificate created successfully for student: '.$student->user_id);
                $created++;
            } catch (\Exception $e) {
                DB::rollback();
                Log::error('Failed to create certificate: '.$e->getMessage());
                $failed++;
            }
        }

        $message = "Group certificate generation completed: created={$created}, skipped_no_user={$skippedNoUser}, failed={$failed}";

        return redirect()->route('certificates.index')
            ->with('success', $message);
    }

    public function edit(Certificate $certificate)
    {
        $certificate->load(['user', 'course', 'group.teacher.user']);
        $certificate->instructor_name = $this->resolveInstructorName($certificate);
        $templates = \App\Models\CertificateTemplate::all();

        return view('certificates.admin.edit', compact('certificate', 'templates'));
    }

    public function finalize(Request $request, Certificate $certificate)
    {
        $request->validate([
            'template_id' => 'nullable|exists:certificate_templates,id',
            'remarks' => 'nullable|string',
        ]);

        $certificate->update([
            'template_id' => $request->template_id,
            'remarks' => $request->remarks,
            'status' => 'issued',
        ]);

        // Generate PDF
        $this->generatePDF($certificate);

        return redirect()->route('certificates.index')
            ->with('success', 'Certificate finalized and issued successfully.');
    }

    /**
     * ✅ دالة generatePDF المحسنة
     */
    /**
     * حذف الشهادة
     */
    public function destroy(Certificate $certificate)
    {
        try {
            // حذف ملف PDF إذا كان موجوداً
            if ($certificate->file_path && Storage::exists($certificate->file_path)) {
                Storage::delete($certificate->file_path);
            }

            $certificate->delete();

            return redirect()->route('certificates.index')
                ->with('success', 'Certificate deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete certificate: '.$e->getMessage());

            return redirect()->route('certificates.index')
                ->with('error', 'Failed to delete certificate. Please try again.');
        }
    }

    private function generatePDF(Certificate $certificate)
    {
        $certificate->load(['user', 'course', 'group.teacher.user', 'issuer']);
        $certificate->instructor_name = $this->resolveInstructorName($certificate);

        // استخدام نفس منطق دالة download
        $template = $certificate->certificate_type == 'group_completion'
            ? 'certificates.templates.group_completion'
            : 'certificates.templates.individual';

        // التأكد من وجود القالب
        if (! view()->exists($template)) {
            Log::error("Certificate template not found for PDF generation: {$template}");

            return false;
        }

        try {
            $pdf = Pdf::loadView($template, compact('certificate'))
                ->setPaper('a4', 'landscape');

            $filename = 'certificates/'.$certificate->certificate_number.'.pdf';
            $pdfContent = $pdf->output();

            // Ensure the certificates directory exists
            Storage::makeDirectory('certificates');

            // Store the PDF
            if (Storage::put($filename, $pdfContent)) {
                $certificate->update(['file_path' => $filename]);

                return true;
            } else {
                Log::error('Failed to store certificate PDF: '.$filename);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('PDF generation failed for certificate '.$certificate->id.': '.$e->getMessage());

            return false;
        }
    }

    public function verify($certificate_number)
    {
        $certificate = Certificate::where('certificate_number', $certificate_number)
            ->with(['user', 'course', 'group.teacher.user', 'issuer'])
            ->first();

        if (! $certificate) {
            return view('certificates.verify', ['valid' => false]);
        }

        return view('certificates.verify', [
            'valid' => true,
            'certificate' => $certificate,
        ]);
    }
}
