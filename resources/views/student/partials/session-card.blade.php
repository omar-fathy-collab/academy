@php
    $session = $item['session'];
    $isPast = $item['is_past'];
    $datetime = \Carbon\Carbon::parse($item['datetime']);
@endphp

<div class="card border-0 rounded-4 shadow-sm mb-4 bg-white transition-hover overflow-hidden">
    <div class="d-flex align-items-stretch flex-column flex-md-row">
        <!-- Date Block -->
        <div class="p-4 text-center d-flex flex-column justify-content-center bg-light border-end" style="min-width: 150px;">
            <div class="fw-bold text-primary fs-3">{{ $datetime->format('d') }}</div>
            <div class="text-uppercase small fw-bold text-muted">{{ $datetime->format('M') }}</div>
            <div class="mt-2 small text-dark fw-bold rounded-pill bg-white border px-2 py-1 shadow-sm">
                <i class="far fa-clock me-1 text-primary"></i> {{ $session->start_time }}
            </div>
        </div>

        <!-- Content Block -->
        <div class="p-4 flex-grow-1">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-dark mb-1">{{ $session->topic ?? 'Course Session' }}</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-semibold">
                            <i class="fas fa-users me-2"></i>{{ $item['group_name'] }}
                        </span>
                        <span class="badge bg-light text-muted border rounded-pill px-3 py-2 fw-semibold">
                            <i class="fas fa-chalkboard-teacher me-2 text-primary opacity-75"></i>{{ $item['teacher_name'] }}
                        </span>
                    </div>
                </div>
                
                <div class="text-md-end flex-shrink-0" style="min-width: 160px;">
                    <!-- Main Action -->
                    <a href="{{ route('student.session_details', $session->uuid ?? $session->session_id) }}" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm mb-2 w-100 transition-200">
                        <i class="fas fa-door-open me-2"></i> View Session
                    </a>

                    <!-- Attendance Actions -->
                    @if(!$item['attendance'] || $item['attendance']->status !== 'present')
                        @if(!$isPast)
                            @if($session->requires_proximity)
                                <button @click="openCheckIn({{ json_encode($item) }})" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold shadow-sm mb-2 w-100">
                                    <i class="fas fa-wifi me-2"></i> WiFi Check-In
                                </button>
                            @else
                                <button @click="markAttendance({{ json_encode($session) }})" class="btn btn-outline-success rounded-pill px-4 py-2 fw-bold shadow-sm mb-2 w-100" :disabled="processing">
                                    <i class="fas fa-hand-pointer me-2"></i> Mark Present
                                </button>
                            @endif
                        @endif
                    @endif

                    <!-- Status Badges -->
                    <div class="mt-1 d-flex justify-content-md-end">
                        @if($item['attendance'])
                            <span class="badge rounded-pill px-3 py-2 {{ $item['attendance']->status === 'present' ? 'bg-success' : 'bg-danger' }} shadow-sm">
                                <i class="fas {{ $item['attendance']->status === 'present' ? 'fa-check-circle' : 'fa-times-circle' }} me-2"></i>
                                {{ strtoupper($item['attendance']->status) }}
                            </span>
                        @elseif($isPast)
                            <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm">
                                <i class="fas fa-calendar-times me-2"></i> ABSENT
                            </span>
                        @else
                            <span class="badge bg-info text-white rounded-pill px-3 py-2 shadow-sm">
                                <i class="fas fa-clock me-2"></i> UPCOMING
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Detail Grid -->
            <div class="row g-3 mt-2 border-top pt-3">
                <div class="col-6 col-md-3">
                    <div class="small text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Assignment</div>
                    @if($item['assignment'])
                        <a href="{{ route('student.submit_assignment', ['id' => $item['assignment']->assignment_id]) }}" class="text-decoration-none fw-bold small {{ $item['submission'] ? 'text-success' : 'text-primary' }}">
                            <i class="fas {{ $item['submission'] ? 'fa-check-circle' : 'fa-arrow-circle-right' }} me-1"></i>
                            {{ $item['submission'] ? 'Submitted' : 'Go to Submit' }}
                        </a>
                    @else
                        <span class="text-muted small">None</span>
                    @endif
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Quiz</div>
                    @if($item['quiz'])
                        <a href="{{ route('student.take_quiz', ['quiz' => $item['quiz']->quiz_id]) }}" class="text-decoration-none fw-bold small {{ $item['quiz_attempt'] ? 'text-success' : 'text-danger' }}">
                            <i class="fas {{ $item['quiz_attempt'] ? 'fa-check-circle' : 'fa-play-circle' }} me-1"></i>
                            {{ $item['quiz_attempt'] ? 'Completed' : 'Take Quiz' }}
                        </a>
                    @else
                        <span class="text-muted small">None</span>
                    @endif
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Materials</div>
                    <a href="{{ route('student.session_details', $session->uuid ?? $session->session_id) }}" class="text-decoration-none fw-bold small text-warning">
                        <i class="fas fa-folder-open me-1"></i>{{ $item['materials_count'] }} Files
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.65rem;">Rating</div>
                    @if($item['rating'])
                        <div class="fw-bold small text-info">
                            <i class="fas fa-star me-1 text-warning"></i>{{ $item['rating']->rating_value }}/10
                        </div>
                    @else
                        <span class="text-muted small">Not Rated</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .transition-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .transition-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.5rem 2rem rgba(0,0,0,0.08) !important;
    }
</style>
