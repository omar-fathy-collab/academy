<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Notification;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\MeetingLog;
use App\Models\Rating;
use App\Models\Session;
use App\Models\SessionMaterial;
use App\Models\SessionMeeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SessionsController extends Controller
{
    public function show(Request $request, $id)
    {
        $user = auth()->user();
        // Check if user is teacher or admin
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            abort(403, 'Unauthorized');
        }

        // Get session with group - support both numeric ID and UUID
        $query = Session::with(['group.course', 'group.teacher', 'meetings']);
        if (is_numeric($id)) {
            $query->where('session_id', $id);
        } else {
            $query->where('uuid', $id);
        }
        $session = $query->first();

        if (! $session) {
            abort(404, 'Session not found');
        }

        // Check if teacher owns this group (if not admin)
        if ($user->isTeacher() && (! $user->teacher || $session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        // Check if session date is within group date range
        $sessionDate = $session->session_date;
        $groupStart = $session->group->start_date;
        $groupEnd = $session->group->end_date;

        if ($sessionDate < $groupStart || $sessionDate > $groupEnd) {
            return back()->with('error', 'Session date is outside the group date range');
        }

        // Get students in group
        $students = $session->group->students()->orderBy('student_name')->get();

        // Get assignments for this session
        $assignments = Assignment::where('session_id', $session->session_id)
            ->orderBy('due_date', 'desc')
            ->get();

        // Get quizzes for this session
        $quizzes = Quiz::where('session_id', $session->session_id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get materials for this session
        $materials = $session->materials()->orderBy('created_at', 'desc')->get();

        // Get videos for this session
        $videos = DB::table('videos')
            ->where('session_id', $session->session_id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get books for this session
        $books = \App\Models\Book::where('session_id', $session->session_id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get attendance and ratings data
        $attendanceData = [];
        $ratingData = [];
        $submissionData = [];

        foreach ($students as $student) {
            // Attendance
            $attendance = Attendance::where('session_id', $session->session_id)
                ->where('student_id', $student->student_id)
                ->first();
            $attendanceData[$student->student_id] = $attendance;

            // Session rating
            $rating = Rating::where('session_id', $session->session_id)
                ->where('student_id', $student->student_id)
                ->where('rating_type', 'session')
                ->first();
            $ratingData[$student->student_id] = $rating;

            // Assignment submissions for all session assignments
            $submissions = [];
            foreach ($assignments as $assignment) {
                $submission = AssignmentSubmission::where('assignment_id', $assignment->assignment_id)
                    ->where('student_id', $student->student_id)
                    ->first();
                $submissions[$assignment->assignment_id] = $submission;
            }
            $submissionData[$student->student_id] = $submissions;
        }

        // Check if session can be edited (today or future, OR if user is admin)
        $isEditable = $user->isAdmin() || ($session->session_date >= now()->toDateString());

        return view('sessions.show', [
            'session' => $session,
            'students' => $students,
            'assignments' => $assignments,
            'quizzes' => $quizzes,
            'materials' => $materials,
            'videos' => $videos,
            'books' => $books,
            'attendanceData' => $attendanceData,
            'ratingData' => $ratingData,
            'submissionData' => $submissionData,
            'isEditable' => $isEditable
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        // Check if user is teacher or admin
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            abort(403, 'Unauthorized');
        }

        $session = Session::with('group')
            ->when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        // Check if teacher owns this group (if not admin)
        if ($user->isTeacher() && (! $user->teacher || $session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        // Check if this is a session details update or attendance/rating update
        if ($request->has('topic')) {
            // Session details update
            $request->validate([
                'topic' => 'required|string|max:255',
                'session_date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required',
                'meeting_link' => 'nullable|url',
                'file_path' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx|max:10240',
            ]);

            // Check if session date is within group date range
            if ($request->session_date < $session->group->start_date || $request->session_date > $session->group->end_date) {
                return back()->with('error', 'Session date must be within the group date range');
            }

            $session->topic = $request->topic;
            $session->session_date = $request->session_date;
            $session->start_time = $request->start_time;
            $session->end_time = $request->end_time;
            $session->notes = $request->notes;
            $session->meeting_link = $request->meeting_link;
            $session->requires_proximity = $request->boolean('requires_proximity');

            // Handle file upload
            if ($request->hasFile('file_path')) {
                $file = $request->file('file_path');
                $filename = time().'_'.$file->getClientOriginalName();
                $file->move(public_path('uploads'), $filename);
                $session->file_path = $filename;
            }

            $session->save();

            // Handle multiple meetings
            if ($request->has('meetings')) {
                $meetingIds = [];
                foreach ($request->input('meetings') as $meetingData) {
                    if (isset($meetingData['id']) && $meetingData['id']) {
                        $meetingIds[] = $meetingData['id'];
                        SessionMeeting::where('id', $meetingData['id'])->update([
                            'title' => $meetingData['title'],
                            'meeting_link' => $meetingData['link'],
                            'end_time' => $meetingData['end_time'] ?? null
                        ]);
                    } elseif (!empty($meetingData['link'])) {
                        $newMeeting = SessionMeeting::create([
                            'session_id' => $session->session_id,
                            'title' => $meetingData['title'] ?? 'Meeting Room',
                            'meeting_link' => $meetingData['link'],
                            'end_time' => $meetingData['end_time'] ?? null
                        ]);
                        $meetingIds[] = $newMeeting->id;
                    }
                }
                // Delete meetings not in the request
                $session->meetings()->whereNotIn('id', $meetingIds)->delete();
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Session updated successfully']);
            }

            return redirect()->route('sessions.show', $session->uuid ?? $session->session_id)->with('success', 'Session updated successfully');
        }

        // Handle attendance/rating update
        // Allow admins to edit past sessions, but restrict teachers
        if (! $user->isAdmin() && $user->isTeacher() && $session->session_date < now()->toDateString()) {
            return back()->with('error', 'Cannot edit past sessions');
        }

        $students = $session->group->students;

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                $sid = $student->student_id;
                $attendanceStatus = $request->input("attendance.{$sid}");
                $ratingValue = $request->input("rating.{$sid}");

                // Save attendance
                if ($attendanceStatus) {
                    Attendance::updateOrCreate(
                        [
                            'session_id' => $session->session_id,
                            'student_id' => $sid,
                        ],
                        [
                            'status' => $attendanceStatus,
                            'recorded_by' => auth()->id(),
                            'recorded_at' => now(),
                        ]
                    );
                }

                // Save session rating
                if ($ratingValue !== null && $ratingValue !== '') {
                    Rating::updateOrCreate(
                        [
                            'student_id' => $sid,
                            'session_id' => $session->session_id,
                            'rating_type' => 'session',
                        ],
                        [
                            'group_id' => $session->group_id,
                            'rating_value' => $ratingValue,
                            'rated_by' => auth()->id(),
                            'rated_at' => now(),
                        ]
                    );

                    // Create notification if rating provided
                    if ($ratingValue > 0) {
                        Notification::create([
                            'user_id' => $student->user_id,
                            'title' => 'Session Rating',
                            'message' => "You have received a session rating: {$ratingValue}/5",
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('sessions.show', $session->uuid ?? $session->session_id)->with('success', 'Session data saved successfully');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error saving data: '.$e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = auth()->user();
        // Check if user is teacher or admin
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            abort(403, 'Unauthorized');
        }

        $session = Session::with('group.course', 'group.teacher')
            ->when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        // Check if teacher owns this group (if not admin)
        if ($user->isTeacher() && (! $user->teacher || $session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        return view('sessions.edit', [
            'session' => $session,
            'group' => $session->group
        ]);
    }

    public function destroy($id)
    {
        try {
            $session = Session::when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();
        $group = $session->group;

            $user = auth()->user();
            // Check permissions
            if ($user->isTeacher() && auth()->id() != $group->teacher->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this session.',
                ], 403);
            }

            // Additional checks for teachers (Admins can delete any session)
            if ($user->isTeacher()) {
                $session_date = $session->session_date;
                $today = \Carbon\Carbon::today();
                $is_session_today_or_future = ($session_date >= $today);
                $has_ratings = $session->ratings()->where('rating_type', 'session')->exists();

                if (! $is_session_today_or_future || $has_ratings) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete this session. It may be in the past or has ratings.',
                    ], 422);
                }
            }

            // Use transaction for safety
            DB::beginTransaction();

            // Step 1: Delete quiz-related data (deepest level first)
            $quizIds = Quiz::where('session_id', $session->session_id)->pluck('quiz_id');

            if ($quizIds->isNotEmpty()) {
                // Get all question IDs for these quizzes
                $questionIds = Question::whereIn('quiz_id', $quizIds)->pluck('question_id');

                if ($questionIds->isNotEmpty()) {
                    // Delete from quiz_answers if table exists
                    if (\Schema::hasTable('quiz_answers')) {
                        $optionIds = Option::whereIn('question_id', $questionIds)->pluck('option_id');

                        if ($optionIds->isNotEmpty()) {
                            QuizAnswer::whereIn('option_id', $optionIds)->delete();
                        }
                    }

                    // Delete options
                    Option::whereIn('question_id', $questionIds)->delete();

                    // Delete questions
                    Question::whereIn('quiz_id', $quizIds)->delete();
                }

                // Delete quiz attempts if table exists
                if (\Schema::hasTable('quiz_attempts')) {
                    QuizAttempt::whereIn('quiz_id', $quizIds)->delete();
                }

                // Delete quizzes
                Quiz::whereIn('quiz_id', $quizIds)->delete();
            }

            // Step 2: Delete assignment-related data
            $assignmentIds = Assignment::where('session_id', $session->session_id)->pluck('assignment_id');

            if ($assignmentIds->isNotEmpty()) {
                // Delete assignment submissions
                AssignmentSubmission::whereIn('assignment_id', $assignmentIds)->delete();

                // Delete assignments
                Assignment::where('session_id', $session->session_id)->delete();
            }

            // Step 3: Delete attendance records
            Attendance::where('session_id', $session->session_id)->delete();

            // Step 4: Delete ratings
            Rating::where('session_id', $session->session_id)->delete();

            // Step 5: Delete session materials
            SessionMaterial::where('session_id', $session->session_id)->delete();

            // Step 6: Finally delete the session
            $session->delete();

            DB::commit();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Session and all related content deleted successfully!',
                ]);
            }

            return redirect()->route('groups.show', $group->uuid ?? $group->group_id)->with('success', 'Session deleted successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found.',
                ], 404);
            }

            return redirect()->back()->with('error', 'Session not found.');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting session: '.$e->getMessage());

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error deleting session: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Error deleting session: '.$e->getMessage());
        }
    }

    public function updateMaterial(Request $request, $materialId)
    {
        $user = auth()->user();
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            abort(403, 'Unauthorized');
        }

        $material = \App\Models\SessionMaterial::with('session.group')->findOrFail($materialId);

        // Check permissions
        if ($user->isTeacher() && (! $user->teacher || $material->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'material' => 'required|file|max:20480', // 20MB
        ]);

        // Delete old file
        if ($material->file_path && Storage::exists('public/'.$material->file_path)) {
            Storage::delete('public/'.$material->file_path);
        }

        // Upload new file
        if ($request->hasFile('material')) {
            $file = $request->file('material');
            $filename = time().'_'.$file->getClientOriginalName();
            $path = $file->storeAs('session_materials', $filename, 'public');

            $material->file_path = $path;
            $material->original_name = $file->getClientOriginalName();
            $material->size = $file->getSize();
            $material->mime_type = $file->getMimeType();
            $material->save();
        }

        return back()->with('success', 'Material updated successfully');
    }

    public function destroyMaterial($materialId)
    {
        $user = auth()->user();
        if (! $user->isAdmin() && ! $user->isTeacher()) {
            return back()->with('error', 'Unauthorized');
        }

        $material = \App\Models\SessionMaterial::with('session.group')->findOrFail($materialId);

        // Check permissions
        if ($user->isTeacher() && (! $user->teacher || $material->session->group->teacher_id != $user->teacher->teacher_id)) {
            return back()->with('error', 'Unauthorized');
        }

        try {
            // Delete file
            if ($material->file_path && Storage::exists('public/'.$material->file_path)) {
                Storage::delete('public/'.$material->file_path);
            }

            // Delete record
            $material->delete();

            return redirect()->route('sessions.show', $material->session->uuid ?? $material->session_id)->with('success', 'Material deleted successfully');

        } catch (\Exception $e) {
            return redirect()->route('sessions.show', $material->session->uuid ?? $material->session_id)->with('error', 'Error deleting material: '.$e->getMessage());
        }
    }

    /**
     * Student: Join meeting and track
     */
    public function joinMeeting($id)
    {
        $user = auth()->user();
        $session = Session::with('meetings')->when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        $meetingId = request('meeting_id');
        $meeting = null;

        if ($meetingId) {
            $meeting = SessionMeeting::findOrFail($meetingId);
        } elseif ($session->meetings->count() === 1) {
            $meeting = $session->meetings->first();
        } elseif (!$session->meeting_link && $session->meetings->count() === 0) {
            return back()->with('error', 'Meeting link is not available yet.');
        }

        // Log the first step
        MeetingLog::create([
            'user_id' => $user->id,
            'session_id' => $session->session_id,
            'meeting_id' => $meeting ? $meeting->id : null,
            'event_type' => 'clicked_join',
            'occurred_at' => now()
        ]);

        return view('student.sessions.join-meeting', [
            'session' => $session,
            'meeting' => $meeting,
            'meetings' => $session->meetings,
            'meeting_link' => $meeting ? $meeting->meeting_link : $session->meeting_link
        ]);
    }

    /**
     * Track second step (redirected to actual meet)
     */
    public function logMeetingEvent(Request $request, $id)
    {
        $user = auth()->user();
        $session = Session::when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        MeetingLog::create([
            'user_id' => $user->id,
            'session_id' => $session->session_id,
            'meeting_id' => $request->input('meeting_id'),
            'event_type' => $request->input('event_type', 'redirected'),
            'occurred_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Track user leaving the meeting (closing join page)
     */
    public function logMeetingLeave(Request $request, $id)
    {
        $user = auth()->user();
        $session = Session::when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        MeetingLog::create([
            'user_id' => $user->id,
            'session_id' => $session->session_id,
            'meeting_id' => $request->input('meeting_id'),
            'event_type' => 'left_meeting',
            'occurred_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Teacher: View who clicked meeting links for a session
     */
    public function meetingLog($id)
    {
        $user = auth()->user();
        $session = Session::with(['meetings.logs.user', 'group'])
            ->when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        // Authorization: teacher must own this session's group
        if ($user->isTeacher() && $user->teacher) {
            if ($session->group->teacher_id !== $user->teacher->teacher_id) {
                abort(403, 'You do not have access to this session.');
            }
        }

        // Build per-meeting stats
        $meetingStats = $session->meetings->map(function ($meeting) {
            $clicks = $meeting->logs->where('event_type', 'clicked_join');
            $redirects = $meeting->logs->where('event_type', 'redirected');

            $uniqueUsers = $meeting->logs->pluck('user_id')->unique();

            return [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_link' => $meeting->meeting_link,
                'total_clicks' => $clicks->count(),
                'total_redirects' => $redirects->count(),
                'unique_students' => $uniqueUsers->count(),
                'logs' => $meeting->logs->sortByDesc('occurred_at')->values()->map(fn($log) => [
                    'id' => $log->id,
                    'user_name' => $log->user?->name ?? 'Unknown',
                    'event_type' => $log->event_type,
                    'occurred_at' => $log->occurred_at,
                ]),
            ];
        });

        return view('teacher.session-meeting-log', [
            'session' => $session,
            'meetingStats' => $meetingStats,
        ]);
    }

    /**
     * Public shareable link: /m/{meetingId}
     * Requires auth (middleware handles redirect to login with intended URL).
     * Verifies student is enrolled, logs the click, redirects to actual meeting.
     */
    public function publicJoinMeeting($meetingId)
    {
        $user = auth()->user();
        $meeting = SessionMeeting::with('session.group')->findOrFail($meetingId);
        $session = $meeting->session;

        // 1. Check if meeting is manually closed
        if ($meeting->is_closed) {
            abort(403, 'This meeting link has been closed by the teacher.');
        }

        // 2. Check if meeting has expired (Students only)
        if ($meeting->end_time && !$user->isAdmin() && !$user->isTeacher()) {
            $sessionDate = $session->session_date;
            $expiryTime = \Carbon\Carbon::parse($sessionDate)->setTimeFromTimeString($meeting->end_time);
            if (now()->greaterThan($expiryTime)) {
                abort(403, 'This meeting link has expired.');
            }
        }


        // Admin & teacher: just redirect without enrollment check
        if (!$user->isAdmin() && !$user->isTeacher()) {
            // Check if student is enrolled in the group
            $student = $user->student;
            $enrolled = false;
            if ($student) {
                $enrolled = DB::table('student_group')
                    ->where('student_id', $student->student_id)
                    ->where('group_id', $session->group_id)
                    ->exists();
            }
            if (!$enrolled) {
                abort(403, 'You are not enrolled in this group.');
            }
        }

        // Log the click
        MeetingLog::create([
            'user_id'    => $user->id,
            'session_id' => $session->session_id,
            'meeting_id' => $meeting->id,
            'event_type' => 'clicked_join',
            'occurred_at' => now(),
        ]);

        return redirect()->away($meeting->meeting_link);
    }

    public function syncAttendanceFromMeetings($id)
    {
        $user = auth()->user();
        $session = Session::with('group')->when(is_numeric($id), fn($q) => $q->where('session_id', $id), fn($q) => $q->where('uuid', $id))
            ->firstOrFail();

        // Authorization
        if ($user->isTeacher() && $user->teacher) {
            if ($session->group->teacher_id !== $user->teacher->teacher_id) {
                abort(403);
            }
        } elseif (!$user->isAdmin()) {
            abort(403);
        }

        // Get all unique users who clicked join for this session
        $userIds = MeetingLog::where('session_id', $session->session_id)
            ->where('event_type', 'clicked_join')
            ->pluck('user_id')
            ->unique();

        $markedCount = 0;
        foreach ($userIds as $uid) {
            $student = \App\Models\Student::where('user_id', $uid)->first();
            if ($student) {
                $attendance = Attendance::updateOrCreate(
                    [
                        'session_id' => $session->session_id,
                        'student_id' => $student->student_id,
                    ],
                    [
                        'status' => 'present',
                        'recorded_by' => auth()->id(),
                        'recorded_at' => now(),
                        'notes' => 'Auto-synced from meeting click log'
                    ]
                );
                if ($attendance->wasRecentlyCreated || $attendance->wasChanged('status')) {
                    $markedCount++;
                }
            }
        }

        return back()->with('success', "Successfully synced attendance for {$markedCount} students from meeting logs.");
    }

    public function toggleMeetingStatus($id)
    {
        $user = auth()->user();
        $meeting = SessionMeeting::with('session.group')->findOrFail($id);

        if (!$user->isAdmin()) {
            if (!$user->isTeacher() || !$user->teacher || $meeting->session->group->teacher_id != $user->teacher->teacher_id) {
                abort(403, 'Unauthorized');
            }
        }

        $meeting->is_closed = !$meeting->is_closed;
        $meeting->save();

        return response()->json([
            'success' => true,
            'is_closed' => (bool)$meeting->is_closed,
            'message' => $meeting->is_closed ? 'Meeting access closed' : 'Meeting access opened'
        ]);
    }
}
