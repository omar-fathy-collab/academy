@extends('layouts.authenticated')

@section('title', 'Teacher Dashboard')

@section('content')
    <!-- Welcome Banner -->
    <div class="bg-primary text-white py-5 position-relative overflow-hidden mb-4" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%)">
        <div class="container-fluid position-relative z-index-1">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img
                        src="{{ $teacher->user->profile->profile_picture_url ?: asset('assets/user_image.jpg') }}"
                        alt="Profile"
                        class="rounded-circle border border-4 border-white shadow-lg bg-white"
                        style="width: 90px; height: 90px; object-fit: cover"
                    >
                </div>
                <div class="col">
                    <h2 class="fw-bold mb-1">Welcome back, {{ $teacher->teacher_name }}! 👋</h2>
                    <p class="mb-0 opacity-75">Here's your teaching summary and active tasks.</p>
                </div>
            </div>
        </div>
        <!-- Decorative Shape -->
        <svg class="position-absolute bottom-0 w-100" style="height: 30px" preserveAspectRatio="none" viewBox="0 0 1440 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 30L1440 30L1440 0C1440 0 1100 30 720 30C340 30 0 0 0 0L0 30Z" fill="var(--bg-main, #f8f9fa)" />
        </svg>
    </div>

    <div class="container-fluid pb-5">
        <!-- Top Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <div class="text-muted small fw-bold text-uppercase mb-2">My Groups</div>
                        <h3 class="fw-bold theme-text-main mb-0">{{ count($groups) }}</h3>
                        <div class="mt-3 text-muted small"><i class="fas fa-users me-1"></i> Active Classes</div>
                        <div class="position-absolute top-0 end-0 p-4 opacity-25">
                            <i class="fas fa-chalkboard-teacher fs-1 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <div class="text-muted small fw-bold text-uppercase mb-2">Total Earnings</div>
                        <h3 class="fw-bold text-success mb-0">{{ number_format($totalPaidWithAdjustments, 0) }} <span class="fs-6">EGP</span></h3>
                        <div class="mt-3 text-muted small"><i class="fas fa-money-bill-wave me-1"></i> Paid to date</div>
                        <div class="position-absolute top-0 end-0 p-4 opacity-25">
                            <i class="fas fa-wallet fs-1 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <div class="text-muted small fw-bold text-uppercase mb-2">Available Balance</div>
                        <h3 class="fw-bold text-primary mb-0">{{ number_format($availableToEarn, 0) }} <span class="fs-6">EGP</span></h3>
                        <div class="mt-3 fw-bold small text-info"><i class="fas fa-hand-holding-usd me-1"></i> Ready for payout</div>
                        <div class="position-absolute top-0 end-0 p-4 opacity-25">
                            <i class="fas fa-chart-line fs-1 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <div class="text-muted small fw-bold text-uppercase mb-2">Pending Grading</div>
                        <h3 class="fw-bold text-warning mb-0">
                            {{ collect($assignments)->sum('pending_grading') }}
                        </h3>
                        <div class="mt-3 text-muted small"><i class="fas fa-tasks me-1"></i> Requires action</div>
                        <div class="position-absolute top-0 end-0 p-4 opacity-25">
                            <i class="fas fa-clipboard-check fs-1 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column - Main Content -->
            <div class="col-xl-8 col-lg-7">
                <!-- Active Groups -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden theme-card">
                    <div class="card-header theme-card p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 theme-text-main">
                            <div class="d-inline-block bg-primary bg-opacity-10 p-2 rounded-3 me-2">
                                <i class="fas fa-users text-primary"></i>
                            </div>
                            My Active Groups
                        </h5>
                        <a href="#" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                    </div>
                    <div class="card-body p-0 theme-card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 theme-text-main">
                                <thead class="theme-thead small text-uppercase">
                                    <tr>
                                        <th class="px-4 py-3 border-0">Group Name</th>
                                        <th class="px-4 py-3 border-0 text-center">Schedule</th>
                                        <th class="px-4 py-3 border-0 text-center">Students</th>
                                        <th class="px-4 py-3 border-0 text-center">Salary Progress</th>
                                        <th class="px-4 py-3 border-0 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($groups as $group)
                                        <tr class="theme-tr">
                                            <td class="px-4 py-3">
                                                <div class="fw-bold theme-text-main">{{ $group->group_name }}</div>
                                                <div class="small text-muted text-truncate" style="max-width: 200px">{{ $group->course->course_name ?? 'N/A' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-center text-muted small">
                                                <span class="badge bg-light text-dark border"><i class="far fa-clock me-1"></i> {{ $group->schedule ?: 'N/A' }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="fw-bold theme-text-main">{{ $group->students_count ?: 0 }}</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="d-flex flex-column align-items-center">
                                                    <div class="small fw-bold mb-1 theme-text-main">{{ number_format($group->teacher_percentage, 0) }}% Share</div>
                                                    <div class="progress w-100" style="height: 6px">
                                                        @php
                                                            $percentage = ($group->paid_amount > 0 && $group->teacher_share > 0) ? ($group->paid_amount / $group->teacher_share) * 100 : 0;
                                                        @endphp
                                                        <div class="progress-bar {{ $group->salary_status === 'paid' ? 'bg-success' : 'bg-primary' }}" role="progressbar" style="width: {{ $percentage }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <a href="{{ route('groups.show', $group->group_id) }}" class="btn btn-sm btn-light border rounded-pill px-3 text-primary fw-medium">View Class</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-5 text-muted">You have no active groups.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Items to Grade -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 theme-card">
                    <div class="card-header theme-card p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 theme-text-main">
                            <div class="d-inline-block bg-warning bg-opacity-10 p-2 rounded-3 me-2">
                                <i class="fas fa-tasks text-warning"></i>
                            </div>
                            Items to Grade
                        </h5>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse(collect($assignments)->filter(fn($a) => $a->pending_grading > 0) as $assignment)
                            <div class="list-group-item p-4 theme-border bg-transparent">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h6 class="fw-bold theme-text-main mb-1">{{ $assignment->title }}</h6>
                                        <div class="text-muted small">
                                            <span class="bg-light px-2 py-1 rounded border me-2">{{ $assignment->group_name }}</span>
                                            <i class="far fa-calendar-alt me-1 text-danger"></i> Due: {{ \Carbon\Carbon::parse($assignment->due_date)->toFormattedDateString() }}
                                        </div>
                                    </div>
                                    <div class="col-md-5 mt-3 mt-md-0 d-flex justify-content-md-end align-items-center gap-3">
                                        <div class="text-center">
                                            <div class="fs-5 fw-bold text-warning">{{ $assignment->pending_grading }}</div>
                                            <div class="smaller text-uppercase fw-bold text-muted">Pending</div>
                                        </div>
                                        <a href="{{ route('assignments.submissions', $assignment->assignment_id) }}" class="btn btn-warning btn-sm text-dark px-3 rounded-pill fw-bold shadow-sm">
                                            Grade Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-5 text-center text-muted">
                                <div class="fs-2 mb-2 text-success opacity-50"><i class="fas fa-check-circle"></i></div>
                                <h6 class="fw-bold theme-text-main">All caught up!</h6>
                                <p class="small mb-0">No assignments pending grading right now.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="col-xl-4 col-lg-5">
                <!-- Financial Snapshot -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #1f2937, #111827); color: white">
                    <div class="card-header bg-transparent border-bottom border-light border-opacity-10 p-4 pb-3">
                        <h5 class="fw-bold mb-0 text-white"><i class="fas fa-chart-pie me-2 text-info"></i> Financial Snapshot</h5>
                    </div>
                    <div class="card-body p-4 position-relative z-index-1">
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom border-light border-opacity-10">
                            <span class="text-white opacity-75">Your Total Share</span>
                            <span class="fw-bold fs-5 text-white">{{ number_format($totalTeacherShare, 0) }} <small>EGP</small></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-white opacity-75">Base Paid</span>
                            <span class="fw-bold text-success">{{ number_format($totalEarned, 0) }} <small>EGP</small></span>
                        </div>
                        @if($totalBonuses > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-white opacity-75">Bonuses</span>
                                <span class="fw-bold text-success">+{{ number_format($totalBonuses, 0) }} <small>EGP</small></span>
                            </div>
                        @endif
                        @if($totalDeductions > 0)
                            <div class="d-flex justify-content-between mb-3 pb-2 border-bottom border-light border-opacity-10">
                                <span class="text-white opacity-75">Deductions</span>
                                <span class="fw-bold text-danger">-{{ number_format($totalDeductions, 0) }} <small>EGP</small></span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between mt-3 bg-white bg-opacity-10 p-3 rounded-3">
                            <span class="fw-bold text-white">Remaining Balance</span>
                            <span class="fw-bold text-info fs-5">{{ number_format($totalRemaining, 0) }} <small>EGP</small></span>
                        </div>
                    </div>
                    <!-- Decorative Graph SVG -->
                    <svg class="position-absolute bottom-0 w-100 opacity-25" style="height: 60px; left: 0" preserveAspectRatio="none" viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0,60 L0,30 C320,60 420,-30 720,30 C1020,90 1120,0 1440,30 L1440,60 L0,60 Z" fill="#3b82f6" />
                    </svg>
                </div>

                <!-- Recent Notifications -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
                    <div class="card-header theme-card p-4 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 theme-text-main">Recent Notifications</h6>
                        <span class="badge bg-danger rounded-pill px-2">{{ count($notifications) }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            @forelse($notifications as $notif)
                                <div class="list-group-item p-3 theme-border bg-transparent list-group-item-action cursor-pointer">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-bold theme-text-main small mb-1">{{ $notif->title ?: 'New Alert' }}</div>
                                            <p class="text-muted smaller mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">
                                                {{ $notif->message ?: 'You have a new system notification.' }}
                                            </p>

                                            <div class="smaller text-muted">
                                                <i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-4 text-center text-muted small">No new notifications.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="card-footer theme-card border-top-0 p-3 text-center">
                        <a href="#" class="text-decoration-none fw-bold text-primary small">View All Notifications</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .smaller { font-size: 0.75rem; }
        .cursor-pointer { cursor: pointer; }
        .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .theme-card-body { background-color: var(--card-bg) !important; }
        .theme-text-main { color: var(--text-main) !important; }
        .theme-border { border-color: var(--border-color) !important; }
        .theme-thead { background-color: var(--bg-main) !important; }
        .theme-tr { border-bottom: 1px solid var(--border-color) !important; }
    </style>
@endsection
