@extends('layouts.authenticated')

@section('title', 'Invoices Hub')

@section('content')
<div x-data='invoicesPage({
    initialInvoices: @json($invoices->items(), JSON_HEX_APOS),
    pagination: @json($invoices->toArray(), JSON_HEX_APOS),
    groups: @json($groups, JSON_HEX_APOS),
    students: @json($students, JSON_HEX_APOS)
})' @payment-recorded.window="fetchInvoices(pagination.current_page)" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">💳 Invoices Hub</h2>
            <p class="text-muted mb-0">Manage billing, payments, and financial records</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
            <a href="{{ route('invoices.export') }}" class="btn btn-outline-success fw-bold rounded-pill px-4 shadow-sm">
                <i class="fas fa-file-excel me-2"></i> Export Excel
            </a>
            <button class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" @click="showCreateModal = true">
                <i class="fas fa-plus me-2"></i> Create Invoice
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-primary">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-file-invoice-dollar fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase mb-0">Total Revenue</p>
                        <h3 class="fw-bold mb-0 theme-text-main">{{ number_format($totalAmount) }} <span class="small fs-6">EGP</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-success">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success me-3">
                        <i class="fas fa-money-check-alt fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase mb-0">Total Collected</p>
                        <h3 class="fw-bold mb-0 text-success">{{ number_format($totalPaid) }} <span class="small fs-6">EGP</span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 theme-card border-start border-4 border-danger">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="p-3 rounded-circle bg-danger bg-opacity-10 text-danger me-3">
                        <i class="fas fa-balance-scale fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase mb-0">Total Unpaid</p>
                        <h3 class="fw-bold mb-0 text-danger">{{ number_format($totalBalance) }} <span class="small fs-6">EGP</span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
        <div class="card-header bg-transparent p-4 border-bottom theme-border d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 theme-text-main">All Invoices</h5>
                <template x-if="selectedInvoices.length > 0">
                    <button @click="handleMarkAsPaid" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                        <i class="fas fa-check-circle me-1"></i> Mark <span x-text="selectedInvoices.length"></span> Paid
                    </button>
                </template>
            </div>

            <div class="d-flex flex-column flex-md-row gap-3" style="flex: 1; max-width: 600px;">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted theme-border"><i class="fas fa-filter"></i></span>
                    <select
                        class="form-select border-start-0 ps-0 shadow-none cursor-pointer theme-badge-bg theme-text-main theme-border"
                        x-model="statusFilter"
                        @change="fetchInvoices(1)"
                    >
                        <option value="">All Statuses</option>
                        <option value="paid">Paid</option>
                        <option value="partial">Partial</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                </div>

                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 text-muted theme-border"><i class="fas fa-search"></i></span>
                    <input
                        type="text"
                        class="form-control border-start-0 ps-0 shadow-none theme-badge-bg theme-text-main theme-border"
                        placeholder="Search invoice #, student..."
                        x-model="search"
                        @input.debounce.500ms="fetchInvoices(1)"
                    />
                    <template x-if="search">
                        <button class="btn btn-outline-secondary border-start-0 bg-transparent theme-border" type="button" @click="search = ''; fetchInvoices(1)">
                            <i class="fas fa-times"></i>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 theme-text-main">
                    <thead class="theme-badge-bg text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3" style="width: 50px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" :checked="selectAll" @change="handleSelectAll" />
                                </div>
                            </th>
                            <th class="px-4 py-3">Invoice Details</th>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Due Date</th>
                            <th class="px-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td colSpan="7" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && invoices.length > 0">
                            <template x-for="invoice in invoices" :key="invoice.invoice_id">
                                <tr class="theme-border" :class="selectedInvoices.includes(invoice.invoice_id) ? 'bg-primary bg-opacity-10' : ''">
                                    <td class="px-4 py-3">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                :checked="selectedInvoices.includes(invoice.invoice_id)"
                                                @change="handleSelectInvoice(invoice.invoice_id)"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="fw-bold theme-text-main" x-text="invoice.invoice_number"></div>
                                        <div class="small text-muted text-truncate" style="max-width: 150px;" x-text="invoice.description"></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="fw-medium theme-text-main" x-text="invoice.student?.student_name || 'N/A'"></div>
                                        <div class="small text-muted" x-text="invoice.group?.group_name || 'Individual'"></div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="fw-bold theme-text-main"><span x-text="Number(invoice.final_amount || invoice.amount).toLocaleString()"></span> EGP</div>
                                        <template x-if="invoice.discount_amount > 0">
                                            <div class="small text-success"><i class="fas fa-tag me-1"></i> -<span x-text="Number(invoice.discount_amount).toLocaleString()"></span></div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span x-html="getStatusBadge(invoice.status)"></span>
                                        <template x-if="invoice.status !== 'paid' && invoice.amount_paid > 0">
                                            <div class="small text-muted mt-1">Paid: <span x-text="Number(invoice.amount_paid).toLocaleString()"></span></div>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-muted small">
                                        <span x-text="new Date(invoice.due_date).toLocaleDateString()"></span>
                                        <template x-if="new Date(invoice.due_date) < new Date() && invoice.status !== 'paid'">
                                            <i class="fas fa-exclamation-circle text-danger ms-2" title="Overdue"></i>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <template x-if="invoice.status !== 'paid'">
                                                <button 
                                                    class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold"
                                                    @click="window.location.href = route('student_payments.show', invoice.uuid || invoice.invoice_id)"
                                                >
                                                    Pay
                                                </button>
                                            </template>
                                            <a :href="route('student.invoice.view', invoice.uuid || invoice.invoice_id)" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="View Invoice">
                                                <i class="fas fa-eye text-primary"></i>
                                            </a>
                                            <button @click="handleSimpleShare(invoice.invoice_id)" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Share Link (WhatsApp)">
                                                <i class="fab fa-whatsapp text-muted"></i>
                                            </button>
                                            <button @click="handleResendWhatsApp(invoice.invoice_id)" class="btn btn-sm btn-success border rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Resend WhatsApp (Auto)">
                                                <i class="fab fa-whatsapp text-white"></i>
                                            </button>
                                            <button @click="handleResendEmail(invoice.invoice_id)" class="btn btn-sm btn-info border rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Resend Email (Auto)">
                                                <i class="fas fa-envelope text-white"></i>
                                            </button>
                                            <button @click="handleCopyLink(invoice.public_token)" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Copy Public Link">
                                                <i class="fas fa-link text-info"></i>
                                            </button>
                                            <a :href="route('invoices.edit', invoice.uuid || invoice.invoice_id)" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Edit">
                                                <i class="fas fa-edit text-warning"></i>
                                            </a>
                                            <button @click="handleDelete(invoice.invoice_id)" class="btn btn-sm btn-light border rounded-circle theme-border" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Delete">
                                                <i class="fas fa-trash text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <template x-if="!loading && invoices.length === 0">
                            <tr>
                                <td colSpan="7" class="text-center py-5 text-muted">
                                    <div class="fs-1 mb-3">🧾</div>
                                    <h5 class="fw-bold">No invoices found</h5>
                                    <p class="small">Try adjusting search or filter criteria</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <template x-if="pagination.last_page > 1">
            <div class="card-footer bg-transparent border-top theme-border p-4 d-flex justify-content-between align-items-center">
                <span class="text-muted small fw-medium">
                    Showing <span class="fw-bold theme-text-main" x-text="((pagination.current_page - 1) * pagination.per_page) + 1"></span> to <span class="fw-bold theme-text-main" x-text="Math.min(pagination.current_page * pagination.per_page, pagination.total)"></span> of <span class="fw-bold theme-text-main" x-text="pagination.total"></span> entries
                </span>
                <nav>
                    <ul class="pagination pagination-sm mb-0 shadow-sm">
                        <li class="page-item" :class="pagination.current_page === 1 ? 'disabled' : ''">
                            <button class="page-link border-0 rounded-start-pill px-3 text-dark fw-bold" @click="handlePageChange(pagination.current_page - 1)">
                                <i class="fas fa-chevron-left small"></i>
                            </button>
                        </li>
                        <template x-for="pageNum in getPageNumbers()" :key="pageNum">
                            <li class="page-item" :class="pagination.current_page === pageNum ? 'active' : ''">
                                <template x-if="pageNum === '...'">
                                    <span class="page-link border-0 bg-transparent text-muted">...</span>
                                </template>
                                <template x-if="pageNum !== '...'">
                                    <button
                                        class="page-link border-0"
                                        :class="pagination.current_page === pageNum ? 'bg-primary text-white fw-bold shadow-sm' : 'text-dark fw-medium'"
                                        @click="handlePageChange(pageNum)"
                                        x-text="pageNum"
                                    ></button>
                                </template>
                            </li>
                        </template>
                        <li class="page-item" :class="pagination.current_page === pagination.last_page ? 'disabled' : ''">
                            <button class="page-link border-0 rounded-end-pill px-3 text-dark fw-bold" @click="handlePageChange(pagination.current_page + 1)">
                                <i class="fas fa-chevron-right small"></i>
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
        </template>
    </div>

    <!-- Create Invoice Modal -->
    <div x-show="showCreateModal" class="modal-backdrop fade show" style="z-index: 1040"></div>
    <div x-show="showCreateModal" @click.away="showCreateModal = false" class="modal fade" :class="showCreateModal ? 'show d-block' : ''" style="z-index: 1050" tabIndex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden theme-card">
                <div class="modal-header theme-badge-bg border-bottom-0 p-4">
                    <h5 class="modal-title fw-bold theme-text-main"><i class="fas fa-file-invoice-dollar text-primary me-2"></i> Create New Invoice</h5>
                    <button type="button" class="btn-close shadow-none" @click="showCreateModal = false"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <form @submit.prevent="submitCreate" id="createInvoiceForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Group (Optional)</label>
                                <select class="form-select rounded-3 theme-badge-bg theme-text-main theme-border" x-model.number="formData.group_id" @change="handleGroupChangeModal">
                                    <option value="">Select Group...</option>
                                    @foreach($groups as $g)
                                        <option value="{{ (int)$g->group_id }}">{{ $g->group_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Student <span class="text-danger">*</span></label>
                                <select class="form-select rounded-3 theme-badge-bg theme-text-main theme-border" x-model.number="formData.student_id" required>
                                    <option value="">Select Student...</option>
                                    <template x-if="!modalStudents || modalStudents.length === 0">
                                        @foreach($students as $s)
                                            <option value="{{ (int)$s->student_id }}">{{ $s->student_name }}</option>
                                        @endforeach
                                    </template>
                                    <template x-for="s in modalStudents" :key="s.student_id">
                                        <option :value="s.student_id" x-text="s.student_name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="col-12 mt-4">
                                <label class="form-label fw-bold small text-muted">Description <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border" x-model="formData.description" placeholder="e.g. Monthly Tuition Fee - October" required />
                            </div>

                            <div class="col-md-6 mt-4">
                                <label class="form-label fw-bold small text-muted">Amount (EGP) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light theme-border">£</span>
                                    <input type="number" step="0.01" class="form-control theme-badge-bg theme-text-main theme-border" x-model="formData.amount" required />
                                </div>
                            </div>
                            <div class="col-md-6 mt-4">
                                <label class="form-label fw-bold small text-muted">Due Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border" x-model="formData.due_date" required />
                            </div>

                            <div class="col-md-6 mt-4">
                                <label class="form-label fw-bold small text-muted">Discount (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" max="100" class="form-control theme-badge-bg theme-text-main theme-border" x-model="formData.discount_percent" />
                                    <span class="input-group-text bg-light theme-border">%</span>
                                </div>
                            </div>
                            <div class="col-md-6 mt-4">
                                <label class="form-label fw-bold small text-muted">Or Fixed Discount (EGP)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light theme-border">£</span>
                                    <input type="number" step="0.01" class="form-control theme-badge-bg theme-text-main theme-border" x-model="formData.discount_amount" />
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer theme-badge-bg border-top-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-medium border shadow-sm theme-card theme-text-main theme-border" @click="showCreateModal = false">Cancel</button>
                    <button type="submit" form="createInvoiceForm" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" :disabled="processing">
                        <template x-if="processing"><span class="spinner-border spinner-border-sm me-2"></span></template>
                        <i x-show="!processing" class="fas fa-save me-2"></i>
                        Create Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>
    <x-payment-modal />
</div>

<script>
function invoicesPage(config) {
    return {
        invoices: config.initialInvoices || [],
        pagination: config.pagination || {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
        },
        groups: config.groups || [],
        initialStudents: config.students || [],
        modalStudents: config.students || [],
        
        loading: false,
        search: '',
        statusFilter: '',
        selectedInvoices: [],
        selectAll: false,
        
        showCreateModal: false,
        processing: false,
        formData: {
            student_id: '',
            group_id: '',
            description: '',
            amount: '',
            discount_percent: '',
            discount_amount: '',
            due_date: new Date().toISOString().split('T')[0],
        },
        
        route(name, params = {}) {
            return window.route(name, params);
        },
        
        resetForm() {
            this.formData = {
                student_id: '', group_id: '', description: '', 
                amount: '', discount_percent: '', discount_amount: '',
                due_date: new Date().toISOString().split('T')[0]
            };
        },

        fetchInvoices(page = 1) {
            this.loading = true;
            axios.get(this.route('invoices.fetch'), {
                params: { 
                    page: page, 
                    search: this.search, 
                    status: this.statusFilter 
                }
            }).then(response => {
                this.invoices = response.data.invoices;
                this.pagination = response.data.pagination;
            }).catch(error => {
                console.error('Error fetching invoices', error);
            }).finally(() => {
                this.loading = false;
            });
        },
        
        handlePageChange(newPage) {
            if (newPage >= 1 && newPage <= this.pagination.last_page) {
                this.fetchInvoices(newPage);
            }
        },
        
        getPageNumbers() {
            const pages = [];
            const current = this.pagination.current_page;
            const last = this.pagination.last_page;
            
            for (let i = 1; i <= last; i++) {
                if (i === 1 || i === last || (i >= current - 1 && i <= current + 1)) {
                    pages.push(i);
                } else if (i === current - 2 || i === current + 2) {
                    pages.push('...');
                }
            }
            return [...new Set(pages)];
        },
        
        handleGroupChangeModal() {
            const groupId = this.formData.group_id;
            if (groupId) {
                axios.get(this.route('invoices.getStudentsByGroup'), { params: { group_id: groupId } })
                    .then(response => {
                        this.modalStudents = response.data;
                    });
            } else {
                this.modalStudents = this.initialStudents;
            }
        },
        
        submitCreate() {
            this.processing = true;
            axios.post(this.route('invoices.store'), this.formData)
                .then((response) => {
                    const msg = response.data.message || 'Invoice created successfully.';
                    Swal.fire({
                        title: 'Success!',
                        text: msg,
                        icon: 'success',
                        timer: 1500
                    });
                    this.showCreateModal = false;
                    this.resetForm();
                    this.fetchInvoices(1);
                    this.formData = {
                        student_id: '', group_id: '', description: '', 
                        amount: '', discount_percent: '', discount_amount: '',
                        due_date: new Date().toISOString().split('T')[0]
                    };
                }).catch(error => {
                    console.error('Invoice creation error:', error);
                    const errorMsg = error.response?.data?.message || 'Failed to create invoice.';
                    Swal.fire('Error', errorMsg, 'error');
                }).finally(() => {
                    this.processing = false;
                });
        },
        
        handleSelectAll() {
            if (this.selectAll) {
                this.selectedInvoices = [];
            } else {
                this.selectedInvoices = this.invoices.map(i => i.invoice_id);
            }
            this.selectAll = !this.selectAll;
        },
        
        handleSelectInvoice(id) {
            if (this.selectedInvoices.includes(id)) {
                this.selectedInvoices = this.selectedInvoices.filter(i => i !== id);
            } else {
                this.selectedInvoices.push(id);
            }
        },
        
        handleMarkAsPaid() {
            if (this.selectedInvoices.length === 0) return;
            Swal.fire({
                title: 'Mark as Paid?',
                text: `Are you sure you want to mark ${this.selectedInvoices.length} invoices as paid?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark as paid!'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.post(this.route('invoices.mark_paid'), { invoice_ids: this.selectedInvoices })
                        .then(() => {
                            this.fetchInvoices(this.pagination.current_page);
                            this.selectedInvoices = [];
                            this.selectAll = false;
                            Swal.fire('Success!', 'Invoices marked as paid.', 'success');
                        });
                }
            });
        },
        
        handleDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete(this.route('invoices.destroy', id))
                        .then(() => {
                            this.fetchInvoices(this.pagination.current_page);
                            Swal.fire('Deleted!', 'Invoice has been deleted.', 'success');
                        });
                }
            });
        },
        
        handleSimpleShare(id) {
            axios.get(this.route('invoices.share', id)).then(res => {
                window.open(res.data.url, '_blank');
            });
        },
        
        handleResendWhatsApp(id) {
            Swal.fire({
                title: 'Open WhatsApp?',
                text: "This will open WhatsApp Web with a pre-filled message.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Yes, open it!',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return axios.post(this.route('invoices.resend_whatsapp', id))
                        .then(response => response.data)
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error.response?.data?.message || error.message}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value?.whatsapp_url) {
                    window.open(result.value.whatsapp_url, '_blank');
                }
            });
        },
        
        handleResendEmail(id) {
            Swal.fire({
                title: 'Send Email?',
                text: "This will open your email client (Gmail, Outlook, etc.) with a formatted message for the student.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Yes, open it!',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return axios.post(this.route('invoices.resend_email', id))
                        .then(response => response.data)
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error.response?.data?.message || error.message}`);
                        });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value?.email_url) {
                    window.open(result.value.email_url, '_blank');
                    Swal.fire({
                        icon: 'success',
                        title: 'Email Initialized!',
                        text: 'Your email client should be opening now.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            });
        },
        
        handleCopyLink(token) {
            const url = this.route('invoices.public.show', token);
            navigator.clipboard.writeText(url);
            Swal.fire({
                icon: 'success',
                title: 'Copied!',
                text: 'Public invoice link copied to clipboard.',
                timer: 1500,
                showConfirmButton: false
            });
        },
        
        getStatusBadge(status) {
            switch (status) {
                case 'paid': return '<span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1 rounded-pill">Paid</span>';
                case 'partial': return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1 rounded-pill">Partial</span>';
                case 'pending':
                case 'unpaid': return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1 rounded-pill">Unpaid</span>';
                default: return '<span class="badge bg-secondary">Unknown</span>';
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
    .smaller { font-size: 0.75rem; }
    .cursor-pointer { cursor: pointer; }
    .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
    [x-cloak] { display: none !important; }
</style>
@endsection
