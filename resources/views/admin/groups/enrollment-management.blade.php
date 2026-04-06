@extends('layouts.authenticated')

@section('title', 'Enrollment Management')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="enrollmentManager({
    requests: {{ json_encode($requests) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">📥 Enrollment Requests</h2>
            <p class="text-muted mb-0">Review student applications for academic groups</p>
        </div>
    </div>

    <!-- Requests Grid -->
    <div class="row g-4">
        <template x-for="req in requests" :key="req.id">
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden h-100 transition-hover">
                    <div class="card-body p-4 text-start" dir="ltr">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <span class="badge bg-light text-muted rounded-pill px-3 py-1 smaller" x-text="req.created_at"></span>
                        </div>
                        
                        <h5 class="fw-bold theme-text-main mb-1" x-text="req.user.username"></h5>
                        <p class="smaller text-muted mb-4" x-text="req.user.email"></p>
                        
                        <div class="p-3 theme-badge-bg rounded-4 border theme-border mb-4">
                            <div class="smaller fw-bold text-primary mb-1">Requested Group:</div>
                            <div class="theme-text-main fw-bold" x-text="req.group.group_name"></div>
                            <div class="smaller text-muted" x-text="req.group.course?.course_name || 'Individual Track'"></div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="smaller text-muted mb-2">Student Notes:</div>
                            <div class="smaller theme-text-main fst-italic" x-text="req.notes || 'No notes available'"></div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                                <button class="btn btn-success border-0 py-2 fw-bold smaller flex-grow-1" @click="updateStatus(req.id, 'approved')">
                                    <i class="fas fa-check me-1"></i> Accept
                                </button>
                                <button class="btn btn-danger border-0 py-2 fw-bold smaller flex-grow-1 border-start theme-border" @click="updateStatus(req.id, 'rejected')">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        
        <template x-if="requests.length === 0">
            <div class="col-12 text-center py-5 text-muted">
                <i class="fas fa-check-circle fa-4x mb-3 opacity-25"></i>
                <h4 class="fw-bold">All Requests Processed</h4>
                <p>There are no pending enrollment requests at this time.</p>
            </div>
        </template>
    </div>
</div>

<script>
function enrollmentManager(config) {
    return {
        requests: config.requests,
        
        updateStatus(id, status) {
            const statusAr = status === 'approved' ? 'approving' : 'rejecting';
            if(confirm(`Are you sure about ${statusAr} this request?`)) {
                axios.post(`/admin/groups/enrollment/${id}/status`, { status })
                    .then(resp => {
                        if(resp.data.success) {
                            Toast.fire({ icon: 'success', title: 'Request updated' });
                            this.requests = this.requests.filter(r => r.id !== id);
                        }
                    });
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
    .transition-hover:hover { transform: translateY(-5px); transition: all 0.3s ease; box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important; }
</style>
@endsection
