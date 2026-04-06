@extends('layouts.authenticated')

@section('title', 'Courses Management')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">Courses Management</h1>
            <p class="text-muted small mb-0">Manage your academy's curriculum and sub-modules.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('courses.create') }}" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm">
                <i class="fas fa-plus me-2"></i> Add New Course
            </a>
            <a href="/courses/import" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
                <i class="fas fa-file-import me-2"></i> Import
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-4" x-data="ajaxTable">
        <div class="card-header theme-card border-bottom theme-border p-4 ajax-content" id="courses-header">
            <form action="{{ route('courses.index') }}" method="GET" class="row g-3 ajax-form" @submit.prevent>
                <div class="col-md-6">
                    <div class="input-group theme-border">
                        <span class="input-group-text theme-badge-bg border-0"><i class="fas fa-search text-muted"></i></span>
                        <input
                            type="text"
                            name="search"
                            class="form-control theme-badge-bg border-0 theme-text-main"
                            placeholder="Search by name or description..."
                            value="{{ $filters['search'] ?? '' }}"
                            @input.debounce.500ms="updateList"
                        >
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card-body p-0 position-relative">
            <!-- Loading Overlay -->
            <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
                <div class="spinner-border text-primary" role="status"></div>
            </div>

            <div class="table-responsive ajax-content" id="courses-table">
                <table class="table table-hover align-middle mb-0 theme-text-main">
                    <thead class="theme-badge-bg">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase theme-text-main" style="width: 80px">ID</th>
                            <th class="px-4 py-3 small text-uppercase theme-text-main">Course Name</th>
                            <th class="px-4 py-3 small text-uppercase theme-text-main">Description</th>
                            <th class="px-4 py-3 small text-uppercase text-center theme-text-main">Subcourses</th>
                            <th class="px-4 py-3 small text-uppercase text-center theme-text-main">Students</th>
                            <th class="px-4 py-3 small text-uppercase text-end theme-text-main">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($courses as $course)
                            <tr class="theme-border">
                                <td class="px-4 py-3"><span class="badge theme-badge-bg theme-text-main border theme-border">{{ $course->course_id }}</span></td>
                                <td class="px-4 py-3 fw-bold text-primary">{{ $course->course_name }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-muted small text-truncate" style="max-width: 300px;">
                                        {{ $course->description ?: 'No description provided' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">
                                        {{ $course->subcourse_count ?? 0 }} Modules
                                    </span>
                                    @if(($course->subcourse_count ?? 0) > 0)
                                        <a href="{{ route('subcourses', $course->course_id) }}" class="ms-2 text-info">
                                            <i class="fas fa-eye small"></i>
                                        </a>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="fw-medium theme-text-main">{{ $course->student_count ?? 0 }}</span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="btn-group btn-group-sm rounded-pill overflow-hidden shadow-sm border theme-border">
                                        <a href="{{ route('courses.edit', $course->course_id) }}" class="btn theme-card border-end theme-border" title="Edit">
                                            <i class="fas fa-edit text-primary"></i>
                                        </a>
                                        <form action="{{ route('courses.destroy', $course->course_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this course and all its subcourses?')">
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
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                                        <p class="mb-0">No courses found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer theme-card border-top theme-border p-4 d-flex justify-content-center ajax-content" id="courses-pagination" @click="navigate">
            @if($courses->hasPages())
                {{ $courses->links() }}
            @endif
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-badge-bg { background-color: var(--badge-bg) !important; }
        
        .page-link { color: #4f46e5; }
        .page-item.active .page-link { background-color: #4f46e5 !important; color: white !important; border-color: #4f46e5 !important; }
        [data-bs-theme='dark'] .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.05); }

        /* Laravel Pagination Compatibility */
        .pagination { margin-bottom: 0; display: flex; gap: 0.25rem; }
        .page-item .page-link { border-radius: 8px !important; border: 1px solid var(--border-color); background: var(--card-bg); padding: 0.5rem 0.85rem; }
    </style>
@endsection
