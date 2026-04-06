@extends('layouts.authenticated')

@section('title', 'Salary Management')

@section('content')
<div x-data='salariesPage({
    initialSalaries: @json($salaries, JSON_HEX_APOS),
    teachers: @json($teachers, JSON_HEX_APOS),
    months: @json($months, JSON_HEX_APOS),
    stats: {
        totalRevenue: {{ $total_revenue }},
        totalTeacherShare: {{ $total_teacher_share }},
        totalAvailable: {{ $total_available_payment }},
        totalPaid: {{ $total_paid_amount }},
        totalRemaining: {{ $total_remaining }}
    }
})' x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">💰 Salary Management</h2>
            <p class="text-muted mb-0">Track teacher earnings, group revenues, and payouts</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
            <a href="{{ route('salaries.create') }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-plus me-2"></i> New Salary Record
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card">
                <div class="card-body p-3">
                    <p class="text-muted smaller fw-bold text-uppercase mb-1">Total Rev.</p>
                    <h5 class="fw-bold theme-text-main mb-0">£<span x-text="stats.totalRevenue.toLocaleString()"></span></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-primary">
                <div class="card-body p-3">
                    <p class="text-muted smaller fw-bold text-uppercase mb-1">Teacher Share</p>
                    <h5 class="fw-bold text-primary mb-0">£<span x-text="stats.totalTeacherShare.toLocaleString()"></span></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-info">
                <div class="card-body p-3">
                    <p class="text-muted smaller fw-bold text-uppercase mb-1">Available</p>
                    <h5 class="fw-bold text-info mb-0">£<span x-text="stats.totalAvailable.toLocaleString()"></span></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-success">
                <div class="card-body p-3">
                    <p class="text-muted smaller fw-bold text-uppercase mb-1">Total Paid</p>
                    <h5 class="fw-bold text-success mb-0">£<span x-text="stats.totalPaid.toLocaleString()"></span></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-danger">
                <div class="card-body p-3">
                    <p class="text-muted smaller fw-bold text-uppercase mb-1">Remaining</p>
                    <h5 class="fw-bold text-danger mb-0">£<span x-text="stats.totalRemaining.toLocaleString()"></span></h5>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card">
                <div class="card-body p-3">
                    <p class="text-muted smaller fw-bold text-uppercase mb-1">Adjustments</p>
                    <h5 class="fw-bold mb-0 {{ $net_adjustments >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $net_adjustments >= 0 ? '+' : '' }}{{ number_format($net_adjustments) }}
                    </h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 theme-card">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" x-model="filters.teacher_id" @change="debouncedFetch()">
                        <option value="">All Teachers</option>
                        <template x-for="t in teachers" :key="t.teacher_id">
                            <option :value="t.teacher_id" x-text="t.teacher_name"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" x-model="filters.month" @change="debouncedFetch()">
                        <option value="">All Months</option>
                        <template x-for="m in months" :key="m">
                            <option :value="m" x-text="m"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group border theme-border rounded-3 overflow-hidden theme-badge-bg">
                        <span class="input-group-text bg-transparent border-0 px-3">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control border-0 bg-transparent py-2 shadow-none theme-text-main"
                            placeholder="Search by group or status..."
                            x-model="searchTerm"
                            @input.debounce.500ms="debouncedFetch()"
                        />
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-secondary rounded-3 py-2 fw-medium" @click="resetFilters()">
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="theme-badge-bg text-muted small text-uppercase">
                    <tr>
                        <th class="px-4 py-3">Teacher</th>
                        <th class="py-3">Group & Details</th>
                        <th class="py-3">Revenue / Share</th>
                        <th class="py-3">Available / Paid</th>
                        <th class="py-3 text-center">Status</th>
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
                    <template x-if="!loading && salads.length > 0">
                        <template x-for="salary in salads" :key="salary.salary_id">
                            <tr class="theme-border">
                                <td class="px-4">
                                    <div class="fw-bold theme-text-main" x-text="salary.teacher_name"></div>
                                    <div class="small text-muted" x-text="salary.month"></div>
                                </td>
                                <td>
                                    <div class="fw-medium theme-text-main" x-text="salary.group_name || 'Manual Adjustment'"></div>
                                    <div class="smaller text-muted">
                                        <span x-text="salary.student_count || 0"></span> Stud. | <span x-text="salary.actual_percentage"></span>% Share
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark mb-0">£<span x-text="Number(salary.actual_revenue).toLocaleString()"></span></div>
                                    <div class="smaller text-primary fw-bold mt-1">Share: £<span x-text="Number(salary.actual_teacher_share).toLocaleString()"></span></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-info mb-0">Avail: £<span x-text="Number(salary.available_payment).toLocaleString()"></span></div>
                                    <div class="smaller text-success fw-bold mt-1">Paid: £<span x-text="Number(salary.paid_amount).toLocaleString()"></span></div>
                                </td>
                                <td class="text-center">
                                    <span x-html="getStatusBadge(salary.payment_status_based_on_available)"></span>
                                </td>
                                <td class="px-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button 
                                            class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold"
                                            @click="window.location.href = route('salaries.pay', salary.uuid || salary.salary_id)"
                                        >
                                            Pay
                                        </button>
                                        
                                        <!-- Notify & Slip Controls -->
                                        <template x-if="salary.paid_amount > 0">
                                            <div class="d-flex gap-1">
                                                <button 
                                                    class="btn btn-sm btn-outline-success border rounded-circle theme-border" 
                                                    style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"
                                                    @click="handleNotify(salary, 'whatsapp')"
                                                    title="Send WhatsApp Slip"
                                                >
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>
                                                <a 
                                                    :href="`/salary-slip/${salary.public_token}`" 
                                                    target="_blank"
                                                    class="btn btn-sm btn-outline-info border rounded-circle theme-border" 
                                                    style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;"
                                                    title="View Salary Slip"
                                                >
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                            </div>
                                        </template>

                                        <a :href="`/salaries/${salary.uuid || salary.salary_id}`" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Details">
                                            <i class="fas fa-eye text-primary"></i>
                                        </a>
                                        <a :href="`/salaries/${salary.uuid || salary.salary_id}/edit`" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Edit">
                                            <i class="fas fa-edit text-warning"></i>
                                        </a>
                                        <button @click="handleDelete(salary.salary_id)" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Delete">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </template>
                    <template x-if="!loading && salads.length === 0">
                        <tr>
                            <td colSpan="6" class="text-center py-5 text-muted">
                                <div class="fs-1 mb-3">👛</div>
                                <h5 class="fw-bold">No salary records found</h5>
                                <p class="small">Adjust your filters to see more results</p>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    <x-payment-modal />
