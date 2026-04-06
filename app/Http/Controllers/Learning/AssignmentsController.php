<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Student\StudentAssignmentController;

class AssignmentsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $teacherId = $user->teacher->teacher_id ?? null;
        $isAdmin = $user->isAdmin();

        if (! $teacherId && ! $isAdmin) {
            return view('assignments.index', ['assignments' => collect([]), 'filters' => []]);
        }

        $search = $request->get('search', '');

        $query = DB::table('assignments as a')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->join('courses as c', 'g.course_id', '=', 'c.course_id')
            ->leftJoin('sessions as s', 'a.session_id', '=', 's.session_id')
            ->leftJoin('assignment_submissions as sub', 'a.assignment_id', '=', 'sub.assignment_id');
        
        if (!$isAdmin) {
            $query->where('g.teacher_id', $teacherId);
        }

        $query->select(
                'a.assignment_id',
                'a.group_id',
                'a.session_id',
                'a.title',
                'a.description',
                'a.teacher_file',
                'a.due_date',
                'a.max_score as assignment_max_score',
                'a.created_at',
                'g.group_name',
                'c.course_name',
                's.topic as session_topic',
                's.session_date',
                DB::raw('COUNT(sub.submission_id) as submissions_count'),
                DB::raw('COALESCE(AVG(sub.score), 0) as avg_score'),
                DB::raw('COUNT(CASE WHEN sub.score IS NOT NULL THEN 1 END) as graded_count')
            )
            ->groupBy(
                'a.assignment_id',
                'a.group_id',
                'a.session_id',
                'a.title',
                'a.description',
                'a.teacher_file',
                'a.due_date',
                'a.max_score',
                'a.created_at',
                'g.group_name',
                'c.course_name',
                's.topic',
                's.session_date'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('a.title', 'like', "%{$search}%")
                    ->orWhere('a.description', 'like', "%{$search}%")
                    ->orWhere('g.group_name', 'like', "%{$search}%")
                    ->orWhere('c.course_name', 'like', "%{$search}%");
            });
        }

        $assignments = $query->orderBy('a.due_date', 'desc')->paginate(15)->withQueryString();

        return view('assignments.index', [
            'assignments' => $assignments,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Fetch assignments for AJAX requests (kept for backward compat).
     */
    public function fetchAssignments(Request $request)
    {
        try {
            $user = auth()->user();
            $teacherId = $user->teacher->teacher_id ?? null;
            $isAdmin = $user->isAdmin();

            if (! $teacherId && ! $isAdmin) {
                return response()->json(['error' => 'Teacher profile not found.'], 403);
            }

            $query = DB::table('assignments as a')
                ->join('groups as g', 'a.group_id', '=', 'g.group_id')
                ->join('courses as c', 'g.course_id', '=', 'c.course_id')
                ->leftJoin('sessions as s', 'a.session_id', '=', 's.session_id')
                ->leftJoin('assignment_submissions as sub', 'a.assignment_id', '=', 'sub.assignment_id');
            
            if (!$isAdmin) {
                $query->where('g.teacher_id', $teacherId);
            }

            $query->select(
                    'a.assignment_id',
                    'a.group_id',
                    'a.session_id',
                    'a.title',
                    'a.description',
                    'a.teacher_file',
                    'a.user_file',
                    'a.due_date',
                    'a.max_score as assignment_max_score',
                    'a.created_by',
                    'a.created_at',
                    'g.group_name',
                    'c.course_name',
                    's.topic as session_topic',
                    's.session_date',
                    's.start_time',
                    's.end_time',
                    DB::raw('COUNT(sub.submission_id) as submissions_count'),
                    DB::raw('COALESCE(AVG(sub.score), 0) as avg_score'),
                    DB::raw('COALESCE(MAX(sub.score), 0) as max_submission_score'),
                    DB::raw('COALESCE(MIN(sub.score), 0) as min_submission_score'),
                    DB::raw('COUNT(CASE WHEN sub.score IS NOT NULL THEN 1 END) as graded_count')
                )
                ->groupBy(
                    'a.assignment_id', 'a.group_id', 'a.session_id', 'a.title', 'a.description',
                    'a.teacher_file', 'a.user_file', 'a.due_date', 'a.max_score', 'a.created_by',
                    'a.created_at', 'g.group_name', 'c.course_name', 's.topic', 's.session_date',
                    's.start_time', 's.end_time'
                );

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('a.title', 'like', "%{$search}%")
                        ->orWhere('a.description', 'like', "%{$search}%")
                        ->orWhere('g.group_name', 'like', "%{$search}%")
                        ->orWhere('c.course_name', 'like', "%{$search}%");
                });
            }

            $assignments = $query->orderBy('a.due_date', 'desc')->paginate(15);

            return response()->json([
                'assignments' => $assignments->items(),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('Fetch assignments error: '.$e->getMessage());

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function create(Request $request)
    {
        $groupId = $request->query('group_id');
        $sessionId = $request->query('session_id');

        $group = DB::table('groups')->where('group_id', $groupId)->orWhere('uuid', $groupId)->first();
        $session = DB::table('sessions')->where('session_id', $sessionId)->orWhere('uuid', $sessionId)->first();

        if (! $group || ! $session) {
            return redirect()->back()->with('error', 'Invalid group or session.');
        }

        // Check if teacher owns this group
        if (auth()->user()->isTeacher() && (! auth()->user()->teacher || $group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        return view('assignments.create', [
            'group' => $group,
            'session' => $session,
        ]);
    }

    public function store(Request $request)
    {
        $group = DB::table('groups')->where('group_id', $request->group_id)->orWhere('uuid', $request->group_id)->first();
        $session = null;
        if ($request->session_id) {
            $session = DB::table('sessions')->where('session_id', $request->session_id)->orWhere('uuid', $request->session_id)->first();
        }

        if ($group) {
            $request->merge(['group_id' => $group->group_id]);
        }
        if ($session) {
            $request->merge(['session_id' => $session->session_id]);
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date|after_or_equal:today',
            'group_id' => 'required|exists:groups,group_id',
            'session_id' => 'nullable|exists:sessions,session_id',
            'teacher_file' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
        ]);

        // Check if teacher owns this group
        if (auth()->user()->isTeacher() && (! auth()->user()->teacher || $group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        // Handle file upload
        $teacher_file_path = null;
        if ($request->hasFile('teacher_file')) {
            $file = $request->file('teacher_file');
            $filename = time().'_'.rand(1000, 9999).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assignments'), $filename);
            $teacher_file_path = 'assignments/'.$filename;
        }

        \App\Models\Assignment::create([
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'group_id' => $request->group_id,
            'session_id' => $request->session_id,
            'teacher_file' => $teacher_file_path,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('sessions.show', $request->session_id)->with('success', 'Assignment created successfully!');
    }

    public function show($id)
    {
        $assignment = DB::table('assignments as a')
            ->join('groups as g', 'a.group_id', '=', 'g.group_id')
            ->join('courses as c', 'g.course_id', '=', 'c.course_id')
            ->leftJoin('sessions as s', 'a.session_id', '=', 's.session_id')
            ->leftJoin('assignment_submissions as sub', 'a.assignment_id', '=', 'sub.assignment_id')
            ->where('a.assignment_id', $id)
            ->select(
                'a.*',
                'a.max_score as assignment_max_score',
                'g.group_name',
                'c.course_name',
                's.topic as session_topic',
                's.session_date',
                DB::raw('COUNT(sub.submission_id) as submissions_count'),
                DB::raw('COALESCE(AVG(sub.score), 0) as avg_score'),
                DB::raw('COUNT(CASE WHEN sub.score IS NOT NULL THEN 1 END) as graded_count')
            )
            ->groupBy(
                'a.assignment_id', 'a.group_id', 'a.session_id', 'a.title', 'a.description',
                'a.teacher_file', 'a.user_file', 'a.due_date', 'a.max_score', 'a.created_by',
                'a.created_at', 'g.group_name', 'c.course_name', 's.topic', 's.session_date'
            )
            ->first();

        if (! $assignment) {
            return redirect()->back()->with('error', 'Assignment not found.');
        }

        $submissions = DB::table('assignment_submissions as sub')
            ->join('students as s', 'sub.student_id', '=', 's.student_id')
            ->leftJoin('users as u', 's.user_id', '=', 'u.id')
            ->where('sub.assignment_id', $id)
            ->select('sub.*', 's.student_name', 'u.email')
            ->orderBy('sub.submission_date', 'desc')
            ->get();

        return view('assignments.show', [
            'assignment' => $assignment,
            'submissions' => $submissions,
        ]);
    }

    public function grade(Request $request)
    {
        $assignmentId = $request->query('assignment_id');
        $studentId = $request->query('student_id');

        $submission = DB::table('assignment_submissions')
            ->where('assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->first();

        if (! $submission) {
            return redirect()->back()->with('error', 'Submission not found.');
        }

        $assignment = DB::table('assignments')->where('assignment_id', $assignmentId)->first();
        $student = DB::table('students')->where('student_id', $studentId)->first();

        return redirect()->route('assignments.show', $assignmentId);
    }

    public function viewSubmissions(Request $request)
    {
        $assignmentId = $request->query('assignment_id') ?? $request->route('assignment');
        return redirect()->route('assignments.show', $assignmentId);
    }

    public function gradeAssignment(Request $request)
    {
        return $this->grade($request);
    }

    public function gradeSubmission(Request $request)
    {
        $request->validate([
            'submission_id' => 'required|integer',
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $submission = DB::table('assignment_submissions')
            ->where('submission_id', $request->submission_id)
            ->first();

        if (! $submission) {
            return redirect()->back()->with('error', 'Submission not found.');
        }

        // Check if teacher owns this assignment's group
        $assignment = DB::table('assignments')
            ->join('groups', 'assignments.group_id', '=', 'groups.group_id')
            ->join('teachers', 'groups.teacher_id', '=', 'teachers.teacher_id')
            ->where('assignments.assignment_id', $submission->assignment_id)
            ->first();

        if (! $assignment || auth()->user()->role_id == 2 && auth()->id() != $assignment->user_id) {
            return redirect()->back()->with('error', 'Unauthorized');
        }

        DB::table('assignment_submissions')
            ->where('submission_id', $request->submission_id)
            ->update([
                'score' => $request->score,
                'feedback' => $request->feedback,
                'graded_at' => now(),
                'graded_by' => auth()->id(),
            ]);

        return redirect()->back()->with('success', 'Submission graded successfully!');
    }

    public function edit($id)
    {
        $assignment = DB::table('assignments')->where('assignment_id', $id)->first();

        if (! $assignment) {
            return redirect()->back()->with('error', 'Assignment not found.');
        }

        // Check if teacher owns this group
        $group = DB::table('groups')->where('group_id', $assignment->group_id)->first();
        if (auth()->user()->isTeacher() && (! auth()->user()->teacher || $group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        return view('assignments.edit', [
            'assignment' => $assignment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'teacher_file' => 'nullable|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
        ]);

        $assignment = \App\Models\Assignment::findOrFail($id);
        
        $group = $assignment->group;
        if (auth()->user()->isTeacher() && (! auth()->user()->teacher || $group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        $updateData = [
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
        ];

        // Handle file upload
        if ($request->hasFile('teacher_file')) {
            // Delete old file
            if ($assignment->teacher_file && file_exists(public_path($assignment->teacher_file))) {
                unlink(public_path($assignment->teacher_file));
            }

            $file = $request->file('teacher_file');
            $filename = time().'_'.rand(1000, 9999).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assignments'), $filename);
            $updateData['teacher_file'] = 'assignments/'.$filename;
        }

        $assignment->update($updateData);

        return redirect()->route('sessions.show', $assignment->session_id)->with('success', 'Assignment updated successfully!');
    }

    public function destroy($id)
    {
        $assignment = \App\Models\Assignment::findOrFail($id);
        
        $group = $assignment->group;
        if (auth()->user()->isTeacher() && (! auth()->user()->teacher || $group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        DB::beginTransaction();
        try {
            // Delete submissions
            $assignment->submissions()->delete();

            // Delete file
            if ($assignment->teacher_file && file_exists(public_path($assignment->teacher_file))) {
                unlink(public_path($assignment->teacher_file));
            }

            // Delete assignment
            $sessionId = $assignment->session_id;
            $assignment->delete();

            DB::commit();

            return redirect()->route('sessions.show', $sessionId)->with('success', 'Assignment deleted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Error deleting assignment: '.$e->getMessage());
        }
    }

    public function submit(Request $request, $assignment)
    {
        return app(StudentAssignmentController::class)->processSubmission($request);
    }
}
