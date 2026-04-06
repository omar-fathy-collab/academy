@extends('layouts.authenticated')

@section('title', 'Invoice Details')

@section('content')
<div class="container py-5" x-data='{
    invoice: @json($invoice, JSON_HEX_APOS),
    payments: @json($payments, JSON_HEX_APOS),
    remaining: {{ $remainingBalance }},
    loadingWA: false,
    loadingEmail: false,
    print() {
        window.print();
    },
    async sendWhatsApp() {
        this.loadingWA = true;
        try {
            const response = await fetch(`/student/payments/${this.invoice.invoice_id}/whatsapp-url`);
            const data = await response.json();
            if (data.success && data.url) {
                window.open(data.url, "_blank");
            } else {
                alert("Could not generate WhatsApp link: " + (data.error || "Unknown error"));
            }
        } catch (e) {
            console.error(e);
            alert("Network error while generating link.");
        } finally {
            this.loadingWA = false;
        }
    },
    async sendEmail() {
        this.loadingEmail = true;
        try {
            const response = await fetch(`/student/payments/${this.invoice.invoice_id}/send-email`, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Content-Type": "application/json"
                }
            });
            const data = await response.json();
            if (data.success) {
                alert(data.message || "Email sent successfully!");
            } else {
                alert("Failed to send email: " + (data.message || "Unknown error"));
            }
        } catch (e) {
            console.error(e);
            alert("Network error while sending email.");
        } finally {
            this.loadingEmail = false;
        }
    }
}'>
    <!-- Action Bar (Hidden when printing) -->
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <a href="{{ auth()->user()->isAdmin() ? route('invoices.index') : route('student.dashboard') }}" class="btn theme-card rounded-circle shadow-sm border theme-border p-2 theme-text-main transition-hover">
            <i class="fas fa-arrow-left fa-lg"></i>
        </a>
        <div class="d-flex gap-2">
            <button @click="print()" class="btn btn-outline-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fas fa-print me-2"></i> Print Invoice
            </button>
            @if($remainingBalance > 0)
                <a href="{{ route('student_payments.show', $invoice->invoice_id) }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                    <i class="fas fa-credit-card me-2"></i> Pay Now
                </a>
            @endif
        </div>
    </div>

    <!-- Success Feedback / Notifications -->
    @if(session('message') || session('whatsapp_url'))
        <div class="row mb-4 animate__animated animate__fadeInDown">
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden" 
                     style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="card-body p-4 text-white">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-white bg-opacity-25 p-3 rounded-circle me-3">
                                    <i class="fas fa-check-double fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="fw-black mb-1">Payment Recorded Successfully!</h4>
                                    <p class="mb-0 opacity-90">Invoice #{{ $invoice->invoice_number }} has been updated.</p>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-2">
                                @if(session('whatsapp_url'))
                                    <a href="{{ session('whatsapp_url') }}" target="_blank" class="btn btn-white fw-black rounded-pill px-4 py-2 hover-scale shadow-lg" style="background: #25D366; color: white; border: none;">
                                        <i class="fab fa-whatsapp me-2"></i> Send WhatsApp
                                    </a>
                                @endif
                                @if(session('email_url'))
                                    <a href="{{ session('email_url') }}" target="_blank" class="btn btn-white fw-black rounded-pill px-4 py-2 hover-scale shadow-lg" style="background: #4e73df; color: white; border: none;">
                                        <i class="fas fa-envelope me-2"></i> Send Email
                                    </a>
                                @endif
                                <button @click="print()" class="btn btn-light fw-bold rounded-pill px-4 py-2 hover-scale">
                                    <i class="fas fa-print me-2"></i> Print Instead
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Invoice Card -->
    <div id="invoice-card" class="card border-0 shadow-lg rounded-4 overflow-hidden theme-card">
        <!-- Invoice Header -->
        <div class="bg-primary p-5 text-white position-relative overflow-hidden">
            <div class="position-absolute top-0 end-0 p-4 opacity-25">
                <i class="fas fa-file-invoice fa-9x rotate-12"></i>
            </div>
            
            <div class="row align-items-center position-relative">
                <div class="col-md-6">
                    <img src="{{ asset('img/shefae-logo.png') }}" alt="Academy Logo" class="mb-4 d-block filter-white" style="height: 60px;">
                    <h2 class="fw-black mb-1">INVOICE</h2>
                    <p class="mb-0 opacity-75">No: <span class="fw-bold" x-text="invoice.invoice_number"></span></p>
                </div>
                <div class="col-md-6 text-md-end mt-4 mt-md-0">
                    <div class="badge rounded-pill px-4 py-2 mb-3 bg-white text-primary fw-bold shadow-sm" x-text="invoice.status.toUpperCase()"></div>
                    <p class="mb-1 opacity-75">Date: <span x-text="new Date(invoice.created_at).toLocaleDateString()"></span></p>
                    <p class="mb-0 opacity-75">Due: <span x-text="new Date(invoice.due_date).toLocaleDateString()"></span></p>
                </div>
            </div>
        </div>

        <div class="card-body p-5">
            <div class="row mb-5 g-4">
                <div class="col-md-6">
                    <h6 class="text-muted fw-bold text-uppercase smaller mb-3">Billed To:</h6>
                    <h5 class="fw-bold mb-1 theme-text-main" x-text="invoice.student.student_name"></h5>
                    <p class="mb-1 theme-text-main opacity-75" x-text="invoice.student.user.email"></p>
                    <p class="mb-0 theme-text-main opacity-75" x-text="invoice.student.phone || 'No phone provided'"></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6 class="text-muted fw-bold text-uppercase smaller mb-3">Academy Details:</h6>
                    <h5 class="fw-bold mb-1 theme-text-main">{{ config('app.name', 'ICT Academy') }}</h5>
                    <p class="mb-1 theme-text-main opacity-75">10th of Ramadan, Egypt</p>
                    <p class="mb-0 theme-text-main opacity-75">info@ict-academy.com</p>
                </div>
            </div>

            <!-- Invoice Table -->
            <div class="table-responsive mb-5">
                <table class="table align-middle">
                    <thead class="theme-badge-bg border-0">
                        <tr>
                            <th class="px-4 py-3 border-0 rounded-start theme-text-main">Item Description</th>
                            <th class="py-3 border-0 text-center theme-text-main">Quantity</th>
                            <th class="py-3 border-0 text-end theme-text-main">Unit Price</th>
                            <th class="px-4 py-3 border-0 text-end rounded-end theme-text-main">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="theme-border">
                            <td class="px-4 py-4">
                                <h6 class="fw-bold mb-1 theme-text-main" x-text="invoice.group.group_name"></h6>
                                <p class="smaller text-muted mb-0">Course Enrollment Fees</p>
                            </td>
                            <td class="text-center theme-text-main">1</td>
                            <td class="text-end theme-text-main">£<span x-text="Number(invoice.amount).toLocaleString()"></span></td>
                            <td class="px-4 text-end fw-bold theme-text-main">£<span x-text="Number(invoice.amount).toLocaleString()"></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary -->
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="p-4 rounded-4 theme-badge-bg">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="theme-text-main opacity-75">Subtotal</span>
                            <span class="theme-text-main fw-bold">£<span x-text="Number(invoice.amount).toLocaleString()"></span></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-danger" x-show="invoice.discount_amount > 0">
                            <span class="opacity-75">Discount (<span x-text="invoice.discount_percent"></span>%)</span>
                            <span class="fw-bold">-£<span x-text="Number(invoice.discount_amount).toLocaleString()"></span></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom theme-border">
                            <span class="theme-text-main opacity-75">Paid Amount</span>
                            <span class="text-success fw-bold">-£<span x-text="Number(invoice.amount_paid).toLocaleString()"></span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-black mb-0 theme-text-main">TOTAL DUE</h5>
                            <h3 class="fw-black mb-0 text-primary">£<span x-text="Number(remaining).toLocaleString()"></span></h3>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-5 theme-border opacity-10">

            <!-- Payment History Timeline -->
            <div class="mb-5">
                <h5 class="fw-black mb-4 theme-text-main"><i class="fas fa-history me-2 text-success"></i> Payment History</h5>
                <div class="timeline-container ps-4">
                    <template x-if="payments.length === 0">
                        <p class="text-muted italic small">No payments recorded for this invoice yet.</p>
                    </template>
                    <template x-for="p in payments" :key="p.payment_id">
                        <div class="d-flex mb-4 position-relative">
                            <div class="timeline-dot bg-success rounded-circle position-absolute" style="width: 12px; height: 12px; left: -26px; top: 6px;"></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 theme-text-main">£<span x-text="Number(p.amount).toLocaleString()"></span> Payment Received</h6>
                                    <span class="smaller text-muted fw-bold" x-text="new Date(p.payment_date).toLocaleDateString()"></span>
                                </div>
                                <p class="smaller text-muted mb-0" x-text="'Via ' + p.payment_method + (p.notes ? ' • ' + p.notes : '')"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="bg-light theme-badge-bg p-4 rounded-4 text-center">
                <p class="text-muted small mb-0 fw-medium">Thank you for your trust in {{ config('app.name') }}. This is a computer-generated document and no signature is required.</p>
            </div>
        </div>
    </div>

    <!-- Admin Quick Actions (Hidden when printing) -->
    @if(auth()->user()->isAdmin())
        <div class="mt-5 d-print-none animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden border-start border-4 border-primary">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h5 class="fw-black mb-1 theme-text-main"><i class="fas fa-tools me-2 text-primary"></i> Admin Quick Actions</h5>
                            <p class="text-muted small mb-0">Manage notifications and receipt delivery for this invoice.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button @click="sendWhatsApp()" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm transition-200" :disabled="loadingWA">
                                <template x-if="!loadingWA">
                                    <span><i class="fab fa-whatsapp me-2"></i> Share via WhatsApp</span>
                                </template>
                                <template x-if="loadingWA">
                                    <span><i class="fas fa-spinner fa-spin me-2"></i> Generating...</span>
                                </template>
                            </button>
                            
                            <button @click="sendEmail()" class="btn btn-outline-primary fw-bold rounded-pill px-4 shadow-sm" :disabled="loadingEmail">
                                <template x-if="!loadingEmail">
                                    <span><i class="fas fa-envelope me-2"></i> Send Email Receipt</span>
                                </template>
                                <template x-if="loadingEmail">
                                    <span><i class="fas fa-spinner fa-spin me-2"></i> Sending...</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Scripts for Notification Actions -->
