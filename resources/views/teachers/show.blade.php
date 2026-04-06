@extends('layouts.authenticated')

@section('title', "Profile - " . ($teacher->teacher_name ?? 'Loading...'))

@section('content')
    <!-- Premium Header -->
    <div class="bg-primary text-white pt-5 pb-4 mb-4 position-relative overflow-hidden shadow-sm" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%)">
        <div class="container-fluid position-relative z-index-1">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img
                        src="{{ $teacher->profile_picture_url ?: asset('assets/user_image.jpg') }}"
                        alt="Profile"
                        class="rounded-circle border border-4 border-white shadow-lg"
                        style="width: 120px; height: 120px; object-fit: cover"
                    >
                </div>
                <div class="col">
                    <h1 class="fw-bold mb-1 display-5">{{ $teacher->teacher_name }}</h1>
                    <p class="mb-2 fs-5 opacity-75">
                        <i class="fas fa-envelope me-2"></i> {{ $teacher->email ?: 'No email provided' }}
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <div class="glass-badge px-3 py-2 rounded-pill d-flex align-items-center shadow-sm border border-white border-opacity-20">
                            <i class="fas fa-star text-warning me-2"></i> 
                            <span class="fw-bold">{{ $avg_rating }}</span>
                            <span class="opacity-75 ms-1 smaller">/ 5.0 ({{ $rating_count }} reviews)</span>
                        </div>
                        <div class="glass-badge px-3 py-2 rounded-pill d-flex align-items-center shadow-sm border border-white border-opacity-20">
                            <i class="fas fa-users text-info me-2"></i> 
                            <span class="fw-bold">{{ count($groups) }}</span>
                            <span class="opacity-75 ms-1 smaller">Active Groups</span>
                        </div>
                        <div class="glass-badge px-3 py-2 rounded-pill d-flex align-items-center shadow-sm border border-white border-opacity-20">
                            <i class="fas fa-calendar-check text-success me-2"></i> 
                            <span class="fw-bold">Joined:</span>
                            <span class="opacity-75 ms-1 smaller text-truncate">{{ $teacher->hire_date ? \Carbon\Carbon::parse($teacher->hire_date)->toFormattedDateString() : 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Decorative Elements -->
        <div class="position-absolute top-0 end-0 opacity-10 h-100" style="right: -5%; transform: rotate(15deg)">
            <i class="fas fa-chalkboard-teacher" style="font-size: 15rem"></i>
        </div>
    </div>

    <div class="container-fluid min-vh-100 pb-5" x-data="{ activeTab: 'overview' }">
        <div class="row g-4">
            <!-- Sidebar Info -->
            <div class="col-xl-3 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4 theme-card">
                    <div class="card-header theme-card-header p-4 border-bottom-0">
                        <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-info-circle text-primary me-2"></i> Contact Info</h5>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0 py-3 bg-transparent theme-border">
                                <div class="text-muted small fw-bold mb-1 text-uppercase">Phone</div>
                                <div class="fw-medium theme-text-main"><i class="fas fa-phone fa-fw me-2 text-muted text-opacity-50"></i> {{ $teacher->phone_number ?: 'Not provided' }}</div>
                            </li>
                            <li class="list-group-item px-0 py-3 bg-transparent theme-border">
                                <div class="text-muted small fw-bold mb-1 text-uppercase">Address</div>
                                <div class="fw-medium theme-text-main"><i class="fas fa-map-marker-alt fa-fw me-2 text-muted text-opacity-50"></i> {{ $teacher->address ?: 'Not provided' }}</div>
                            </li>
                            <li class="list-group-item px-0 py-3 bg-transparent border-0">
                                <div class="text-muted small fw-bold mb-1 text-uppercase">Status</div>
                                <div>
                                    <span class="badge rounded-pill px-3 py-2 {{ $teacher->is_active ? 'bg-success' : 'bg-danger' }}">
                                        {{ $teacher->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Recent Reviews Summary -->
                <div class="card border-0 shadow-sm rounded-4 theme-card">
                    <div class="card-header theme-card-header p-4 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 theme-text-main">Recent Reviews</h6>
                        <i class="fas fa-chevron-right text-muted small cursor-pointer" @click="activeTab = 'reviews'"></i>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse(collect($ratings)->take(3) as $rating)
                                <div class="list-group-item p-4 theme-border bg-transparent">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div class="fw-bold small theme-text-main">{{ $rating->student_name }}</div>
                                        <div class="text-warning small">
                                            @for($i = 0; $i < 5; $i++)
                                                <i class="fa{{ $i < $rating->rating_value ? 's' : 'r' }} fa-star"></i>
                                            @endfor
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-0 font-italic">"{{ $rating->comments ?: 'No comment provided' }}"</p>
                                </div>
                            @empty
                                <div class="p-4 text-center text-muted small">No reviews available yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-xl-9 col-lg-8">
                <!-- Navigation Tabs -->
                <ul class="nav nav-pills nav-fill theme-card p-2 rounded-pill shadow-sm mb-4 gap-2">
                    <li class="nav-item">
                        <button class="nav-link rounded-pill fw-bold" :class="activeTab === 'overview' ? 'active shadow-sm' : 'text-muted'" @click="activeTab = 'overview'">
                            <i class="fas fa-layer-group me-2"></i> Groups ({{ count($groups) }})
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill fw-bold" :class="activeTab === 'sessions' ? 'active shadow-sm' : 'text-muted'" @click="activeTab = 'sessions'">
                            <i class="fas fa-video me-2"></i> Sessions ({{ count($sessions) }})
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill fw-bold" :class="activeTab === 'assignments' ? 'active shadow-sm' : 'text-muted'" @click="activeTab = 'assignments'">
                            <i class="fas fa-tasks me-2"></i> Assignments ({{ count($assignments) }})
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill fw-bold" :class="activeTab === 'reviews' ? 'active shadow-sm' : 'text-muted'" @click="activeTab = 'reviews'">
                            <i class="fas fa-star me-2"></i> Reviews ({{ $rating_count ?: 0 }})
                        </button>
                    </li>
                </ul>

                <!-- Content Panels -->
                <div class="bg-transparent">

                    <!-- Groups Tab -->
                    <div class="row g-4 fade show active" x-show="activeTab === 'overview'" x-transition>
                        <div class="col-12 d-flex justify-content-between align-items-center mb-2">
                            <h4 class="fw-bold mb-0 theme-text-main">Assigned Groups</h4>
                            {{-- Optional: Action button --}}
                        </div>
                        @forelse($groups as $group)
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden theme-card card-hover">
                                    <div class="card-body p-4 position-relative">
                                        <div class="d-flex justify-content-between mb-3">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill fw-bold shadow-sm">
                                                {{ $group->course_name }}
                                            </span>
                                            <button class="btn btn-sm btn-light border theme-border theme-card rounded-circle"><i class="fas fa-ellipsis-v text-muted"></i></button>
                                        </div>
                                        <h5 class="fw-bold theme-text-main mb-1"><a href="{{ route('groups.show', $group->group_id) }}" class="text-decoration-none theme-text-main">{{ $group->group_name }}</a></h5>
                                        <p class="text-muted small mb-4"><i class="far fa-clock me-1"></i> {{ $group->schedule }}</p>

                                        <div class="row text-center mt-auto align-items-center">
                                            <div class="col-6 border-end theme-border">
                                                <div class="fs-3 fw-bold theme-text-main">{{ $group->student_count }}</div>
                                                <div class="text-muted smaller text-uppercase fw-bold">Students</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="fs-3 fw-bold text-info">{{ $group->session_count }}</div>
                                                <div class="text-muted smaller text-uppercase fw-bold">Sessions</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer theme-card-footer border-top-0 p-3 text-center">
                                        <a href="{{ route('groups.show', $group->group_id) }}" class="btn btn-link text-decoration-none shadow-none fw-bold p-0 text-primary">View Group Details <i class="fas fa-arrow-right ms-1"></i></a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <div class="text-muted fs-1 mb-3"><i class="fas fa-layer-group opacity-25"></i></div>
                                <p class="text-muted fw-medium">Teacher is not assigned to any groups yet.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Sessions Tab -->
                    <div class="card border-0 shadow-sm rounded-4 fade show active overflow-hidden theme-card" x-show="activeTab === 'sessions'" x-transition>
                        <div class="card-header theme-card-header p-4 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-video text-primary me-2"></i> Session History</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 theme-text-main">
                                    <thead class="theme-card-footer text-muted small text-uppercase">
                                        <tr>
                                            <th class="px-4 py-3 theme-border">Date & Time</th>
                                            <th class="px-4 py-3 theme-border">Topic</th>
                                            <th class="px-4 py-3 theme-border">Group</th>
                                            <th class="px-4 py-3 theme-border text-center">Attendance</th>
                                            <th class="px-4 py-3 theme-border text-end">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($sessions as $session)
                                            <tr class="theme-border">
                                                <td class="px-4 py-3 theme-border">
                                                    <div class="fw-bold theme-text-main">{{ \Carbon\Carbon::parse($session->session_date)->toFormattedDateString() }}</div>
                                                    <div class="text-muted small">{{ $session->start_time }} - {{ $session->end_time }}</div>
                                                </td>
                                                <td class="px-4 py-3 fw-medium theme-text-main theme-border">
                                                    {{ $session->topic }}
                                                </td>
                                                <td class="px-4 py-3 text-muted small theme-border">
                                                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">{{ $session->group_name }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-center theme-border">
                                                    <div class="d-inline-flex gap-1 align-items-center">
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2" title="Present">{{ $session->present_count ?: 0 }}</span>
                                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2" title="Absent">{{ $session->absent_count ?: 0 }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-end theme-border">
                                                    <a href="{{ route('sessions.show', $session->uuid ?? $session->session_id) }}" class="btn btn-sm btn-light theme-card border theme-border px-3 rounded-pill fw-medium text-primary">View</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No sessions recorded.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Tab -->
                    <div class="card border-0 shadow-sm rounded-4 fade show active overflow-hidden theme-card" x-show="activeTab === 'assignments'" x-transition>
                        <div class="card-header theme-card-header p-4 border-bottom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-tasks text-success me-2"></i> Created Assignments</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            @forelse($assignments as $assignment)
                                <div class="list-group-item p-4 theme-border bg-transparent align-items-center d-flex justify-content-between">
                                    <div>
                                        <h6 class="fw-bold mb-1 theme-text-main">{{ $assignment->title }}</h6>
                                        <div class="text-muted small d-flex gap-3 align-items-center">
                                            <span><i class="far fa-calendar-alt text-danger me-1"></i> Due: {{ \Carbon\Carbon::parse($assignment->due_date)->toFormattedDateString() }}</span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{ $assignment->group_name }}</span>
                                        </div>
                                    </div>
                                    <div class="text-end text-sm">
                                        <div class="fw-bold fs-5 text-success">{{ $assignment->submission_count }}</div>
                                        <div class="text-muted smaller text-uppercase fw-bold">Submissions</div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-5 text-center text-muted">No assignments created yet.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Reviews Tab -->
                    <div class="card border-0 shadow-sm rounded-4 fade show active overflow-hidden theme-card" x-show="activeTab === 'reviews'" x-transition>
                        <div class="card-header theme-card-header p-4 border-bottom mb-0">
                            <h5 class="fw-bold mb-0 theme-text-main">Student Reviews ({{ $rating_count }})</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @forelse($ratings as $rating)
                                    <div class="list-group-item p-4 theme-border bg-transparent flex-column align-items-start">
                                        <div class="d-flex w-100 justify-content-between mb-2">
                                            <h6 class="mb-1 fw-bold theme-text-main">{{ $rating->student_name }}</h6>
                                            <smalc class="text-muted"><i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($rating->rated_at)->toFormattedDateString() }}</smalc>
                                        </div>
                                        <div class="text-warning mb-2 fs-6">
                                            @for($i = 0; $i < 5; $i++)
                                                <i class="fa{{ $i < $rating->rating_value ? 's' : 'r' }} fa-star me-1"></i>
                                            @endfor
                                        </div>
                                        <p class="mb-2 theme-text-main font-italic opacity-75">"{{ $rating->comments }}"</p>
                                        <div class="d-flex text-muted smaller theme-badge-bg d-inline-block px-3 py-2 rounded-3 mt-2">
                                            <div class="me-3"><strong class="theme-text-main">Session:</strong> {{ $rating->topic }} ({{ \Carbon\Carbon::parse($rating->session_date)->toFormattedDateString() }})</div>
                                            <div><strong class="theme-text-main">Group:</strong> {{ $rating->group_name }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-5 text-center text-muted">No reviews recorded yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-card-header { background-color: var(--card-bg) !important; }
        .theme-card-footer { background-color: var(--bg-main) !important; opacity: 0.9; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-badge-bg { background-color: var(--bg-main) !important; }
        
        .smaller { font-size: 0.70rem; }
        .smaller { font-size: 0.75rem; }
        .card-hover { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .nav-pills .nav-link.active { background-color: #f0fdf4 !important; color: #16a34a !important; }
        [data-bs-theme='dark'] .nav-pills .nav-link.active { background-color: #064e3b !important; color: #34d399 !important; }
        .cursor-pointer { cursor: pointer; }
        .glass-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #fff;
        }
    </style>
@endsection
