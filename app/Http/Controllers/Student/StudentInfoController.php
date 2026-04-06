<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Rating;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentInfoController extends Controller
{
    /**
     * Display student information page.
     */
    public function show(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        if (! $user->is_active || ! $user->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized access');
        }

        $uuid = $request->get('uuid') ?? $request->get('id');

        if (! $uuid) {
            return redirect()->route('students.index')->with('error', 'Invalid student identifier');
        }

        $studentQuery = Student::with(['user', 'user.profile']);
        
        if (is_numeric($uuid)) {
            $student = $studentQuery->where('student_id', $uuid)->first();
        } else {
            $student = $studentQuery->where('uuid', $uuid)->first();
        }

        if (! $student) {
            return redirect()->route('students.index')->with('error', 'Student not found');
        }

        $studentId = $student->student_id; // Keep studentId for subsequent internal queries

        $studentGroups = StudentGroup::with(['group.course', 'group.teacher'])
            ->where('student_id', $studentId)
            ->get();

        $groups = $studentGroups->map(function ($studentGroup) {
            return $studentGroup->group;
        })->sortByDesc('start_date');

        $groupsWithDetails = [];

        // Prepare batched attendance and session counts per group to avoid N+1 queries
        $groupIds = $groups->pluck('group_id')->toArray();

        $totalSessionsPerGroup = \App\Models\Session::whereIn('group_id', $groupIds)
            ->groupBy('group_id')
            ->selectRaw('group_id, COUNT(*) as sessions_count')
            ->pluck('sessions_count', 'group_id')
            ->toArray();

        $attendanceAgg = \App\Models\Attendance::join('sessions', 'attendance.session_id', '=', 'sessions.session_id')
            ->where('attendance.student_id', $studentId)
            ->whereIn('sessions.group_id', $groupIds)
            ->selectRaw('sessions.group_id as group_id, attendance.status as status, COUNT(*) as cnt')
            ->groupBy('sessions.group_id', 'attendance.status')
            ->get();

        // transform to [group_id => [status => count]]
        $attendanceMap = [];
        foreach ($attendanceAgg as $row) {
            $gid = $row->group_id;
            $status = $row->status;
            $cnt = $row->cnt;
            if (! isset($attendanceMap[$gid])) {
                $attendanceMap[$gid] = [];
            }
            $attendanceMap[$gid][$status] = $cnt;
        }

        foreach ($groups as $group) {
            $sessions = Session::with(['attendances' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            }, 'ratings' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId)->where('rating_type', 'session');
            }])
                ->where('group_id', $group->group_id)
                ->orderBy('session_date', 'DESC')
                ->orderBy('start_time', 'DESC')
                ->get();

            $assignments = Assignment::with(['submissions' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            }])
                ->where('group_id', $group->group_id)
                ->orderBy('due_date', 'DESC')
                ->get();

            $avgRating = Rating::where('student_id', $studentId)
                ->where('group_id', $group->group_id)
                ->where('rating_type', 'monthly')
                ->selectRaw('AVG(rating_value) as avg_rating, COUNT(*) as count')
                ->first();

            // Group-level attendance summary (uses precomputed maps)
            $totalSessions = $totalSessionsPerGroup[$group->group_id] ?? 0;
            $attended = $attendanceMap[$group->group_id]['present'] ?? 0;
            $absent = $attendanceMap[$group->group_id]['absent'] ?? 0;
            $late = $attendanceMap[$group->group_id]['late'] ?? 0;
            $excused = $attendanceMap[$group->group_id]['excused'] ?? 0;
            $groupAttendancePercentage = $totalSessions > 0 ? round(($attended / $totalSessions) * 100, 1) : null;

            $groupsWithDetails[] = [
                'group' => $group,
                'sessions' => $sessions,
                'assignments' => $assignments,
                'avg_rating' => $avgRating,
                'group_attendance' => [
                    'total_sessions' => $totalSessions,
                    'attended' => $attended,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $excused,
                    'percentage' => $groupAttendancePercentage,
                ],
            ];
        }

        // Student-wide summaries
        $quizAttemptsCount = \App\Models\QuizAttempt::where('student_id', $studentId)->count();
        $avgQuizScore = \App\Models\QuizAttempt::where('student_id', $studentId)->avg('score');
        $quizzesTaken = \App\Models\QuizAttempt::where('student_id', $studentId)
            ->distinct('quiz_id')->count('quiz_id');

        // Attendance summary: total records, present count, attendance percentage, last attendance
        $totalAttendance = \App\Models\Attendance::where('student_id', $studentId)->count();
        $presentCount = \App\Models\Attendance::where('student_id', $studentId)->where('status', 'present')->count();
        $attendancePercentage = $totalAttendance > 0 ? round(($presentCount / $totalAttendance) * 100, 1) : null;
        $lastAttendance = \App\Models\Attendance::where('student_id', $studentId)->orderByDesc('recorded_at')->first();

        // Overall rating summary
        $overallRating = \App\Models\Rating::where('student_id', $studentId)
            ->selectRaw('AVG(rating_value) as avg_rating, COUNT(*) as count')
            ->first();

        return view('students.show', [
            'student' => $student,
            'groupsWithDetails' => $groupsWithDetails,
            'quizAttemptsCount' => $quizAttemptsCount,
            'avgQuizScore' => $avgQuizScore,
            'quizzesTaken' => $quizzesTaken,
            'totalAttendance' => $totalAttendance,
            'presentCount' => $presentCount,
            'attendancePercentage' => $attendancePercentage,
            'lastAttendance' => $lastAttendance,
            'overallRating' => $overallRating
        ]);
    }
}