@if(auth()->user()->isAdmin())
<script>
    document.addEventListener('alpine:init', () => {
        // We need to merge this into the existing x-data if possible, 
        // but for now, we'll use a separate approach or update the original init.
    });
</script>
@endif

<style>
    @media print {
        /* Hide specific UI components that are not needed for the invoice */
        .main-sidebar-v3, .top-navbar-v3, footer, .d-print-none, 
        .admin-actions, .success-alert, .impersonation-banner-fixed { 
            display: none !important; 
        }
        
        /* Reset layout containers to take full width and remove offsets */
        .premium-lms-v3 { display: block !important; background: white !important; }
        .content-container-v3 { 
            margin-left: 0 !important; 
            padding: 0 !important; 
            width: 100% !important; 
            background: white !important;
        }
        .main-content-v3 { margin-top: 0 !important; padding: 0 !important; }
        .container, .container-fluid { 
            padding: 0 !important; 
            margin: 0 !important; 
            max-width: 100% !important; 
        }

        /* Optimize the invoice card itself */
        #invoice-card {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 1px solid #eee !important;
            box-shadow: none !important;
            transform: scale(0.96);
            transform-origin: top center;
            page-break-inside: avoid;
        }

        /* Extreme compression to fit on one A4 */
        #invoice-card .p-5 { padding: 1.5rem !important; }
        #invoice-card .p-4 { padding: 1rem !important; }
        #invoice-card .mb-5 { margin-bottom: 0.8rem !important; }
        #invoice-card .my-5 { margin-top: 0.8rem !important; margin-bottom: 0.8rem !important; }
        #invoice-card h2 { font-size: 1.6rem !important; margin-bottom: 0.3rem !important; }
        #invoice-card h3 { font-size: 1.4rem !important; }
        #invoice-card h5 { font-size: 1.1rem !important; }
        #invoice-card img { height: 45px !important; margin-bottom: 0.8rem !important; }
        .table td, .table th { padding: 0.6rem !important; font-size: 0.95rem !important; }
        
        /* Ensure colors print correctly */
        body { background: white !important; color: black !important; }
        @page { size: auto; margin: 10mm; }
        
        .bg-primary { background-color: #4e73df !important; color: white !important; -webkit-print-color-adjust: exact; }
        .theme-badge-bg { background-color: #f8f9fc !important; -webkit-print-color-adjust: exact; }
        .filter-white { filter: brightness(0) invert(1); }
    }
    
    .fw-black { font-weight: 900; }
    .rotate-12 { transform: rotate(-12deg); }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .smaller { font-size: 0.75rem; }
    .transition-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .timeline-container { border-left: 2px dashed var(--border-color); }
</style>
@endsection
