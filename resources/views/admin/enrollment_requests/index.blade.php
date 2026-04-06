@extends('layouts.authenticated')

@section('title', 'Enrollment Requests')

@section('content')
<div x-data="{ 
    ...enrollmentRequestsHandler(),
    ...ajaxTable()
}">

    {{-- Page Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                <i class="fas fa-user-check me-2 text-primary"></i> Enrollment Requests
            </h1>
            <p class="text-muted small mb-0">Review and manage student course purchase requests.</p>
        </div>
        <button
            @click="openManualEnroll()"
            class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm d-flex align-items-center gap-2"
            data-bs-toggle="modal"
            data-bs-target="#manualEnrollModal"
        >
            <i class="fas fa-user-plus"></i>
            <span>Manually Enroll Student</span>
        </button>
    </div>

    {{-- Main Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-4">
        {{-- Filters & Search --}}
        <div class="card-header border-0 theme-badge-bg p-4 d-flex justify-content-between align-items-center flex-wrap gap-3 ajax-content" id="enrollment-filters" @click="navigate">
            <div class="d-flex gap-2">
                <a href="{{ route('enrollment-requests.index') }}"
                   class="btn btn-sm rounded-pill px-3 fw-bold {{ !request('status') ? 'btn-primary' : 'btn-outline-secondary' }}">
                    All
                </a>
                <a href="{{ route('enrollment-requests.index', ['status' => 'pending']) }}"
                   class="btn btn-sm rounded-pill px-3 fw-bold {{ request('status') === 'pending' ? 'btn-warning' : 'btn-outline-secondary' }}">
                    Pending
                </a>
                <a href="{{ route('enrollment-requests.index', ['status' => 'paid']) }}"
                   class="btn btn-sm rounded-pill px-3 fw-bold {{ request('status') === 'paid' ? 'btn-success' : 'btn-outline-secondary' }}">
                    Paid
                </a>
                <a href="{{ route('enrollment-requests.index', ['status' => 'approved']) }}"
                   class="btn btn-sm rounded-pill px-3 fw-bold {{ request('status') === 'approved' ? 'btn-info' : 'btn-outline-secondary' }}">
                    Approved
                </a>
                <a href="{{ route('enrollment-requests.index', ['status' => 'rejected']) }}"
                   class="btn btn-sm rounded-pill px-3 fw-bold {{ request('status') === 'rejected' ? 'btn-danger' : 'btn-outline-secondary' }}">
                    Rejected
                </a>
            </div>
            <form method="GET" action="{{ route('enrollment-requests.index') }}" class="d-flex gap-2 align-items-center ajax-form" @submit.prevent="updateList">
                @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                <div class="input-group">
                    <span class="input-group-text theme-card theme-border border-end-0">
                        <i class="fas fa-search text-muted small"></i>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search by name or course..."
                           class="form-control theme-card theme-text-main theme-border border-start-0"
                           @input.debounce.500ms="updateList">
                </div>
                @if(request('search'))
                    <a href="{{ route('enrollment-requests.index', ['status' => request('status')]) }}" class="btn btn-light rounded-pill px-3 border theme-border fw-bold">Clear</a>
                @endif
            </form>
        </div>

        <div class="position-relative">
            <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            
            {{-- Table --}}
            <div class="table-responsive ajax-content" id="enrollment-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="theme-badge-bg text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Student</th>
                            <th class="py-3">Course</th>
                            <th class="py-3 text-center">Amount</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-center">Date</th>
                            <th class="py-3 text-end px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr class="theme-border">
                                <td class="px-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 fw-bold d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                            {{ mb_strtoupper(mb_substr($req->user->username ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold theme-text-main">{{ $req->user->username ?? 'Unknown User' }}</div>
                                            <div class="small text-muted">{{ $req->user->email ?? 'No email' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-primary">{{ $req->course->course_name ?? 'Deleted Course' }}</div>
                                    <div class="small text-muted">ID: {{ $req->course_id }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="fw-bold theme-text-main">{{ number_format($req->amount) }}</span>
                                    <span class="small text-muted ms-1">EGP</span>
                                </td>
                                <td class="py-3 text-center">
                                    @switch($req->status)
                                        @case('pending')
                                            <span class="badge bg-warning bg-opacity-15 text-warning border border-warning rounded-pill px-3 py-1 fw-bold">Pending</span>
                                            @break
                                        @case('approved')
                                            <span class="badge bg-info bg-opacity-15 text-info border border-info rounded-pill px-3 py-1 fw-bold">Approved</span>
                                            @break
                                        @case('paid')
                                            <span class="badge bg-success bg-opacity-15 text-success border border-success rounded-pill px-3 py-1 fw-bold">Paid</span>
                                            @break
                                        @case('rejected')
                                            <span class="badge bg-danger bg-opacity-15 text-danger border border-danger rounded-pill px-3 py-1 fw-bold">Rejected</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary bg-opacity-15 text-muted border rounded-pill px-3 py-1 fw-bold">{{ ucfirst($req->status) }}</span>
                                    @endswitch
                                </td>
                                <td class="py-3 text-center small text-muted">
                                    {{ $req->created_at->format('d M Y') }}
                                </td>
                                <td class="py-3 text-end px-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button
                                            @click="openUpdateModal({{ json_encode($req) }})"
                                            class="btn btn-sm btn-light border theme-border rounded-circle"
                                            data-bs-toggle="modal"
                                            data-bs-target="#updateStatusModal"
                                            title="Update Status"
                                        >
                                            <i class="fas fa-edit text-warning"></i>
                                        </button>
                                        <form action="{{ route('enrollment-requests.destroy', $req->id) }}" method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this request?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border theme-border rounded-circle" title="Delete Request">
                                                <i class="fas fa-trash text-danger"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x opacity-25 mb-3 d-block"></i>
                                    No enrollment requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer theme-badge-bg border-top theme-border py-3 d-flex justify-content-center ajax-content" id="enrollment-pagination" @click="navigate">
            @if($requests->hasPages())
                {{ $requests->links('pagination::bootstrap-5') }}
            @endif
        </div>
    </div>

    {{-- Update Status Modal --}}
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content theme-card border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold theme-text-main">
                        <i class="fas fa-edit me-2 text-primary"></i> Update Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form :action="'/enrollment-requests/' + selectedRequest?.id" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body px-4 pb-0">
                        <div class="p-3 rounded-4 theme-badge-bg mb-4 text-center">
                            <div class="small text-muted mb-1">Student</div>
                            <div class="fw-bold theme-text-main" x-text="selectedRequest?.user?.username ?? '—'"></div>
                            <div class="small text-primary fw-bold mt-1" x-text="selectedRequest?.course?.course_name ?? '—'"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">New Status</label>
                            <select name="status" class="form-select theme-card theme-border theme-text-main" x-model="status" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved (no payment)</option>
                                <option value="paid">Paid (activate immediately)</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small">Admin Notes</label>
                            <textarea name="notes" class="form-control theme-card theme-border theme-text-main"
                                      rows="3" x-model="notes"
                                      placeholder="e.g. Reason for rejection or payment confirmation..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 gap-2">
                        <button type="submit" class="btn btn-primary flex-fill py-2 rounded-pill fw-bold">Update Request</button>
                        <button type="button" class="btn btn-outline-secondary flex-fill py-2 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Manual Enroll Modal --}}
    <div class="modal fade" id="manualEnrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content theme-card border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 p-4">
                    <h5 class="modal-title fw-bold theme-text-main">
                        <i class="fas fa-user-plus me-2 text-success"></i> Manually Enroll Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('enrollment-requests.manual-enroll') }}" method="POST">
                    @csrf
                    <div class="modal-body px-4 pb-0">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Select Student</label>
                            <select name="student_id" class="form-select theme-card theme-border theme-text-main" required>
                                <option value="">-- Choose a student --</option>
                                @foreach($allStudents as $student)
                                    <option value="{{ $student->student_id }}">
                                        {{ $student->user->username }} ({{ $student->user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">Select Course</label>
                            <select name="course_id" class="form-select theme-card theme-border theme-text-main" required>
                                <option value="">-- Choose a course --</option>
                                @foreach($allCourses as $course)
                                    <option value="{{ $course->course_id }}">
                                        {{ $course->course_name }} ({{ $course->is_free ? 'Free' : 'Paid' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small">Notes</label>
                            <textarea name="notes" class="form-control theme-card theme-border theme-text-main"
                                      rows="2" placeholder="e.g. Paid in cash at the center..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 gap-2">
                        <button type="submit" class="btn btn-success flex-fill py-2 rounded-pill fw-bold">Activate Course for Student</button>
                        <button type="button" class="btn btn-outline-secondary flex-fill py-2 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
</style>

@push('scripts')
<script>
function enrollmentRequestsHandler() {
    return {
        selectedRequest: null,
        status: '',
        notes: '',

        openUpdateModal(req) {
            this.selectedRequest = req;
            this.status = req.status;
            this.notes = req.notes || '';
        },

        openManualEnroll() {
            // Ready for future logic
        }
    }
}
</script>
@endpush
@endsection
