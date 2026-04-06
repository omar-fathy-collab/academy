@extends('layouts.guest')

@section('title', $course->course_name)

@section('content')
    <div class="container py-5" x-data="{ 
        submitting: false,
        handleEnroll() {
            if (!{{ auth()->check() ? 'true' : 'false' }}) {
                window.location.href = '{{ route('login') }}?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }
            
            if (confirm('{{ $course->is_free ? "Do you want to enroll in this free course?" : "Do you want to send a purchase request for this course?" }}')) {
                this.submitting = true;
                this.$refs.enrollForm.submit();
            }
        }
    }">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('courses.explore') }}">Browse Courses</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $course->course_name }}</li>
            </ol>
        </nav>

        <div class="row g-5">
            <!-- Course Details -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-img-top bg-primary bg-opacity-10 d-flex align-items-center justify-content-center py-5">
                        <i class="fas fa-graduation-cap fa-5x text-primary"></i>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-primary px-3 py-2 rounded-pill">Technical Course</span>
                            @if($course->is_free)
                                <span class="badge bg-success px-3 py-2 rounded-pill">Free</span>
                            @else
                                <span class="badge bg-info px-3 py-2 rounded-pill orange-badge">Paid</span>
                            @endif
                        </div>
                        
                        <h1 class="display-6 fw-bold mb-4">{{ $course->course_name }}</h1>
                        
                        <h5 class="fw-bold mb-3 border-bottom pb-2">About the Course</h5>
                        <p class="text-muted mb-5 lead">
                            {{ $course->description ?: 'No detailed description available for this course at the moment.' }}
                        </p>

                        <h5 class="fw-bold mb-4 border-bottom pb-2">Course Content ({{ count($course->subcourses ?? []) }} Units)</h5>
                        <div class="accordion accordion-flush" id="courseAccordion">
                            @forelse($course->subcourses as $idx => $sub)
                                <div class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed fw-bold py-3 bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#sub-{{ $sub->subcourse_id }}">
                                            <span class="badge bg-primary me-3">{{ $sub->subcourse_number }}</span>
                                            {{ $sub->subcourse_name }}
                                        </button>
                                    </h2>
                                    <div id="sub-{{ $sub->subcourse_id }}" class="accordion-collapse collapse" data-bs-parent="#courseAccordion">
                                        <div class="accordion-body text-muted">
                                            <div class="d-flex align-items-center gap-3 small">
                                                <span><i class="far fa-clock me-1"></i> {{ $sub->duration_hours }} Hours</span>
                                                <span><i class="fas fa-play-circle me-1"></i> Video Lesson</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted border rounded-3 dashed-border">
                                    Content will be reviewed and added soon.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar / Enrollment -->
            <div class="col-lg-4">
                <div class="card sticky-top border-0 shadow-lg rounded-4 overflow-hidden" style="top: 100px;">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold mb-1">
                                {!! $course->is_free ? 'Completely Free' : number_format($course->price, 0) . ' <small>EGP</small>' !!}
                            </h3>
                            <p class="small text-muted mb-0">Guaranteeing premium educational quality</p>
                        </div>

                        <div class="d-grid gap-3 mb-4">
                            <form action="{{ route('courses.enroll', $course->course_id) }}" method="POST" x-ref="enrollForm" class="d-none">
                                @csrf
                            </form>
                            <button 
                                @click="handleEnroll()" 
                                :disabled="submitting"
                                class="btn {{ $course->is_free ? 'btn-success' : 'btn-primary' }} btn-lg rounded-pill py-3 fw-bold shadow-sm transition-all"
                            >
                                <template x-if="submitting">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                </template>
                                <template x-if="!submitting">
                                    <i class="fas {{ $course->is_free ? 'fa-bolt' : 'fa-shopping-cart' }} me-2"></i>
                                </template>
                                <span>{{ $course->is_free ? 'Start Learning Now' : 'Request Course Purchase' }}</span>
                            </button>
                            
                            @unless(auth()->check())
                                <a 
                                    href="{{ route('register') }}" 
                                    class="btn btn-outline-secondary btn-lg rounded-pill py-3 fw-bold transition-all"
                                >
                                    Create a New Account
                                </a>
                            @endunless
                        </div>

                        <div class="features-list small text-muted">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Build real practical skills
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Lifetime access to content
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Certificate of completion upon success
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Technical support & direct contact with the teacher
                            </div>
                        </div>
                        
                        @unless($course->is_free)
                            <div class="mt-4 p-3 bg-light rounded-3 small text-center text-primary">
                                <i class="fas fa-info-circle me-2"></i>
                                You will be provided with available payment methods after submitting the request (Vodafone Cash, Fawry, ...)
                            </div>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('fawry_payload'))
        <script src="https://www.fawrypay.com/atfawry/plugin/atfawry-plugin.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const payload = @js(session('fawry_payload'));
                if (window.FawryPay) {
                    window.FawryPay.checkout(payload, {
                        locale: 'en',
                        displayMode: 'POPUP',
                        onSuccess: (response) => {
                            window.location.href = "{{ route('fawry.callback') }}?merchantRefNum=" + payload.merchantRefNum + "&orderStatus=PAID";
                        },
                        onFailure: (response) => {
                            console.log('Fawry Failure:', response);
                        }
                    });
                }
            });
        </script>
    @endif

    <style>
        .breadcrumb-item + .breadcrumb-item::before {
            content: "→";
        }
        .orange-badge {
            background-color: #f6ad55 !important;
        }
        .dashed-border {
            border-style: dashed !important;
        }
        .transition-all:hover {
            transform: scale(1.02);
        }
        .accordion-button:not(.collapsed) {
            background-color: var(--bs-primary-bg-subtle);
            color: var(--bs-primary-text-emphasis);
        }
    </style>
@endsection
