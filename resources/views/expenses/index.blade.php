@extends('layouts.authenticated')

@section('title', 'Expense Management')

@section('content')
@php
    $isAdmin = Auth::user()->isAdminFull() || Auth::user()->hasAdminPermission('manage_financials');
@endphp

<div x-data="expensesPage({
    initialExpenses: {{ json_encode($expenses) }},
    categories: {{ json_encode($expense_categories) }},
    paymentMethods: {{ json_encode($payment_methods) }},
    totalExpenses: {{ $total_expenses }},
    monthlyExpenses: {{ $monthly_expenses }},
    expensesByCategory: {{ json_encode($expenses_by_category) }},
    expensesByPayment: {{ json_encode($expenses_by_payment) }},
    isAdmin: {{ $isAdmin ? 'true' : 'false' }}
})" x-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold theme-text-main mb-1">Expense Management</h2>
            <p class="text-muted small mb-0">Track and manage all academy expenses</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('expenses.export') }}" class="btn btn-outline-success px-4 py-2 rounded-3 shadow-sm transition-hover">
                <i class="fas fa-file-excel me-2"></i> Export
            </a>
            <a 
                href="{{ route('expenses.add') }}"
                class="btn btn-primary px-4 py-2 rounded-3 shadow-sm transition-hover"
            >
                <i class="fas fa-plus me-2"></i> Add Expense
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden transition-hover theme-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded-pill text-primary">
                            <i class="fas fa-wallet fa-lg"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-uppercase text-muted smaller fw-bold mb-0">Total Expenses</h6>
                            <h3 class="fw-bold theme-text-main mb-0">£<span x-text="totalExpenses.toLocaleString()"></span></h3>
                        </div>
                    </div>
                    <div class="badge bg-success bg-opacity-10 text-success border-0 rounded-pill px-2 py-1 smaller">
                        <i class="fas fa-check-circle me-1"></i> Approved
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden transition-hover theme-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 p-3 rounded-pill text-success">
                            <i class="fas fa-calendar-alt fa-lg"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-uppercase text-muted smaller fw-bold mb-0">Monthly (This Month)</h6>
                            <h3 class="fw-bold theme-text-main mb-0">£<span x-text="monthlyExpenses.toLocaleString()"></span></h3>
                        </div>
                    </div>
                    <div class="small text-muted smaller">Current billing cycle</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden transition-hover theme-card {{ $expenses->where('is_approved', 0)->count() > 0 ? 'border-start border-4 border-warning' : '' }}">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 p-3 rounded-pill text-warning">
                            <i class="fas fa-clock fa-lg"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-uppercase text-muted smaller fw-bold mb-0">Pending Approval</h6>
                            <h3 class="fw-bold theme-text-main mb-0" x-text="initialExpenses.filter(e => e.is_approved === 0).length"></h3>
                        </div>
                    </div>
                    <div class="badge bg-warning bg-opacity-10 text-dark border-0 rounded-pill px-2 py-1 smaller">Requires Action</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden transition-hover theme-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-info bg-opacity-10 p-3 rounded-pill text-info">
                            <i class="fas fa-tags fa-lg"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="text-uppercase text-muted smaller fw-bold mb-0">Categories</h6>
                            <h3 class="fw-bold theme-text-main mb-0" x-text="categories.length"></h3>
                        </div>
                    </div>
                    <div class="small text-muted smaller">Expense labels</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs & Filters -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="nav nav-pills theme-badge-bg p-1 rounded-4 shadow-sm border theme-border">
            <button class="nav-link rounded-3 px-4 py-2 fw-bold transition-hover" :class="activeTab === 'all' ? 'active shadow-sm' : 'theme-text-main'" @click="activeTab = 'all'">
                All
            </button>
            <button class="nav-link rounded-3 px-4 py-2 fw-bold position-relative transition-hover" :class="activeTab === 'pending' ? 'active shadow-sm' : 'theme-text-main'" @click="activeTab = 'pending'">
                Pending Review
                <span x-show="initialExpenses.filter(e => e.is_approved === 0).length > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.6rem;" x-text="initialExpenses.filter(e => e.is_approved === 0).length"></span>
            </button>
            <button class="nav-link rounded-3 px-4 py-2 fw-bold transition-hover" :class="activeTab === 'approved' ? 'active shadow-sm' : 'theme-text-main'" @click="activeTab = 'approved'">
                Approved History
            </button>
        </div>

        <div class="d-flex flex-grow-1 gap-2" style="max-width: 600px;">
            <div class="input-group border theme-border rounded-4 overflow-hidden theme-badge-bg">
                <span class="input-group-text bg-transparent border-0 px-3 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control border-0 bg-transparent py-2 shadow-none theme-text-main smaller" placeholder="Search expenses..." x-model="searchTerm" />
            </div>
            
            <select class="form-select border theme-border rounded-4 py-2 shadow-none theme-badge-bg theme-text-main smaller w-auto" x-model="categoryFilter">
                <option value="all">All Categories</option>
                <template x-for="cat in categories" :key="cat">
                    <option :value="cat" x-text="cat"></option>
                </template>
            </select>
        </div>
    </div>

    <!-- Expense Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-5 border-top border-5" :class="activeTab === 'pending' ? 'border-warning' : 'border-primary'">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="theme-badge-bg text-muted small text-uppercase fw-bold">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="py-3">Category</th>
                        <th class="py-3">Description</th>
                        <th class="py-3">Amount</th>
                        <th class="py-3">Method</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="expense in filteredExpenses()" :key="expense.expense_id">
                        <tr class="theme-border" :class="expense.is_approved === 0 ? 'bg-warning bg-opacity-10' : ''">
                            <td class="px-4 fw-medium theme-text-main" x-text="expense.expense_date"></td>
                            <td>
                                <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-medium" x-text="expense.category"></span>
                            </td>
                            <td class="text-muted small" style="max-width: 250px;" x-text="expense.description"></td>
                            <td class="fw-bold theme-text-main">£<span x-text="Number(expense.amount).toLocaleString()"></span></td>
                            <td>
                                <div class="d-flex align-items-center theme-text-main">
                                    <i :class="getPaymentMethodIcon(expense.payment_method)" class="fas me-2 text-muted"></i>
                                    <span class="small" x-text="expense.payment_method || 'N/A'"></span>
                                </div>
                            </td>
                            <td>
                                <template x-if="expense.is_approved === 1">
                                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3 py-2">
                                        <i class="fas fa-check-circle me-1"></i> Approved
                                    </span>
                                </template>
                                <template x-if="expense.is_approved === 0">
                                    <span class="badge bg-warning-subtle text-warning border border-warning border-opacity-10 rounded-pill px-3 py-2">
                                        <i class="fas fa-clock me-1"></i> Pending
                                    </span>
                                </template>
                            </td>
                            <td class="px-4 text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <!-- Admin Only Actions -->
                                    <template x-if="isAdmin">
                                        <div class="d-flex gap-2">
                                            <template x-if="expense.is_approved === 0">
                                                <button @click="handleAction('approve', expense.expense_id)" class="btn btn-sm btn-success rounded-pill shadow-sm px-3 fw-bold transition-hover">
                                                    Approve
                                                </button>
                                            </template>
                                            <template x-if="expense.is_approved === 1">
                                                <button @click="handleAction('reject', expense.expense_id)" class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold transition-hover">
                                                    Revoke
                                                </button>
                                            </template>
                                            <button @click="handleAction('delete', expense.expense_id)" class="btn btn-sm btn-light border theme-border rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Delete">
                                                <i class="fas fa-trash text-danger"></i>
                                            </button>
                                        </div>
                                    </template>
                                    
                                    <!-- Non-Admin View (If they recorded it) -->
                                    <template x-if="!isAdmin">
                                        <span class="text-muted smaller">View Only</span>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredExpenses().length === 0">
                        <td colSpan="7" class="text-center py-5 text-muted">
                            <div class="py-4">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                <h5 class="fw-bold">No items in this category</h5>
                                <p class="small mb-0">Try adjusting your filters or active tab.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analytics Section -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="card-header bg-transparent border-0 py-3 px-4">
                    <h6 class="fw-bold mb-0 theme-text-main">Expenses by Category</h6>
                </div>
                <div class="card-body p-4 pt-0">
                    <div id="categoryChart"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="card-header bg-transparent border-0 py-3 px-4">
                    <h6 class="fw-bold mb-0 theme-text-main">Expenses by Method</h6>
                </div>
                <div class="card-body p-4 pt-0">
                    <div id="methodChart"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
