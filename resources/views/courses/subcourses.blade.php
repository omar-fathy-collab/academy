@extends('layouts.authenticated')

@section('title', "Subcourses - {$course->course_name}")

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">Subcourses: {{ $course->course_name }}</h1>
            <p class="text-muted small mb-0">Detailed list of modules for this course.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('courses.edit', $course->course_id) }}" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm">
                <i class="fas fa-edit me-2"></i> Edit All
            </a>
            <a href="{{ route('courses.index') }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
                <i class="fas fa-arrow-left me-2"></i> Back to Courses
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 theme-text-main">
                    <thead class="theme-badge-bg">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase theme-text-main" style="width: 80px">#</th>
                            <th class="px-4 py-3 small text-uppercase theme-text-main">Module Name</th>
                            <th class="px-4 py-3 small text-uppercase theme-text-main">Description</th>
                            <th class="px-4 py-3 small text-uppercase text-center theme-text-main">Duration</th>
                            <th class="px-4 py-3 small text-uppercase text-end theme-text-main">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subcourses as $sub)
                            <tr class="theme-border">
                                <td class="px-4 py-3"><span class="badge theme-badge-bg theme-text-main border theme-border">{{ $sub->subcourse_number }}</span></td>
                                <td class="px-4 py-3 fw-bold text-primary">{{ $sub->subcourse_name }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-muted small">
                                        {{ $sub->description ?: 'No description' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">
                                        {{ $sub->duration_hours }} Hours
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="btn-group btn-group-sm rounded-pill overflow-hidden shadow-sm border theme-border">
                                        {{-- Note: Original React had a specific edit route for subcourses, checking if it exists in routes --}}
                                        <a href="{{ route('courses.edit', $course->course_id) }}" class="btn theme-card border-end theme-border" title="Edit via Course">
                                            <i class="fas fa-edit text-primary"></i>
                                        </a>
                                        <form action="{{ route('subcourses.destroy', $sub->subcourse_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this subcourse?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn theme-card text-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i>
                                        <p class="mb-0">No subcourses found for this course.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-badge-bg { background-color: var(--badge-bg) !important; }
    </style>
@endsection
