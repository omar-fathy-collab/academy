<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Option;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentQuizController extends Controller
{
    public function takeQuiz(Request $request, $quizId)
    {
        $quiz = Quiz::with('questions.options', 'session.group')->findOrFail($quizId);

        // Check if quiz is active
        if (! $quiz->is_active) {
            abort(404, 'Quiz not found');
        }

        // Get current user and student
        $user = Auth::user();
        if (! $user->isStudent()) {
            abort(403, 'Unauthorized');
        }

        $student = Student::where('user_id', $user->id)->firstOrFail();

        // Validate student access to this quiz
        $hasAccess = DB::table('student_group')
            ->join('sessions', 'student_group.group_id', '=', 'sessions.group_id')
            ->where('student_group.student_id', $student->student_id)
            ->where('sessions.session_id', $quiz->session_id)
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Unauthorized');
        }

        // Handle POST request (quiz submission)
        if ($request->isMethod('post') && $request->has('submit_quiz')) {
            return $this->processQuizSubmission($request, $quiz, $student);
        }

        // Get completed attempts
        $completedAttempts = QuizAttempt::where('quiz_id', $quizId)
            ->where('student_id', $student->student_id)
            ->where('status', 'completed')
            ->count();

        // Check max attempts
        if ($completedAttempts >= $quiz->max_attempts) {
            $latestAttempt = QuizAttempt::where('quiz_id', $quizId)
                ->where('student_id', $student->student_id)
                ->orderBy('start_time', 'desc')
                ->first();

            return redirect()->route('student.quiz.results', ['attempt_id' => $latestAttempt->attempt_id]);
        }

        // Get or create current attempt
        $currentAttempt = QuizAttempt::where('quiz_id', $quizId)
            ->where('student_id', $student->student_id)
            ->where('status', 'in_progress')
            ->orderBy('start_time', 'desc')
            ->first();

        if (! $currentAttempt) {
            $currentAttempt = QuizAttempt::create([
                'quiz_id' => $quizId,
                'student_id' => $student->student_id,
                'start_time' => now(),
                'status' => 'in_progress',
            ]);
        }

        if ($quiz->questions->isEmpty()) {
            Log::warning("Quiz ID $quizId loaded with 0 questions for Student ID {$student->student_id}");
        }

        return view('student.quizzes.take', compact('quiz', 'currentAttempt', 'student'));
    }

    public function quizResults(Request $request)
    {
        $attemptId = $request->query('attempt_id');
        if (! $attemptId) {
            abort(404, 'Attempt ID is required');
        }

        $user = Auth::user();
        if (! $user->isStudent()) {
            abort(403, 'Unauthorized');
        }
        $student = Student::query()->where('user_id', $user->id)->firstOrFail();

        $attempt = QuizAttempt::with('quiz.questions.options', 'student')
            ->where('student_id', $student->student_id)
            ->findOrFail($attemptId);

        // جلب جميع الإجابات
        $allAnswers = DB::table('quiz_answers')
            ->where('attempt_id', $attemptId)
            ->get();

        // تجميع الإجابات حسب السؤال
        $answersByQuestion = $allAnswers->groupBy('question_id');

        // حساب عدد الأسئلة الصحيحة والنتائج التفصيلية لكل سؤال
        $correctQuestions = 0;
        $pointsByQuestion = [];
        foreach ($attempt->quiz->questions as $question) {
            $questionAnswers = $answersByQuestion->get($question->question_id, collect());
            
            // حساب إجمالي النقاط المكتسبة لهذا السؤال
            $pointsEarned = $questionAnswers->sum('points_earned');
            $pointsByQuestion[$question->question_id] = $pointsEarned;

            $hasIncorrect = $questionAnswers->where('is_correct', 0)->count() > 0;
            $correctOptionsCount = $question->options->where('is_correct', 1)->count();
            $correctSelectedCount = $questionAnswers->where('is_correct', 1)->count();
            
            // يعتبر السؤال صحيحاً فقط إذا كانت النقاط المكتسبة تساوي نقاط السؤال كاملة (بدقة عالية لتجنب مشاكل الفواصل)
            $isQuestionCorrect = abs($pointsEarned - $question->points) < 0.01;


            if ($isQuestionCorrect) {
                $correctQuestions++;
            }
        }

        return view('student.quizzes.results', [
            'attempt'           => $attempt,
            'correctQuestions'  => $correctQuestions,
            'answersByQuestion' => $answersByQuestion,
            'pointsByQuestion'  => $pointsByQuestion,
        ]);
    }

    private function processQuizSubmission(Request $request, Quiz $quiz, Student $student)
    {
        // البحث عن المحاولة الحالية وتحديثها
        $attempt = QuizAttempt::where('quiz_id', $quiz->quiz_id)
            ->where('student_id', $student->student_id)
            ->where('status', 'in_progress')
            ->first();

        if (! $attempt) {
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->quiz_id,
                'student_id' => $student->student_id,
                'start_time' => now(),
                'end_time' => now(),
                'status' => 'completed',
            ]);
        } else {
            $attempt->update([
                'end_time' => now(),
                'status' => 'completed',
            ]);
        }

        $totalScore = 0;
        $totalPoints = 0;

        // مسح أي إجابات سابقة لهذه المحاولة لضمان عدم تكرار النقاط
        DB::table('quiz_answers')->where('attempt_id', $attempt->attempt_id)->delete();

        foreach ($quiz->questions as $question) {
            $totalPoints += $question->points;

            $pointsEarned = 0;

            if ($question->question_type == 'single_choice') {
                $optionId = $request->input('question_'.$question->question_id);
                if ($optionId) {
                    $option = Option::find($optionId);
                    if ($option && $option->question_id == $question->question_id) {
                        $pointsEarned = $option->is_correct ? $question->points : 0;
                        $totalScore += $pointsEarned;

                        \App\Models\QuizAnswer::create([
                            'attempt_id' => $attempt->attempt_id,
                            'question_id' => $question->question_id,
                            'option_id' => $optionId,
                            'is_correct' => $option->is_correct,
                            'points_earned' => $pointsEarned,
                        ]);
                    }
                }
            } elseif ($question->question_type == 'multiple_choice') {
                $optionIds = $request->input('question_'.$question->question_id, []);

                // الحصول على جميع الخيارات الصحيحة لهذا السؤال
                $correctOptionIds = $question->options->where('is_correct', 1)->pluck('option_id')->toArray();
                $totalCorrectOptions = count($correctOptionIds);

                // حساب الإجابات الصحيحة المختارة والإجابات الخاطئة المختارة
                $correctSelected = 0;
                $incorrectSelected = 0;

                foreach ($optionIds as $optionId) {
                    $option = Option::find($optionId);
                    if ($option && $option->question_id == $question->question_id) {
                        if ($option->is_correct) {
                            $correctSelected++;
                        } else {
                            $incorrectSelected++;
                        }
                    }
                }

                // ملاحظة: تم مسح الإجابات في بداية الدالة لكل المحاولة


                // حساب النقاط بناءً على القواعد الصحيحة
                if ($incorrectSelected > 0) {
                    // إذا اختار أي إجابة خاطئة - صفر نقطة
                    $pointsEarned = 0;
                } elseif ($correctSelected == $totalCorrectOptions) {
                    // إذا اختار جميع الإجابات الصحيحة - النقاط كاملة
                    $pointsEarned = $question->points;
                } else {
                    // إذا لم يختر جميع الإجابات الصحيحة - نقاط جزئية
                    $pointsEarned = ($correctSelected / $totalCorrectOptions) * $question->points;
                }

                $totalScore += $pointsEarned;

                // حفظ جميع الإجابات المختارة في قاعدة البيانات
                foreach ($optionIds as $optionId) {
                    $option = Option::find($optionId);
                    if ($option && $option->question_id == $question->question_id) {
                        $isCorrect = $option->is_correct;

                        \App\Models\QuizAnswer::create([
                            'attempt_id' => $attempt->attempt_id,
                            'question_id' => $question->question_id,
                            'option_id' => $optionId,
                            'is_correct' => $isCorrect,
                            'points_earned' => $isCorrect ? ($pointsEarned / max($correctSelected, 1)) : 0,
                        ]);
                    }
                }
            }
        }

        // حساب النسبة المئوية
        $percentage = $totalPoints > 0 ? ($totalScore / $totalPoints) * 100 : 0;

        $attempt->update([
            'score' => $percentage,
        ]);

        // مسح مؤقت التخزين المحلي
        if ($quiz->time_limit > 0) {
            echo "<script>localStorage.removeItem('quizTimer_{$quiz->quiz_id}');</script>";
        }

        return redirect()->route('student.quiz.results', ['attempt_id' => $attempt->attempt_id]);
    }

    public function myQuizzes()
    {
        /** @var User $user */
        $user = Auth::user();
        if (! ($user->isStudent() || $user->isAdmin())) {
            abort(403, 'Unauthorized');
        }

        $student = Student::where('user_id', $user->id)->first();
        if (! $student && ! $user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        // Get all quizzes that the student has access to through their groups
        $groupIds = $student ? DB::table('student_group')->where('student_id', $student->student_id)->pluck('group_id')->toArray() : [];
        
        $quizzes = Quiz::with(['session.group', 'questions'])
            ->join('sessions', 'quizzes.session_id', '=', 'sessions.session_id')
            ->whereIn('sessions.group_id', $groupIds)
            ->where('quizzes.is_active', 1)
            ->select('quizzes.*')
            ->distinct()
            ->get();

        // Get attempts for each quiz
        foreach ($quizzes as $quiz) {
            $quiz->attempts = QuizAttempt::where('quiz_id', $quiz->quiz_id)
                ->where('student_id', $student->student_id)
                ->orderBy('start_time', 'desc')
                ->get();

            $quiz->completed_attempts = $quiz->attempts->where('status', 'completed')->count();
            $quiz->latest_attempt = $quiz->attempts->first();
        }

        return view('student.quizzes.index', compact('quizzes', 'student'));
    }
}
