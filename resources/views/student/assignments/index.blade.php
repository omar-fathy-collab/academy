@extends('layouts.authenticated')

@section('title', 'My Assignments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
    <div>
        <h1 class="h3 fw-bold mb-1 theme-text-main">My Assignments</h1>
        <p class="text-muted small mb-0">Track your homework, submit your work, and view grades</p>
    </div>
</div>

<div class="row g-4">
    @forelse($assignments as $assignment)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden theme-card transition-hover">
                <div class="card-header border-0 bg-primary bg-opacity-10 p-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-white text-primary rounded-pill px-3 py-2 shadow-sm small">
                            {{ $assignment->course_name }}
                        </span>
                        @php
                            $dueDate = \Carbon\Carbon::parse($assignment->due_date);
                            $isOverdue = $dueDate->isPast() && !$assignment->submission_date;
                        @endphp
                        <span class="badge {{ $assignment->submission_date ? 'bg-success-subtle text-success' : ($isOverdue ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') }} rounded-pill px-3 py-2 border">
                            {{ $assignment->submission_date ? 'Submitted' : ($isOverdue ? 'Overdue' : 'Pending') }}
                        </span>
                    </div>
                    <h6 class="fw-bold mb-0 theme-text-main text-truncate">{{ $assignment->title }}</h6>
                    <p class="small text-primary mb-0 mt-1"><i class="fas fa-users me-1"></i> {{ $assignment->group_name }}</p>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    <p class="text-muted small mb-4 line-clamp-2">
                        {{ $assignment->description ?: 'No additional instructions provided.' }}
                    </p>

                    <div class="p-3 theme-badge-bg rounded-3 border mb-4 mt-auto theme-border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted smaller">Due Date:</span>
                            <span class="fw-bold small theme-text-main">{{ $dueDate->format('M d, Y') }}</span>
                        </div>
                        @if($assignment->score !== null)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted smaller">Grade:</span>
                                <span class="badge bg-success rounded-pill px-3 py-1">{{ $assignment->score }}/100</span>
                            </div>
                        @endif
                        @if($assignment->submission_date)
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted smaller">Submitted On:</span>
                                <span class="small theme-text-main">{{ \Carbon\Carbon::parse($assignment->submission_date)->format('M d, Y') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="d-grid gap-2">
                        @if($assignment->teacher_file)
                            <a href="/{{ $assignment->teacher_file }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill py-2">
                                <i class="fas fa-download me-2"></i> Download Instructions
                            </a>
                        @endif
                        <a href="{{ route('student.submit_assignment', ['id' => $assignment->assignment_id]) }}" class="btn {{ $assignment->submission_date ? 'btn-light border theme-border theme-text-main' : 'btn-dark' }} btn-sm rounded-pill py-2">
                            {{ $assignment->submission_date ? 'Edit Submission' : 'Submit Assignment' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center py-5">
            <div class="mb-4">
                <i class="fas fa-clipboard-list fa-4x text-muted opacity-25"></i>
            </div>
            <h4 class="fw-bold theme-text-main">No assignments yet</h4>
            <p class="text-muted">You don't have any pending or submitted assignments at the moment.</p>
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
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
@endsection
