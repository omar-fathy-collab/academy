@extends('layouts.authenticated')

@section('title', 'Certificate Management')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="certificateAdmin({
    certificates: {{ json_encode($certificates->items()) }},
    requests: {{ json_encode($requests) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">📜 Academic Certifications</h2>
            <p class="text-muted mb-0">Audit, issue, and manage student credentials</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('certificates.create') }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-plus me-2"></i> Issue New Certificate
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-pill shadow-sm d-inline-flex border theme-border" dir="ltr">
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-bold" :class="activeTab === 'issued' ? 'active' : ''" @click="activeTab = 'issued'">
                Issued Certificates <span class="badge bg-white text-primary ms-2" x-text="certificatesCount"></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link rounded-pill px-4 fw-bold" :class="activeTab === 'requests' ? 'active' : ''" @click="activeTab = 'requests'">
                Pending Requests <span class="badge bg-danger text-white ms-2" x-text="requests.length"></span>
            </button>
        </li>
    </ul>

    <!-- Issued Certificates -->
    <div x-show="activeTab === 'issued'" x-transition>
        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mb-5 ajax-content position-relative" id="issued-certificates-grid">
            <!-- Loading Overlay -->
            <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
                <div class="spinner-border text-primary" role="status"></div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-start" dir="ltr">
                    <thead class="theme-badge-bg text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Serial Number</th>
                            <th class="py-3">Student</th>
                            <th class="py-3">Course Track</th>
                            <th class="py-3 text-center">Performance</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($certificates as $cert)
                            <tr class="theme-border">
                                <td class="px-4">
                                    <div class="fw-bold theme-text-main text-uppercase">{{ $cert->certificate_number }}</div>
                                    <div class="smaller text-muted">{{ $cert->issue_date }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold theme-text-main">{{ $cert->user->profile->full_name ?? $cert->user->username }}</div>
                                    <div class="smaller text-muted">{{ $cert->user->email }}</div>
                                </td>
                                <td>
                                    <div class="smaller fw-bold theme-text-main">{{ $cert->course->course_name ?? 'Individual Merit' }}</div>
                                    <div class="smaller text-muted">By: {{ $cert->instructor_name ?? 'Academy Office' }}</div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        @if($cert->attendance_percentage)
                                            <span class="badge bg-info bg-opacity-10 text-info smaller" title="Attendance">
                                                <i class="fas fa-user-check me-1"></i> {{ round($cert->attendance_percentage) }}%
                                            </span>
                                        @endif
                                        @if($cert->quiz_average)
                                            <span class="badge bg-success bg-opacity-10 text-success smaller" title="Grades">
                                                <i class="fas fa-graduation-cap me-1"></i> {{ round($cert->quiz_average) }}%
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                                        <a href="{{ route('certificates.download', $cert->id) }}" class="btn btn-sm btn-light border-0 px-3" title="Download PDF">
                                            <i class="fas fa-download text-primary"></i>
                                        </a>
                                        <a href="{{ route('certificates.verify.public', $cert->certificate_number) }}" target="_blank" class="btn btn-sm btn-light border-0 px-3" title="Verify">
                                            <i class="fas fa-external-link-alt text-secondary"></i>
                                        </a>
                                        <form action="{{ route('certificates.destroy', $cert->id) }}" method="POST" onsubmit="return confirm('Permanently delete certificate?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border-0 px-3">
                                                <i class="far fa-trash-alt text-danger"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-certificate fa-3x mb-3 opacity-25"></i>
                                    <h6>No issued certificates found</h6>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer theme-badge-bg border-top-0 p-4" @click="navigate">
                {{ $certificates->links() }}
            </div>
        </div>
    </div>

    <!-- Pending Requests -->
    <div x-show="activeTab === 'requests'" x-transition>
        <div class="row g-4">
            <template x-for="req in requests" :key="req.id">
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden h-100">
                        <div class="card-body p-4 text-start" dir="ltr">
                            <div class="d-flex justify-content-between align-items-start mb-3 flex-row">
                                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <span class="badge bg-light text-muted rounded-pill px-3 py-1 smaller" x-text="req.created_at"></span>
                            </div>
                            <h6 class="fw-bold theme-text-main mb-1" x-text="req.user.username"></h6>
                            <p class="smaller text-muted mb-4" x-text="req.course?.course_name || 'Individual Merit'"></p>
                            
                            <div class="p-3 theme-badge-bg rounded-4 border theme-border mb-4">
                                <div class="smaller text-muted mb-2">Request Remarks:</div>
                                <div class="smaller theme-text-main" x-text="req.remarks || 'No remarks available'"></div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary rounded-pill fw-bold py-2 shadow-sm" @click="approveRequest(req)">
                                    <i class="fas fa-check-circle me-2"></i> Approve & Issue
                                </button>
                                <button class="btn btn-outline-danger btn-sm rounded-pill py-2 border-0" @click="rejectRequest(req.id)">
                                    Reject Request
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="requests.length === 0">
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                    <h6>No pending requests</h6>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function certificateAdmin(config) {
    return {
        ...ajaxTable(),
        activeTab: 'issued',
        certificates: config.certificates,
        requests: config.requests,
        certificatesCount: {{ $certificates->total() }},
        
        approveRequest(req) {
            Swal.fire({
                title: 'Issue Certificate',
                text: `Do you want to issue a certificate for student ${req.user.username}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, issue now'
            }).then(result => {
                if(result.isConfirmed) {
                    window.location.href = `{{ url('certificates/create') }}?user_id=${req.user_id}&request_id=${req.id}`;
                }
            });
        },
        
        rejectRequest(id) {
            if(confirm('Do you want to reject this request?')) {
                axios.post(`{{ url('certificate-requests') }}/${id}/reject`)
                    .then(() => window.location.reload());
            }
        }

    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.72rem; }
    .nav-pills .nav-link { color: var(--text-muted); }
    .nav-pills .nav-link.active { background-color: var(--primary-color); color: white; }
</style>
@endsection
