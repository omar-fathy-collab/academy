@extends('layouts.authenticated')

@section('title', 'Session: ' . ($session->topic ?? 'Details'))

@section('content')
<div class="container-fluid py-4 min-vh-100 bg-light" x-data="sessionDetailManager()">
    <!-- Navigation & Header -->
    <div class="mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <a href="{{ route('student.my_sessions') }}" class="btn btn-white shadow-sm rounded-pill px-4 py-2 text-decoration-none text-dark fw-bold border align-self-start bg-white">
            <i class="fas fa-arrow-left me-2 text-primary"></i>
            <span>Back to My Sessions</span>
        </a>

        @if($isToday)
            <div class="align-self-start align-self-md-auto">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm animate-pulse">
                    <i class="fas fa-bolt me-1"></i> Happening Today
                </span>
            </div>
        @endif
    </div>

    <!-- Session Header (Premium & Integrated) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-body p-0">
            <div class="row g-0">
                <div class="col-lg-8 p-4 p-md-5">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 me-3 small fw-bold">
                            {{ $session->group->group_name ?? 'N/A' }}
                        </span>
                        <span class="text-muted small"><i class="fas fa-university me-1"></i> {{ $session->group->course->course_name ?? 'N/A' }}</span>
                    </div>
                    <h1 class="display-6 fw-bold text-dark mb-3">
                        {{ $session->topic ?? 'Session Details' }}
                    </h1>
                    <div class="d-flex flex-wrap gap-4 mt-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-2 p-2 me-2 text-info">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <span class="text-muted small fw-bold">{{ $session->group->teacher->teacher_name ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-2 p-2 me-2 text-success">
                                <i class="fas fa-clock"></i>
                            </div>
                            <span class="text-muted small fw-bold">{{ \Carbon\Carbon::parse($sessionDateTime)->format('h:i A') }} - {{ \Carbon\Carbon::parse($sessionEndDateTime)->format('h:i A') }}</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-2 p-2 me-2 text-primary">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <span class="text-muted small fw-bold">{{ \Carbon\Carbon::parse($sessionDateTime)->format('d M, Y') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 d-none d-lg-flex align-items-center justify-content-center bg-light border-start">
                    <div class="text-center p-4">
                        <div class="bg-white rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-graduation-cap fa-2x text-primary"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Learning Session</h6>
                        <p class="text-muted extra-small mb-0">Follow all instructions carefully</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <div class="card-body p-0">
            <ul class="nav nav-pills nav-fill flex-column flex-md-row" id="sessionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                        <i class="fas fa-th-large me-2"></i> Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">
                        <i class="fas fa-folder-open me-2"></i> Materials ({{ count($session->materials) }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="recordings-tab" data-bs-toggle="tab" data-bs-target="#recordings" type="button" role="tab">
                        <i class="fas fa-video me-2"></i> Recordings ({{ count($videos) }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab">
                        <i class="fas fa-tasks me-2"></i> Assignments ({{ count($assignments) }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="quizzes-tab" data-bs-toggle="tab" data-bs-target="#quizzes" type="button" role="tab">
                        <i class="fas fa-bolt me-2"></i> Quizzes ({{ count($quizzes) }})
                    </button>
                </li>
                @if(count($books) > 0)
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="books-tab" data-bs-toggle="tab" data-bs-target="#books" type="button" role="tab">
                        <i class="fas fa-book me-2"></i> Books
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 rounded-0 fw-bold border-bottom border-3 border-transparent" id="meetings-tab" data-bs-toggle="tab" data-bs-target="#meetings" type="button" role="tab">
                        <i class="fas fa-video-camera me-2"></i> Meetings
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="sessionTabsContent">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <!-- Status Cards Row (Prominent) -->
            <div class="row g-4 mb-5">
                <!-- Attendance Card -->
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4 transition-hover overflow-hidden bg-white position-relative">
                        @if((!$attendance || $attendance->status !== 'present') && !$isPast)
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-primary bg-opacity-10 d-flex flex-column align-items-center justify-content-center p-3" style="z-index: 10; backdrop-filter: blur(2px);">
                                <div class="bg-white rounded-circle p-3 mb-2 text-primary shadow-sm">
                                    <i class="fas fa-wifi fa-xl animate-pulse"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-2 small">Attendance Window Open</h6>
                                <button @click="markAttendance()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm" :disabled="processing">
                                    <span x-show="!processing">Check In Now</span>
                                    <span x-show="processing">Processing...</span>
                                </button>
                            </div>
                        @endif

                        <div class="bg-primary bg-opacity-10 text-primary rounded-pill d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 54px; height: 54px;">
                            <i class="fas fa-user-check fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-muted text-uppercase extra-small mb-2">My Attendance</h6>

                        @if($attendance)
                            <div>
                                <div class="fs-5 fw-bold {{ $attendance->status === 'present' ? 'text-success' : 'text-danger' }}">
                                    <i class="fas {{ $attendance->status === 'present' ? 'fa-check-circle' : 'fa-times-circle' }} me-1"></i>
                                    {{ ucfirst($attendance->status) }}
                                </div>
                                @if($attendance->recorded_at)
                                    <div class="text-muted extra-small mt-1">At {{ \Carbon\Carbon::parse($attendance->recorded_at)->format('h:i A') }}</div>
                                @endif
                            </div>
                        @else
                            <div class="text-warning fw-bold fs-5">
                                <i class="fas fa-clock-rotate-left me-1"></i> Pending
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Rating/Performance Card -->
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4 bg-white transition-hover">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-pill d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 54px; height: 54px;">
                            <i class="fas fa-star fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-muted text-uppercase extra-small mb-2">My Rating</h6>
                        @if($rating)
                            <div>
                                <div class="fs-5 fw-bold text-dark">
                                    @for($i = 0; $i < 5; $i++)
                                        <i class="fas fa-star {{ $i < $rating->rating_value ? 'text-warning' : 'text-light' }} me-1 small"></i>
                                    @endfor
                                </div>
                                <div class="fw-bold mt-1 text-primary">{{ $rating->rating_value }} / 5</div>
                            </div>
                        @else
                            <div class="text-muted fw-bold fs-5 italic">Not Rated</div>
                        @endif
                    </div>
                </div>

                <!-- Next Assignment Card -->
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4 bg-white transition-hover">
                        <div class="bg-info bg-opacity-10 text-info rounded-pill d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 54px; height: 54px;">
                            <i class="fas fa-tasks fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-muted text-uppercase extra-small mb-2">Assignments</h6>
                        <div class="fs-5 fw-bold text-dark">{{ count($assignments) }} Total</div>
                        <div class="small text-muted mt-1">{{ $assignments->where('student_submission', '!=', null)->count() }} Submitted</div>
                    </div>
                </div>

                <!-- Next Quiz Card -->
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100 text-center p-4 bg-white transition-hover">
                        <div class="bg-success bg-opacity-10 text-success rounded-pill d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 54px; height: 54px;">
                            <i class="fas fa-bolt fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-muted text-uppercase extra-small mb-2">Quizzes</h6>
                        <div class="fs-5 fw-bold text-dark">{{ count($quizzes) }} Total</div>
                        <div class="small text-muted mt-1">{{ $quizzes->where('best_attempt', '!=', null)->count() }} Completed</div>
                    </div>
                </div>
            </div>

            <!-- Session Notes -->
            @if($session->notes)
                <div class="card border-0 shadow-sm rounded-4 mb-5 bg-white">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold text-dark mb-4"><i class="fas fa-sticky-note me-2 text-primary"></i> Session Instructions & Bio</h5>
                        <div class="p-4 bg-light rounded-4 border-start border-4 border-primary">
                            <p class="mb-0 text-muted" style="white-space: pre-line; line-height: 1.6;">{{ $session->notes }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Materials Tab -->
        <div class="tab-pane fade" id="materials" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-file-alt text-primary me-2"></i> Study Materials
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(count($session->materials) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($session->materials as $material)
                                @php
                                    $ext = pathinfo($material->original_name, PATHINFO_EXTENSION);
                                    $iconClass = match(strtolower($ext)) {
                                        'pdf' => 'fa-file-pdf text-danger',
                                        'doc', 'docx' => 'fa-file-word text-primary',
                                        'xls', 'xlsx' => 'fa-file-excel text-success',
                                        'zip', 'rar' => 'fa-file-archive text-warning',
                                        default => 'fa-file text-muted'
                                    };
                                @endphp
                                <div class="list-group-item p-4 border-0 border-bottom bg-transparent hover-bg-light transition-200">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-3 p-3 me-4 shadow-sm d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                                <i class="fas {{ $iconClass }} fa-xl"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1">{{ $material->original_name }}</h6>
                                                <div class="text-muted extra-small">
                                                    <span><i class="far fa-calendar-alt me-1"></i> {{ \Carbon\Carbon::parse($material->created_at)->format('M d, Y') }}</span>
                                                    <span class="mx-2">•</span>
                                                    <span><i class="fas fa-hdd me-1"></i> {{ round($material->size / 1024) }} KB</span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="{{ route('student.session.material.download', ['session_id' => $session->session_id, 'file_name' => $material->original_name]) }}" 
                                           class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-sm shadow-sm">
                                            <i class="fas fa-download me-2"></i> Download
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <img src="https://illustrations.popsy.co/gray/empty-folder.svg" alt="No materials" style="height: 150px;" class="mb-4">
                            <p class="mb-0">No documents have been uploaded for this session yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recordings Tab -->
        <div class="tab-pane fade" id="recordings" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-play-circle text-danger me-2"></i> Session Recordings
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(count($videos) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($videos as $video)
                                <div class="list-group-item p-4 border-0 border-bottom bg-transparent hover-bg-light transition-200">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center">
                                            <div class="position-relative me-4 shadow rounded-3 overflow-hidden" style="width: 120px; height: 70px; background: #000;">
                                                <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                                                    @if($video->provider === 'youtube')
                                                        <i class="fab fa-youtube text-danger fa-2x"></i>
                                                    @elseif($video->provider === 'vimeo')
                                                        <i class="fab fa-vimeo-v text-info fa-2x"></i>
                                                    @else
                                                        <i class="fas fa-play text-white fa-xl"></i>
                                                    @endif
                                                </div>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-2">{{ $video->title }}</h6>
                                                <div class="d-flex align-items-center gap-3">
                                                    @if($video->watched_percentage > 0)
                                                        <div class="progress" style="width: 100px; height: 6px; background-color: #e9ecef;">
                                                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $video->watched_percentage }}%"></div>
                                                        </div>
                                                        <span class="text-muted extra-small fw-bold">{{ round($video->watched_percentage) }}% Watched</span>
                                                    @endif
                                                    @if($video->is_completed)
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 extra-small rounded-pill">
                                                            <i class="fas fa-check me-1"></i> Done
                                                        </span>
                                                    @endif
                                                    @if(($video->status ?? '') === 'processing')
                                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1 extra-small rounded-pill animate-pulse">
                                                            <i class="fas fa-spinner fa-spin me-1"></i> Processing...
                                                        </span>
                                                    @endif
                                                </div>

                                            </div>
                                        </div>
                                        <button @click="openVideoPlayer(@js($video))" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm btn-sm">
                                            <i class="fas fa-play-circle me-2"></i> Watch Now
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <img src="https://illustrations.popsy.co/gray/video-call.svg" alt="No recordings" style="height: 150px;" class="mb-4">
                            <p class="mb-0">No exercise or session recordings available yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Assignments Tab -->
        <div class="tab-pane fade" id="assignments" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-tasks text-info me-2"></i> Assignments
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(count($assignments) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($assignments as $assign)
                                <div class="list-group-item p-4 border-0 border-bottom bg-transparent hover-bg-light transition-200">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-dark mb-1">{{ $assign->title }}</h6>
                                            <div class="d-flex align-items-center gap-3 mt-2">
                                                <span class="extra-small text-muted"><i class="far fa-calendar-alt me-1"></i> Due: {{ \Carbon\Carbon::parse($assign->due_date)->format('M d, Y') }}</span>
                                                @if($assign->student_submission)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 extra-small">
                                                        <i class="fas fa-check-circle me-1"></i> Submitted
                                                    </span>
                                                    @if($assign->student_submission->score !== null)
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 extra-small">
                                                            Score: {{ $assign->student_submission->score }}/100
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 extra-small">
                                                        <i class="fas fa-clock me-1"></i> Not Submitted
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        <a href="{{ route('student.submit_assignment', ['id' => $assign->assignment_id]) }}" class="btn btn-outline-primary rounded-pill px-4 fw-bold btn-sm shadow-sm">
                                            {{ $assign->student_submission ? 'View Submission' : 'Submit Now' }}
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <img src="https://illustrations.popsy.co/gray/success.svg" alt="No assignments" style="height: 150px;" class="mb-4">
                            <p class="mb-0">Great! There are no assignments for this session.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Quizzes Tab -->
        <div class="tab-pane fade" id="quizzes" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-bolt text-success me-2"></i> Online Quizzes
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(count($quizzes) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($quizzes as $q)
                                <div class="list-group-item p-4 border-0 border-bottom bg-transparent hover-bg-light transition-200">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-dark mb-1">{{ $q->title }}</h6>
                                            <div class="d-flex align-items-center gap-3 mt-2">
                                                <span class="extra-small text-muted"><i class="fas fa-clock me-1"></i> {{ $q->time_limit ? $q->time_limit . ' mins' : 'No limit' }}</span>
                                                <span class="extra-small text-muted"><i class="fas fa-redo me-1"></i> {{ $q->remaining_attempts }} / {{ $q->max_attempts }} Attempts Left</span>
                                                @if($q->best_attempt)
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 extra-small">
                                                        Best: {{ $q->best_attempt->score }}%
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($q->remaining_attempts > 0 && !$isUpcoming)
                                            <a href="{{ route('student.take_quiz', ['quiz' => $q->quiz_id]) }}" class="btn btn-success rounded-pill px-4 fw-bold btn-sm shadow-sm">
                                                Start Quiz
                                            </a>
                                        @else
                                            <button class="btn btn-secondary rounded-pill px-4 fw-bold btn-sm shadow-sm" disabled>
                                                {{ $isUpcoming ? 'Locked' : 'No Attempts' }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <img src="https://illustrations.popsy.co/gray/product-launch.svg" alt="No quizzes" style="height: 150px;" class="mb-4">
                            <p class="mb-0">No quizzes are linked to this session.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Books Tab -->
        <div class="tab-pane fade" id="books" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-book text-purple me-2" style="color: #6f42c1;"></i> Digital Books
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(count($books) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($books as $book)
                                <div class="list-group-item p-4 border-0 border-bottom bg-transparent hover-bg-light transition-200">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-3 p-3 me-4 shadow-sm d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; color: #6f42c1;">
                                                <i class="fas fa-book-open fa-xl"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1">{{ $book->title }}</h6>
                                                <p class="text-muted extra-small mb-0">{{ $book->author ?? 'Academy Edition' }}</p>
                                            </div>
                                        </div>
                                        <a href="{{ route('student.books.view', $book->id) }}" 
                                           class="btn btn-outline-purple rounded-pill px-4 fw-bold btn-sm shadow-sm" style="color: #6f42c1; border-color: #6f42c1;">
                                            <i class="fas fa-eye me-2"></i> Open Book
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <img src="https://illustrations.popsy.co/gray/book-crashe.svg" alt="No books" style="height: 150px;" class="mb-4">
                            <p class="mb-0">No digital books assigned to this session.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Meetings Tab -->
        <div class="tab-pane fade" id="meetings" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-video-camera text-primary me-2"></i> Active Meeting Rooms
                    </h5>
                </div>
                <div class="card-body p-4">
                    @if($session->meetings->count() > 0)
                        <div class="row g-4">
                            @foreach($session->meetings as $meeting)
                                <div class="col-md-6">
                                    <div class="card border rounded-4 shadow-none p-4 h-100 transition-200 {{ $meeting->is_closed ? 'bg-light opacity-75' : 'theme-badge-bg border-primary border-opacity-25' }}">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="p-3 rounded-4 bg-primary bg-opacity-10 text-primary">
                                                <i class="fas fa-video fa-xl"></i>
                                            </div>
                                            @if($meeting->is_closed)
                                                <span class="badge bg-danger rounded-pill px-3">CLOSED</span>
                                            @elseif($isToday)
                                                <span class="badge bg-success rounded-pill px-3 animate-pulse">LIVE NOW</span>
                                            @endif
                                        </div>
                                        <h5 class="fw-bold text-dark mb-1">{{ $meeting->title }}</h5>
                                        <p class="text-muted small mb-4">Click below to join the virtual classroom. Ensure your microphone and camera are ready.</p>
                                        
                                        @if(!$meeting->is_closed)
                                            <a href="{{ route('meeting.join.public', $meeting->id) }}" target="_blank" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">
                                                <i class="fas fa-door-open me-2"></i> Join Room
                                            </a>
                                        @else
                                            <button class="btn btn-secondary w-100 rounded-pill py-2 fw-bold" disabled>
                                                <i class="fas fa-lock me-2"></i> Room Closed
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-5 text-center text-muted">
                            <img src="https://illustrations.popsy.co/gray/video-call.svg" alt="No meetings" style="height: 150px;" class="mb-4">
                            <p class="mb-0">There are no virtual meetings scheduled for this session yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>


    <!-- Video Player Modal -->
    <div x-show="showVideoModal" class="modal fade" :class="{ 'show d-block': showVideoModal }" x-cloak style="background: #000; z-index: 2000;">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content border-0 rounded-0 bg-black position-relative overflow-hidden">
                <!-- Header Overlay -->
                <div class="position-absolute top-0 start-0 w-100 p-4 d-flex justify-content-between align-items-center z-3 bg-gradient-dark">
                    <h5 class="text-white mb-0 fw-bold" x-text="videoData?.title"></h5>
                    <button type="button" class="btn btn-link text-white text-decoration-none bg-white bg-opacity-10 rounded-circle p-2" @click="closeVideoPlayer" style="width: 45px; height: 45px;">
                        <i class="fas fa-times fs-5"></i>
                    </button>
                </div>

                <!-- Watermark -->
                <div x-show="isPlaying" 
                     class="position-absolute text-white opacity-25 fw-bold select-none pointer-events-none z-2"
                     :style="`left: ${watermarkPos.x}%; top: ${watermarkPos.y}%; transition: all 1s linear; font-size: 1.2rem;`"
                     x-text="'{{ Auth::user()->email }}'">
                </div>

                <!-- Player Interface -->
                <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-black">
                    <template x-if="videoData?.provider === 'youtube'">
                        <iframe :src="`https://www.youtube.com/embed/${getYoutubeId(videoData.file_path)}?autoplay=1&modestbranding=1&rel=0`" 
                                class="w-100 h-100 border-0" allow="autoplay; encrypted-media; fullscreen"></iframe>
                    </template>
                    <template x-if="videoData?.provider === 'vimeo'">
                        <iframe :src="`https://player.vimeo.com/video/${getVimeoId(videoData.file_path)}?autoplay=1`" 
                                class="w-100 h-100 border-0" allow="autoplay; fullscreen"></iframe>
                    </template>
                    <template x-if="videoData?.provider === 'local'">
                        <div class="w-100 h-100 position-relative">
                            <video x-ref="videoPlayer" 
                                   class="w-100 h-100" 
                                   controls 
                                   controlsList="nodownload" 
                                   @timeupdate="onTimeUpdate"
                                   @play="isPlaying = true"
                                   @pause="isPlaying = false">
                                <source :src="videoUrl" type="video/mp4">
                            </video>
                        </div>
                    </template>
                </div>

                <!-- Custom Controls (Optional/Simplified) -->
                <div class="position-absolute bottom-0 start-0 w-100 p-4 d-flex justify-content-center z-3 pointer-events-none">
                    <div class="badge bg-black bg-opacity-50 text-white rounded-pill px-4 py-2 border border-white border-opacity-25" x-show="videoData?.provider === 'local'">
                        Security Enabled: {{ Auth::user()->name }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function sessionDetailManager() {
        return {
            processing: false,
            showWifiModal: false,
            error: null,
            success: null,

            // Video Player State
            showVideoModal: false,
            videoData: null,
            videoUrl: null,
            isPlaying: false,
            watermarkPos: { x: 10, y: 10 },
            watermarkInterval: null,
            heartbeatInterval: null,
            lastSavedPosition: 0,
            heartbeatUrl: null,

            async openVideoPlayer(video) {
                if (video.status === 'processing') {
                    Swal.fire({
                        title: 'Video Processing',
                        text: 'This video is still being prepared. Please try again in 5-10 minutes. | جاري معالجة الفيديو، يرجى المحاولة مرة أخرى بعد 5-10 دقائق.',
                        icon: 'info',
                        showConfirmButton: true,
                        confirmButtonText: 'Understood | حسناً',
                        confirmButtonColor: '#4e73df'
                    });
                    return;
                }

                this.videoData = video;
                this.showVideoModal = true;

                // For external providers, we don't need a secure signed URL
                if (video.provider !== 'local') {
                    this.videoUrl = video.file_path;
                    this.startWatermark();
                    return;
                }

                try {
                    const response = await axios.get(route('student.secure_video.url', video.id));
                    this.videoUrl = response.data.url;
                    this.heartbeatUrl = response.data.heartbeat_url;
                    this.lastSavedPosition = video.last_position || 0;

                    this.$nextTick(() => {
                        const player = this.$refs.videoPlayer;
                        player.load();
                        player.currentTime = this.lastSavedPosition;
                        player.play().catch(e => console.log("Autoplay blocked"));
                    });
                    this.startHeartbeat();
                    this.startWatermark();
                } catch (err) {
                    Swal.fire('Error', 'Failed to load video stream.', 'error');
                    this.showVideoModal = false;
                }
            },


            closeVideoPlayer() {
                this.showVideoModal = false;
                this.isPlaying = false;
                if (this.$refs.videoPlayer) this.$refs.videoPlayer.pause();
                this.videoUrl = null;
                this.stopHeartbeat();
                this.stopWatermark();
            },

            startWatermark() {
                this.watermarkInterval = setInterval(() => {
                    this.watermarkPos = {
                        x: Math.floor(Math.random() * 70) + 5,
                        y: Math.floor(Math.random() * 80) + 10
                    };
                }, 4000);
            },

            stopWatermark() {
                if (this.watermarkInterval) clearInterval(this.watermarkInterval);
            },

            startHeartbeat() {
                this.heartbeatInterval = setInterval(() => {
                    if (this.isPlaying && this.$refs.videoPlayer && this.heartbeatUrl) {
                        this.sendHeartbeat();
                    }
                }, 10000);
            },

            stopHeartbeat() {
                if (this.heartbeatInterval) clearInterval(this.heartbeatInterval);
            },

            async sendHeartbeat() {
                const player = this.$refs.videoPlayer;
                const currentTime = Math.floor(player.currentTime);
                const duration = Math.floor(player.duration);
                
                try {
                    await axios.post(this.heartbeatUrl, {
                        current_position: currentTime,
                        duration: duration,
                        is_completed: (currentTime / duration) > 0.9
                    });
                } catch (e) {
                    console.error("Heartbeat failed", e);
                }
            },

            onTimeUpdate() {
                // Potential seek restrictions logic here
            },

            getYoutubeId(url) {
                if (!url) return '';
                // Handle provider_id if URL format fails
                if (url.length === 11 && !url.includes('.')) return url;
                
                const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
                const match = url.match(regExp);
                return (match && match[2].length === 11) ? match[2] : (this.videoData?.provider_id || url);
            },

            getVimeoId(url) {
                if (!url) return '';
                if (/^\d+$/.test(url)) return url;
                
                const match = url.match(/vimeo\.com\/(\d+)/);
                return match ? match[1] : (this.videoData?.provider_id || url);
            },


            async markAttendance() {
                this.processing = true;
                this.error = null;
                this.success = null;

                try {
                    const response = await fetch("{{ route('api.student.attendance.checkin', $session->uuid ?? $session->session_id) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({})
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.success = data.message || 'Attendance registered successfully!';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        throw new Error(data.error || 'Check-in failed');
                    }
                } catch (err) {
                    this.error = err.message;
                } finally {
                    this.processing = false;
                }
            }
        }
    }
</script>

<style>
    /* Premium Tabs Styles */
    #sessionTabs .nav-link {
        color: #64748b;
        transition: all 0.3s ease;
        position: relative;
    }
    #sessionTabs .nav-link:hover {
        background-color: #f8fafc;
        color: #4e73df;
    }
    #sessionTabs .nav-link.active {
        background-color: transparent !important;
        color: #4e73df !important;
        border-bottom: 3px solid #4e73df !important;
    }
    #sessionTabs .nav-link i {
        font-size: 1.1rem;
        transition: transform 0.3s ease;
    }
    #sessionTabs .nav-link.active i {
        transform: scale(1.1);
    }

    /* Animation & Polish */
    .transition-200 { transition: all 0.2s ease; }
    .transition-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 2rem rgba(13, 110, 253, 0.1) !important;
    }
    .hover-bg-light:hover {
        background-color: #f8fafc !important;
    }
    .extra-small { font-size: 0.75rem; }
    .text-purple { color: #6f42c1; }
    .btn-outline-purple { color: #6f42c1; border-color: #6f42c1; }
    .btn-outline-purple:hover { background-color: #6f42c1; color: white; }
    
    .animate-pulse {
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.02); }
    }
    
    .card { transition: border-color 0.3s ease, box-shadow 0.3s ease; }
    
    /* Video Player Styles */
    .bg-gradient-dark {
        background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
    }
    .select-none { user-select: none; }
    .pointer-events-none { pointer-events: none; }
    .z-3 { z-index: 3; }
    .z-2 { z-index: 2; }
    [x-cloak] { display: none !important; }
</style>
@endsection
