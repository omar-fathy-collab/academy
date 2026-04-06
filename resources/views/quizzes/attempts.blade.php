@extends('layouts.authenticated')

@section('title', 'Quiz Attempts: ' . $quiz->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11">
        <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
            <div class="d-flex align-items-center">
                <a href="{{ route('quizzes.edit', $quiz->quiz_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 fw-bold mb-1 theme-text-main">Student Results: {{ $quiz->title }}</h1>
                    <p class="text-muted small mb-0">Group: {{ $quiz->session->group->group_name ?? 'N/A' }} | Total Attempts: {{ $attempts->total() }}</p>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden" x-data="ajaxTable()" x-cloak>
            <div class="card-header border-0 bg-transparent p-4 pb-0">
                <h5 class="fw-bold theme-text-main mb-0">Attempts Log</h5>
            </div>
            
            <div class="card-body p-4 position-relative ajax-content" id="quiz-attempts-grid">
                <!-- Loading Overlay -->
                <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle theme-text-main">
                        <thead class="theme-badge-bg">
                            <tr class="theme-border">
                                <th>Student</th>
                                <th>Started At</th>
                                <th>Ended At</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attempts as $attempt)
                                <tr class="theme-border">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="theme-badge-bg rounded-circle p-2 me-2 border theme-border">
                                                <i class="fas fa-user-graduate smaller"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-bold">{{ $attempt->student->student_name ?? 'Unknown Student' }}</p>
                                                <small class="text-muted">{{ $attempt->student->user->email ?? 'no-email@academy.com' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="mb-0 small">{{ \Carbon\Carbon::parse($attempt->start_time)->format('M d, Y H:i') }}</p>
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($attempt->start_time)->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        @if($attempt->end_time)
                                            <p class="mb-0 small text-success">{{ \Carbon\Carbon::parse($attempt->end_time)->format('H:i') }}</p>
                                            <small class="text-muted">Duration: {{ \Carbon\Carbon::parse($attempt->start_time)->diffInMinutes($attempt->end_time) }} mins</small>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Not Finished Yet</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary">{{ $attempt->score }}</span> / {{ $quiz->questions->sum('points') }}
                                    </td>
                                    <td>
                                        @php
                                            $totalPoints = $quiz->questions->sum('points');
                                            $percentage = $totalPoints > 0 ? ($attempt->score / $totalPoints) * 100 : 0;
                                        @endphp
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold {{ $percentage >= 50 ? 'text-success' : 'text-danger' }} me-3">{{ round($percentage, 1) }}%</span>
                                            <div class="progress flex-grow-1" style="height: 6px; min-width: 80px;">
                                                <div class="progress-bar {{ $percentage >= 50 ? 'bg-success' : 'bg-danger' }}" role="progressbar" style="width: {{ $percentage }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('attempts.show', $attempt->attempt_id) }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 border-0 bg-light">
                                            Answers Details
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        No attempts for this quiz yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex justify-content-center" @click="navigate">
                    {{ $attempts->links() }}
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
    .smaller { font-size: 0.75rem; }
</style>
@endsection