function expensesPage(config) {
    return {
        initialExpenses: config.initialExpenses,
        categories: config.categories,
        paymentMethods: config.paymentMethods,
        totalExpenses: config.totalExpenses,
        monthlyExpenses: config.monthlyExpenses,
        expensesByCategory: config.expensesByCategory,
        expensesByPayment: config.expensesByPayment,
        isAdmin: config.isAdmin || false,
        
        searchTerm: '',
        statusFilter: 'all', // Legacy, we now use activeTab
        categoryFilter: 'all',
        activeTab: 'all',
        
        init() {
            this.renderCharts();
            // If there are pending expenses and user is admin, show pending tab by default
            const pendingCount = this.initialExpenses.filter(e => e.is_approved === 0).length;
            if (this.isAdmin && pendingCount > 0) {
                this.activeTab = 'pending';
            }
        },
        
        filteredExpenses() {
            return this.initialExpenses.filter(expense => {
                const description = expense.description || '';
                const category = expense.category || '';
                
                const matchesSearch = description.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                    category.toLowerCase().includes(this.searchTerm.toLowerCase());
                    
                const matchesCategory = this.categoryFilter === 'all' || expense.category === this.categoryFilter;

                const matchesTab = this.activeTab === 'all' ||
                    (this.activeTab === 'pending' && expense.is_approved === 0) ||
                    (this.activeTab === 'approved' && expense.is_approved === 1);

                return matchesSearch && matchesCategory && matchesTab;
            });
        },
        
        getPaymentMethodIcon(method) {
            method = method?.toLowerCase();
            if (method === 'cash') return 'fa-money-bill-wave';
            if (method === 'visa' || method === 'bank transfer' || method === 'credit card') return 'fa-credit-card';
            if (method === 'vodafone_cash' || method === 'instapay') return 'fa-mobile-alt';
            return 'fa-receipt';
        },
        
        handleAction(action, id) {
            let url = '';
            let method = 'POST';
            let title = '';
            let confirmButton = '#3085d6';
            
            if (action === 'approve') {
                url = `/expenses/${id}/approve`;
                title = 'Approve this expense?';
                confirmButton = '#198754';
            } else if (action === 'reject') {
                url = `/expenses/${id}/reject`;
                title = 'Revoke approval for this expense?';
                confirmButton = '#ffc107';
            } else if (action === 'delete') {
                url = `/expenses/${id}`;
                method = 'DELETE';
                title = 'Permanently delete this record?';
                confirmButton = '#d33';
            }
            
            Swal.fire({
                title: title,
                text: action === 'delete' ? "This action cannot be undone!" : "This will update the financial records.",
                icon: action === 'delete' ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: confirmButton,
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    container: 'theme-swal-container',
                    popup: 'theme-card rounded-4 border-0'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    axios({
                        method: method,
                        url: url,
                        data: { _token: '{{ csrf_token() }}' }
                    }).then(() => {
                        window.location.reload();
                    }).catch(err => {
                        Swal.fire('Error', 'Unauthorized or system failure.', 'error');
                    });
                }
            });
        },
        
        renderCharts() {
            const isDark = document.body.classList.contains('dark-theme');
            
            // Category Chart
            const catLabels = Object.keys(this.expensesByCategory);
            const catValues = Object.values(this.expensesByCategory);
            
            new ApexCharts(document.querySelector("#categoryChart"), {
                series: catValues,
                chart: { type: 'donut', height: 250 },
                labels: catLabels,
                colors: ['#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#6610f2', '#fd7e14'],
                legend: { position: 'bottom', labels: { colors: isDark ? '#fff' : '#000' } },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '70%' } } }
            }).render();
            
            // Method Chart
            const methodLabels = Object.keys(this.expensesByPayment);
            const methodValues = Object.values(this.expensesByPayment);
            
            new ApexCharts(document.querySelector("#methodChart"), {
                series: methodValues,
                chart: { type: 'donut', height: 250 },
                labels: methodLabels,
                colors: ['#20c997', '#6f42c1', '#e83e8c', '#adb5bd'],
                legend: { position: 'bottom', labels: { colors: isDark ? '#fff' : '#000' } },
                dataLabels: { enabled: false },
                plotOptions: { pie: { donut: { size: '70%' } } }
            }).render();
        }
    };
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.75rem; }
    .transition-hover { transition: all 0.3s ease; }
    .transition-hover:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important; }
    [x-cloak] { display: none !important; }
</style>
@endsection
