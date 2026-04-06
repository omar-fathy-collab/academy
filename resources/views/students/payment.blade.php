@extends('layouts.authenticated')

@section('title', 'Record Payment - ' . $invoice->invoice_number)

@section('content')
<div class="container py-4" x-data="paymentForm()">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ url()->previous() }}" class="btn theme-card rounded-circle shadow-sm border theme-border p-2 theme-text-main transition-hover">
                <i class="fas fa-arrow-left fa-lg"></i>
            </a>
            <div>
                <h2 class="h4 fw-black mb-0 theme-text-main">Record Student Payment</h2>
                <p class="text-muted small mb-0">Invoice #{{ $invoice->invoice_number }} | {{ $invoice->student->student_name }}</p>
            </div>
        </div>
        <div class="d-print-none">
            <span class="badge rounded-pill px-3 py-2 {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning' }} shadow-sm">
                {{ strtoupper($invoice->status) }}
            </span>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center">
            <i class="fas fa-exclamation-circle me-3 fa-lg"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    <div class="row g-4">
        <!-- Main Payment Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden theme-card">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('student.payment.process') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="invoice_id" value="{{ $invoice->invoice_id }}">
                        
                        <!-- Section: Payment Amount -->
                        <div class="mb-5">
                            <h5 class="fw-bold mb-4 theme-text-main d-flex align-items-center">
                                <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">1</span>
                                Payment Details
                            </h5>
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Payment Amount (EGP)</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden border theme-border">
                                        <span class="input-group-text bg-light border-0 theme-text-main">£</span>
                                        <input type="number" name="amount" step="0.01" class="form-control border-0 theme-card theme-text-main" 
                                               x-model.number="paymentAmount" :max="remainingBalance" required>
                                    </div>
                                    <div class="mt-2 d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Max: <span class="fw-bold" x-text="remainingBalance"></span> EGP</span>
                                        <button type="button" @click="paymentAmount = remainingBalance" class="btn btn-link btn-sm p-0 text-primary fw-bold text-decoration-none">Pay Full Balance</button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Payment Method</label>
                                    <select name="payment_method" class="form-select form-select-lg shadow-sm border theme-border theme-card theme-text-main rounded-3" required>
                                        <option value="cash">Cash</option>
                                        <option value="instapay">InstaPay</option>
                                        <option value="vodafone_cash">Vodafone Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="fawry">Fawry</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Discounts (Optional) -->
                        <div class="mb-5 bg-light theme-badge-bg p-4 rounded-4 border theme-border">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 theme-text-main"><i class="fas fa-percentage me-2 text-primary"></i> Adjust Discount</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" x-model="showDiscount">
                                </div>
                            </div>
                            
                            <div x-show="showDiscount" x-collapse>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">Discount Percent (%)</label>
                                        <input type="number" name="discount_percent" step="0.1" class="form-control shadow-sm theme-card theme-text-main border theme-border" 
                                               x-model.number="discountPercent" @input="updateFromPercent()">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">Discount Amount (EGP)</label>
                                        <input type="number" name="discount_amount" step="0.01" class="form-control shadow-sm theme-card theme-text-main border theme-border" 
                                               x-model.number="discountAmount" @input="updateFromAmount()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Additional Info -->
                        <div class="mb-5">
                            <h5 class="fw-bold mb-4 theme-text-main d-flex align-items-center">
                                <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">2</span>
                                Documentation & Notes
                            </h5>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Notes</label>
                                <textarea name="notes" rows="3" class="form-control shadow-sm border theme-border theme-card theme-text-main rounded-3" 
                                          placeholder="Enter internal notes, transaction IDs, etc."></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Receipt Image / Screenshot</label>
                                <div class="upload-zone p-4 border-2 border-dashed rounded-4 text-center transition-hover theme-border cursor-pointer position-relative"
                                     style="border-style: dashed !important;">
                                    <input type="file" name="receipt_image" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <p class="mb-0 theme-text-main fw-bold">Click to upload or drag and drop</p>
                                    <p class="text-muted small mb-0">PNG, JPG, PDF up to 5MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Notifications -->
                        <div class="mb-5">
                            <h5 class="fw-bold mb-4 theme-text-main d-flex align-items-center">
                                <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px; font-size: 0.9rem;">3</span>
                                Notifications
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-4 theme-border d-flex align-items-center justify-content-between transition-hover">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-success-subtle text-success p-2 rounded-3 me-3">
                                                <i class="fab fa-whatsapp fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold theme-text-main">WhatsApp Receipt</h6>
                                                <p class="smaller text-muted mb-0">Send payment confirmation</p>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" checked>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded-4 theme-border d-flex align-items-center justify-content-between transition-hover">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary-subtle text-primary p-2 rounded-3 me-3">
                                                <i class="fas fa-envelope fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold theme-text-main">Email Receipt</h6>
                                                <p class="smaller text-muted mb-0">Send official invoice</p>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="send_email" value="1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2 pt-3">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-black shadow-lg py-3 transition-hover">
                                <i class="fas fa-check-circle me-2"></i> RECORD PAYMENT
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Summary -->
        <div class="col-lg-4">
            <!-- Invoice Info Card -->
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 overflow-hidden border-top border-4 border-primary">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 theme-text-main">Invoice Summary</h5>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Base Amount</span>
                            <span class="fw-bold theme-text-main">{{ number_format($invoice->amount, 2) }} EGP</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Current Discount</span>
                            <span class="text-danger fw-bold">-{{ number_format($discountAmount, 2) }} EGP</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 pt-2 border-top theme-border">
                            <span class="text-muted fw-bold">Final Amount</span>
                            <span class="fw-bold theme-text-main">{{ number_format($finalAmount, 2) }} EGP</span>
                        </div>
                    </div>

                    <div class="mb-4 p-3 bg-light theme-badge-bg rounded-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Total Paid</span>
                            <span class="text-success fw-bold">{{ number_format($invoice->amount_paid, 2) }} EGP</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted fw-black">BALANCE DUE</span>
                            <span class="text-primary fw-black fs-5" x-text="formatCurrency(remainingBalance)"></span>
                        </div>
                    </div>

                    <div class="pt-2 border-top theme-border">
                        <div class="d-flex align-items-center mt-3">
                            <div class="avatar-sm me-3 bg-light theme-badge-bg p-2 rounded-circle border theme-border">
                                <i class="fas fa-graduation-cap text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold small theme-text-main">{{ $invoice->student->student_name }}</h6>
                                <p class="smaller text-muted mb-0">Group: {{ $invoice->group->group_name ?? 'Individual' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other Unpaid Invoices -->
            @if($oldInvoices->count() > 0)
                <div class="card border-0 shadow-sm rounded-4 theme-card border-top border-4 border-warning">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3 theme-text-main"><i class="fas fa-exclamation-triangle text-warning me-2"></i> Other Pending Invoices</h6>
                        <div class="list-group list-group-flush theme-border">
                            @foreach($oldInvoices as $old)
                                <a href="{{ route('student.payment.show', $old->invoice_id) }}" class="list-group-item list-group-item-action bg-transparent border-0 px-0 py-3 transition-hover">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold small theme-text-main">#{{ $old->invoice_number }}</div>
                                            <div class="smaller text-muted">{{ $old->group->group_name ?? 'Individual' }}</div>
                                        </div>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle smaller rounded-pill px-2">
                                            {{ number_format($old->balance_due, 2) }} EGP
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    function paymentForm() {
        return {
            originalAmount: {{ $invoice->amount }},
            amountPaid: {{ $invoice->amount_paid }},
            discountPercent: {{ $invoice->discount_percent }},
            discountAmount: {{ $discountAmount }},
            paymentAmount: {{ $balanceDue }},
            showDiscount: false,
            
            get finalAmount() {
                if (this.discountAmount > 0) {
                    return this.originalAmount - this.discountAmount;
                }
                return this.originalAmount - (this.originalAmount * (this.discountPercent / 100));
            },
            
            get remainingBalance() {
                return Math.max(0, this.finalAmount - this.amountPaid).toFixed(2);
            },
            
            updateFromPercent() {
                this.discountAmount = (this.originalAmount * (this.discountPercent / 100)).toFixed(2);
                this.paymentAmount = this.remainingBalance;
            },
            
            updateFromAmount() {
                this.discountPercent = ((this.discountAmount / this.originalAmount) * 100).toFixed(1);
                this.paymentAmount = this.remainingBalance;
            },
            
            formatCurrency(val) {
                return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EGP';
            }
        }
    }
</script>

<style>
    .fw-black { font-weight: 900; }
    .smaller { font-size: 0.75rem; }
    .transition-hover { transition: all 0.2s ease; }
    .transition-hover:hover { transform: translateY(-3px); }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    
    .upload-zone:hover { border-color: var(--bs-primary) !important; background-color: rgba(var(--bs-primary-rgb), 0.05); }
    
    .form-switch .form-check-input {
        width: 3rem;
        height: 1.5rem;
        cursor: pointer;
    }
    
    /* Animation for collapse */
    [x-collapse] {
        transition: height 300ms cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
</style>
@endsection
