@extends('layouts.authenticated')

@section('title', 'Edit Quiz: ' . $quiz->title)

@section('content')
<div x-data="{
    is_active: {{ $quiz->is_active ? 'true' : 'false' }},
    is_public: {{ $quiz->is_public ? 'true' : 'false' }},
    
    showQuestionModal: false,
    showBulkModal: false,
    modalTitle: 'Add New Question',
    submitUrl: '{{ route('quizzes.questions.store', $quiz->quiz_id) }}',
    method: 'POST',
    
    questionId: null,
    questionText: '',
    questionType: 'single_choice',
    points: 1,
    options: ['', '', '', ''],
    correctOption: 0,
    correctOptions: [],
    
    openAddModal() {
        this.resetForm();
        this.modalTitle = 'Add New Question';
        this.submitUrl = '{{ route('quizzes.questions.store', $quiz->quiz_id) }}';
        this.method = 'POST';
        this.showQuestionModal = true;
    },
    
    openEditModal(q) {
        this.resetForm();
        this.questionId = q.question_id;
        this.questionText = q.question_text;
        this.questionType = q.question_type;
        this.points = q.points;
        this.options = q.options.map(o => o.option_text);
        
        if (this.questionType === 'single_choice') {
            this.correctOption = q.options.findIndex(o => o.is_correct);
        } else {
            this.correctOptions = q.options.reduce((acc, o, i) => {
                if (o.is_correct) acc.push(i);
                return acc;
            }, []);
        }
        
        this.modalTitle = 'Edit Question';
        this.submitUrl = `/quizzes/{{ $quiz->quiz_id }}/questions/${q.question_id}`;
        this.method = 'PUT';
        this.showQuestionModal = true;
    },
    
    resetForm() {
        this.questionId = null;
        this.questionText = '';
        this.questionType = 'single_choice';
        this.points = 1;
        this.options = ['', '', '', ''];
        this.correctOption = 0;
        this.correctOptions = [];
    },
    
    addOption() {
        this.options.push('');
    },
    
    removeOption(index) {
        if (this.options.length > 2) {
            this.options.splice(index, 1);
            if (this.correctOption === index) this.correctOption = 0;
            this.correctOptions = this.correctOptions.filter(i => i !== index).map(i => i > index ? i - 1 : i);
        }
    }
}">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div class="d-flex align-items-center">
            <a href="{{ route('sessions.show', $quiz->session->uuid ?? $quiz->session_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h3 fw-bold mb-1 theme-text-main">Edit Quiz: {{ $quiz->title }}</h1>
                <p class="text-muted small mb-0">Group: {{ $quiz->session->group->group_name }} | Session: {{ $quiz->session->topic }}</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('quizzes.attempts', $quiz->quiz_id) }}" class="btn btn-outline-info rounded-pill px-4">
                <i class="fas fa-chart-bar me-2"></i> Student Results
            </a>
            <button @click="showBulkModal = true" class="btn btn-outline-primary rounded-pill px-4 shadow-sm border theme-border">
                <i class="fas fa-file-import me-2"></i> Bulk Import
            </button>
            <button @click="openAddModal()" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-plus me-2"></i> Add Question
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Settings Sidebar -->
        <div class="col-lg-4 order-lg-2">
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 position-sticky" style="top: 2rem;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 theme-text-main"><i class="fas fa-cog me-2 text-primary"></i>Quiz Settings</h5>
                    <form action="{{ route('quizzes.update', $quiz->quiz_id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Quiz Title</label>
                            <input type="text" name="title" class="form-control theme-badge-bg border-0" value="{{ $quiz->title }}" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small">Duration (mins)</label>
                                <input type="number" name="time_limit" class="form-control theme-badge-bg border-0" value="{{ $quiz->time_limit }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small">Attempts</label>
                                <input type="number" name="max_attempts" class="form-control theme-badge-bg border-0" value="{{ $quiz->max_attempts }}">
                            </div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="is_active" id="is_active" value="1" {{ $quiz->is_active ? 'checked' : '' }} style="float: left;">
                            <label class="form-check-label small ms-4" for="is_active">Activate quiz for students</label>
                        </div>
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="is_public" id="is_public" value="1" {{ $quiz->is_public ? 'checked' : '' }} style="float: left;">
                            <label class="form-check-label small ms-4" for="is_public">Make quiz public</label>
                        </div>
                        <button type="submit" class="btn btn-dark w-100 rounded-pill py-2">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Questions List -->
        <div class="col-lg-8 order-lg-1">
            @if($quiz->questions->isEmpty())
                <div class="card border-0 shadow-sm rounded-4 theme-card p-5 text-center">
                    <i class="fas fa-question-circle fa-4x text-muted opacity-25 mb-3"></i>
                    <h4 class="fw-bold theme-text-main">No Questions Yet</h4>
                    <p class="text-muted mb-4">Start by adding the first question to build your quiz.</p>
                    <button @click="openAddModal()" class="btn btn-primary rounded-pill px-5 shadow-sm py-3 fw-bold">
                        <i class="fas fa-plus me-2"></i> Add Your First Question
                    </button>
                </div>
            @else
                <div class="d-flex flex-column gap-4">
                    @foreach($quiz->questions as $index => $q)
                        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                            <div class="card-header border-0 bg-light p-3 d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary rounded-pill px-3 py-2">Question #{{ $index + 1 }}</span>
                                <div class="d-flex gap-2">
                                    <button @click='openEditModal(@json($q->load("options")))' class="btn btn-sm btn-light border rounded-circle shadow-sm">
                                        <i class="fas fa-edit text-warning"></i>
                                    </button>
                                    <form action="{{ route('quizzes.questions.destroy', ['quiz_id' => $quiz->quiz_id, 'question_id' => $q->question_id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this question?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light border rounded-circle shadow-sm"><i class="fas fa-trash text-danger"></i></button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-4 text-ltr">
                                <h5 class="fw-bold theme-text-main mb-3">{{ $q->question_text }}</h5>
                                @if($q->image_path)
                                    <img src="/{{ $q->image_path }}" class="img-fluid rounded-4 mb-4 border" style="max-height: 300px;">
                                @endif
                                <div class="row g-3">
                                    @foreach($q->options as $o)
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-4 border {{ $o->is_correct ? 'bg-success bg-opacity-10 border-success' : 'theme-border bg-light' }} d-flex align-items-center justify-content-between">
                                                <span>{{ $o->option_text }}</span>
                                                @if($o->is_correct)
                                                    <i class="fas fa-check-circle text-success"></i>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-4 pt-3 border-top theme-border d-flex justify-content-between text-muted small">
                                    <span>Question Type: {{ $q->question_type === 'single_choice' ? 'Single Choice' : 'Multiple Choice' }}</span>
                                    <span>Points: {{ $q->points }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Add/Edit Question Modal -->
    <div x-show="showQuestionModal" class="custom-modal-backdrop" style="display:none;" x-cloak>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card overflow-hidden">
                <div class="modal-header border-0 bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold" x-text="modalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" @click="showQuestionModal = false"></button>
                </div>
                <div class="modal-body p-4 p-md-5 text-ltr">
                    <form :action="submitUrl" method="POST" enctype="multipart/form-data">
                        @csrf
                        <template x-if="method === 'PUT'">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small">Question Text</label>
                                <textarea name="question_text" class="form-control theme-badge-bg border-0 py-3" rows="3" x-model="questionText" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Question Type</label>
                                <select name="question_type" class="form-select theme-badge-bg border-0" x-model="questionType">
                                    <option value="single_choice">Single Choice</option>
                                    <option value="multiple_choice">Multiple Choice</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Points</label>
                                <input type="number" name="points" class="form-control theme-badge-bg border-0" x-model="points" min="1" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small">Question Image (Optional)</label>
                                <input type="file" name="question_image" class="form-control theme-badge-bg border-0">
                            </div>

                            <div class="col-12 mt-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h6 class="fw-bold mb-0">Options</h6>
                                    <button type="button" @click="addOption()" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fas fa-plus me-1"></i> Add Option
                                    </button>
                                </div>
                                <div class="row g-3">
                                    <template x-for="(opt, index) in options" :key="index">
                                        <div class="col-md-12">
                                            <div class="input-group">
                                                <span class="input-group-text theme-badge-bg border-0">
                                                    <template x-if="questionType === 'single_choice'">
                                                        <input 
                                                            type="radio" 
                                                            name="correct_option" 
                                                            :value="index" 
                                                            :checked="correctOption == index"
                                                            @change="correctOption = index"
                                                        >
                                                    </template>
                                                    <template x-if="questionType === 'multiple_choice'">
                                                        <input 
                                                            type="checkbox" 
                                                            name="correct_options[]" 
                                                            :value="index"
                                                            :checked="correctOptions.includes(index)"
                                                            @change="correctOptions.includes(index) ? correctOptions = correctOptions.filter(i => i !== index) : correctOptions.push(index)"
                                                        >
                                                    </template>
                                                </span>
                                                <input type="text" name="options[]" class="form-control border-0 theme-badge-bg" x-model="options[index]" placeholder="Option text..." required>
                                                <button type="button" class="btn btn-light border-0 theme-badge-bg text-danger" @click="removeOption(index)" :disabled="options.length <= 2">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="col-12 mt-5 pt-4 border-top">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary py-3 rounded-pill fw-bold shadow">
                                        <span x-text="method === 'POST' ? 'Add Question' : 'Save Changes'"></span>
                                    </button>
                                    <button type="button" class="btn btn-light py-2 rounded-pill" @click="showQuestionModal = false">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Bulk Import Modal -->
    <div x-show="showBulkModal" class="custom-modal-backdrop" style="display:none;" x-cloak>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card overflow-hidden">
                <div class="modal-header border-0 bg-dark text-white p-4">
                    <h5 class="modal-title fw-bold">Bulk Import Questions</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showBulkModal = false"></button>
                </div>
                <div class="modal-body p-4 p-md-5">
                    <form action="{{ route('quizzes.questions.bulk', $quiz->quiz_id) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-uppercase mb-3">Paste Your Questions Below</label>
                            <div class="alert bg-primary bg-opacity-10 border-0 rounded-4 p-3 mb-4">
                                <h6 class="fw-bold small mb-2"><i class="fas fa-info-circle me-2"></i> Formatting Instructions:</h6>
                                <ul class="small mb-0 opacity-75">
                                    <li>One question per block (separate questions with a blank line).</li>
                                    <li>First line is the **Question Text**.</li>
                                    <li>Following lines are **Options**.</li>
                                    <li>Add a **star (`*`)** at the end of the correct answer.</li>
                                </ul>
                            </div>
                            <textarea 
                                name="questions_text" 
                                class="form-control theme-badge-bg border-0 p-4 rounded-4 family-monospace" 
                                rows="12" 
                                placeholder="Example:&#10;What is 2+2?&#10;3&#10;4*&#10;5&#10;&#10;Which colors are in the flag?&#10;Red*&#10;Green&#10;White*"
                                required
                            ></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-3 rounded-pill fw-bold shadow">
                                <i class="fas fa-upload me-2"></i> Process and Import All Questions
                            </button>
                            <button type="button" class="btn btn-light py-2 rounded-pill" @click="showBulkModal = false">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .text-ltr { direction: ltr; }
    [x-cloak] { display: none !important; }

    .custom-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow-y: auto;
        padding: 20px;
    }
    .custom-modal-backdrop .modal-dialog {
        margin: 0;
        width: 100%;
        max-width: 800px;
    }
</style>
@endsection
