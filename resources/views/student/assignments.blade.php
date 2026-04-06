@extends('layouts.authenticated')

@section('title', 'My Assignments')

@section('content')
<div class="container-fluid py-4 min-vh-100 p-0">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 fw-bold text-dark">
            <i class="fas fa-tasks text-primary me-2"></i>
            My Assignments
        </h1>
        <a href="{{ route('student.dashboard.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm fw-bold px-3">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Assignments List -->
    <div class="row g-4">
        @forelse($assignments as $item)
            <div class="col-lg-6 col-xl-4">
                <div class="card border-0 rounded-4 shadow-sm h-100 assignment-card" style="transition: all 0.3s ease">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge rounded-pill px-3 py-2 
                                @if($item->status === 'Graded') bg-success 
                                @elseif($item->status === 'Submitted (Pending Grading)') bg-info 
                                @else bg-danger @endif">
                                {{ $item->status }}
                            </span>
                            @if($item->score !== null)
                                <div class="text-end">
                                    <div class="fw-bold fs-5 text-success">{{ $item->score }} / {{ $item->max_score ?? 100 }}</div>
                                    <div class="text-muted extra-small" style="font-size: 0.7rem;">Grade</div>
                                </div>
                            @endif
                        </div>

                        <h5 class="fw-bold text-dark mb-1">{{ $item->title }}</h5>
                        <div class="text-muted small mb-2">
                            <i class="fas fa-users me-1"></i>{{ $item->group_name }} | {{ $item->course_name }}
                        </div>

                        <div class="mb-3">
                            <p class="text-muted small mb-0 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                {{ $item->description ?? 'No description provided.' }}
                            </p>
                        </div>

                        <div class="bg-light rounded-3 p-3 mb-4 mt-auto">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Due Date:</span>
                                <span class="small fw-bold {{ \Carbon\Carbon::parse($item->due_date)->isPast() ? 'text-danger' : 'text-dark' }}">
                                    {{ \Carbon\Carbon::parse($item->due_date)->format('M d, Y') }}
                                </span>
                            </div>
                            @if($item->submission_date)
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted small">Submitted On:</span>
                                    <span class="small fw-bold text-success">{{ \Carbon\Carbon::parse($item->submission_date)->format('M d, Y') }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="d-grid gap-2">
                            @if($item->status === 'Not Submitted' || $item->status === 'Submitted (Pending Grading)')
                                <a href="{{ route('student.submit_assignment', ['id' => $item->assignment_id]) }}" 
                                   class="btn rounded-pill fw-bold {{ $item->submission_id ? 'btn-outline-info' : 'btn-primary' }}">
                                    <i class="fas fa-{{ $item->submission_id ? 'edit' : 'upload' }} me-2"></i>
                                    {{ $item->submission_id ? 'Update Submission' : 'Submit Assignment' }}
                                </a>
                            @else
                                <a href="{{ route('student.submit_assignment', ['id' => $item->assignment_id]) }}" 
                                   class="btn btn-outline-success rounded-pill fw-bold">
                                    <i class="fas fa-eye me-2"></i>View Grade & Feedback
                                </a>
                            @endif

                            @if($item->teacher_file)
                                <a href="{{ asset($item->teacher_file) }}" target="_blank" class="btn btn-link btn-sm text-decoration-none text-muted">
                                    <i class="fas fa-download me-1"></i>Assignment File
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <i class="fas fa-tasks fa-4x text-muted mb-3 opacity-25"></i>
                <h4 class="text-muted">No assignments found</h4>
                <p class="text-muted">You have no assignments assigned yet.</p>
            </div>
        @endforelse
    </div>
</div>

<style>
    .assignment-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
</style>
@endsection
