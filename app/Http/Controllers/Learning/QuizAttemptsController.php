<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Group;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Http\Request;

class QuizAttemptsController extends Controller
{
    /**
     * Display all quiz attempts with nested filtering.
     */
    public function index(Request $request)
    {
        $query = QuizAttempt::query();

        // Get groups for dropdown
        $groups = Group::orderBy('created_at', 'desc')->get();

        // Initialize variables
        $quizzes = collect();
        $selectedGroup = null;
        $selectedQuiz = null;
        $selectedStudent = null;
        $students = collect();

        // Filter by selected group
        if ($request->filled('group_id')) {
            $selectedGroup = Group::find($request->group_id);

            // Get quizzes for this group
            $quizzes = Quiz::whereHas('session.group', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            })->get();

            $query->whereHas('quiz.session.group', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            });
        }

        // Filter by selected quiz (can work independently of group)
        if ($request->filled('quiz_id')) {
            $selectedQuiz = Quiz::find($request->quiz_id);
            $query->where('quiz_id', $request->quiz_id);

            // If group not selected but quiz is, get its group
            if (! $selectedGroup && $selectedQuiz && $selectedQuiz->session) {
                $selectedGroup = $selectedQuiz->session->group;
                $quizzes = Quiz::whereHas('session.group', function ($q) use ($selectedGroup) {
                    $q->where('group_id', $selectedGroup->group_id);
                })->get();
            }
        }

        // Filter by selected student
        if ($request->filled('student_id')) {
            $selectedStudent = Student::find($request->student_id);
            $query->where('student_id', $request->student_id);
        }

        // Apply other filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        // Get students for dropdown (only those with attempts based on filters)
        $studentIds = (clone $query)->distinct()->pluck('student_id');
        $students = Student::whereIn('student_id', $studentIds)
            ->with('user')
            ->get();

        $attempts = $query->with(['student.user', 'quiz.session.group'])
            ->orderBy('start_time', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('quiz_attempts.index', [
            'groups'          => $groups,
            'quizzes'         => $quizzes,
            'attempts'        => $attempts,
            'students'        => $students,
            'selectedGroup'   => $selectedGroup,
            'selectedQuiz'    => $selectedQuiz,
            'selectedStudent' => $selectedStudent,
            'filters'         => $request->only(['group_id', 'quiz_id', 'student_id', 'status', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Display detailed view of a specific attempt.
     */
    /**
     * Display detailed view of a specific attempt.
     */
    /**
     * Display detailed view of a specific attempt.
     */
    public function show($attemptId)
    {
        $attempt = QuizAttempt::with([
            'student.user',
            'quiz.session.group',
            'answers.question.options',
            'answers.option',
        ])->findOrFail($attemptId);

        // Calculate statistics
        $totalQuestions = $attempt->answers->count();
        $correctAnswers = $attempt->answers->where('is_correct', true)->count();
        $wrongAnswers = $attempt->answers->where('is_correct', false)->count();

        // Calculate skipped/unanswered questions
        // Assuming skipped questions are those where option_id is null or answer is empty
        $skippedQuestions = $attempt->answers->filter(function ($answer) {
            return empty($answer->option_id) && empty($answer->answer_text);
        })->count();

        // Or if you have a specific field for skipped questions
        // $skippedQuestions = $attempt->answers->whereNull('option_id')->count();

        return view('quiz_attempts.show', compact(
            'attempt',
            'totalQuestions',
            'correctAnswers',
            'wrongAnswers',
            'skippedQuestions'
        ));
    }

    /**
     * Display student's attempt details.
     */
    /**
     * Display student's attempt details.
     */
    public function showStudentAttempt($studentId, $attemptId)
    {
        $student = Student::with('user')->findOrFail($studentId);
        $attempt = QuizAttempt::where('student_id', $studentId)
            ->where('attempt_id', $attemptId)
            ->with(['quiz', 'answers.question.options', 'answers.option'])
            ->firstOrFail();

        // Calculate statistics
        $totalQuestions = $attempt->answers->count();
        $correctAnswers = $attempt->answers->where('is_correct', true)->count();
        $wrongAnswers = $attempt->answers->where('is_correct', false)->count();

        return view('quiz_attempts.student_attempt', compact(
            'student',
            'attempt',
            'totalQuestions',
            'correctAnswers',
            'wrongAnswers'
        ));
    }

    /**
     * Delete a quiz attempt.
     */
    public function destroy($attemptId)
    {
        $attempt = QuizAttempt::findOrFail($attemptId);
        $attempt->delete();

        return redirect()->route('quiz.attempts.index')
            ->with('success', 'تم حذف محاولة الكويز بنجاح');
    }
}
