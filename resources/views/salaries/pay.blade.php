@extends('layouts.authenticated')

@section('title', 'Record Salary Payment')

@section('content')
<div class="container-fluid pb-5">
    <!-- Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-auto">
            <a href="{{ route('salaries.index') }}" class="btn btn-light border theme-border rounded-circle p-2 shadow-sm">
                <i class="fas fa-arrow-left text-primary"></i>
            </a>
        </div>
        <div class="col">
            <h2 class="fw-bold theme-text-main mb-0">💳 Record Salary Payment</h2>
            <p class="text-muted mb-0">Processing payout for {{ $salary->teacher_name }} - {{ $salary->month }}</p>
        </div>
        <div class="col-auto">
             <button class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm" @click="window.print()">
                <i class="fas fa-print me-2"></i> Print View
            </button>
        </div>
    </div>

    <div class="row g-4" x-data="salaryPayment({ 
        salaryId: '{{ $salary->uuid ?: $salary->salary_id }}',
        remaining: {{ $remaining_amount }},
        available: {{ $available_payment }},
        maxAllowed: {{ $max_allowed }},
        isPaid: '{{ $salary->status === 'paid' ? 'true' : 'false' }}'
    })">
        <!-- Left Column: Details & Breakdown -->
        <div class="col-lg-8">
            <!-- Salary Summary Card -->
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 overflow-hidden">
                <div class="card-header bg-primary bg-opacity-10 border-0 p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="avatar-md me-3 bg-white p-2 rounded-4 shadow-sm border theme-border text-center">
                                <i class="fas fa-chalkboard-teacher text-primary fs-3"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 fw-bold theme-text-main">{{ $salary->teacher_name }}</h4>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3">
                                    {{ $salary->month }}
                                </span>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="text-muted smaller fw-bold text-uppercase mb-1">Net Calculated Share</p>
                            <h3 class="fw-bold text-primary mb-0">£{{ number_format($salary->teacher_share, 2) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="p-3 theme-badge-bg rounded-4 border theme-border">
                                <p class="text-muted smaller mb-1">Revenue Generated</p>
                                <h5 class="fw-bold mb-0">£{{ number_format($salary->group_revenue, 2) }}</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-success bg-opacity-10 rounded-4 border border-success-subtle">
                                <p class="text-success smaller mb-1">Paid Already</p>
                                <h5 class="fw-bold text-success mb-0">£{{ number_format($paid_amount, 2) }}</h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-danger bg-opacity-10 rounded-4 border border-danger-subtle h-100 d-flex flex-column justify-content-center">
                                <p class="text-danger smaller mb-1">Remaining Balance</p>
                                <h5 class="fw-bold text-danger mb-0">£{{ number_format($remaining_amount, 2) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adjustments & Transfers -->
            <div class="row g-4 mb-4">
                <!-- Bonuses & Deductions -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 theme-card h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold theme-text-main mb-3"><i class="fas fa-magic text-warning me-2"></i> Adjustments</h5>
                            
                            @if($pendingBonuses->isEmpty() && $pendingDeductions->isEmpty())
                                <div class="text-center py-4 opacity-50">
                                    <i class="fas fa-check-circle fs-1 text-muted mb-2"></i>
                                    <p class="mb-0 small">No pending adjustments</p>
                                </div>
                            @else
                                <div class="list-group list-group-flush border-0">
                                    @foreach($pendingBonuses as $bonus)
                                        <div class="list-group-item bg-transparent theme-border px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold small theme-text-main">{{ $bonus->reason ?: 'Bonus' }}</div>
                                                <div class="smaller text-muted">{{ \Carbon\Carbon::parse($bonus->created_at)->format('d M') }}</div>
                                            </div>
                                            <div class="text-success fw-bold">+£{{ number_format($bonus->amount, 2) }}</div>
                                        </div>
                                    @endforeach
                                    @foreach($pendingDeductions as $deduction)
                                        <div class="list-group-item bg-transparent theme-border px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold small theme-text-main">{{ $deduction->reason ?: 'Deduction' }}</div>
                                                <div class="smaller text-muted">{{ \Carbon\Carbon::parse($deduction->created_at)->format('d M') }}</div>
                                            </div>
                                            <div class="text-danger fw-bold">-£{{ number_format($deduction->amount, 2) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Incoming Transfers -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 theme-card h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold theme-text-main mb-3"><i class="fas fa-exchange-alt text-info me-2"></i> Incoming Transfers</h5>
                            
                            @if($incomingTransfers->isEmpty())
                                <div class="text-center py-4 opacity-50">
                                    <i class="fas fa-inbox fs-1 text-muted mb-2"></i>
                                    <p class="mb-0 small">No pending transfers</p>
                                </div>
                            @else
                                <div class="list-group list-group-flush border-0">
                                    @foreach($incomingTransfers as $transfer)
                                        <div class="list-group-item bg-transparent theme-border px-0 py-2 d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold small theme-text-main">From: {{ $transfer->source_teacher_name }}</div>
                                                <div class="smaller text-muted">{{ $transfer->transfer_month }}</div>
                                            </div>
                                            <div class="text-info fw-bold">+£{{ number_format($transfer->transfer_amount, 2) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outgoing Transfers (Deductions) -->
            @if($outgoingTransfers->isNotEmpty())
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 border-start border-4 border-danger">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-danger mb-3"><i class="fas fa-share-square me-2"></i> Outgoing Transfers (Deducted)</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <thead class="smaller text-muted text-uppercase">
                                <tr>
                                    <th>Target Teacher</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($outgoingTransfers as $transfer)
                                <tr>
                                    <td class="fw-bold small theme-text-main">{{ $transfer->target_teacher_name }}</td>
                                    <td class="fw-bold text-danger">£{{ number_format($transfer->transfer_amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $transfer->payment_status === 'paid' ? 'bg-success' : 'bg-warning' }} rounded-pill smaller">
                                            {{ ucfirst($transfer->payment_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('salaries.pay', $transfer->target_salary_id) }}" class="btn btn-link btn-xs p-0 smaller text-primary">View</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column: Payment Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card border-top border-5 border-success h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold theme-text-main mb-4">Record Payment</h5>
                    
                    @if($salary->status === 'paid')
                        <div class="alert alert-success rounded-4 border-0 mb-4 d-flex align-items-center">
                            <i class="fas fa-check-circle fs-3 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Fully Paid</h6>
                                <p class="mb-0 smaller">This salary record is closed.</p>
                            </div>
                        </div>
                    @endif

                    <form action="{{ route('salaries.process-payment', $salary->uuid ?: $salary->salary_id) }}" method="POST" id="paymentForm">
                        @csrf
                        
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold smaller">PAYMENT AMOUNT (EGP)</label>
                            <div class="input-group input-group-lg border theme-border rounded-4 overflow-hidden theme-badge-bg">
                                <span class="input-group-text bg-transparent border-0 text-success fw-bold">£</span>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    class="form-control border-0 bg-transparent fw-bold theme-text-main shadow-none" 
                                    name="payment_amount"
                                    x-model="paymentAmount"
                                    :max="maxAllowed"
                                    required
                                >
                            </div>
                            <div class="mt-2 d-flex justify-content-between">
                                <button type="button" class="btn btn-link btn-xs p-0 smaller text-primary text-decoration-none" @click="paymentAmount = remaining">Pay Full Balance</button>
                                <span class="smaller text-muted" :class="paymentAmount > maxAllowed ? 'text-danger fw-bold' : ''">Max Allowed: £<span x-text="maxAllowed.toFixed(2)"></span></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold smaller">PAYMENT METHOD</label>
                            <div class="row g-2">
                                <div class="col-4">
                                    <input type="radio" class="btn-check" name="payment_method" id="cash" value="cash" checked required>
                                    <label class="btn btn-outline-success w-100 rounded-3 py-2 smaller fw-bold" for="cash">
                                        <i class="fas fa-wallet d-block mb-1"></i> Cash
                                    </label>
                                </div>
                                <div class="col-4">
                                    <input type="radio" class="btn-check" name="payment_method" id="bank" value="bank_transfer" required>
                                    <label class="btn btn-outline-primary w-100 rounded-3 py-2 smaller fw-bold" for="bank">
                                        <i class="fas fa-university d-block mb-1"></i> Bank
                                    </label>
                                </div>
                                <div class="col-4">
                                    <input type="radio" class="btn-check" name="payment_method" id="v_cash" value="vodafone_cash" required>
                                    <label class="btn btn-outline-danger w-100 rounded-3 py-2 smaller fw-bold" for="v_cash">
                                        <i class="fas fa-mobile-alt d-block mb-1"></i> V. Cash
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Included Adjustments check -->
                        @if($pendingBonuses->isNotEmpty() || $incomingTransfers->isNotEmpty())
                        <div class="mb-4 p-3 theme-badge-bg rounded-4 border theme-border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold smaller theme-text-main">Included in this payment:</h6>
                                <span class="badge bg-primary rounded-pill smaller" x-text="selectedAdjustmentsCount"></span>
                            </div>
                            
                            @foreach($pendingBonuses as $bonus)
                            <div class="form-check smaller mb-1">
                                <input class="form-check-input" type="checkbox" name="selected_bonuses[]" value="{{ $bonus->adjustment_id }}" id="bonus_{{ $bonus->adjustment_id }}" checked @change="updateSelectedCount()">
                                <label class="form-check-label theme-text-main" for="bonus_{{ $bonus->adjustment_id }}">
                                    Bonus: {{ $bonus->reason ?: 'Adjustment' }} (+£{{ number_format($bonus->amount, 2) }})
                                </label>
                            </div>
                            @endforeach

                            @foreach($incomingTransfers as $transfer)
                            <div class="form-check smaller mb-1">
                                <input class="form-check-input" type="checkbox" name="selected_incoming_transfers[]" value="{{ $transfer->transfer_id }}" id="transfer_{{ $transfer->transfer_id }}" checked @change="updateSelectedCount()">
                                <label class="form-check-label theme-text-main" for="transfer_{{ $transfer->transfer_id }}">
                                    Transfer: {{ $transfer->source_teacher_name }} (+£{{ number_format($transfer->transfer_amount, 2) }})
                                </label>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold smaller">INTERNAL NOTES</label>
                            <textarea class="form-control border theme-border rounded-4 theme-badge-bg theme-text-main shadow-none" name="notes" rows="2" placeholder="Reference numbers, reasons..."></textarea>
                        </div>

                        <div class="d-grid mt-auto">
                            <button 
                                type="submit" 
                                class="btn btn-success btn-lg rounded-pill fw-bold py-3 shadow-lg transition-hover"
                                :disabled="paymentAmount <= 0 || isPaid === 'true'"
                            >
                                <i class="fas fa-check-circle me-2"></i> Record & Close Payout
                            </button>
                        </div>
                    </form>

                    <!-- Post-Payment Actions -->
                    <div class="mt-4 pt-4 border-top theme-border">
                        <h6 class="fw-bold smaller theme-text-main mb-3 text-uppercase opacity-50">Notification Center</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <button class="btn btn-outline-success w-100 rounded-3 py-2 smaller fw-bold" @click="handleNotify('whatsapp')">
                                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-primary w-100 rounded-3 py-2 smaller fw-bold" @click="handleNotify('email')">
                                    <i class="fas fa-envelope me-1"></i> Email
                                </button>
                            </div>
                            <div class="col-12 mt-2 text-center">
                                <p class="smaller text-muted mb-0">The teacher will receive a secure link to their salary slip.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function salaryPayment(config) {
    return {
        salaryId: config.salaryId,
        remaining: config.remaining,
        available: config.available,
        maxAllowed: config.maxAllowed,
        isPaid: config.isPaid,
        paymentAmount: config.remaining > 0 ? config.remaining : 0,
        selectedAdjustmentsCount: 0,

        init() {
            this.updateSelectedCount();
        },

        updateSelectedCount() {
            const checked = document.querySelectorAll('input[type="checkbox"]:checked').length;
            this.selectedAdjustmentsCount = checked;
        },

        handleNotify(type) {
            axios.post(`/salaries/${this.salaryId}/notify`, { type: type })
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
    .transition-hover:hover { transform: translateY(-2px); }
    .avatar-md { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; }
    @media print {
        .col-lg-4, .btn-light, .btn-outline-primary, .col-auto:first-child { display: none !important; }
        .col-lg-8 { width: 100% !important; }
        body { background-color: white !important; }
    }
</style>
@endpush
@endsection
