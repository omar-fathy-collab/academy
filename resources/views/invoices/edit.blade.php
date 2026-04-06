@extends('layouts.authenticated')

@section('title', 'Edit Invoice: ' . $invoice->invoice_number)

@section('content')
<script id="invoice-page-data" type="application/json">
{
    "invoice": @json($invoice),
    "groups": @json($groups),
    "students": @json($students),
    "formattedDate": "{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '' }}"
}
</script>

<div class="row justify-content-center pt-4" x-data="editInvoice()" x-init="initData()">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center">
                <a href="{{ route('invoices.index') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </a>
                <div>
                    <h2 class="fw-bold theme-text-main mb-1">Edit Invoice</h2>
                    <p class="text-muted small mb-0">Invoice #: <span class="theme-text-main fw-bold" x-text="invoice?.invoice_number || 'N/A'"></span></p>
                </div>
            </div>
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-4 py-2 border border-primary border-opacity-10 shadow-sm" x-text="invoice?.status || 'peding'"></span>
        </div>

        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
            <div class="card-header theme-badge-bg border-bottom-0 p-4">
                <h5 class="fw-bold theme-text-main mb-0"><i class="fas fa-edit text-primary me-2"></i> Invoice Information</h5>
            </div>
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('invoices.update', $invoice->invoice_id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Group (Optional)</label>
                            <select name="group_id" class="form-select rounded-3 theme-badge-bg theme-text-main theme-border" x-model.number="formData.group_id">
                                <option value="">Select Group...</option>
                                @foreach($groups as $g)
                                    <option value="{{ (int)$g->group_id }}">{{ $g->group_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Student <span class="text-danger">*</span></label>
                            <select name="student_id" id="student_select" class="form-select rounded-3 theme-badge-bg theme-text-main theme-border" x-model.number="formData.student_id" required>
                                <option value="">Select Student...</option>
                                @foreach($students as $s)
                                    <option value="{{ (int)$s->student_id }}">{{ $s->student_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 mt-4">
                            <label class="form-label fw-bold small text-muted">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border" x-model="formData.description" required />
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small text-muted">Amount (EGP) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light theme-border">£</span>
                                <input type="number" name="amount" step="0.01" class="form-control theme-badge-bg theme-text-main theme-border" x-model="formData.amount" required />
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small text-muted">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control rounded-3 theme-badge-bg theme-text-main theme-border" x-model="formData.due_date" required />
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small text-muted">Discount (%)</label>
                            <div class="input-group">
                                <input type="number" name="discount_percent" step="0.1" max="100" class="form-control theme-badge-bg theme-text-main theme-border" x-model="formData.discount_percent" />
                                <span class="input-group-text bg-light theme-border">%</span>
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small text-muted">Fixed Discount (EGP)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light theme-border">£</span>
                                <input type="number" name="discount_amount" step="0.01" class="form-control theme-badge-bg theme-text-main theme-border" x-model="formData.discount_amount" />
                            </div>
                        </div>

                        <template x-if="invoice && invoice.amount_paid > 0">
                            <div class="col-12 mt-4">
                                <div class="alert alert-info border-0 rounded-4 p-4 d-flex align-items-center">
                                    <div class="me-4 text-primary fs-3"><i class="fas fa-info-circle"></i></div>
                                    <div>
                                        <h6 class="fw-bold mb-1">Previous Payments Detected</h6>
                                        <p class="mb-0 small">This invoice has already received <span class="fw-bold" x-text="Number(invoice.amount_paid).toLocaleString()"></span> EGP in payments. Changing the total amount or discount must ensure the final amount remains above what's already paid.</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="col-12 mt-5">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-3 rounded-pill shadow fw-bold text-uppercase">
                                    <i class="fas fa-save me-2"></i> Update Invoice
                                </button>
                                <button type="button" @click="handleResetPayments" class="btn btn-outline-danger py-3 rounded-pill fw-bold text-uppercase border-2" x-show="invoice && invoice.amount_paid > 0">
                                    <i class="fas fa-history me-2"></i> Reset All Payments
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editInvoice() {
    return {
        invoice: {},
        groups: [],
        initialStudents: [],
        modalStudents: [],
        formData: {
            student_id: '',
            group_id: '',
            description: '',
            amount: 0,
            discount_percent: 0,
            discount_amount: 0,
            due_date: ''
        },
        
        initData() {
            try {
                const dataElement = document.getElementById('invoice-page-data');
                if (dataElement) {
                    const config = JSON.parse(dataElement.textContent);
                    this.invoice = config.invoice || {};
                    this.groups = config.groups || [];
                    this.initialStudents = config.students || [];
                    this.modalStudents = []; // Start empty so Blade pre-rendered options are used initially
                    
                    this.formData = {
                        student_id: this.invoice.student_id ? Number(this.invoice.student_id) : '',
                        group_id: this.invoice.group_id ? Number(this.invoice.group_id) : '',
                        description: this.invoice.description || '',
                        amount: this.invoice.amount || 0,
                        discount_percent: this.invoice.discount_percent || 0,
                        discount_amount: this.invoice.discount_amount || 0,
                        due_date: config.formattedDate || ''
                    };
                }
            } catch (e) {
                console.error("Error initializing invoice data: ", e);
            }
        },
        
        route(name, id) {
            if (typeof window.route === 'function') {
                return window.route(name, id);
            }
            return '#';
        },
        
        handleGroupChange() {
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
        
        handleResetPayments() {
            if (!this.invoice.invoice_id) return;
            
            Swal.fire({
                title: 'Reset Payments?',
                text: "This will DELETE all recorded payments for this invoice. This action is irreversible!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, reset everything!'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.post(this.route('invoices.reset_payments', this.invoice.invoice_id))
                        .then(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Reset Successful',
                                text: 'All payments have been removed.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        });
                }
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
</style>
@endsection
