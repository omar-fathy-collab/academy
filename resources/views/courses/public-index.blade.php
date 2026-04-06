@extends('layouts.guest')

@section('title', 'Explore Courses')

@section('content')
    <!-- Hero Section -->
    <div class="bg-primary bg-opacity-10 py-5 mb-5 border-bottom" x-data="{ 
        ...ajaxTable(),
        search: '{{ $filters['search'] ?? '' }}' 
    }">
        <div class="container text-center py-4">
            <h1 class="display-4 fw-bold text-primary mb-3">Explore Your Learning Tracks</h1>
            <p class="lead text-muted mb-4 px-md-5">
                We offer a diverse range of technical and professional courses to help you build a promising future.
            </p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <form action="{{ route('courses.explore') }}" method="GET" class="input-group shadow-sm rounded-pill overflow-hidden ajax-form" @submit.prevent>
                        <input
                            type="text"
                            name="search"
                            class="form-control border-0 px-4 py-3"
                            placeholder="Search for a specific course..."
                            value="{{ $filters['search'] ?? '' }}"
                            @input.debounce.500ms="updateList"
                        >
                        <button class="btn btn-primary px-4" type="button" @click="updateList">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5 ajax-content position-relative min-vh-50" id="public-courses-grid">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <div class="row g-4">
            @forelse($courses as $course)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm hover-lift transition-all rounded-4 overflow-hidden">
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center py-5 position-relative">
                            <i class="fas fa-laptop-code fa-4x text-primary opacity-25"></i>
                            @if($course->is_free)
                                <span class="position-absolute top-0 end-0 m-3 badge bg-success px-3 py-2 rounded-pill">
                                    Free
                                </span>
                            @else
                                <span class="position-absolute top-0 end-0 m-3 badge bg-primary px-3 py-2 rounded-pill">
                                    {{ number_format($course->price, 0) }} EGP
                                </span>
                            @endif
                        </div>
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-2">{{ $course->course_name }}</h5>
                            <p class="card-text text-muted small slice-text mb-4">
                                {{ $course->description ?: 'No description available for this course.' }}
                            </p>
                            <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                                <div class="small text-muted">
                                    <i class="fas fa-layer-group me-1"></i>
                                    {{ $course->subcourses_count ?? 0 }} Training Units
                                </div>
                                <a 
                                    href="{{ route('courses.public.show', $course->course_id) }}" 
                                    class="btn btn-outline-primary btn-sm rounded-pill px-3"
                                >
                                    Details <i class="fas fa-arrow-right ms-1 small"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <div class="opacity-25 mb-4">
                        <i class="fas fa-search fa-5x"></i>
                    </div>
                    <h3 class="text-muted">No courses found matching your search</h3>
                    <a href="{{ route('courses.explore') }}" class="btn btn-link">Reset Search</a>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($courses->hasPages())
            <div class="mt-5 d-flex justify-content-center" @click="navigate">
                {{ $courses->links() }}
            </div>
        @endif
    </div>

    <style>
        .hover-lift { transition: transform 0.3s ease-in-out; }
        .hover-lift:hover {
            transform: translateY(-8px);
        }
        .transition-all {
            transition: all 0.3s ease-in-out;
        }
        .slice-text {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        /* Custom Pagination Styling to match React design */
        .pagination .page-link {
            border: none;
            border-radius: 8px !important;
            margin: 0 4px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            padding: 0.5rem 1rem;
            color: #2D3748;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            color: white !important;
        }
    </style>
@endsection
