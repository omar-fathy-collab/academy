@extends('layouts.authenticated')

@section('title', 'Finalize & Issue Certificate')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="certificateEdit()" x-cloak>
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- Header Section -->
            <div class="d-flex align-items-center justify-content-between mb-4">
                <a href="{{ route('certificates.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold shadow-sm px-4">
                    <i class="fas fa-arrow-left me-2 text-primary"></i> Back to Hub
                </a>
                <div class="text-end">
                    <span class="badge bg-{{ $certificate->status === 'issued' ? 'success' : 'warning-subtle text-warning' }} rounded-pill px-3 py-2 border border-{{ $certificate->status === 'issued' ? 'success' : 'warning' }} border-opacity-25">
                        Status: {{ strtoupper($certificate->status) }}
                    </span>
                </div>
            </div>

            <div class="card border-0 shadow-lg rounded-4 theme-card p-4 p-md-5 mb-4 bg-white">
                <div class="text-center mb-5">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3 shadow-sm">
                        <i class="fas fa-file-signature fa-3x"></i>
                    </div>
                    <h2 class="fw-bold theme-text-main">Finalize & Issue Certificate</h2>
                    <p class="text-muted small">Review student details and academic performance before generating the final credential.</p>
                </div>

                <div class="row g-4 d-ltr">
                    <!-- Student Info Card -->
                    <div class="col-md-6">
                        <div class="p-4 theme-badge-bg rounded-4 border theme-border h-100 bg-light">
                            <h6 class="fw-bold text-uppercase smaller text-muted mb-3 italic">Student Details</h6>
                            <div class="d-flex align-items-center">
                                <div class="bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fas fa-user-graduate text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ $certificate->user->name ?? 'N/A' }}</h6>
                                    <p class="smaller text-muted mb-0">{{ $certificate->user->email ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Course/Group Info Card -->
                    <div class="col-md-6">
                        <div class="p-4 theme-badge-bg rounded-4 border theme-border h-100 bg-light">
                            <h6 class="fw-bold text-uppercase smaller text-muted mb-3 italic">Academic Track</h6>
                            <h6 class="fw-bold mb-1 text-dark">{{ $certificate->course->course_name ?? 'Individual Issue' }}</h6>
                            <p class="smaller text-muted mb-0">Group: {{ $certificate->group->group_name ?? 'N/A' }}</p>
                            <p class="smaller text-muted mb-0">Instructor: {{ $certificate->instructor_name ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <!-- Performance Metrics Row -->
                    <div class="col-12 mt-4">
                        <div class="p-4 bg-light rounded-4 border">
                            <h6 class="fw-bold text-uppercase smaller text-muted mb-4 text-center">Calculated Academic Metrics</h6>
                            <div class="row text-center g-3">
                                <div class="col-md-4">
                                    <div class="display-6 fw-bold text-primary mb-1">{{ number_format($certificate->attendance_percentage ?? 0, 1) }}%</div>
                                    <div class="smaller fw-bold text-muted text-uppercase">Final Attendance</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="display-6 fw-bold text-success mb-1">{{ number_format($certificate->quiz_average ?? 0, 1) }}%</div>
                                    <div class="smaller fw-bold text-muted text-uppercase">Quiz Average</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="display-6 fw-bold text-info mb-1">{{ number_format($certificate->final_rating ?? 0, 1) }}/5</div>
                                    <div class="smaller fw-bold text-muted text-uppercase">Student Rating</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="theme-border my-5">

                    <!-- Finalize Form -->
                    <div class="col-12">
                        <form action="{{ route('certificates.finalize', $certificate->id) }}" method="POST" dir="ltr">
                            @csrf
                            <div class="row g-4">
                                <div class="col-md-6 text-start">
                                    <label class="form-label fw-bold smaller text-uppercase">Certificate Template</label>
                                    <select name="template_id" class="form-select rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required>
                                        <option value="">-- Select Design Template --</option>
                                        @foreach($templates as $template)
                                            <option value="{{ $template->id }}" {{ $certificate->template_id == $template->id ? 'selected' : '' }}>
                                                {{ $template->name }}
                                            </option>
                                        @endforeach

                                    </select>
                                    @if(count($templates) === 0)
                                        <p class="extra-small text-danger mt-1">
                                            <i class="fas fa-exclamation-triangle me-1"></i> No templates found. Please create one in 
                                            <a href="{{ route('admin.library') }}" class="text-primary text-decoration-underline">Library Hub</a>.
                                        </p>
                                    @endif
                                </div>

                                <div class="col-md-6 text-start">
                                    <label class="form-label fw-bold smaller text-uppercase">Issue Number (Auto Generated)</label>
                                    <div class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2 bg-light">
                                        {{ $certificate->certificate_number }}
                                    </div>
                                </div>

                                <div class="col-12 text-start">
                                    <label class="form-label fw-bold smaller text-uppercase">Personal Remarks / Special Mention</label>
                                    <textarea name="remarks" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" rows="3" placeholder="e.g. Graduated with Honors in Advance Design Methods...">{{ $certificate->remarks }}</textarea>
                                </div>

                                <div class="col-12 text-center mt-5">
                                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm transition-hover">
                                        <i class="fas fa-check-double me-2"></i> Confirm & Issue Global ID
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function certificateEdit() {
    return {
        // Alpine data if needed
    };
}
</script>

<style>
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .smaller { font-size: 0.72rem; }
    .extra-small { font-size: 0.65rem; }
    .transition-hover:hover { transform: translateY(-3px); }
    .d-ltr { direction: ltr !important; }
    .italic { font-style: italic; }
</style>
@endsection
