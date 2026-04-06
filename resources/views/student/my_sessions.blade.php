@extends('layouts.authenticated')

@section('title', 'My Sessions')

@section('content')
<div class="container-fluid py-4 p-0" x-data="sessionManager()">
    <!-- Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 fw-bold text-dark">
            <i class="fas fa-chalkboard text-primary me-2"></i>
            My Sessions
        </h1>
        <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary rounded-pill shadow-sm fw-bold px-3">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Performance Banner -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 bg-white overflow-hidden">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-3 text-center border-end">
                    <div class="display-4 fw-bold text-primary">{{ $totalSessions }}</div>
                    <div class="text-muted fw-bold small text-uppercase">Total Sessions</div>
                </div>
                <div class="col-md-3 text-center border-end">
                    <div class="display-4 fw-bold text-success">{{ $attendedSessions }}</div>
                    <div class="text-muted fw-bold small text-uppercase">Attended</div>
                </div>
                @php
                    $attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;
                @endphp
                <div class="col-md-3 text-center border-end">
                    <div class="display-4 fw-bold text-info">{{ $attendanceRate }}%</div>
                    <div class="text-muted fw-bold small text-uppercase">Attendance Rate</div>
                </div>
                <div class="col-md-3">
                    <div class="px-3">
                        <div class="progress rounded-pill mb-2" style="height: 12px;">
                            <div
                                class="progress-bar bg-info progress-bar-striped progress-bar-animated"
                                style="width: {{ $attendanceRate }}%"
                            ></div>
                        </div>
                        <div class="text-muted small">Keep it up! Regular attendance is key to success.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs for Sessions -->
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-2 rounded-pill shadow-sm" x-ref="pillTabs">
        <li class="nav-item">
            <button class="nav-link rounded-pill fw-bold" :class="activeTab === 'upcoming' ? 'active bg-primary' : 'text-muted'" @click="activeTab = 'upcoming'">
                Upcoming ({{ count($upcomingSessions) }})
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill fw-bold" :class="activeTab === 'past' ? 'active bg-primary' : 'text-muted'" @click="activeTab = 'past'">
                Past ({{ count($pastSessions) }})
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Upcoming Sessions -->
        <div x-show="activeTab === 'upcoming'" x-transition x-cloak>
            @forelse($upcomingSessions as $item)
                @include('student.partials.session-card', ['item' => $item, 'type' => 'upcoming'])
            @empty
                <div class="py-5 text-center text-muted bg-white rounded-4 shadow-sm">
                    <i class="fas fa-calendar-check fa-4x mb-3 opacity-25"></i>
                    <h4>No upcoming sessions</h4>
                    <p>You are all caught up for now!</p>
                </div>
            @endforelse
        </div>

        <!-- Past Sessions -->
        <div x-show="activeTab === 'past'" x-transition x-cloak>
            @forelse($pastSessions as $item)
                @include('student.partials.session-card', ['item' => $item, 'type' => 'past'])
            @empty
                <div class="py-5 text-center text-muted bg-white rounded-4 shadow-sm">
                    <i class="fas fa-history fa-4x mb-3 opacity-25"></i>
                    <h4>No past sessions found</h4>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal for WiFi Check-in -->
    <div x-show="showWifiModal" class="modal-backdrop fade show"></div>
    <div class="modal fade show" x-show="showWifiModal" tabindex="-1" style="display: block;" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold">WiFi Attendance Check-In</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showWifiModal = false"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-wifi fa-3x text-primary"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2" x-text="activeSession?.session?.topic || 'Session Check-In'"></h4>
                        <p class="text-muted">You must be connected to the SAME WiFi network as the instructor to check in.</p>
                    </div>

                    <div x-show="!processing" class="d-grid gap-3">
                        <button @click="markAttendance(activeSession.session, true)" class="btn btn-primary btn-lg rounded-pill fw-bold shadow">
                            <i class="fas fa-check-circle me-2"></i> Check In Now
                        </button>
                        <button @click="showWifiModal = false" class="btn btn-light rounded-pill fw-bold">Cancel</button>
                    </div>

                    <div x-show="processing" class="py-3">
                        <div class="spinner-border text-primary mb-3"></div>
                        <p class="mb-0 fw-bold">Authenticating with network...</p>
                    </div>

                    <div x-show="error" class="alert alert-danger mt-4 rounded-3 border-0 shadow-sm" x-text="error"></div>
                    <div x-show="success" class="alert alert-success mt-4 rounded-3 border-0 shadow-sm" x-text="success"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function sessionManager() {
        return {
            activeTab: 'upcoming',
            showWifiModal: false,
            activeSession: null,
            processing: false,
            error: null,
            success: null,

            openCheckIn(item) {
                this.activeSession = item;
                this.showWifiModal = true;
                this.error = null;
                this.success = null;
                this.processing = false;
            },

            async markAttendance(session, isWifi = false) {
                this.processing = true;
                this.error = null;
                this.success = null;
                
                try {
                    const response = await fetch(`/api/student/sessions/${session.uuid || session.session_id}/checkin`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({})
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.success = data.message || 'Attendance recorded successfully!';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        throw new Error(data.error || 'Failed to record attendance');
                    }
                } catch (err) {
                    this.error = err.message;
                } finally {
                    this.processing = false;
                }
            }
        };
    }
</script>

<style>
    .nav-pills .nav-link { 
        color: #6c757d; 
        padding: 0.8rem; 
        transition: all 0.2s ease;
    }
    .modal-backdrop {
        background-color: rgba(0,0,0,0.5);
    }
    [x-cloak] { display: none !important; }
</style>
@endsection
