@extends('layouts.authenticated')

@section('title', 'My Quizzes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
    <div>
        <h1 class="h3 fw-bold mb-1 theme-text-main">My Quizzes</h1>
        <p class="text-muted small mb-0">Review your available tests and past performance</p>
    </div>
</div>

<div class="row g-4">
    @forelse($quizzes as $quiz)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden theme-card transition-hover">
                <div class="card-header border-0 bg-primary bg-opacity-10 p-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-white text-primary rounded-pill px-3 py-2 shadow-sm small">
                            {{ $quiz->session->group->group_name }}
                        </span>
                        @php
                            $canAttempt = $quiz->completed_attempts < $quiz->max_attempts;
                        @endphp
                        <span class="badge {{ $canAttempt ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} rounded-pill px-3 py-2 border">
                            {{ $canAttempt ? 'Available' : 'Attempts Used' }}
                        </span>
                    </div>
                    <h6 class="fw-bold mb-0 theme-text-main text-truncate">{{ $quiz->title }}</h6>
                    <p class="small text-primary mb-0 mt-1"><i class="fas fa-clock me-1"></i> {{ $quiz->time_limit ?: 'Unlimited' }} mins</p>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    <p class="text-muted small mb-4 line-clamp-2">
                        {{ $quiz->description ?: 'Test your knowledge on ' . $quiz->session->topic }}
                    </p>

                    <div class="p-3 theme-badge-bg rounded-3 border mb-4 mt-auto theme-border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted smaller">Questions:</span>
                            <span class="fw-bold small theme-text-main">{{ $quiz->questions->count() }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted smaller">Attempts:</span>
                            <span class="small theme-text-main fw-bold">{{ $quiz->completed_attempts }} / {{ $quiz->max_attempts }}</span>
                        </div>
                        @if($quiz->latest_attempt && $quiz->latest_attempt->status == 'completed')
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted smaller">Latest Score:</span>
                                <span class="badge {{ $quiz->latest_attempt->score >= 50 ? 'bg-success' : 'bg-danger' }} rounded-pill px-3 py-1">{{ round($quiz->latest_attempt->score, 1) }}%</span>
                            </div>
                        @endif
                    </div>

                    <div class="d-grid gap-2">
                        @if($canAttempt)
                            <a href="{{ route('student.take_quiz', $quiz->uuid ?? $quiz->quiz_id) }}" class="btn btn-dark btn-sm rounded-pill py-2 shadow-sm">
                                <i class="fas fa-play me-2"></i> Start Quiz
                            </a>
                        @endif
                        @if($quiz->latest_attempt)
                            <a href="{{ route('student.quiz.results', ['attempt_id' => $quiz->latest_attempt->attempt_id]) }}" class="btn btn-light border theme-border theme-text-main btn-sm rounded-pill py-2">
                                <i class="fas fa-poll me-2"></i> View Latest Results
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-5">
            <div class="mb-4">
                <i class="fas fa-vial fa-4x text-muted opacity-25"></i>
            </div>
            <h4 class="fw-bold theme-text-main">No quizzes available</h4>
            <p class="text-muted">You don't have any pending quizzes for your groups at the moment.</p>
        </div>
    @endforelse
</div>

<style>
    .transition-hover { transition: all 0.3s ease; }
    .transition-hover:hover { transform: translateY(-5px); box-shadow: 0 1rem 3rem rgba(0,0,0,0.1) !important; }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.75rem; }
</style>
@endsection
