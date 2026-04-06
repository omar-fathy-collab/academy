@extends('layouts.authenticated')

@section('title', 'Assignments')

@section('content')
<div x-data="{ 
    ...ajaxTable(),
    search: '{{ $filters['search'] ?? '' }}'
}" class="position-relative">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">Student Assignments</h1>
            <p class="text-muted small mb-0">Monitor and grade student submissions across your groups</p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 ajax-content" id="assignments-filters">
        <div class="card-body p-4">
            <form class="row g-3 align-items-center ajax-form" action="{{ route('assignments.index') }}" method="GET" @submit.prevent>
                <div class="col-md-6 col-lg-8">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 theme-border">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input 
                            type="text" 
                            name="search"
                            class="form-control border-start-0 theme-card theme-border theme-text-main" 
                            placeholder="Search by title, group, or course..."
                            value="{{ $filters['search'] ?? '' }}"
                            @input.debounce.500ms="updateList"
                        >
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 d-flex gap-2">
                    <button type="button" class="btn btn-dark rounded-pill px-4 flex-grow-1" @click="updateList">
                        Search
                    </button>
                    @if(request('search'))
                        <a href="{{ route('assignments.index') }}" class="btn btn-light rounded-pill px-4 border">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="ajax-content position-relative min-vh-50" id="assignments-grid">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        @if(count($assignments) == 0)
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-tasks fa-4x text-muted opacity-25"></i>
                </div>
                <h4 class="fw-bold theme-text-main">No assignments found</h4>
                <p class="text-muted">Try adjusting your filters or create a new assignment in a group session.</p>
            </div>
        @else
            <div class="row g-4">
                @foreach($assignments as $assignment)
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden theme-card transition-hover">
                            <div class="card-header border-0 bg-primary bg-opacity-10 p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-white text-primary rounded-pill px-3 py-2 shadow-sm small">
                                        {{ $assignment->course_name }}
                                    </span>
                                    @php
                                        $dueDate = \Carbon\Carbon::parse($assignment->due_date);
                                        $isOverdue = $dueDate->isPast();
                                    @endphp
                                    <span class="badge {{ $isOverdue ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} rounded-pill px-3 py-2 border {{ $isOverdue ? 'border-danger-subtle' : 'border-success-subtle' }}">
                                        Due: {{ $dueDate->format('M d, Y') }}
                                    </span>
                                </div>
                                <h6 class="fw-bold mb-0 theme-text-main text-truncate">{{ $assignment->title }}</h6>
                                <p class="small text-primary mb-0 mt-1"><i class="fas fa-users me-1"></i> {{ $assignment->group_name }}</p>
                            </div>
                            <div class="card-body p-4 d-flex flex-column">
                                <p class="text-muted small mb-4 line-clamp-2">
                                    {{ $assignment->description ?: 'No description provided.' }}
                                </p>

                                <div class="row g-2 mb-4 mt-auto">
                                    <div class="col-6">
                                        <div class="p-2 border rounded-3 theme-border bg-light bg-opacity-50 text-center">
                                            <p class="text-muted smaller mb-0">Submissions</p>
                                            <h6 class="mb-0 theme-text-main fw-bold">{{ $assignment->submissions_count }}</h6>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 border rounded-3 theme-border bg-light bg-opacity-50 text-center">
                                            <p class="text-muted smaller mb-0">Graded</p>
                                            <h6 class="mb-0 {{ $assignment->graded_count == $assignment->submissions_count ? 'text-success' : 'text-warning' }} fw-bold">
                                                {{ $assignment->graded_count }}/{{ $assignment->submissions_count }}
                                            </h6>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="p-2 border rounded-3 theme-border bg-light bg-opacity-50 text-center">
                                            <p class="text-muted smaller mb-1">Class Avg Score</p>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $assignment->avg_score }}%"></div>
                                            </div>
                                            <h6 class="mb-0 theme-text-main fw-bold mt-1 small">{{ round($assignment->avg_score, 1) }}%</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="{{ route('assignments.show', $assignment->assignment_id) }}" class="btn btn-dark btn-sm flex-grow-1 rounded-pill">
                                        View & Grade
                                    </a>
                                    <a href="{{ route('assignments.edit', $assignment->assignment_id) }}" class="btn btn-light btn-sm rounded-circle border theme-border">
                                        <i class="fas fa-edit text-warning"></i>
                                    </a>
                                    <form action="{{ route('assignments.destroy', $assignment->assignment_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this assignment and all its submissions?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-light btn-sm rounded-circle border theme-border">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 d-flex justify-content-center" @click="navigate">
                {{ $assignments->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    .transition-hover { transition: all 0.3s ease; }
    .transition-hover:hover { transform: translateY(-5px); box-shadow: 0 1rem 3rem rgba(0,0,0,0.1) !important; }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .smaller { font-size: 0.7rem; }
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
@endsection
