@extends('layouts.authenticated')

@section('title', 'Attempt Quiz: ' . $quiz->title)

@section('content')
<div x-data="{
    // Quiz State
    questions: {{ json_encode($quiz->questions->map(fn($q) => [
        'id' => $q->question_id,
        'text' => $q->question_text,
        'type' => $q->question_type,
        'image' => $q->image_path ? '/' . $q->image_path : null,
        'options' => $q->options->map(fn($o) => ['id' => $o->option_id, 'text' => $o->option_text])
    ])) }},
    currentQuestionIndex: 0,
    answers: {}, // Stores question_id -> option_id (single) or [option_id] (multiple)
    
    // Timer State
    timeLeft: {{ ($quiz->time_limit ?: 0) * 60 }},
    timerInterval: null,
    
    init() {
        // Initialize answers for multiple choice as arrays
        this.questions.forEach(q => {
            if (q.type === 'multiple_choice') this.answers[q.id] = [];
        });
        
        // Start timer if applicable
        if (this.timeLeft > 0) {
            this.timerInterval = setInterval(() => {
                if (this.timeLeft > 0) {
                    this.timeLeft--;
                } else {
                    this.submitQuiz();
                }
            }, 1000);
        }
    },
    
    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    },
    
    get currentQuestion() {
        return this.questions[this.currentQuestionIndex];
    },
    
    nextQuestion() {
        if (this.currentQuestionIndex < this.questions.length - 1) {
            this.currentQuestionIndex++;
        }
    },
    
    prevQuestion() {
        if (this.currentQuestionIndex > 0) {
            this.currentQuestionIndex--;
        }
    },
    
    toggleMultipleChoice(questionId, optionId) {
        if (!this.answers[questionId]) this.answers[questionId] = [];
        const index = this.answers[questionId].indexOf(optionId);
        if (index > -1) {
            this.answers[questionId].splice(index, 1);
        } else {
            this.answers[questionId].push(optionId);
        }
    },
    
    submitQuiz() {
        if (this.timerInterval) clearInterval(this.timerInterval);
        document.getElementById('quizSubmissionForm').submit();
    }
}">
    <!-- Quiz Header with Timer -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 position-sticky top-0 z-index-1000 border-bottom theme-border">
        <div class="card-body p-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="rounded-circle theme-badge-bg p-3 me-3 border theme-border">
                    <i class="fas fa-file-signature text-primary"></i>
                </div>
                <div>
                    <h5 class="fw-bold theme-text-main mb-0">{{ $quiz->title }}</h5>
                    <p class="text-muted small mb-0">Question <span x-text="currentQuestionIndex + 1"></span> of {{ $quiz->questions->count() }}</p>
                </div>
            </div>
            
            <template x-if="timeLeft > 0">
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <p class="text-muted smaller mb-0 text-uppercase fw-bold">Time Remaining</p>
                        <h4 :class="timeLeft < 60 ? 'text-danger animate-pulse' : 'theme-text-main'" class="fw-bold mb-0 font-monospace" x-text="formatTime(timeLeft)"></h4>
                    </div>
                    <div class="rounded-circle p-3 border theme-border d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i :class="timeLeft < 60 ? 'text-danger' : 'text-primary'" class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </template>
        </div>
        <!-- Progress Bar -->
        <div class="progress rounded-0" style="height: 4px;">
            <div class="progress-bar bg-primary" :style="`width: ${((currentQuestionIndex + 1) / questions.length) * 100}%`" role="progressbar"></div>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <!-- Question Content -->
            <div class="card border-0 shadow rounded-4 theme-card p-4 p-md-5 mb-4 text-ltr min-vh-50">
                <template x-if="questions.length === 0">
                    <div class="text-center py-5">
                        <i class="fas fa-question-circle fa-4x text-muted opacity-25 mb-3"></i>
                        <h4 class="fw-bold theme-text-main">No questions found for this quiz.</h4>
                        <p class="text-muted">Please contact your instructor if you believe this is an error.</p>
                        <a href="{{ route('student.my_sessions') }}" class="btn btn-outline-primary rounded-pill px-4 mt-3">
                            Return to Sessions
                        </a>
                    </div>
                </template>

                <template x-if="currentQuestion">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 border border-primary border-opacity-10">
                                <span x-text="currentQuestion.type === 'single_choice' ? 'Single Choice' : 'Multiple Choice'"></span>
                            </span>
                            <span class="text-muted small">Question <span x-text="currentQuestionIndex + 1"></span></span>
                        </div>
                        
                        <h3 class="fw-bold theme-text-main mb-4" x-text="currentQuestion.text"></h3>
                        
                        <template x-if="currentQuestion.image">
                            <div class="mb-4 text-center">
                                <img :src="currentQuestion.image" class="img-fluid rounded-4 border shadow-sm" style="max-height: 400px;">
                            </div>
                        </template>

                        <!-- Options Section -->
                        <div class="row g-3 mt-4">
                            <template x-for="(opt, i) in currentQuestion.options" :key="opt.id">
                                <div class="col-12">
                                    <label 
                                        class="p-4 rounded-4 border theme-border cursor-pointer d-flex align-items-center justify-content-between transition-all w-100"
                                        :class="{
                                            'bg-primary bg-opacity-10 border-primary': currentQuestion.type === 'single_choice' ? (answers[currentQuestion.id] == opt.id) : answers[currentQuestion.id].includes(opt.id),
                                            'bg-light hover-bg-light': !(currentQuestion.type === 'single_choice' ? (answers[currentQuestion.id] == opt.id) : answers[currentQuestion.id].includes(opt.id))
                                        }"
                                    >
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <div class="me-4 theme-badge-bg rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width:32px;">
                                                <span class="fw-bold small" x-text="String.fromCharCode(65 + i)"></span>
                                            </div>
                                            <span class="fw-bold theme-text-main" x-text="opt.text"></span>
                                        </div>
                                        
                                        <input 
                                            :type="currentQuestion.type === 'single_choice' ? 'radio' : 'checkbox'" 
                                            :name="`q_${currentQuestion.id}`" 
                                            class="form-check-input ms-3"
                                            :value="opt.id"
                                            x-model="answers[currentQuestion.id]"
                                            @change="currentQuestion.type === 'multiple_choice' ? toggleMultipleChoice(currentQuestion.id, opt.id) : null"
                                            style="transform: scale(1.5);"
                                        >
                                    </label>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Quiz Navigation -->
            <div class="d-flex justify-content-between align-items-center p-3 theme-card rounded-4 shadow-sm border theme-border">
                <button 
                    @click="prevQuestion()" 
                    class="btn btn-light rounded-pill px-4 border" 
                    :disabled="currentQuestionIndex === 0"
                >
                    <i class="fas fa-chevron-left me-2"></i> Previous
                </button>
                
                <div class="d-none d-md-flex gap-1">
                    <template x-for="(q, i) in questions" :key="i">
                        <button 
                            @click="currentQuestionIndex = i"
                            class="rounded-circle border-0 d-flex align-items-center justify-content-center"
                            :class="{
                                'bg-primary text-white': currentQuestionIndex === i,
                                'bg-success text-white': answers[q.id] && (Array.isArray(answers[q.id]) ? answers[q.id].length > 0 : true),
                                'bg-light theme-text-main': currentQuestionIndex !== i && !(answers[q.id] && (Array.isArray(answers[q.id]) ? answers[q.id].length > 0 : true))
                            }"
                            style="width: 8px; height: 8px; padding: 0;"
                        ></button>
                    </template>
                </div>

                <template x-if="currentQuestionIndex < questions.length - 1">
                    <button @click="nextQuestion()" class="btn btn-dark rounded-pill px-4">
                        Next <i class="fas fa-chevron-right ms-2"></i>
                    </button>
                </template>
                
                <template x-if="currentQuestionIndex === questions.length - 1">
                    <button @click="submitQuiz()" class="btn btn-success rounded-pill px-4 shadow animate-pulse">
                        <i class="fas fa-paper-plane me-2"></i> Finish and Submit Quiz
                    </button>
                </template>
            </div>
        </div>

        <!-- Hidden Form for Submission -->
        <form id="quizSubmissionForm" :action="window.location.href" method="POST" class="d-none">
            @csrf
            <input type="hidden" name="submit_quiz" value="1">
            <template x-for="(val, qId) in answers" :key="qId">
                <div>
                    <template x-if="Array.isArray(val)">
                        <template x-for="optId in val" :key="optId">
                            <input type="hidden" :name="`question_${qId}[]`" :value="optId">
                        </template>
                    </template>
                    <template x-if="!Array.isArray(val)">
                        <input type="hidden" :name="`question_${qId}`" :value="val">
                    </template>
                </div>
            </template>
        </form>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .text-ltr { direction: ltr; }
    .smaller { font-size: 0.75rem; }
    .cursor-pointer { cursor: pointer; }
    .transition-all { transition: all 0.2s ease-in-out; }
    .hover-bg-light:hover { background-color: rgba(0,0,0,0.05) !important; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .7; } }
    .min-vh-50 { min-height: 50vh; }
    [x-cloak] { display: none !important; }
</style>
@endsection
