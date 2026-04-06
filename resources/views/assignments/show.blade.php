@extends('layouts.authenticated')

@section('title', 'Assignment: ' . $assignment->title)

@section('content')
<div x-data="{ 
    showGradeModal: false,
    selectedSubmission: null,
    score: 0,
    feedback: '',

    openGradeModal(submission) {
        this.selectedSubmission = submission;
        this.score = submission.score || 0;
        this.feedback = submission.feedback || '';
        this.showGradeModal = true;
    }
}">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <div class="d-flex align-items-center mb-1">
                <a href="{{ route('assignments.index') }}" class="btn btn-sm btn-light border theme-border rounded-circle me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 fw-bold mb-0 theme-text-main">{{ $assignment->title }}</h1>
            </div>
            <p class="text-muted small mb-0 ms-5">
                <span class="badge bg-primary me-2">{{ $assignment->course_name }}</span>
                {{ $assignment->group_name }} | Due: {{ \Carbon\Carbon::parse($assignment->due_date)->format('M d, Y') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('assignments.edit', $assignment->assignment_id) }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
                <i class="fas fa-edit me-2 text-warning"></i> Edit Details
            </a>
            @if($assignment->teacher_file)
                <a href="/{{ $assignment->teacher_file }}" target="_blank" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm">
                    <i class="fas fa-download me-2"></i> Teacher File
                </a>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <!-- Stats Sidebar -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle theme-badge-bg d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                        <h3 class="mb-0 fw-bold theme-text-main">{{ $assignment->submissions_count }}</h3>
                    </div>
                    <p class="text-muted small mb-0">Total Submissions</p>
                    <hr class="theme-border my-4">
                    <div class="row g-2">
                        <div class="col-6">
                            <h6 class="fw-bold mb-1 theme-text-main">{{ $assignment->graded_count }}</h6>
                            <p class="text-muted smaller mb-0">Graded</p>
                        </div>
                        <div class="col-6">
                            <h6 class="fw-bold mb-1 text-warning">{{ $assignment->submissions_count - $assignment->graded_count }}</h6>
                            <p class="text-muted smaller mb-0">Pending</p>
                        </div>
                        <div class="col-12 mt-3">
                            <h5 class="fw-bold mb-1 text-success">{{ round($assignment->avg_score, 1) }}%</h5>
                            <p class="text-muted smaller mb-0">Class Average</p>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $assignment->avg_score }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 theme-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold theme-text-main mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Description</h6>
                    <p class="text-muted small mb-0">
                        {{ $assignment->description ?: 'No additional description provided for this assignment.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="card-header border-0 bg-transparent p-4 pb-0">
                    <h5 class="fw-bold theme-text-main mb-0">Student Submissions</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle theme-text-main">
                            <thead class="theme-badge-bg">
                                <tr class="theme-border">
                                    <th>Student</th>
                                    <th>Submitted On</th>
                                    <th>Score</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($submissions as $sub)
                                    <tr class="theme-border">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="theme-badge-bg rounded-circle p-2 me-2 border theme-border">
                                                    <i class="fas fa-user-graduate smaller"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 fw-bold">{{ $sub->student_name }}</p>
                                                    <small class="text-muted">{{ $sub->email }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="mb-0 small">{{ \Carbon\Carbon::parse($sub->submission_date)->format('M d, Y') }}</p>
                                            <small class="text-muted">{{ \Carbon\Carbon::parse($sub->submission_date)->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            @if($sub->score !== null)
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">
                                                    {{ $sub->score }}/100
                                                </span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">
                                                    Un-graded
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex gap-2 justify-content-end">
                                                @if($sub->file_path)
                                                    @php
                                                        $filePath = $sub->file_path;
                                                        // Handle JSON-encoded file paths
                                                        if (str_starts_with($filePath, '[{') || str_starts_with($filePath, '{')) {
                                                            $fileData = json_decode($filePath, true);
                                                            if (is_array($fileData)) {
                                                                $filePath = isset($fileData[0]['path']) ? $fileData[0]['path'] : ($fileData['path'] ?? $filePath);
                                                            }
                                                        }
                                                    @endphp
                                                    <a href="{{ asset('storage/' . ltrim($filePath, '/')) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                        <i class="fas fa-file-download me-1"></i> File
                                                    </a>
                                                @endif
                                                <button @click="openGradeModal({{ json_encode($sub) }})" class="btn btn-dark btn-sm rounded-pill px-3">
                                                    Grade
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            No student has submitted this assignment yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grading Modal -->
    <div x-show="showGradeModal" 
         class="modal fade" 
         :class="{ 'show d-block': showGradeModal }" 
         style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);" 
         x-transition
         x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold theme-text-main">Grade Submission</h5>
                    <button type="button" class="btn-close" @click="showGradeModal = false"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="p-3 bg-light rounded-3 mb-4 theme-border border">
                        <small class="text-muted d-block mb-1">Student:</small>
                        <h6 class="fw-bold mb-0 theme-text-main" x-text="selectedSubmission?.student_name"></h6>
                    </div>
                    <form action="{{ route('assignments.grade_submission') }}" method="POST">
                        @csrf
                        <input type="hidden" name="submission_id" :value="selectedSubmission?.submission_id">
                        <div class="mb-4">
                            <label class="form-label fw-bold small">Score (out of 100)</label>
                            <input type="number" name="score" class="form-control theme-card theme-border theme-text-main" x-model="score" min="0" max="100" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold small">Feedback to student (Optional)</label>
                            <textarea name="feedback" class="form-control theme-card theme-border theme-text-main" rows="4" x-model="feedback"></textarea>
                        </div>
                        <div class="d-grid g-2">
                            <button type="submit" class="btn btn-primary rounded-pill py-2 shadow">
                                Save Grade
                            </button>
                            <button type="button" class="btn btn-link text-muted mt-2" @click="showGradeModal = false">Cancel</button>
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
    .smaller { font-size: 0.75rem; }
    [x-cloak] { display: none !important; }
</style>
@endsection