</div>

<script>
function salariesPage(config) {
    return {
        salads: config.initialSalaries,
        teachers: config.teachers,
        months: config.months,
        stats: config.stats,
        
        loading: false,
        searchTerm: '',
        filters: {
            teacher_id: '',
            month: '',
            status: ''
        },
        
        route(name, id) {
            return window.route(name, id);
        },
        
        debouncedFetch() {
            this.loading = true;
            axios.get(this.route('salaries.search'), {
                params: {
                    search: this.searchTerm,
                    teacher_id: this.filters.teacher_id,
                    month: this.filters.month,
                    status: this.filters.status
                }
            }).then(response => {
                if (response.data.success) {
                    this.salads = response.data.salaries;
                    // Note: simplified mapping if the keys differ 
                    this.salads.forEach(s => {
                        s.actual_revenue = s.calculated_revenue;
                        s.actual_teacher_share = s.calculated_teacher_share;
                        s.available_payment = s.calculated_available_payment;
                        s.payment_status_based_on_available = this.calcStatus(s);
                    });
                }
            }).finally(() => {
                this.loading = false;
            });
        },
        
        calcStatus(s) {
            const avail = s.available_payment || 0;
            const paid = s.paid_amount || 0;
            if (paid <= 0) return 'pending';
            if (paid >= avail) return 'paid';
            return 'partial';
        },
        
        resetFilters() {
            this.searchTerm = '';
            this.filters = { teacher_id: '', month: '', status: '' };
            this.debouncedFetch();
        },
        
        handleDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Deleting this record will also remove associated payments!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete(this.route('salaries.destroy', id))
                        .then(() => {
                            window.location.reload();
                        });
                }
            });
        },
        
        getStatusBadge(status) {
            switch (status) {
                case 'paid': return '<span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1 rounded-pill">Paid</span>';
                case 'partial': return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1 rounded-pill">Partial</span>';
                default: return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1 rounded-pill">Pending</span>';
            }
        },

        handleNotify(salary, type) {
            const id = salary.uuid || salary.salary_id;
            axios.post(`/salaries/${id}/notify`, { type: type })
                .then(response => {
                    if (response.data.success) {
                        const url = type === 'whatsapp' ? response.data.whatsapp_url : response.data.email_url;
                        if (url) {
                            window.open(url, '_blank');
                        } else {
                            Swal.fire('Error', 'Contact data missing for this teacher', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Notification error:', error);
                    Swal.fire('Error', 'Failed to generate notification link', 'error');
                });
        }
    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.7rem; }
    .transition-hover:hover { transform: translateY(-2px); }
</style>
@endsection
