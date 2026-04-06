@extends('layouts.authenticated')

@section('title', 'System Activity Logs')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="activityLogs({
    initialActivities: {{ json_encode($activities->items()) }},
    pagination: {
        total: {{ $activities->total() }},
        per_page: {{ $activities->perPage() }},
        current_page: {{ $activities->currentPage() }},
        last_page: {{ $activities->lastPage() }}
    }
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">🕵️ System Audit Logs</h2>
            <p class="text-muted mb-0">Track all administrative actions and record changes</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
            <button @click="resetFilters()" class="btn btn-outline-secondary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-undo me-2"></i> Reset 
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 overflow-hidden">
        <div class="card-body p-3">
            <div class="input-group border theme-border rounded-3 overflow-hidden theme-badge-bg">
                <span class="input-group-text bg-transparent border-0 px-3">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input
                    type="text"
                    class="form-control border-0 bg-transparent py-2 shadow-none theme-text-main"
                    placeholder="Search by action, user, or ID..."
                    x-model="searchTerm"
                    @input.debounce.500ms="fetchActivities()"
                />
            </div>
        </div>
    </div>

    <!-- Activity Table -->
    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="theme-badge-bg text-muted small text-uppercase">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="py-3">Action</th>
                        <th class="py-3">Subject</th>
                        <th class="py-3">IP Address</th>
                        <th class="py-3">Date & Time</th>
                        <th class="px-4 py-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="loading">
                        <tr>
                            <td colSpan="6" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && activities.length > 0">
                        <template x-for="act in activities" :key="act.id">
                            <tr class="theme-border">
                                <td class="px-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold theme-text-main" x-text="act.user?.username || 'System'"></div>
                                            <div class="smaller text-muted" x-text="act.user?.email || 'automated_task'"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-3 py-1 smaller" :class="getActionBadge(act.action)" x-text="act.action"></span>
                                </td>
                                <td>
                                    <div class="smaller fw-bold theme-text-main" x-text="act.subject_type.split('\\').pop()"></div>
                                    <div class="smaller text-muted">ID: #<span x-text="act.subject_id"></span></div>
                                </td>
                                <td>
                                    <code class="smaller text-muted" x-text="act.ip || 'N/A'"></code>
                                </td>
                                <td>
                                    <div class="smaller theme-text-main" x-text="new Date(act.created_at).toLocaleString()"></div>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button @click="showDetails(act)" class="btn btn-sm btn-light border theme-border rounded-pill px-3 shadow-sm fw-bold">
                                            Details
                                        </button>
                                        <template x-if="canRollback(act)">
                                            <button @click="handleRollback(act)" class="btn btn-sm btn-outline-danger rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Rollback Action">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </template>
                    <template x-if="!loading && activities.length === 0">
                        <tr>
                            <td colSpan="6" class="text-center py-5 text-muted">
                                <div class="fs-1 mb-3">🔍</div>
                                <h5 class="fw-bold">No logs found</h5>
                                <p class="small">Try adjusting your search criteria</p>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Custom Pagination -->
        <div class="card-footer theme-badge-bg border-top-0 p-4 d-flex justify-content-between align-items-center">
            <span class="smaller text-muted">Showing page <span x-text="pagination.current_page"></span> of <span x-text="pagination.last_page"></span></span>
            <div class="btn-group">
                <button class="btn btn-sm btn-light border theme-border" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Previous</button>
                <button class="btn btn-sm btn-light border theme-border" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" :class="showModal ? 'show d-block' : ''" id="detailsModal" tabindex="-1" :aria-modal="showModal" :role="showModal ? 'dialog' : ''" x-show="showModal" style="background-color: rgba(0,0,0,0.5);" x-cloak>
        <div class="modal-dialog modal-lg">
            <div class="modal-content theme-card border-0 shadow-lg">
                <div class="modal-header theme-badge-bg border-0 p-4">
                    <h5 class="modal-title fw-bold">Action Analysis: #<span x-text="activeLog?.id"></span></h5>
                    <button type="button" class="btn-close" @click="showModal = false"></button>
                </div>
                <div class="modal-body p-4 p-md-5">
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 theme-badge-bg rounded-4 border theme-border">
                                <p class="smaller text-muted text-uppercase fw-bold mb-1">Old State (Before)</p>
                                <pre class="font-monospace smaller theme-text-main mb-0 overflow-auto" style="max-height: 300px;" x-text="JSON.stringify(activeLog?.old_data || {}, null, 2)"></pre>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 theme-badge-bg rounded-4 border theme-border">
                                <p class="smaller text-muted text-uppercase fw-bold mb-1">New State (After)</p>
                                <pre class="font-monospace smaller theme-text-main mb-0 overflow-auto" style="max-height: 300px;" x-text="JSON.stringify(activeLog?.new_data || activeLog?.changes || {}, null, 2)"></pre>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info border-0 rounded-4 p-3 smaller">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>User Agent:</strong> <span x-text="activeLog?.user_agent"></span>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" @click="showModal = false">Close Analysis</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function activityLogs(config) {
    return {
        activities: config.initialActivities,
        pagination: config.pagination,
        loading: false,
        searchTerm: '',
        activeLog: null,
        showModal: false,
        
        fetchActivities(page = 1) {
            this.loading = true;
            axios.get(window.route('activities.search'), {
                params: { 
                    search: this.searchTerm,
                    page: page
                }
            }).then(response => {
                this.activities = response.data.activities;
                this.pagination = response.data.pagination;
            }).finally(() => {
                this.loading = false;
            });
        },
        
        changePage(page) {
            this.fetchActivities(page);
        },
        
        resetFilters() {
            this.searchTerm = '';
            this.fetchActivities(1);
        },
        
        showDetails(log) {
            this.activeLog = log;
            this.showModal = true;
        },
        
        handleRollback(log) {
            Swal.fire({
                title: 'Confirm Rollback?',
                text: "This will revert the subject record to its previous state. This action cannot be easily undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Rollback'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Send a POST request to handle rollback
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.route('activities.rollback', log.id);
                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    const hiddenToken = document.createElement('input');
                    hiddenToken.type = 'hidden';
                    hiddenToken.name = '_token';
                    hiddenToken.value = token;
                    form.appendChild(hiddenToken);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        },
        
        canRollback(log) {
            const actions = ['updated', 'created', 'deleted'];
            return actions.includes(log.action.toLowerCase());
        },
        
        getActionBadge(action) {
            switch(action.toLowerCase()) {
                case 'created': return 'bg-success bg-opacity-10 text-success border border-success';
                case 'updated': return 'bg-warning bg-opacity-10 text-warning border border-warning';
                case 'deleted': return 'bg-danger bg-opacity-10 text-danger border border-danger';
                case 'rollback': return 'bg-info bg-opacity-10 text-info border border-info';
                default: return 'bg-secondary bg-opacity-10 text-muted border';
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
    .transition-hover:hover { transform: translateY(-3px); }
</style>
@endsection
