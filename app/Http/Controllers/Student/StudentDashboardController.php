<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Invoice;
use App\Models\Quiz;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Session;
use App\Models\SessionMaterial;
use App\Models\Student;
use App\Models\GroupEnrollmentRequest;
use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StudentDashboardController extends Controller
{
    public function dashboard()
    {

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->isStudent() && !$user->isAdmin()) {
            abort(403);
        }
        $userId = Auth::id();
        $student = Auth::user()->student ? $user->student()->with(['user', 'groups.course', 'groups.teacher'])->first() : null;

        if (!$student && !$user->isAdmin()) {
            abort(403, 'Student profile not found.');
        }

        if (!$student && $user->isAdmin()) {
            return view('student.dashboard', [
                'student' => null,
                'groups' => collect(),
                'groupSessions' => [],
                'groupAssignments' => [],
                'notifications' => collect(),
                'unified' => [],
                'soonSessions' => [],
                'soonAssignments' => [],
                'sessionRows' => [],
                'upcomingQuizzes' => [],
                'invoiceSummary' => (object)[
                    'total_invoices' => 0,
                    'total_amount' => 0,
                    'total_paid' => 0,
                    'total_balance' => 0,
                    'pending_count' => 0
                ],
                'recentMaterials' => collect(),
                'certificates' => collect(),
                'pendingRequest' => null,
                'allEnrollmentRequests' => collect(),
                'unpaidInvoices' => collect(),
                'liveMeetings' => [],
                'academyAnnouncements' => \App\Models\Notification::whereIn('type', ['announcement', 'admin', 'important'])
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(),
                'avgQuizScore' => 0,
                'avgAssignmentScore' => 0,
                'attendanceStats' => []
            ]);
        }

        // Get enrolled groups
        $groups = $student ? $student->groups()->with(['course', 'teacher'])->orderBy('start_date', 'desc')->get() : collect();

        // ========== كود التشخيص ==========
        Log::info('=== Student Dashboard Debug ===');
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $student = $user ? $user->student : null;

        Log::info('Student ID: '.($student ? $student->student_id : 'NONE'));
        Log::info('Number of groups: '.$groups->count());

        foreach ($groups as $group) {
            Log::info('Group: '.$group->group_name.' (ID: '.$group->group_id.')');

            // جلب جميع جلسات هذه المجموعة
            $sessions = Session::where('group_id', $group->group_id)

                ->orderBy('session_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();

            Log::info('  Total sessions: '.$sessions->count());

            $now = now();
            Log::info('  Current time: '.$now->toDateTimeString());

            foreach ($sessions as $session) {
                try {
                    $sessionDateTime = \Carbon\Carbon::parse($session->session_date)
                        ->setTimeFromTimeString($session->start_time);
                    $isFuture = $sessionDateTime > $now;

                    Log::info('  Session: '.$session->session_id.
                        ' | Date: '.$session->session_date.
                        ' | Time: '.$session->start_time.
                        ' | DateTime: '.$sessionDateTime->toDateTimeString().
                        ' | Is Future: '.($isFuture ? 'YES' : 'NO'));

                } catch (\Exception $e) {
                    Log::info('  Session: '.$session->session_id.' | ERROR: '.$e->getMessage());
                }
            }
        }
        // ========== نهاية كود التشخيص ==========

        // Get sessions for all groups
        $groupSessions = [];
        foreach ($groups as $group) {
            $groupSessions[$group->group_id] = Session::where('group_id', '=', $group->group_id)
                ->orderBy('session_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();
        }

        // Get assignments for all groups
        $groupAssignments = [];
        foreach ($groups as $group) {
            $groupAssignments[$group->group_id] = Assignment::with(['submissions' => function ($query) use ($student) {
                if ($student) {
                    $query->where('student_id', '=', $student->student_id);
                } else {
                    $query->whereRaw('1=0');
                }
            }])->where('group_id', '=', $group->group_id)->orderBy('due_date', 'asc')->get();
        }

        // Get notifications
        $notifications = DB::table('notifications')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Unified ratings and attendance table
        $unified = [];
        foreach ($groups as $group) {
            // Last attendance
            /** @var \App\Models\Attendance|null $attendance */
            $attendance = $student ? Attendance::query()->where('student_id', $student->student_id)

                ->whereHas('session', function ($query) use ($group) {
                    $query->where('group_id', '=', $group->group_id);
                })
                ->orderBy('attendance_id', 'desc')
                ->first(['*']) : null;

            // Last session rating
            /** @var \App\Models\Rating|null $sessionRating */
            $sessionRating = $student ? Rating::query()->where('student_id', '=', $student->student_id)
                ->where('group_id', '=', $group->group_id)
                ->where('rating_type', '=', 'session')
                ->orderBy('rated_at', 'desc')
                ->first(['*']) : null;

            // Last assignment rating
            /** @var \App\Models\AssignmentSubmission|null $assignmentRating */
            $assignmentRating = $student ? AssignmentSubmission::query()->where('student_id', '=', $student->student_id)
                ->whereHas('assignment', function ($query) use ($group) {
                    $query->where('group_id', '=', $group->group_id);
                })
                ->orderBy('submission_id', 'desc')
                ->first(['*']) : null;

            // Last monthly rating
            /** @var \App\Models\Rating|null $monthlyRating */
            $monthlyRating = $student ? Rating::query()->where('student_id', '=', $student->student_id)
                ->where('group_id', '=', $group->group_id)
                ->where('rating_type', '=', 'monthly')
                ->orderBy('rated_at', 'desc')
                ->first(['*']) : null;

            $unified[] = [
                'group_name' => $group->group_name,
                'course_name' => $group->course->course_name,
                'attendance' => $attendance,
                'session' => $sessionRating,
                'assignment' => $assignmentRating,
                'monthly' => $monthlyRating,
            ];
        }

        // ========== قسم الجلسات القادمة (بعد التعديل) ==========
        $now = now();
        $startOfToday = $now->copy()->startOfDay(); // بداية اليوم الحالي (2026-02-14 00:00:00)
        $soonSessions = [];
        $soonAssignments = [];

        foreach ($groups as $group) {
            $sessions = $groupSessions[$group->group_id] ?? collect();
            foreach ($sessions as $session) {
                try {
                    // الحل الصحيح: استخدام Carbon::parse مع setTimeFromTimeString لتجنب تكرار الوقت
                    $sessionDateTime = \Carbon\Carbon::parse($session->session_date)
                        ->setTimeFromTimeString($session->start_time);

                    // طباعة للـ debugging
                    Log::info('Session: '.$session->session_id.
                        ' | Date: '.$session->session_date.
                        ' | Time: '.$session->start_time.
                        ' | DateTime: '.$sessionDateTime->toDateTimeString().
                        ' | Start of Today: '.$startOfToday->toDateTimeString().
                        ' | Is Today or Future: '.($sessionDateTime >= $startOfToday ? 'YES' : 'NO'));

                    // ✅ التعديل هنا: جلب أي جلسات اليوم أو في المستقبل
                    if ($sessionDateTime >= $startOfToday) {
                        Log::info('  ==> Adding to soonSessions');
                        
                        // Current status flags (4 hours window for "past" today)
                        $sessionEnd = (clone $sessionDateTime)->addHours(2);
                        $isPast = $now->gt($sessionEnd);

                        // Get attendance for this session
                        $attendance = Attendance::query()->where('session_id', '=', $session->session_id)
                            ->where('student_id', '=', $student->student_id)
                            ->first(['*']);

                        $soonSessions[] = [
                            'group' => $group->group_name,
                            'group_id' => $group->group_id,
                            'topic' => $session->topic,
                            'date' => $session->session_date,
                            'time' => $session->start_time,
                            'end_time' => $session->end_time,
                            'session_id' => $session->session_id,
                            'uuid' => $session->uuid,
                            'datetime' => $sessionDateTime,
                            'is_today' => $sessionDateTime->isToday(),
                            'is_past' => $isPast,
                            'attendance' => $attendance,
                            'requires_proximity' => $session->requires_proximity,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Error parsing session date for dashboard: '.$e->getMessage());
                    // Fallback to legacy date parsing if needed
                    try {
                         $sessionDateTime = \Carbon\Carbon::parse($session->session_date);
                         if ($sessionDateTime >= $startOfToday) {
                             $soonSessions[] = [
                                 'group' => $group->group_name,
                                 'group_id' => $group->group_id,
                                 'topic' => $session->topic,
                                 'date' => $session->session_date,
                                 'time' => $session->start_time,
                                 'session_id' => $session->session_id,
                                 'uuid' => $session->uuid,
                                 'datetime' => $sessionDateTime,
                                 'is_today' => $sessionDateTime->isToday(),
                                 'is_past' => false,
                                 'attendance' => null,
                                 'requires_proximity' => $session->requires_proximity,
                             ];
                         }
                    } catch (\Exception $e2) {
                        Log::error('Parsing completely failed for session '.$session->session_id);
                    }
                }
            }

            // الواجبات القادمة
            $assignments = $groupAssignments[$group->group_id] ?? collect();
            foreach ($assignments as $assignment) {
                try {
                    $dueDateTime = \Carbon\Carbon::parse($assignment->due_date);
                    if ($dueDateTime > $now && ! $assignment->submissions->where('student_id', '=', $student->student_id)->count()) {
                        $soonAssignments[] = [
                            'group' => $group->group_name,
                            'title' => $assignment->title,
                            'due' => $assignment->due_date,
                            'id' => $assignment->assignment_id,
                            'datetime' => $dueDateTime,
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip invalid dates
                }
            }
        }

        // ترتيب جميع الجلسات القادمة حسب التاريخ (الأقرب أولاً)
        Log::info('Total soonSessions before sorting: '.count($soonSessions));

        if (! empty($soonSessions)) {
            usort($soonSessions, function ($a, $b) {
                return $a['datetime']->timestamp - $b['datetime']->timestamp;
            });

            // أخذ أول 5 جلسات فقط
            $soonSessions = array_slice($soonSessions, 0, 5);

            // طباعة الجلسات المختارة
            foreach ($soonSessions as $index => $session) {
                Log::info('Selected session '.($index + 1).': '.
                    $session['group'].' - '.
                    $session['date'].' '.$session['time']);
            }
        }

        Log::info('Total soonSessions after sorting: '.count($soonSessions));

        // ترتيب الواجبات القادمة
        if (! empty($soonAssignments)) {
            usort($soonAssignments, function ($a, $b) {
                return $a['datetime']->timestamp - $b['datetime']->timestamp;
            });
            $soonAssignments = array_slice($soonAssignments, 0, 5);
        }
        // ========== نهاية القسم ==========

        // Session rows for history
        $sessionRows = [];
        foreach ($groups as $group) {
            $sessions = $groupSessions[$group->group_id] ?? collect();
            foreach ($sessions as $session) {
                $attendance = Attendance::query()->where('session_id', '=', $session->session_id)->where('student_id', '=', $student->student_id)->first(['*']);
                $rating = Rating::query()->where('session_id', '=', $session->session_id)->where('student_id', '=', $student->student_id)->where('rating_type', '=', 'session')->first(['*']);
                // Get the latest assignment for the group with due_date <= session_date
                $assignment = Assignment::query()->where('group_id', '=', $group->group_id)->where('due_date', '<=', $session->session_date)->orderBy('due_date', 'desc')->first(['*']);
                $submission = null;
                if ($assignment) {
                    $submission = AssignmentSubmission::query()->where('assignment_id', '=', $assignment->assignment_id)->where('student_id', '=', $student->student_id)->first(['*']);
                }
                $sessionRows[] = [
                    'date' => $session->session_date,
                    'group' => $group->group_name,
                    'topic' => $session->topic,
                    'session_id' => $session->session_id,
                    'uuid' => $session->uuid,
                    'attendance' => $attendance ? $attendance->status : 'absent',
                    'rating' => $rating ? $rating->rating_value : null,
                    'rating_comments' => $rating ? $rating->comments : '',
                    'assignment' => $assignment,
                    'submission' => $submission,
                ];
            }
        }

        // Upcoming quizzes
        $upcomingQuizzes = [];

        // Optimized Quiz & Attempts fetching (Single query for all attempts)
        $studentAttempts = DB::table('quiz_attempts')
            ->where('student_id', $student->student_id)
            ->select('quiz_id', DB::raw('count(*) as count'))
            ->groupBy('quiz_id')
            ->pluck('count', 'quiz_id');

        foreach ($groups as $group) {
            $quizzes = Quiz::where('group_id', '=', $group->group_id)
                ->where('is_active', '=', 1)
                ->join('sessions', 'quizzes.session_id', '=', 'sessions.session_id')
                ->orderBy('sessions.session_date')
                ->select('quizzes.*')
                ->get();

            foreach ($quizzes as $quiz) {
                $attempts = $studentAttempts[$quiz->quiz_id] ?? 0;
                $remainingAttempts = $quiz->max_attempts - $attempts;
                $upcomingQuizzes[] = [
                    'quiz' => $quiz,
                    'group_name' => $group->group_name,
                    'attempts' => $attempts,
                    'remaining_attempts' => $remainingAttempts,
                ];
            }
        }

        // Optimized Attendance Percentage per group (2 queries total for all groups)
        $groupIds = $groups->pluck('group_id')->toArray();
        $sessionsCountPerGroup = Session::whereIn('group_id', $groupIds)
            ->where('session_date', '<=', now()->toDateString())
            ->select('group_id', DB::raw('count(*) as total'))
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        $attendedCountPerGroup = Attendance::where('student_id', $student->student_id)
            ->where('status', 'present')
            ->whereHas('session', fn($q) => $q->whereIn('group_id', $groupIds))
            ->join('sessions', 'attendance.session_id', '=', 'sessions.session_id')
            ->select('sessions.group_id', DB::raw('count(*) as total'))
            ->groupBy('sessions.group_id')
            ->pluck('total', 'group_id');

        $attendanceStats = [];
        foreach ($groups as $group) {
            $total = $sessionsCountPerGroup[$group->group_id] ?? 0;
            $attended = $attendedCountPerGroup[$group->group_id] ?? 0;
            $attendanceStats[$group->group_id] = $total > 0 ? round(($attended / $total) * 100) : 0;
        }

        // Average Performance (Already efficient)
        $avgQuizScore = \App\Models\QuizAttempt::where('student_id', $student->student_id)
            ->whereNotNull('score')
            ->avg('score') ?: 0;
            
        $avgAssignmentScore = \App\Models\AssignmentSubmission::where('student_id', $student->student_id)
            ->whereNotNull('score')
            ->avg('score') ?: 0;

        // Specialized Announcements
        $academyAnnouncements = Notification::whereIn('type', ['announcement', 'admin', 'important'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // RE-ADDED MISSING VARIABLES (Optimized)
        $invoiceSummary = DB::table('invoices')
            ->selectRaw('COUNT(*) as total_invoices, SUM(amount) as total_amount, SUM(amount_paid) as total_paid, SUM(amount - amount_paid) as total_balance, SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count')
            ->where('student_id', '=', $student->student_id)
            ->first();

        $unpaidInvoices = \App\Models\Invoice::with('group')
            ->where('student_id', $student->student_id)
            ->where('amount_paid', '<', DB::raw('amount'))
            ->orderBy('due_date', 'asc')
            ->get();

        $recentMaterials = SessionMaterial::whereHas('session', function ($q) use ($groupIds) {
            $q->whereIn('group_id', $groupIds);
        })->with(['session', 'uploader'])->orderBy('created_at', 'desc')->take(5)->get();

        $certificates = \App\Models\Certificate::with(['course', 'group'])
            ->where('user_id', $userId)
            ->where('status', 'issued')
            ->orderBy('issue_date', 'desc')
            ->get();

        $allEnrollmentRequests = GroupEnrollmentRequest::with("group.course")
            ->where("user_id", $userId)
            ->latest()
            ->get();

        // Check for pending certificate request
        $pendingRequest = \App\Models\CertificateRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        // Calculate live meetings
        $liveMeetings = [];
        $now = now();
        // A meeting is active if it's today and within a reasonable window
        foreach ($soonSessions as $sSession) {
            if ($sSession['is_today']) {
                $sessionDateTime = $sSession['datetime'];
                
                // Show "Soon" up to 60 minutes before
                $startTimeVisible = $sessionDateTime->copy()->subMinutes(60);
                
                // Keep "Live" up to end_time or 6 hours fallback
                $endTime = !empty($sSession['end_time']) 
                    ? \Carbon\Carbon::parse($sSession['date'])->setTimeFromTimeString($sSession['end_time'])
                    : $sessionDateTime->copy()->addHours(6);

                if ($now->between($startTimeVisible, $endTime)) {
                    // Fetch actual meeting links for this session
                    // Fix: Check if meeting is NOT closed
                    $sessionMeetings = \App\Models\SessionMeeting::where('session_id', $sSession['session_id'])
                        ->where('is_closed', false)
                        ->get();
                    foreach ($sessionMeetings as $meeting) {
                        $isLive = $now->gte($sessionDateTime);
                        $liveMeetings[] = [
                            'group' => $sSession['group'],
                            'topic' => $sSession['topic'] ?: $meeting->title,
                            'title' => $meeting->title,
                            'link' => route('meeting.join.public', ['meetingId' => $meeting->id]),
                            'session_uuid' => $sSession['uuid'],
                            'status' => $isLive ? 'live' : 'soon',
                            'start_time' => $sessionDateTime->format('H:i'),
                        ];
                    }
                }
            }
        }

        return view('student.dashboard', [
            'student' => $student,
            'groups' => $groups,
            'attendanceStats' => $attendanceStats,
            'avgQuizScore' => round($avgQuizScore, 1),
            'avgAssignmentScore' => round($avgAssignmentScore, 1),
            'academyAnnouncements' => $academyAnnouncements,
            'groupSessions' => $groupSessions,
            'groupAssignments' => $groupAssignments,
            'notifications' => $notifications,
            'unified' => $unified,
            'soonSessions' => $soonSessions,
            'soonAssignments' => $soonAssignments,
            'sessionRows' => $sessionRows,
            'upcomingQuizzes' => $upcomingQuizzes,
            'invoiceSummary' => $invoiceSummary,
            'recentMaterials' => $recentMaterials,
            'certificates' => $certificates,
            'pendingRequest' => $pendingRequest,
            'allEnrollmentRequests' => $allEnrollmentRequests,
            'unpaidInvoices' => $unpaidInvoices,
            'liveMeetings' => $liveMeetings
        ]);
    }

    public function submitAssignment(Request $request, $id = null)
    {
        $user = Auth::user();

        // Check if user is a student
        if (! $user || ! $user->isStudent()) {
            abort(403, 'Unauthorized');
        }

        $userId = $user->id;
        $assignmentId = $id ?? $request->query('id') ?? 0;

        // Get student details
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Get assignment with group info
        $assignment = DB::table('assignments')
            ->join('groups', 'assignments.group_id', '=', 'groups.group_id')
            ->join('student_group', 'groups.group_id', '=', 'student_group.group_id')
            ->where('assignments.assignment_id', $assignmentId)
            ->where('student_group.student_id', $student->student_id)
            ->select('assignments.*', 'groups.group_id', 'groups.group_name')
            ->first();

        if (! $assignment) {
            abort(404, 'Assignment not found');
        }

        // Check for existing submission
        $submission = AssignmentSubmission::where('assignment_id', $assignmentId)
            ->where('student_id', $student->student_id)
            ->first();

        return view('student.assignments.submit', [
            'assignment' => $assignment,
            'submission' => $submission,
            'student' => $student
        ]);
    }

    public function processSubmission(Request $request)
    {
        $user = Auth::user();

        // Check if user is a student
        if (! $user || ! $user->isStudent()) {
            abort(403, 'Unauthorized');
        }

        $userId = $user->id;

        $assignmentId = $request->input('assignment_id');

        // Get student
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Get assignment
        $assignment = Assignment::findOrFail($assignmentId);

        // Check the student is in this group
        $isInGroup = DB::table('student_group')
            ->where('student_id', $student->student_id)
            ->where('group_id', $assignment->group_id)
            ->exists();

        if (! $isInGroup) {
            abort(403, 'Unauthorized');
        }

        $allowedTypes = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'rar', '7z'];
        $uploadedFiles = [];
        $message = $request->input('message', '');
        $userFilePath = null;

        /* ============================================================
           1) Handle Optional User File (user_file)
           ============================================================ */
        if ($request->hasFile('user_file')) {

            $userFile = $request->file('user_file');
            $ext = strtolower($userFile->getClientOriginalExtension());

            if (! in_array($ext, $allowedTypes)) {
                return back()->with('error', "User file type not allowed: .$ext");
            }

            if ($userFile->getSize() > 15 * 1024 * 1024) {
                return back()->with('error', 'User file too large (max 15MB).');
            }

            // Directory in "public"
            $userUploadPath = public_path('assignments_user');

            if (! file_exists($userUploadPath)) {
                mkdir($userUploadPath, 0777, true);
            }

            $newName = time().'_'.uniqid().'.'.$ext;

            // Move to public
            $userFile->move($userUploadPath, $newName);

            // Path saved to DB
            $userFilePath = 'assignments_user/'.$newName;
        }

        /* ============================================================
           2) Handle Multi-File Upload (files[])
           ============================================================ */
        if (! $request->hasFile('files')) {
            return back()->with('error', 'Please select at least one file.');
        }

        // Folder in public
        $folderName = "assignment_{$assignmentId}_student_{$student->student_id}_".time();
        $submissionFolderPath = public_path("assignments_submissions/$folderName");

        if (! file_exists($submissionFolderPath)) {
            mkdir($submissionFolderPath, 0777, true);
        }

        foreach ($request->file('files') as $file) {

            $ext = strtolower($file->getClientOriginalExtension());

            if (! in_array($ext, $allowedTypes)) {
                return back()->with('error', "File type not allowed: {$file->getClientOriginalName()}");
            }

            if ($file->getSize() > 15 * 1024 * 1024) {
                return back()->with('error', "File too large (max 15MB): {$file->getClientOriginalName()}");
            }

            $newName = uniqid().'_'.time().'.'.$ext;

            // Move to folder
            $file->move($submissionFolderPath, $newName);

            // Save path for DB
            $uploadedFiles[] = "assignments_submissions/$folderName/$newName";
        }

        if (empty($uploadedFiles)) {
            return back()->with('error', 'No files were uploaded successfully.');
        }

        $filePathsJson = json_encode($uploadedFiles);

        /* ============================================================
           3) Save Submission (updateOrCreate)
           ============================================================ */
        $submission = AssignmentSubmission::updateOrCreate(
            [
                'assignment_id' => $assignmentId,
                'student_id' => $student->student_id,
            ],
            [
                'file_path' => $filePathsJson,
                'feedback' => $message,
                'submission_date' => now(),
            ]
        );

        /* ============================================================
           4) Save Optional user_file in Assignment
           ============================================================ */
        if ($userFilePath) {
            $assignment->update(['user_file' => $userFilePath]);
        }

        /* ============================================================
           5) Send Notification to Teacher
           ============================================================ */
        $teacher = DB::table('groups')
            ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->where('groups.group_id', $assignment->group_id)
            ->select('teachers.user_id')
            ->first();

        if ($teacher) {
            \App\Models\Notification::create([
                'user_id' => $teacher->user_id,
                'title' => $assignment->title,
                'message' => "Student submitted assignment: {$assignment->title}",
            ]);
        }

        return back()->with('success', 'Assignment submitted successfully! Files uploaded: '.count($uploadedFiles));
    }

    public function myAssignments()
    {

        $userId = Auth::id();

        // Check if user is a student (role_id = 3)
        $user = DB::table('users')->where('id', $userId)->first();
        if (! Auth::user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get student details
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Get assignments with submissions, groups, and courses
        $assignments = DB::table('assignments')
            ->join('groups', 'assignments.group_id', '=', 'groups.group_id')
            ->join('courses', 'groups.course_id', '=', 'courses.course_id')
            ->join('student_group', 'groups.group_id', '=', 'student_group.group_id')
            ->leftJoin('assignment_submissions', function ($join) use ($student) {
                $join->on('assignments.assignment_id', '=', 'assignment_submissions.assignment_id')
                    ->where('assignment_submissions.student_id', '=', $student->student_id);
            })
            ->where('student_group.student_id', $student->student_id)
            ->whereNotNull('assignments.session_id')
            ->select(
                'assignments.assignment_id',
                'assignments.title',
                'assignments.description',
                'assignments.due_date',
                'assignments.session_id',
                'assignments.teacher_file',
                'assignments.user_file',
                'groups.group_name',
                'courses.course_name',
                'assignment_submissions.submission_id',
                'assignment_submissions.score',
                'assignment_submissions.feedback',
                'assignment_submissions.submission_date',
                'assignment_submissions.graded_at',
                'assignment_submissions.file_path'
            )
            ->orderBy('assignments.due_date', 'desc')
            ->get();

        // Add status to each assignment
        foreach ($assignments as $assignment) {
            if ($assignment->submission_id === null) {
                $assignment->status = 'Not Submitted';
            } elseif ($assignment->score === null) {
                $assignment->status = 'Submitted (Pending Grading)';
            } else {
                $assignment->status = 'Graded';
            }
        }

        return view('student.assignments', [
            'assignments' => $assignments
        ]);
    }

    public function certificates(Request $request)
    {
        $groupIdFromQuery = $request->query('group_id');

        $userId = Auth::id();

        // Check if user is a student (role_id = 3)
        $user = DB::table('users')->where('id', $userId)->first();
        if (! Auth::user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get student details
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Get issued certificates for the student
        $certificates = \App\Models\Certificate::with(['course', 'group'])
            ->where('user_id', $userId)
            ->where('status', 'issued')
            ->orderBy('issue_date', 'desc')
            ->get();

        // Check for pending certificate request
        $pendingRequest = \App\Models\CertificateRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        // Get student's groups for the request dropdown
        $groups = $student->groups()->with('course')->get();

        return view('student.certificates', [
            'certificates' => $certificates,
            'pendingRequest' => $pendingRequest,
            'student' => $student,
            'groups' => $groups,
            'preSelectedGroupId' => $groupIdFromQuery
        ]);
    }

    public function requestCertificate(Request $request)
    {
        Log::info('Certificate request submission started.', [
            'user_id' => Auth::id(),
            'group_id' => $request->input('group_id')
        ]);

        $userId = Auth::id();


        // Check if user is a student
        if (! Auth::user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get student details
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Check if there's already a pending request
        $existingRequest = \App\Models\CertificateRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            Log::warning('Certificate request failed: Pending request already exists.', ['user_id' => $userId]);
            return back()->with('error', 'You already have a pending certificate request.');
        }

        $groupId = $request->input('group_id');

        $courseId = null;

        if ($groupId) {
            $group = Group::where('group_id', $groupId)
                ->whereHas('students', function($q) use ($student) {
                    $q->where('students.student_id', $student->student_id);
                })
                ->first();

            if ($group) {
                $courseId = $group->course_id;
            } else {
                return back()->with('error', 'Invalid group selected.');
            }
        } else {
            // Fallback to first group if none selected
            $group = $student->groups()->first();
            if ($group) {
                $groupId = $group->group_id;
                $courseId = $group->course_id;
            }
        }

        if (!$courseId || !$groupId) {
            Log::error('Certificate request failed: No valid course or group.', ['user_id' => $userId, 'group_id' => $groupId]);
            return back()->with('error', 'You must be enrolled in at least one course to request a certificate.');
        }


        // Create certificate request
        \App\Models\CertificateRequest::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'group_id' => $groupId,
            'status' => 'pending',
            'remarks' => $request->input('reason', 'Individual request from student.'),
        ]);

        Log::info('Certificate request submitted successfully.', ['user_id' => $userId, 'group_id' => $groupId]);

        return back()->with('success', 'Certificate request submitted successfully.');
    }


    public function viewCertificate($id)
    {
        $userId = Auth::id();

        // Check if user is a student (role_id = 3)
        $user = DB::table('users')->where('id', $userId)->first();
        if (! Auth::user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get the certificate and ensure it belongs to the student
        $certificate = \App\Models\Certificate::with(['course', 'group', 'user'])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('status', 'issued')
            ->firstOrFail();

        return view('certificates.view', compact('certificate'));
    }

    /**
     * عرض جميع جلسات الطالب (الماضية والحالية والمستقبلية)
     */
    public function mySessions()
    {
        $userId = auth()->id();

        // Check if user is a student
        $user = DB::table('users')->where('id', $userId)->first();
        if (! auth()->user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get student details
        $student = Student::with(['user'])->where('user_id', $userId)->firstOrFail();

        // Get all groups the student is enrolled in
        $groups = $student->groups()->with(['course', 'teacher'])->get();
        $groupIds = $groups->pluck('group_id')->toArray();

        // Get current time for comparison
        $now = now();
        $startOfToday = $now->copy()->startOfDay();

        // Get all sessions for student's groups with related data
        $allSessions = Session::with(['group.course', 'group.teacher', 'materials'])
            ->whereIn('group_id', $groupIds)
            ->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(function ($session) use ($student, $now, $startOfToday) {
                // ✅ الطريقة الصحيحة والآمنة لإنشاء تاريخ ووقت الجلسة
                try {
                    $sessionDateTime = \Carbon\Carbon::parse($session->session_date)
                        ->setTimeFromTimeString($session->start_time);
                } catch (\Exception $e) {
                    // إذا فشلت كل المحاولات، استخدم التاريخ فقط مع وقت افتراضي
                    Log::error('Failed to parse session datetime for session ID: '.$session->session_id);
                    $sessionDateTime = \Carbon\Carbon::parse($session->session_date)->setTime(0, 0, 0);
                }

                // Determine session status (with 6-hour duration assumption for fallback)
                // Fix: Correct Carbon parsing for end_time
                $sessionEndDateTime = $session->end_time 
                    ? \Carbon\Carbon::parse($session->session_date)->setTimeFromTimeString($session->end_time)
                    : $sessionDateTime->copy()->addHours(6);

                if ($now->between($sessionDateTime, $sessionEndDateTime)) {
                    $status = 'current'; // الحصة مستمرة الآن
                } elseif ($now->lt($sessionDateTime)) {
                    $status = 'upcoming'; // الحصة لم تبدأ بعد
                } else {
                    $status = 'past'; // الحصة انتهت
                }

                // Get attendance for this session
                $attendance = Attendance::where('session_id', $session->session_id)
                    ->where('student_id', $student->student_id)
                    ->first();

                // Get rating for this session
                $rating = Rating::where('session_id', $session->session_id)
                    ->where('student_id', $student->student_id)
                    ->where('rating_type', 'session')
                    ->first();

                // Get assignment for this session if any
                $assignment = Assignment::where('group_id', $session->group_id)
                    ->where('session_id', $session->session_id)
                    ->first();

                $submission = null;
                if ($assignment) {
                    $submission = AssignmentSubmission::where('assignment_id', $assignment->assignment_id)
                        ->where('student_id', $student->student_id)
                        ->first();
                }

                // Get quiz for this session if any
                $quiz = Quiz::where('session_id', $session->session_id)
                    ->where('is_active', 1)
                    ->first();

                $quizAttempt = null;
                if ($quiz) {
                    $quizAttempt = DB::table('quiz_attempts')
                        ->where('quiz_id', $quiz->quiz_id)
                        ->where('student_id', $student->student_id)
                        ->first();
                }

                return [
                    'session' => $session,
                    'group_name' => $session->group->group_name,
                    'course_name' => $session->group->course->course_name ?? '',
                    'teacher_name' => $session->group->teacher->teacher_name ?? '',
                    'datetime' => $sessionDateTime,
                    'status' => $status,
                    'is_today' => $sessionDateTime->isToday(),
                    'is_upcoming' => $status == 'upcoming',
                    'is_past' => $status == 'past',
                    'attendance' => $attendance,
                    'rating' => $rating,
                    'assignment' => $assignment,
                    'submission' => $submission,
                    'quiz' => $quiz,
                    'quiz_attempt' => $quizAttempt,
                    'materials_count' => $session->materials->count(),
                ];
            });

        // Separate sessions by status
        $upcomingSessions = $allSessions->filter(function ($item) {
            return $item['status'] == 'upcoming';
        })->sortBy('datetime')->values();

        $pastSessions = $allSessions->filter(function ($item) {
            return $item['status'] == 'past';
        })->sortByDesc('datetime')->values();

        // Get counts
        $totalSessions = $allSessions->count();
        $attendedSessions = $allSessions->filter(function ($item) {
            return $item['attendance'] && $item['attendance']->status == 'present';
        })->count();

        return view('student.my_sessions', [
            'student' => $student,
            'groups' => $groups,
            'upcomingSessions' => $upcomingSessions,
            'pastSessions' => $pastSessions,
            'totalSessions' => $totalSessions,
            'attendedSessions' => $attendedSessions
        ]);
    }

    /**
     * عرض تفاصيل جلسة محددة
     */
    /**
     * عرض تفاصيل جلسة محددة
     */
    /**
     * عرض تفاصيل جلسة محددة
     */
    /**
     * تحميل مادة تعليمية باستخدام session_id واسم الملف
     */
    /**
     * عرض مجموعاتي الدراسية
     */
    public function myGroups()
    {
        $userId = Auth::id();
        $student = Student::where('user_id', '=', $userId)->firstOrFail();

        $groups = $student->groups()->with(['course', 'teacher'])->get();

        return view('student.my_groups', [
            'student' => $student,
            'groups' => $groups
        ]);
    }

    /**
     * عرض تفاصيل المجموعة والجلسات الخاصة بها
     */
    public function groupDetails($groupId)
    {
        $userId = Auth::id();
        $student = Student::where('user_id', '=', $userId)->firstOrFail();

        // Check if group exists and student is enrolled
        $group = Group::with(['course', 'teacher'])->findOrFail($groupId);

        $isEnrolled = DB::table('student_group')
            ->where('student_id', $student->student_id)
            ->where('group_id', $group->group_id)
            ->exists();

        if (!$isEnrolled) {
            abort(403, 'You are not enrolled in this group.');
        }

        $sessions = Session::where('group_id', $group->group_id)
            ->with(['materials'])
            ->orderBy('session_date', 'desc')
            ->get();

        // Aggregated materials for the "Materials" tab
        $allMaterials = SessionMaterial::whereIn('session_id', $sessions->pluck('session_id'))
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('student.group_details', [
            'student' => $student,
            'group' => $group,
            'sessions' => $sessions,
            'allMaterials' => $allMaterials
        ]);
    }

    public function downloadSessionMaterial($session_id, $file_name)
    {
        $userId = auth()->id();

        // Check if user is a student
        $user = DB::table('users')->where('id', $userId)->first();
        if (! auth()->user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get session - resolving from UUID if needed
        $session = Session::where('session_id', $session_id)
            ->orWhere('uuid', $session_id)
            ->firstOrFail();

        // Get student details
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Check if student is enrolled in this session's group
        $isEnrolled = DB::table('student_group')
            ->where('student_id', $student->student_id)
            ->where('group_id', $session->group_id)
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'You are not enrolled in this group');
        }

        // Find material by session_id (numeric) and original_name (consistent with model)
        $material = SessionMaterial::where('session_id', $session->session_id)
            ->where('original_name', $file_name)
            ->first();

        if (! $material) {
            abort(404, 'Material not found');
        }

        // Get file path
        // Get absolute file path from storage
        $filePath = storage_path('app/public/' . $material->file_path);


        // Check if file exists
        if (! file_exists($filePath)) {
            abort(404, 'File not found');
        }

        // Return file download
        return response()->download($filePath, $material->file_name);
    }

    public function sessionDetails($session_id)
    {
        $userId = auth()->id();

        // Check if user is a student
        $user = DB::table('users')->where('id', $userId)->first();
        // Check if user is a student
        if (! auth()->user()->isStudent()) {
            abort(403, 'Unauthorized');
        }

        // Get student details
        $student = Student::where('user_id', $userId)->firstOrFail();

        // Get session with related data
        $session = Session::with([
            'group.course',
            'group.teacher',
            'materials.uploader',
            'meetings',
        ])->where('session_id', $session_id)
          ->orWhere('uuid', $session_id)
          ->firstOrFail();

        // Get videos for this session with student progress
        $videos = DB::table('videos')
            ->where('session_id', $session->session_id)
            ->get()
            ->map(function ($video) use ($student) {
                $progress = DB::table('video_progress')
                    ->where('video_id', $video->id)
                    ->where('student_id', $student->student_id)
                    ->first();

                /** @var object|null $progress */
                $video->watched_percentage = $progress ? (float) $progress->watched_percentage : 0;
                $video->is_completed = $progress ? (bool) $progress->is_completed : false;
                $video->last_position = $progress ? (int) $progress->last_position : 0;
                return $video;
            });

        // Check if student is enrolled in this session's group
        $isEnrolled = DB::table('student_group')
            ->where('student_id', $student->student_id)
            ->where('group_id', $session->group_id)
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'You are not enrolled in this group');
        }

        // Get attendance separately
        $attendance = Attendance::where('session_id', $session->session_id)
            ->where('student_id', $student->student_id)
            ->first();

        // Get session datetime
        try {
            $sessionDateTime = \Carbon\Carbon::parse($session->session_date)
                ->setTimeFromTimeString($session->start_time);
            
            if (!empty($session->end_time)) {
                $sessionEndDateTime = \Carbon\Carbon::parse($session->session_date)
                    ->setTimeFromTimeString($session->end_time);
            } else {
                $sessionEndDateTime = $sessionDateTime->copy()->addHours(6);
            }
        } catch (\Exception $e) {
            $sessionDateTime = \Carbon\Carbon::parse($session->session_date);
            $sessionEndDateTime = $sessionDateTime->copy()->addHours(6);
        }

        $now = now();
        $isPast = $sessionEndDateTime < $now;
        $isToday = $sessionDateTime->isToday();
        $isUpcoming = $sessionDateTime > $now;

        // Get rating
        $rating = Rating::where('session_id', $session->session_id)
            ->where('student_id', $student->student_id)
            ->where('rating_type', 'session')
            ->first();

        // Get all assignments for this session with student's submissions
        $assignments = Assignment::where('session_id', $session->session_id)
            ->with(['submissions' => function($q) use ($student) {
                $q->where('student_id', $student->student_id);
            }])
            ->orderBy('due_date', 'desc')
            ->get()
            ->map(function($a) {
                $a->student_submission = $a->submissions->first();
                return $a;
            });

        // Get all active quizzes for this session with student's attempts
        $quizzes = Quiz::where('session_id', $session->session_id)
            ->where('is_active', 1)
            ->with(['attempts' => function($q) use ($student) {
                $q->where('student_id', $student->student_id);
            }])
            ->get()
            ->map(function($q) use ($student) {
                $q->student_attempts_count = $q->attempts->count();
                $q->best_attempt = $q->attempts->sortByDesc('score')->first();
                $q->remaining_attempts = max(0, $q->max_attempts - $q->student_attempts_count);
                return $q;
            });

        // Get books for this session
        $books = \App\Models\Book::where('session_id', $session->session_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('student.session_details', [
            'student' => $student,
            'session' => $session,
            'videos' => $videos,
            'sessionDateTime' => $sessionDateTime->toIso8601String(),
            'sessionEndDateTime' => $sessionEndDateTime->toIso8601String(),
            'isPast' => $isPast,
            'isToday' => $isToday,
            'isUpcoming' => $isUpcoming,
            'attendance' => $attendance,
            'rating' => $rating,
            'assignments' => $assignments,
            'quizzes' => $quizzes,
            'books' => $books,
        ]);
    }
}
