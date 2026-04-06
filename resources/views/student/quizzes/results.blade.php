@extends('layouts.authenticated')

@section('title', 'Quiz Results: ' . $attempt->quiz->title)

@section('content')
<div class="row justify-content-center pb-5">
    <div class="col-lg-9">
        <!-- Results Header -->
        <div class="card border-0 shadow-sm rounded-4 theme-card mb-5 overflow-hidden text-center text-ltr">
            <div class="card-body p-5">
                <div class="mb-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm border theme-border" style="width: 100px; height: 100px; background-color: {{ $attempt->score >= 50 ? 'rgba(25, 135, 84, 0.1)' : 'rgba(220, 53, 69, 0.1)' }}">
                        <i class="fas {{ $attempt->score >= 50 ? 'fa-trophy text-success' : 'fa-times-circle text-danger' }} fa-3x"></i>
                    </div>
                    <h2 class="fw-bold theme-text-main mb-1">{{ $attempt->score >= 50 ? 'Well done!' : 'You need more practice' }}</h2>
                    <p class="text-muted">You have completed the quiz: <span class="theme-text-main fw-bold">{{ $attempt->quiz->title }}</span></p>
                </div>

                <div class="row g-3 justify-content-center mb-4">
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 border theme-border bg-light">
                            <h3 class="fw-bold theme-text-main mb-1">{{ round($attempt->score, 1) }}%</h3>
                            <p class="text-muted smaller mb-0 text-uppercase">Percentage</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 border theme-border bg-light">
                            <h3 class="fw-bold theme-text-main mb-1">{{ $correctQuestions }} / {{ $attempt->quiz->questions->count() }}</h3>
                            <p class="text-muted smaller mb-0 text-uppercase">Correct Questions</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-4 border theme-border bg-light">
                             <h3 class="fw-bold theme-text-main mb-1">{{ round(\Carbon\Carbon::parse($attempt->start_time)->diffInMinutes($attempt->end_time), 0) }}</h3>

                            <p class="text-muted smaller mb-0 text-uppercase">Time Taken (m)</p>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3">
                    <a href="{{ route('student.my_quizzes') }}" class="btn btn-dark rounded-pill px-5 py-2">
                        Back to Quizzes
                    </a>
                    @if($attempt->quiz->attempts->count() < $attempt->quiz->max_attempts)
                        <a href="{{ route('student.take_quiz', $attempt->quiz->uuid ?? $attempt->quiz_id) }}" class="btn btn-primary rounded-pill px-5 py-2 shadow">
                            Try Again
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Answer Review -->
        <h3 class="fw-bold theme-text-main mb-4 text-ltr">Review Answers</h3>
        <div class="d-flex flex-column gap-4">
            @foreach($attempt->quiz->questions as $index => $q)
                @php
                    $qAnswers = collect($answersByQuestion[$q->question_id] ?? []);
                    $pointsEarned = $pointsByQuestion[$q->question_id] ?? 0;
                    $isCorrect = $pointsEarned >= $q->points;
                    $isPartial = $pointsEarned > 0 && $pointsEarned < $q->points;
                    
                    $statusClass = $isCorrect ? 'bg-success' : ($isPartial ? 'bg-warning' : 'bg-danger');
                    $bgClass = $isCorrect ? 'bg-success bg-opacity-10' : ($isPartial ? 'bg-warning bg-opacity-10' : 'bg-danger bg-opacity-10');
                    $textClass = $isCorrect ? 'text-success' : ($isPartial ? 'text-warning-emphasis' : 'text-danger');
                    $iconClass = $isCorrect ? 'fa-check-circle' : ($isPartial ? 'fa-exclamation-circle' : 'fa-times-circle');
                    $statusText = $isCorrect ? 'Correct Answer' : ($isPartial ? 'Partially Correct' : 'Wrong Answer');
                @endphp
                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-header border-0 p-3 d-flex justify-content-between align-items-center {{ $bgClass }}">
                        <div>
                            <span class="badge {{ $statusClass }} rounded-pill px-3 py-2">Question #{{ $index + 1 }}</span>
                            <span class="ms-2 fw-bold theme-text-main small">Points: {{ round($pointsEarned, 2) }} / {{ $q->points }}</span>
                        </div>
                        <span class="{{ $textClass }} fw-bold">
                            <i class="fas {{ $iconClass }} me-2"></i>
                            {{ $statusText }}
                        </span>
                    </div>
                    <div class="card-body p-4 text-ltr">
                        <h5 class="fw-bold theme-text-main mb-3">{{ $q->question_text }}</h5>
                        @if($q->image_path)
                            <img src="/{{ $q->image_path }}" class="img-fluid rounded-4 mb-4 border" style="max-height: 250px;">
                        @endif
                        
                        <div class="row g-3 mt-2">
                            @foreach($q->options as $o)
                                @php
                                    $isSelected = $qAnswers->where('option_id', $o->option_id)->isNotEmpty();
                                @endphp
                                <div class="col-12">
                                    <div class="p-3 rounded-4 border d-flex align-items-center justify-content-between 
                                        {{ $o->is_correct ? 'bg-success bg-opacity-10 border-success border-2' : ($isSelected && !$o->is_correct ? 'bg-danger bg-opacity-10 border-danger border-2' : 'theme-border bg-light') }}">
                                        <div class="d-flex align-items-center">
                                            @if($o->is_correct)
                                                <i class="fas fa-check-circle text-success me-3"></i>
                                            @elseif($isSelected)
                                                <i class="fas fa-times-circle text-danger me-3"></i>
                                            @else
                                                <i class="far fa-circle text-muted me-3"></i>
                                            @endif
                                            <span class="{{ ($o->is_correct || $isSelected) ? 'fw-bold theme-text-main' : 'text-muted' }}">{{ $o->option_text }}</span>
                                        </div>
                                        @if($isSelected)
                                            <span class="badge bg-dark rounded-pill px-3">Your Answer</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .text-ltr { direction: ltr; text-align: left; }
    .smaller { font-size: 0.75rem; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
</style>
@endsection
