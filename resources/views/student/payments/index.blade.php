@extends('layouts.authenticated')

@section('title', 'My Invoices & Payments')

@section('content')
<div class="container-fluid py-4 p-0" x-data="paymentManager()">
    <!-- Header -->
    <div class="mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">Financial Overview</h2>
            <p class="text-muted mb-0">Manage your invoices, track payments, and submit proofs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('student.payments.history') }}" class="btn btn-white shadow-sm rounded-pill px-4 fw-bold border bg-white">
                <i class="fas fa-history me-2 text-primary"></i> Payment History
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-file-invoice-dollar fa-xl"></i>
                    </div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Amount</h6>
                    <h3 class="fw-bold text-dark mb-0">EGP {{ number_format($totalAmount, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-check-circle fa-xl"></i>
                    </div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Amount Paid</h6>
                    <h3 class="fw-bold text-success mb-0">EGP {{ number_format($totalPaid, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-start border-5 border-danger">
                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-exclamation-circle fa-xl"></i>
                    </div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Remaining Balance</h6>
                    <h3 class="fw-bold text-danger mb-0">EGP {{ number_format($totalBalance, 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-list text-primary me-2"></i> My Invoices
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Invoice #</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Group / Course</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Amount</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Paid</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Status</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="fw-bold text-dark">#{{ $invoice->invoice_number }}</span>
                                    <div class="text-muted extra-small">{{ \Carbon\Carbon::parse($invoice->created_at)->format('M d, Y') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="fw-bold text-dark">{{ $invoice->group->group_name ?? 'N/A' }}</div>
                                    <div class="text-muted extra-small">{{ $invoice->group->course->course_name ?? 'Course' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="fw-bold">EGP {{ number_format($invoice->final_amount, 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center text-success">
                                    <div class="fw-bold">EGP {{ number_format($invoice->amount_paid, 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $statusClass = match($invoice->status) {
                                            'paid' => 'bg-success text-success',
                                            'partial' => 'bg-warning text-dark',
                                            'pending' => 'bg-danger text-danger',
                                            default => 'bg-secondary text-white'
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} bg-opacity-10 rounded-pill px-3 py-2 border-0 fw-bold">
                                        {{ ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        @if($invoice->status !== 'paid')
                                            <button @click="openPaymentModal(@js($invoice))" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm">
                                                <i class="fas fa-upload me-1"></i> Pay
                                            </button>
                                        @endif
                                        <a href="{{ route('student.invoice.view', $invoice->invoice_id) }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-bold">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-5 text-center text-muted">
                                    <i class="fas fa-file-invoice fa-4x mb-3 opacity-25"></i>
                                    <p class="mb-0">No invoices found in your account.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Proof Modal -->
    <div x-show="showModal" class="modal fade" :class="{ 'show d-block': showModal }" x-cloak style="background: rgba(0,0,0,0.6); backdrop-filter: blur(5px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header bg-primary text-white p-4 border-0">
                    <h5 class="modal-title fw-bold">Submit Payment Proof</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showModal = false"></button>
                </div>
                <form @submit.prevent="submitProof">
                    <div class="modal-body p-4">
                        <div x-show="error" class="alert alert-danger rounded-3 p-2 small mb-3" x-text="error"></div>
                        
                        <div class="bg-light rounded-4 p-3 mb-4 border">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="small text-muted mb-1">Invoice Number</div>
                                    <div class="fw-bold text-dark" x-text="'#' + selectedInvoice?.invoice_number"></div>
                                </div>
                                <div class="col text-end border-start">
                                    <div class="small text-muted mb-1">Balance Due</div>
                                    <div class="fw-bold text-danger" x-text="formatCurrency(selectedInvoice?.final_amount - selectedInvoice?.amount_paid)"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small text-uppercase">Payment Screenshot / Receipt</label>
                            <div class="dropzone-area border-dashed rounded-4 p-4 text-center cursor-pointer hover-bg-light transition-all" 
                                 :class="{ 'border-primary bg-primary bg-opacity-10': dragging }"
                                 @dragover.prevent="dragging = true"
                                 @dragleave.prevent="dragging = false"
                                 @drop.prevent="handleDrop($event)"
                                 @click="$refs.fileInput.click()">
                                
                                <div x-show="!file">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary opacity-50 mb-3"></i>
                                    <p class="mb-0 text-muted small">Drag your receipt here or <span class="text-primary fw-bold">click to browse</span></p>
                                </div>
                                
                                <div x-show="file" class="d-flex align-items-center justify-content-center gap-3">
                                    <div class="bg-primary bg-opacity-10 rounded p-2">
                                        <i class="fas fa-image text-primary fa-2x"></i>
                                    </div>
                                    <div class="text-start">
                                        <div class="fw-bold text-dark small text-truncate" style="max-width: 200px;" x-text="file?.name"></div>
                                        <div class="text-muted extra-small" x-text="formatFileSize(file?.size)"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light rounded-circle" @click.stop="file = null">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <input type="file" x-ref="fileInput" class="d-none" accept="image/*" @change="handleFileChange($event)">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold text-dark small text-uppercase">Notes (Optional)</label>
                            <textarea x-model="notes" class="form-control rounded-3 border-light bg-light" rows="3" placeholder="Reference number, method, etc..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer p-4 pt-0 border-0 gap-2">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" :disabled="processing || !file">
                            <span x-show="!processing"><i class="fas fa-paper-plane me-2"></i> Submit Proof</span>
                            <span x-show="processing" class="spinner-border spinner-border-sm me-2"></span>
                            <span x-show="processing">Uploading...</span>
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
function paymentManager() {
    return {
        showModal: false,
        selectedInvoice: null,
        file: null,
        notes: '',
        processing: false,
        error: null,
        dragging: false,

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-EG', { style: 'currency', currency: 'EGP' }).format(amount || 0);
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        openPaymentModal(invoice) {
            this.selectedInvoice = invoice;
            this.showModal = true;
            this.file = null;
            this.notes = '';
            this.error = null;
        },

        handleFileChange(e) {
            this.file = e.target.files[0];
        },

        handleDrop(e) {
            this.dragging = false;
            this.file = e.dataTransfer.files[0];
        },

        async submitProof() {
            if (!this.file) return;
            this.processing = true;
            this.error = null;

            const formData = new FormData();
            formData.append('payment_screenshot', this.file);
            formData.append('payment_notes', this.notes);
            formData.append('invoice_id', this.selectedInvoice.invoice_id);

            try {
                // Using the named route for submissions
                await axios.post(route('invoices.submit_payment', this.selectedInvoice.invoice_id), formData, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                
                Swal.fire({
                    icon: 'success',
                    title: 'Proof Submitted!',
                    text: 'Your payment proof has been sent for verification.',
                    timer: 3000,
                    showConfirmButton: false
                });
                
                setTimeout(() => window.location.reload(), 2000);
            } catch (err) {
                this.error = err.response?.data?.message || 'Failed to submit proof. Please try again.';
                this.processing = false;
            }
        }
    };
}
</script>
@endpush

@push('styles')
<style>
    .extra-small { font-size: 0.75rem; }
    .cursor-pointer { cursor: pointer; }
    .hover-bg-light:hover { background-color: rgba(0,0,0,0.02); }
    .border-dashed { border-style: dashed !important; border-width: 2px !important; border-color: #dee2e6 !important; }
    .transition-all { transition: all 0.2s ease; }
</style>
@endpush
