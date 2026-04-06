@extends('layouts.authenticated')

@section('title', 'Quiz: ' . $quiz->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
            <div class="d-flex align-items-center">
                <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 fw-bold mb-1 theme-text-main">Preview Quiz: {{ $quiz->title }}</h1>
                    <p class="text-muted small mb-0">Group: {{ $quiz->session->group->group_name }} | {{ $quiz->questions->count() }} Questions</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="fas fa-edit me-2"></i> Edit Questions
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 overflow-hidden">
                    <div class="card-header border-0 bg-primary bg-opacity-10 p-4 text-center">
                        <h6 class="fw-bold text-primary mb-0">Quick Stats</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted smaller">Questions:</span>
                            <span class="fw-bold theme-text-main">{{ $quiz->questions->count() }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted smaller">Total Points:</span>
                            <span class="fw-bold theme-text-main">{{ $quiz->questions->sum('points') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted smaller">Time Limit:</span>
                            <span class="fw-bold theme-text-main">{{ $quiz->time_limit ? $quiz->time_limit . ' mins' : 'Unlimited' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted smaller">Max Attempts:</span>
                            <span class="fw-bold theme-text-main">{{ $quiz->max_attempts }}</span>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                    <h6 class="fw-bold theme-text-main mb-3">Quiz Description</h6>
                    <p class="text-muted small mb-0">{{ $quiz->description ?: 'No description added for this quiz.' }}</p>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="d-flex flex-column gap-4">
                    @foreach($quiz->questions as $index => $q)
                        <div class="card border-0 shadow-sm rounded-4 theme-card p-4 text-ltr">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold theme-text-main mb-0">Question #{{ $index + 1 }} <span class="text-muted smaller ps-2">({{ $q->points }} points)</span></h6>
                                <span class="badge {{ $q->question_type === 'single_choice' ? 'bg-info-subtle text-info' : 'bg-primary-subtle text-primary' }} rounded-pill px-3">
                                    {{ $q->question_type === 'single_choice' ? 'Single Choice' : 'Multiple Choice' }}
                                </span>
                            </div>
                            <p class="theme-text-main mb-3">{{ $q->question_text }}</p>
                            @if($q->image_path)
                                <img src="/{{ $q->image_path }}" class="img-fluid rounded-4 mb-4 border" style="max-height: 250px;">
                            @endif
                            <div class="list-group list-group-flush theme-border">
                                @foreach($q->options as $o)
                                    <div class="list-group-item bg-transparent border-0 px-0 d-flex align-items-center">
                                        <i class="far {{ $o->is_correct ? 'fa-check-circle text-success' : 'fa-circle text-muted' }} me-3"></i>
                                        <span class="{{ $o->is_correct ? 'fw-bold theme-text-main' : 'text-muted' }}">{{ $o->option_text }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .text-ltr { direction: ltr; }
    .smaller { font-size: 0.75rem; }
</style>
@endsection
