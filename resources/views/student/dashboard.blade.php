@extends('layouts.authenticated')

@section('title', 'Dashboard')

@section('content')
<div x-data="studentDashboard()" class="container-fluid p-0">
    <!-- Academy Announcements -->
    @if(count($academyAnnouncements) > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 rounded-4 shadow-sm bg-dark text-white overflow-hidden">
                    <div class="card-body p-0">
                        <div id="announcementCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                @foreach($academyAnnouncements as $index => $ann)
                                    <div class="carousel-item {{ $index === 0 ? 'active' : '' }} p-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary bg-opacity-25 p-3">
                                                <i class="fas fa-bullhorn text-primary fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1 text-primary">Academy News</h6>
                                                <p class="mb-0 fw-bold">{{ $ann->title }}</p>
                                                <p class="small mb-0 opacity-75 text-truncate" style="max-width: 600px;">{{ $ann->message }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Welcome Header -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden" 
         style="background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%)">
        <div class="card-body p-4 p-md-5 text-white position-relative">
            <div class="position-relative z-index-1">
                <h1 class="display-6 fw-bold mb-2">Welcome back, {{ $student->student_name ?? Auth::user()->name }}!</h1>
                <p class="lead mb-0 opacity-75">Your academic journey is looking great. Here's your current standing.</p>
            </div>
            <i class="fas fa-graduation-cap position-absolute end-0 bottom-0 mb-n4 me-n4 opacity-25 d-none d-sm-block text-white" 
               style="font-size: 12rem;"></i>
        </div>
    </div>

    <!-- Academic Progress Gauges -->
    <div class="row g-4 mb-4">
        @foreach($groups->take(3) as $group)
            <div class="col-md-4">
                <div class="card border-0 rounded-4 shadow-sm text-center h-100 bg-white">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-muted small text-uppercase mb-4">{{ $group->group_name }}</h6>
                        <div class="position-relative d-inline-flex mb-3">
                            <svg class="progress-ring" width="120" height="120">
                                <circle class="progress-ring__circle-bg" stroke="rgba(0,0,0,0.05)" stroke-width="8" fill="transparent" r="50" cx="60" cy="60"/>
                                <circle class="progress-ring__circle" stroke="#0d6efd" stroke-width="8" 
                                        stroke-dasharray="314.15" 
                                        stroke-dashoffset="{{ 314.15 - (314.15 * ($attendanceStats[$group->group_id] ?? 0) / 100) }}" 
                                        stroke-linecap="round" fill="transparent" r="50" cx="60" cy="60"/>
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <h3 class="fw-bold mb-0 text-primary">{{ $attendanceStats[$group->group_id] ?? 0 }}%</h3>
                                <p class="extra-small text-muted mb-0">Attendance</p>
                            </div>
                        </div>
                        <p class="small text-muted mb-0">Based on archived sessions</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Quick Performance Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3 d-inline-block mb-3">
                        <i class="fas fa-brain fa-xl"></i>
                    </div>
                    <h4 class="fw-bold mb-1">{{ $avgQuizScore }}<small class="text-muted fs-6">/10</small></h4>
                    <p class="text-muted mb-0 small fw-bold">Quiz Average</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-info bg-opacity-10 text-info p-3 d-inline-block mb-3">
                        <i class="fas fa-tasks fa-xl"></i>
                    </div>
                    <h4 class="fw-bold mb-1">{{ $avgAssignmentScore }}<small class="text-muted fs-6">/100</small></h4>
                    <p class="text-muted mb-0 small fw-bold">Assignment Score</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 d-inline-block mb-3">
                        <i class="fas fa-certificate fa-xl"></i>
                    </div>
                    <h4 class="fw-bold mb-1">{{ count($certificates) }}</h4>
                    <p class="text-muted mb-0 small fw-bold">Earned Certificates</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 rounded-4 shadow-sm bg-white h-100">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-3 d-inline-block mb-3">
                        <i class="fas fa-file-invoice-dollar fa-xl"></i>
                    </div>
                    <h5 class="fw-bold mb-1 text-danger">EGP {{ number_format($invoiceSummary->total_balance ?? 0, 0) }}</h5>
                    <p class="text-muted mb-0 small fw-bold">Total Due</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Activity Timeline -->
    <div class="row g-4 mb-4">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-grid gap-3">
                        <a href="{{ route('groups.index') }}" class="btn btn-light border rounded-pill p-3 text-start d-flex align-items-center transition-all hover-bg-primary">
                            <i class="fas fa-plus-circle text-primary me-3 fa-lg"></i>
                            <div>
                                <div class="fw-bold small">Join New Group | انضم لمجموعة جديدة</div>
                                <div class="extra-small text-muted">Explore available courses and join now | استكشف الدورات المتاحة وانضم الآن</div>

                            </div>
                        </a>
                        <a href="{{ route('student.certificates.index') }}" class="btn btn-light border rounded-pill p-3 text-start d-flex align-items-center transition-all hover-bg-primary">
                            <i class="fas fa-award text-success me-3 fa-lg"></i>
                            <div>
                                <div class="fw-bold small">Request Certificate | طلب شهادة</div>
                                <div class="extra-small text-muted">Claim your achievement records</div>
                            </div>
                        </a>
                        <a href="{{ route('student.library') }}" class="btn btn-light border rounded-pill p-3 text-start d-flex align-items-center transition-all hover-bg-primary">
                            <i class="fas fa-book-open text-info me-3 fa-lg"></i>
                            <div>
                                <div class="fw-bold small">Browse Library</div>
                                <div class="extra-small text-muted">Access books and video materials</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="col-lg-8">
            <div class="card border-0 rounded-4 shadow-sm h-100 bg-white">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-stream me-2"></i>Recent Activity Timeline</h6>
                </div>
                <div class="card-body p-4">
                    <div class="timeline-container position-relative">
                        @forelse(array_slice($sessionRows, -5) as $row)
                            <div class="timeline-item position-relative ps-4 pb-4 border-start theme-border ms-2">
                                <a href="{{ route('student.session_details', $row['uuid'] ?? $row['session_id']) }}" class="text-decoration-none transition-200 d-block">
                                    <div class="timeline-dot position-absolute start-0 translate-middle-x rounded-circle border-4 border-white shadow-sm {{ $row['attendance'] === 'present' ? 'bg-success' : 'bg-danger' }}" 
                                         style="width: 16px; height: 16px; left: -1px; top: 0;"></div>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="fw-bold mb-1 small text-dark hover-text-primary">{{ $row['topic'] }}</h6>
                                            <p class="extra-small text-muted mb-0">
                                                <i class="fas fa-clock me-1"></i> {{ \Carbon\Carbon::parse($row['date'])->format('M d, Y') }} | {{ $row['group'] }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="badge {{ $row['attendance'] === 'present' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} rounded-pill border py-1 px-2 extra-small">
                                                {{ ucfirst($row['attendance']) }}
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @empty
                            <div class="text-center py-4 opacity-50">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p class="small">No recent activity detected.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Meetings Notification -->
    
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <!-- Upcoming Sessions -->
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-calendar-alt me-2"></i>Upcoming Sessions</h5>
                    <a href="{{ route('student.my_sessions') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
                </div>
                <div class="card-body p-0">
                    @forelse($soonSessions as $session)
                        <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <a href="{{ route('student.session_details', $session['uuid'] ?? $session['session_id']) }}" class="text-decoration-none transition-200">
                                    <h6 class="fw-bold mb-1 text-dark hover-text-primary">{{ $session['topic'] }}</h6>
                                </a>
                                <div class="text-muted small">
                                    <i class="fas fa-users me-1 text-primary"></i>{{ $session['group'] }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($session['date'])->format('M d, Y') }}</div>
                                <div class="text-muted small mb-2">{{ $session['time'] }}</div>

                                @if($session['is_today'] && !$session['is_past'] && (!isset($session['attendance']) || $session['attendance']['status'] !== 'present'))
                                    @if($session['requires_proximity'])
                                        <button @click="openCheckIn(@js($session))" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                            <i class="fas fa-wifi me-1"></i> Check In
                                        </button>
                                    @else
                                        <button @click="markPresent(@js($session))" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" :disabled="processingAttendance === {{ $session['session_id'] }}">
                                            <span x-show="processingAttendance !== {{ $session['session_id'] }}">Mark Present</span>
                                            <span x-show="processingAttendance === {{ $session['session_id'] }}">Wait...</span>
                                        </button>
                                    @endif
                                @endif

                                @if(isset($session['attendance']) && $session['attendance']['status'] === 'present')
                                    <span class="badge bg-success-subtle text-success rounded-pill border border-success-subtle px-3 py-2">
                                        <i class="fas fa-check-circle me-1"></i> Present
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-calendar-check fa-3x mb-3 opacity-25"></i>
                            <p class="mb-0">No upcoming sessions scheduled.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Recent Materials -->
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-folder-open me-2 text-warning"></i>Recent Materials</h5>
                    <a href="{{ route('student.materials.index') }}" class="btn btn-sm btn-outline-warning rounded-pill px-3">All Materials</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                                @foreach($recentMaterials as $material)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-3 p-3 me-3 text-danger">
                                                    <i class="fas fa-file-pdf fs-5"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $material->original_name }}</div>
                                                    <div class="text-muted extra-small">{{ $material->session->topic }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <a href="{{ route('student.session.material.download', ['session_id' => $material->session_id, 'file_name' => $material->original_name]) }}" class="btn btn-sm btn-light rounded-pill px-3">
                                                <i class="fas fa-download me-1"></i> Download
                                            </a>

                                        </td>
                                    </tr>
                                @endforeach
                                @if(count($recentMaterials) === 0)
                                    <tr><td colspan="2" class="p-5 text-center text-muted">No materials available.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Assignments & Quizzes -->
        <div class="col-lg-4">
            <!-- Pending Assignments -->
            <div class="card border-0 rounded-4 shadow-sm mb-4 bg-primary text-white">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="fas fa-tasks me-2"></i>Pending Tasks</h5>
                    @forelse($soonAssignments as $assignment)
                        <div class="mb-3 p-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-25 position-relative">
                            <a href="{{ route('student.submit_assignment', ['id' => $assignment['id']]) }}" class="text-white text-decoration-none stretched-link">
                                <h6 class="fw-bold mb-1">{{ $assignment['title'] }}</h6>
                                <div class="small opacity-75">
                                    <i class="fas fa-clock me-1"></i> Due: {{ \Carbon\Carbon::parse($assignment['due'])->format('M d, Y') }}
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <p class="mb-0">All caught up!</p>
                        </div>
                    @endforelse
                    <a href="{{ route('student.assignments') }}" class="btn btn-light btn-sm rounded-pill w-100 fw-bold mt-2">All Assignments</a>
                </div>
            </div>

            <!-- Unpaid Invoices -->
            @if(count($unpaidInvoices) > 0)
                <div class="card border-0 rounded-4 shadow-sm mb-4 border-start border-5 border-warning">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-bold text-warning"><i class="fas fa-file-invoice-dollar me-2"></i>Unpaid Invoices</h6>
                        <span class="badge bg-warning text-dark rounded-pill">{{ count($unpaidInvoices) }}</span>
                    </div>
                    <div class="card-body p-0">
                        @foreach($unpaidInvoices as $inv)
                            <div class="p-4 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold text-dark small">Invoice #{{ $inv->invoice_number }}</div>
                                    <div class="fw-bold text-danger small">EGP {{ number_format($inv->amount - $inv->amount_paid, 2) }}</div>
                                </div>
                                <div class="text-muted extra-small mb-2">{{ $inv->group->group_name }}</div>
                                <button @click="openPaymentModal(@js($inv))" class="btn btn-sm btn-outline-warning rounded-pill w-100 fw-bold">
                                    <i class="fas fa-upload me-1"></i> Submit Payment Proof
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Active Quizzes -->
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold">Active Quizzes</h6>
                    <span class="badge bg-danger rounded-pill">{{ count($upcomingQuizzes) }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($upcomingQuizzes as $item)
                        <div class="p-4 border-bottom">
                            <h6 class="fw-bold mb-1 small">{{ $item['quiz']->title }}</h6>
                            <div class="text-muted extra-small mb-2">Group: {{ $item['group_name'] }}</div>
                            <a href="{{ route('student.take_quiz', ['quiz' => $item['quiz']->uuid]) }}" class="btn btn-sm btn-outline-primary rounded-pill w-100 fw-bold">Take Quiz</a>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted small">No active quizzes</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- WiFi Check-In Modal -->
    <div x-show="showWiFiModal" class="modal fade" :class="{ 'show d-block': showWiFiModal }" x-cloak style="background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-wifi text-primary me-2"></i> WiFi Check-In</h5>
                    <button type="button" class="btn-close" @click="showWiFiModal = false"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="text-muted small mb-4">You must be connected to the center's WiFi network to record attendance.</p>
                    
                    <div class="mb-4">
                        <template x-if="checkInStatus === 'idle'">
                            <div class="rounded-circle bg-light text-primary d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-map-marker-alt fa-3x"></i>
                            </div>
                        </template>
                        <template x-if="checkInStatus === 'scanning'">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-wifi fa-3x fa-beat"></i>
                            </div>
                        </template>
                        <template x-if="checkInStatus === 'success'">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-check-circle fa-4x"></i>
                            </div>
                        </template>
                        <template x-if="checkInStatus === 'error'">
                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-times-circle fa-4x"></i>
                            </div>
                        </template>
                    </div>

                    <h5 x-show="checkInStatus === 'scanning'" class="fw-bold">Verifying connection...</h5>
                    <h5 x-show="checkInStatus === 'success'" class="fw-bold text-success">Attendance recorded successfully!</h5>
                    <div x-show="checkInStatus === 'error'" class="alert alert-danger rounded-4 py-2 small" x-text="errorMessage"></div>

                    <div class="mt-4">
                        <button x-show="checkInStatus === 'idle' || checkInStatus === 'error'" @click="handleCheckIn()" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm">
                            <span x-text="checkInStatus === 'error' ? 'Try Again' : 'Record Attendance Now'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Proof Modal -->
    <div x-show="showPaymentModal" class="modal fade" :class="{ 'show d-block': showPaymentModal }" x-cloak style="background: rgba(0,0,0,0.6);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Submit Payment Proof</h5>
                    <button type="button" class="btn-close" @click="showPaymentModal = false"></button>
                </div>
                <form @submit.prevent="handlePaymentSubmit">
                    <div class="modal-body px-4 pb-4">
                        <div class="alert alert-info border-0 rounded-4 small py-2 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Invoice #<span x-text="selectedInvoice?.invoice_number"></span> - Due: <strong x-text="formatCurrency(selectedInvoice?.amount - selectedInvoice?.amount_paid)"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Payment Screenshot / Receipt</label>
                            <input type="file" @change="paymentFile = $event.target.files[0]" class="form-control rounded-3" accept="image/*" required>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold">Notes (Optional)</label>
                            <textarea x-model="paymentNotes" class="form-control rounded-3" rows="2" placeholder="Any additional details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4" @click="showPaymentModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" :disabled="submittingPayment">
                            <span x-show="!submittingPayment">Submit Proof</span>
                            <span x-show="submittingPayment"><span class="spinner-border spinner-border-sm me-2"></span>Wait...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function studentDashboard() {
    return {
        processingAttendance: null,
        showWiFiModal: false,
        activeSession: null,
        checkInStatus: 'idle', // idle, scanning, success, error
        errorMessage: '',
        
        showPaymentModal: false,
        selectedInvoice: null,
        paymentFile: null,
        paymentNotes: '',
        submittingPayment: false,

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-EG', { style: 'currency', currency: 'EGP' }).format(amount || 0);
        },

        openCheckIn(session) {
            this.activeSession = session;
            this.showWiFiModal = true;
            this.checkInStatus = 'idle';
            this.errorMessage = '';
        },

        async handleCheckIn() {
            this.checkInStatus = 'scanning';
            try {
                const response = await axios.post(route('api.student.attendance.checkin', this.activeSession.uuid || this.activeSession.session_id));
                this.checkInStatus = 'success';
                setTimeout(() => window.location.reload(), 2000);
            } catch (err) {
                this.checkInStatus = 'error';
                this.errorMessage = err.response?.data?.error || 'Failed to verify connection.';
            }
        },

        async markPresent(session) {
            if (!confirm('Mark yourself as present?')) return;
            this.processingAttendance = session.session_id;
            try {
                await axios.post(route('api.student.attendance.checkin', session.uuid || session.session_id));
                window.location.reload();
            } catch (err) {
                alert(err.response?.data?.error || 'Error marking attendance');
                this.processingAttendance = null;
            }
        },

        openPaymentModal(invoice) {
            this.selectedInvoice = invoice;
            this.showPaymentModal = true;
            this.paymentFile = null;
            this.paymentNotes = '';
        },

        async handlePaymentSubmit() {
            if (!this.paymentFile) return alert('Please select a file');
            this.submittingPayment = true;
            
            const formData = new FormData();
            formData.append('payment_screenshot', this.paymentFile);
            formData.append('payment_notes', this.paymentNotes);

            try {
                await axios.post(route('invoices.submit_payment', this.selectedInvoice.invoice_id), formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                alert('Payment proof submitted successfully!');
                window.location.reload();
            } catch (err) {
                alert('Failed to submit payment proof.');
                this.submittingPayment = false;
            }
        }
    };
}
</script>
@endpush

@push('styles')
<style>
    .progress-ring__circle {
        transition: stroke-dashoffset 0.6s ease-in-out;
        transform: rotate(-90deg);
        transform-origin: 50% 50%;
    }
    .animate-pulse { animation: pulse-border 2s infinite; }
    @keyframes pulse-border { 0%, 100% { border-left-color: #dc3545; } 50% { border-left-color: #ffc107; } }
    .letter-spacing-1 { letter-spacing: 1px; }
    .extra-small { font-size: 0.7rem; }
    .transition-all { transition: all 0.3s ease; }
    .hover-bg-primary:hover { background-color: rgba(13, 110, 253, 0.05) !important; border-color: #0d6efd !important; transform: translateY(-3px); }
    .timeline-item::after { content: ''; position: absolute; left: -1px; top: 16px; bottom: 0; width: 2px; background: rgba(0,0,0,0.05); }
    .timeline-item:last-child::after { display: none; }
    .bg-primary-subtle { background-color: rgba(13, 110, 253, 0.1); }
    .bg-success-subtle { background-color: rgba(25, 135, 84, 0.1); }
    .bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1); }
    .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1); }
</style>
@endpush
