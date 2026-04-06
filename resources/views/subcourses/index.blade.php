@extends('layouts.authenticated')

@section('title', 'Subcourses — ' . ($course->course_name ?? 'All Courses'))

@section('content')
<div class="container py-4 min-vh-100 theme-bg-main">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-0 theme-text-main">
                <i class="fas fa-list-ol text-warning me-2"></i>Subcourses
            </h4>
            <p class="text-muted small mb-0">Course: <strong class="theme-text-main">{{ $course->course_name ?? 'All Courses' }}</strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('courses.index') }}" class="btn theme-card border theme-border rounded-pill px-3 theme-text-main shadow-sm">
                <i class="fas fa-arrow-left me-1"></i> Courses
            </a>
            <a href="{{ route('subcourses.create', ['course_id' => $course->course_id ?? '']) }}" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-plus me-2"></i>Add Subcourse
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0 theme-text-main">
                <thead class="theme-badge-bg text-muted small text-uppercase fw-semibold">
                    <tr>
                        <th class="px-4 py-3 theme-text-main">#</th>
                        <th class="px-4 py-3 theme-text-main">Subcourse Name</th>
                        <th class="px-4 py-3 theme-text-main">Order</th>
                        <th class="px-4 py-3 theme-text-main">Duration (h)</th>
                        <th class="px-4 py-3 theme-text-main">Description</th>
                        <th class="px-4 py-3 text-end theme-text-main">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subcourses as $index => $s)
                        <tr class="border-bottom theme-border">
                            <td class="px-4 py-3 text-muted">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 fw-medium theme-text-main">{{ $s->subcourse_name }}</td>
                            <td class="px-4 py-3">
                                <span class="badge bg-warning text-dark rounded-pill">{{ $s->subcourse_number }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted">{{ $s->duration_hours ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted small">
                                {{ Str::limit($s->description, 60) ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('subcourses.edit', $s->subcourse_id) }}" class="btn btn-sm btn-outline-warning rounded-pill border-0 shadow-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('subcourses.destroy', $s->subcourse_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this subcourse?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill border-0 shadow-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colSpan="6" class="text-center text-muted py-5 border-0">
                                <div class="py-4 opacity-50">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No subcourses yet — add one above</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .theme-bg-main { background-color: var(--bg-main) !important; }
    
    [data-bs-theme='dark'] .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.05) !important; }
    
    .btn-outline-warning:hover { color: #000 !important; }
</style>
@endpush
@endsection
