@extends('layouts.authenticated')

@section('title', 'Student Profile: ' . ($student->user->username ?? 'N/A'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-5 pt-3 pb-2 border-bottom">
    <h1 class="h3 fw-bold mb-0">
        <i class="fas fa-user-graduate me-2 text-primary"></i>Student Profile
    </h1>
    <a href="{{ route('students.index') }}" class="btn btn-outline-primary btn-sm px-4 rounded-pill">
        <i class="fas fa-arrow-left me-2"></i> Back to List
    </a>
</div>

{{-- Profile Header Card --}}
<div class="card border-0 shadow-sm rounded-4 theme-card mb-4 overflow-hidden">
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-md-3 theme-card p-5 text-center d-flex flex-column align-items-center justify-content-center border-end">
                <img src="{{ $student->user->profile->profile_picture ?? '/assets/user_image.jpg' }}" 
                     class="rounded-circle shadow-lg mb-3 border border-4 theme-border object-fit-cover" 
                     style="width: 150px; height: 150px;" alt="Profile" />
                
                <span class="badge rounded-pill mb-2 px-3 py-2 {{ ($student->user->is_active ?? false) ? 'bg-success' : 'bg-secondary' }}">
                    {{ ($student->user->is_active ?? false) ? 'Active Account' : 'Inactive' }}
                </span>
                
                <h4 class="fw-bold mb-1">{{ $student->user->username ?? 'N/A' }}</h4>
                <p class="text-muted small">Student ID: #{{ $student->student_id }}</p>
            </div>
            
            <div class="col-md-9 p-4">
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <label class="text-muted small d-block mb-1">Email Address</label>
                        <div class="fw-bold text-truncate"><i class="fas fa-envelope me-2 text-primary"></i>{{ $student->user->email ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small d-block mb-1">Phone Number</label>
                        <div class="fw-bold"><i class="fas fa-phone me-2 text-primary"></i>{{ $student->user->profile->phone_number ?? 'N/A' }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small d-block mb-1">Enrollment Date</label>
                        <div class="fw-bold"><i class="fas fa-calendar-alt me-2 text-primary"></i>{{ date('M d, Y', strtotime($student->enrollment_date)) }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-md-2">
                        <div class="p-3 rounded-4 bg-primary text-primary bg-opacity-10 text-center theme-stat-card">
                            <div class="mb-1"><i class="fas fa-users"></i></div>
                            <div class="fw-bold h4 mb-0">{{ count($groupsWithDetails) }}</div>
                            <div class="text-muted small fw-bold mt-1">Groups</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="p-3 rounded-4 bg-info text-info bg-opacity-10 text-center theme-stat-card">
                            <div class="mb-1"><i class="fas fa-question-circle"></i></div>
                            <div class="fw-bold h4 mb-0">{{ $quizzesTaken }}</div>
                            <div class="text-muted small fw-bold mt-1">Quizzes</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="p-3 rounded-4 bg-success text-success bg-opacity-10 text-center theme-stat-card">
                            <div class="mb-1"><i class="fas fa-chart-line"></i></div>
                            <div class="fw-bold h4 mb-0">{{ $avgQuizScore ? round($avgQuizScore) . '%' : 'N/A' }}</div>
                            <div class="text-muted small fw-bold mt-1">Avg Score</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="p-3 rounded-4 bg-warning text-warning bg-opacity-10 text-center theme-stat-card">
                            <div class="mb-1"><i class="fas fa-clipboard-check"></i></div>
                            <div class="fw-bold h4 mb-0">{{ $attendancePercentage !== null ? $attendancePercentage . '%' : 'N/A' }}</div>
                            <div class="text-muted small fw-bold mt-1">Attendance</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <div class="p-3 rounded-4 bg-danger text-danger bg-opacity-10 text-center theme-stat-card">
                            <div class="mb-1"><i class="fas fa-star"></i></div>
                            <div class="fw-bold h4 mb-0">{{ $overallRating && $overallRating->avg_rating ? number_format($overallRating->avg_rating, 1) : 'N/A' }}</div>
                            <div class="text-muted small fw-bold mt-1">Rating</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Groups Detail --}}
<div class="card border-0 shadow-sm rounded-4 theme-card mb-4">
    <div class="card-header theme-card border-0 p-4 pb-0">
        <h5 class="fw-bold mb-0">Enrolled Groups</h5>
    </div>
    <div class="card-body p-4">
        @if(count($groupsWithDetails) > 0)
            <div class="accordion accordion-flush" id="studentGroupsAccordion">
                @foreach($groupsWithDetails as $index => $detail)
                    <div class="accordion-item border rounded-4 mb-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-3 px-4 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#group-{{ $index }}">
                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                    <div>
                                        <span class="text-primary">{{ $detail['group']->group_name }}</span>
                                        <span class="text-muted ms-2 small">| {{ $detail['group']->course->course_name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        @if(isset($detail['group_attendance']['percentage']))
                                            <span class="badge rounded-pill {{ $detail['group_attendance']['percentage'] >= 75 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }}">
                                                Attendance: {{ $detail['group_attendance']['percentage'] }}%
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="group-{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#studentGroupsAccordion">
                            <div class="accordion-body p-4 pt-0">
                                <div class="row g-4 mb-4 border-top pt-3">
                                    <div class="col-md-4">
                                        <div class="small text-muted">Teacher</div>
                                        <div class="fw-bold">{{ $detail['group']->teacher->teacher_name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted">Schedule</div>
                                        <div class="fw-bold">{{ $detail['group']->schedule }}</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted">Avg Monthly Rating</div>
                                        <div class="fw-bold text-warning">
                                            @if($detail['avg_rating'] && $detail['avg_rating']->avg_rating)
                                                <i class="fas fa-star me-1"></i>
                                                {{ number_format($detail['avg_rating']->avg_rating, 1) }} / 5
                                            @else
                                                <span class="text-muted">No rating</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <h6 class="fw-bold mb-3 mt-4"><i class="fas fa-calendar-alt me-2 text-primary"></i>Session History</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle table-borderless border-top pt-2">
                                        <thead>
                                            <tr class="text-muted small">
                                                <th>Date</th>
                                                <th>Topic</th>
                                                <th>Attendance</th>
                                                <th>Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($detail['sessions'] as $session)
                                                @php
                                                    $att = $session->attendances->first();
                                                    $rat = $session->ratings->first();
                                                @endphp
                                                <tr class="border-bottom">
                                                    <td class="py-2 small">{{ date('M d, Y', strtotime($session->session_date)) }}</td>
                                                    <td class="py-2 small fw-bold">{{ $session->topic ?? 'N/A' }}</td>
                                                    <td class="py-2">
                                                        <span class="badge rounded-pill {{ strtolower($att->status ?? '') === 'present' ? 'bg-success' : 'bg-danger' }}">
                                                            {{ ucfirst($att->status ?? 'N/A') }}
                                                        </span>
                                                    </td>
                                                    <td class="py-2">
                                                        @if($rat)
                                                            <span class="text-warning small"><i class="fas fa-star me-1"></i>{{ $rat->rating_value }}</span>
                                                        @else
                                                            <span class="text-muted small">Pending</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-5 bg-light rounded-4">
                <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">No group enrollments found.</p>
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .accordion-button:not(.collapsed) {
        background-color: var(--bs-primary-bg-subtle, rgba(13, 110, 253, 0.05));
        color: var(--app-primary-color, #0d6efd);
        box-shadow: none;
    }
    .accordion-button:focus { box-shadow: none; }
    .theme-stat-card { border: 1px solid rgba(0,0,0,0.05); }
    [data-bs-theme="dark"] .theme-stat-card { 
        border-color: rgba(255,255,255,0.05); 
        background-color: rgba(255,255,255,0.05) !important; 
        color: inherit !important; 
    }
    [data-bs-theme="dark"] .theme-stat-card i {
        opacity: 0.8;
    }
</style>
@endpush
@endsection
