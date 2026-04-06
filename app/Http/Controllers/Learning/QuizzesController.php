<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Option;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class QuizzesController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $teacherId = $user->teacher->teacher_id ?? null;

        if (!$isAdmin && !$teacherId) {
            abort(403, 'Unauthorized');
        }

        $search = $request->get('search', '');

        $query = Quiz::with(['session.group.course', 'creator'])
            ->when(!$isAdmin, function($q) use ($user, $teacherId) {
                // If not admin, show quizzes created by them OR in groups they teach
                $q->where(function($sub) use ($user, $teacherId) {
                    $sub->where('created_by', $user->id)
                        ->orWhereHas('session.group', function ($g) use ($teacherId) {
                            $g->where('teacher_id', $teacherId);
                        });
                });
            })
            ->when($search, function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });

        $quizzes = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('quizzes.index', [
            'quizzes' => $quizzes,
            'filters' => ['search' => $search]
        ]);
    }

    public function create(Request $request)

    {
        $session_id = $request->query('session_id');
        $session = Session::with('group')->where('session_id', $session_id)->orWhere('uuid', $session_id)->first();

        if (! $session) {
            abort(404, 'Session not found');
        }

        // Check if teacher owns this group (if not admin)
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        return view('quizzes.create', compact('session'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'session_id' => 'required', // will be validated after looking up by UUID if needed
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'is_public' => 'nullable|boolean',
        ]);

        $session = Session::with('group')->where('session_id', $request->session_id)->orWhere('uuid', $request->session_id)->first();
        if ($session) {
            // merge the actual integer session_id so that validation passes if UUID was submitted
            $request->merge(['session_id' => $session->session_id]);
        }

        if (! $session) {
            abort(404, 'Session not found');
        }

        // Check if teacher owns this group (if not admin)
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        $quiz = Quiz::create([
            'session_id'   => $request->session_id,
            'title'        => $request->title,
            'description'  => $request->description,
            'time_limit'   => $request->time_limit,
            'max_attempts' => $request->max_attempts ?? 1,
            'is_active'    => true,
            'is_public'    => $request->is_public ?? false,
            'created_by'   => Auth::id(),
        ]);

        return redirect()->route('quizzes.edit', $quiz->uuid ?? $quiz->quiz_id)->with('success', 'Quiz created successfully! Now add questions.');
    }

    public function show($quiz_id)
    {
        $quiz = Quiz::with('questions', 'session.group')->findOrFail($quiz_id);

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        return view('quizzes.show', compact('quiz'));
    }

    public function edit($quiz_id)
    {
        $quiz = Quiz::with('questions.options', 'session.group')->findOrFail($quiz_id);

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        return view('quizzes.edit', compact('quiz'));
    }

    public function update(Request $request, $quiz_id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
        ]);

        $quiz = Quiz::findOrFail($quiz_id);

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        $quiz->update([
            'title' => $request->title,
            'description' => $request->description,
            'time_limit' => $request->time_limit,
            'max_attempts' => $request->max_attempts,
            'is_active' => $request->has('is_active'),
            'is_public' => $request->has('is_public') || $request->is_public,
        ]);

        return back()->with('success', 'Quiz updated successfully!');
    }

    public function storeQuestion(Request $request, $quiz_id)
    {
        // تحديد القواعد بناءً على نوع السؤال
        $validationRules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:single_choice,multiple_choice',
            'points' => 'required|integer|min:1',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'question_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // إضافة القواعد الخاصة بالإجابات الصحيحة بناءً على نوع السؤال
        if ($request->question_type === 'single_choice') {
            $validationRules['correct_option'] = 'required|integer';
        } else {
            $validationRules['correct_options'] = 'required|array|min:1';
            $validationRules['correct_options.*'] = 'integer';
        }

        $request->validate($validationRules);

        $quiz = Quiz::findOrFail($quiz_id);

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        // Handle file upload
        $image_path = null;
        if ($request->hasFile('question_image')) {
            $file = $request->file('question_image');
            $filename = time().'_'.rand(1000, 9999).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('quiz_images'), $filename);
            $image_path = 'quiz_images/'.$filename;
        }

        // خزن مباشرة عند إنشاء السؤال
        $question = QuizQuestion::create([
            'quiz_id' => $quiz_id,
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'points' => $request->points,
            'image_path' => $image_path, // هنا فقط
        ]);

        // Create options
        foreach ($request->options as $index => $option_text) {
            if (! empty(trim($option_text))) {
                // تحديد إذا كان الخيار صحيحاً بناءً على نوع السؤال
                $is_correct = false;

                if ($request->question_type === 'single_choice') {
                    // للسؤال الفردي: correct_option هو رقم الفهرس
                    $is_correct = ($request->correct_option == $index) ? 1 : 0;
                } else {
                    // للسؤال المتعدد: correct_options هو array من الفهارس
                    $is_correct = in_array($index, $request->correct_options) ? 1 : 0;
                }

                Option::create([
                    'question_id' => $question->question_id,
                    'option_text' => trim($option_text),
                    'is_correct' => $is_correct,
                ]);
            }
        }

        return back()->with('success', 'Question added successfully!');
    }

    public function bulkStore(Request $request, $quiz_id)
    {
        $request->validate([
            'questions_text' => 'required|string',
        ]);

        $quiz = Quiz::findOrFail($quiz_id);

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        $text = $request->questions_text;
        // Split by blocks (one or more empty lines)
        $blocks = preg_split('/\n\s*\n/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $count = 0;
        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines);

            if (count($lines) < 2) continue; // Need at least question + 1 option

            $questionText = array_shift($lines);
            
            // Re-detect correct options and clean up text
            $correctIndices = [];
            $cleanOptions = [];
            foreach ($lines as $index => $opt) {
                if (str_ends_with($opt, '*')) {
                    $correctIndices[] = $index;
                    $cleanOptions[] = rtrim($opt, '*');
                } else {
                    $cleanOptions[] = $opt;
                }
            }

            if (empty($cleanOptions)) continue;

            $questionType = (count($correctIndices) > 1) ? 'multiple_choice' : 'single_choice';

            $question = QuizQuestion::create([
                'quiz_id' => $quiz_id,
                'question_text' => $questionText,
                'question_type' => $questionType,
                'points' => 1,
            ]);

            foreach ($cleanOptions as $index => $optText) {
                Option::create([
                    'question_id' => $question->question_id,
                    'option_text' => $optText,
                    'is_correct' => in_array($index, $correctIndices),
                ]);
            }
            $count++;
        }

        return back()->with('success', "$count questions imported successfully!");
    }

    public function updateQuestion(Request $request, $quiz_id, $question_id)
    {
        // تحديد القواعد بناءً على نوع السؤال
        $validationRules = [
            'question_text' => 'required|string',
            'question_type' => 'required|in:single_choice,multiple_choice',
            'points' => 'required|integer|min:1',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'question_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // إضافة القواعد الخاصة بالإجابات الصحيحة بناءً على نوع السؤال
        if ($request->question_type === 'single_choice') {
            $validationRules['correct_option'] = 'required|integer';
        } else {
            $validationRules['correct_options'] = 'required|array|min:1';
            $validationRules['correct_options.*'] = 'integer';
        }

        $request->validate($validationRules);

        $quiz = Quiz::findOrFail($quiz_id);
        $question = QuizQuestion::where('quiz_id', $quiz_id)->where('question_id', $question_id)->firstOrFail();

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        // Handle file upload
        $image_path = $question->image_path;
        if ($request->hasFile('question_image')) {
            // Delete old image if exists
            if ($image_path && File::exists(public_path($image_path))) {
                File::delete(public_path($image_path));
            }

            $file = $request->file('question_image');
            $filename = time().'_'.rand(1000, 9999).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('quiz_images'), $filename);
            $image_path = 'quiz_images/'.$filename;
        }

        $question->update([
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'points' => $request->points,
            'image_path' => $image_path,
        ]);

        // Manage options: Update existing, create new, and delete old safely
        $existingOptionIds = $question->options->pluck('option_id')->toArray();
        $submittedOptionIds = array_filter($request->option_ids ?? []);
        
        // 1. Delete options that aren't in the submitted list
        // (Note: This might still fail if there are answers, but it's the correct intent)
        Option::where('question_id', $question->question_id)
            ->whereNotIn('option_id', $submittedOptionIds)
            ->delete();

        foreach ($request->options as $index => $option_text) {
            if (! empty(trim($option_text))) {
                $is_correct = false;
                if ($request->question_type === 'single_choice') {
                    $is_correct = ($request->correct_option == $index) ? 1 : 0;
                } else {
                    $is_correct = in_array($index, $request->correct_options) ? 1 : 0;
                }

                $optionId = $request->option_ids[$index] ?? null;

                if ($optionId && in_array($optionId, $existingOptionIds)) {
                    // Update existing
                    Option::where('option_id', $optionId)->update([
                        'option_text' => trim($option_text),
                        'is_correct' => $is_correct,
                    ]);
                } else {
                    // Create new
                    Option::create([
                        'question_id' => $question->question_id,
                        'option_text' => trim($option_text),
                        'is_correct' => $is_correct,
                    ]);
                }
            }
        }

        return back()->with('success', 'Question updated successfully!');
    }

    public function destroyQuestion($quiz_id, $question_id)
    {
        $quiz = Quiz::findOrFail($quiz_id);
        $question = QuizQuestion::where('quiz_id', $quiz_id)->where('question_id', $question_id)->firstOrFail();

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2 && (! $user->teacher || $quiz->session->group->teacher_id != $user->teacher->teacher_id)) {
            abort(403, 'Unauthorized');
        }

        // Delete image if exists
        if ($question->image_path && File::exists(public_path($question->image_path))) {
            File::delete(public_path($question->image_path));
        }

        $question->delete();

        return back()->with('success', 'Question deleted successfully!');
    }

    /**
     * Display quiz attempts for a specific quiz.
     */
    public function showAttempts($quizId)
    {
        $quiz = Quiz::with(['attempts.student.user'])->findOrFail($quizId);

        $attempts = $quiz->attempts()
            ->with(['student.user', 'answers'])
            ->orderBy('start_time', 'desc')
            ->paginate(20);

        return view('quizzes.attempts', compact('quiz', 'attempts'));
    }

    public function fetch(Request $request)
    {
        $query = $request->query('query');
        $user = Auth::user();

        $quizzes = Quiz::where(function($q) use ($user) {
                $q->where('created_by', '=', $user->id)
                  ->orWhere('is_public', '=', true);
            })
            ->when($query, function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%");
            })
            ->with(['session.group'])
            ->limit(10)
            ->get();

        return response()->json($quizzes);
    }

    public function clone(Request $request)
    {
        $request->validate([
            'source_quiz_id' => 'required',
            'target_session_id' => 'required',
        ]);

        $sourceQuiz = Quiz::with('questions.options')->findOrFail($request->source_quiz_id);
        $targetSession = Session::findOrFail($request->target_session_id);

        // Check permissions
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->role_id == 2) {
            if (!$user->teacher || $targetSession->group->teacher_id != $user->teacher->teacher_id) {
                abort(403, 'Unauthorized');
            }
        }

        $newQuiz = $sourceQuiz->replicate(['uuid']);
        $newQuiz->uuid = (string) \Illuminate\Support\Str::uuid();
        $newQuiz->session_id = $targetSession->session_id;
        $newQuiz->title = $sourceQuiz->title . ' (Copy)';
        $newQuiz->created_by = Auth::id();
        $newQuiz->save();

        foreach ($sourceQuiz->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->quiz_id = $newQuiz->quiz_id;
            $newQuestion->save();

            foreach ($question->options as $option) {
                $newOption = $option->replicate();
                $newOption->question_id = $newQuestion->question_id;
                $newOption->save();
            }
        }

        return redirect()->route('quizzes.edit', $newQuiz->uuid ?? $newQuiz->quiz_id)->with('success', 'Quiz cloned successfully! You can now edit it.');
    }
}
