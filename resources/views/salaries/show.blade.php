@extends('layouts.authenticated')

@section('title', 'Salary Details: ' . $salary->teacher_name)

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data='salaryShow({
    salary: @json($salary, JSON_HEX_APOS),
    groups: @json($salary_groups, JSON_HEX_APOS),
    payments: @json($payments, JSON_HEX_APOS)
})' x-init="init()" x-cloak>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('salaries.index') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border transition-hover">
                <i class="fas fa-arrow-left fa-lg"></i>
            </a>
            <div>
                <h2 class="fw-bold theme-text-main mb-1" x-text="salary.teacher_name"></h2>
                <p class="text-muted small mb-0">Salary Breakdown - <span class="fw-bold" x-text="salary.month"></span></p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('salaries.pay', $salary->uuid ?? $salary->salary_id) }}" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-money-bill-wave me-2"></i> Record Payout
            </a>
            <a href="{{ route('salaries.edit', $salary->uuid ?? $salary->salary_id) }}" class="btn btn-outline-warning fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-edit me-2"></i> Edit Record
            </a>
        </div>
    </div>

    <!-- Summary Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-primary">
                <div class="card-body p-4">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Teacher Share</p>
                    <h3 class="fw-bold theme-text-main mb-0">£<span x-text="Number(salary.net_salary).toLocaleString()"></span></h3>
                    <div class="smaller text-muted mt-2">Inc. bonuses & deductions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-success">
                <div class="card-body p-4">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Amount Paid</p>
                    <h3 class="fw-bold text-success mb-0">£<span x-text="Number(salary.paid_amount || {{ $paid_amount }}).toLocaleString()"></span></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card border-start border-4 border-danger">
                <div class="card-body p-4">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Balance Remaining</p>
                    <h3 class="fw-bold text-danger mb-0">£<span x-text="Number({{ $remaining_amount }}).toLocaleString()"></span></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card">
                <div class="card-body p-4">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Adjustments</p>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="smaller">Bonuses</span>
                        <span class="smaller fw-bold text-success">+£<span x-text="Number(salary.bonuses).toLocaleString()"></span></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="smaller">Deductions</span>
                        <span class="smaller fw-bold text-danger">-£<span x-text="Number(salary.deductions).toLocaleString()"></span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Group Breakdown -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden h-100">
                <div class="card-header theme-badge-bg border-bottom-0 p-4">
                    <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-layer-group text-primary me-2"></i> Group Revenue Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="accordion accordion-flush" id="groupAccordion">
                        <template x-for="(group, index) in groups" :key="group.group_id">
                            <div class="accordion-item theme-card border-bottom theme-border">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed theme-card theme-text-main shadow-none" type="button" data-bs-toggle="collapse" :data-bs-target="'#collapse-' + index">
                                        <div class="d-flex justify-content-between w-100 me-3">
                                            <span class="fw-bold" x-text="group.group_name"></span>
                                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 smaller">
                                                Share: £<span x-text="Number(group.teacher_share).toLocaleString()"></span>
                                            </span>
                                        </div>
                                    </button>
                                </h2>
                                <div :id="'collapse-' + index" class="accordion-collapse collapse" data-bs-parent="#groupAccordion">
                                    <div class="accordion-body theme-badge-bg p-4">
                                        <div class="row mb-3 g-3">
                                            <div class="col-md-4">
                                                <div class="p-3 theme-card rounded-3 theme-border border text-center">
                                                    <p class="smaller text-muted mb-1 text-uppercase fw-bold">Students</p>
                                                    <h5 class="fw-bold mb-0" x-text="group.total_students"></h5>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 theme-card rounded-3 theme-border border text-center">
                                                    <p class="smaller text-muted mb-1 text-uppercase fw-bold">Rev. Collected</p>
                                                    <h5 class="fw-bold mb-0">£<span x-text="Number(group.total_paid_fees).toLocaleString()"></span></h5>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 theme-card rounded-3 theme-border border text-center">
                                                    <p class="smaller text-muted mb-1 text-uppercase fw-bold">Teacher %</p>
                                                    <h5 class="fw-bold mb-0" x-text="group.teacher_percentage_display + '%'"></h5>
                                                </div>
                                            </div>
                                        </div>

                                        <h6 class="fw-bold smaller text-muted text-uppercase mb-3">Student Payment Details</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm theme-text-main">
                                                <thead class="smaller text-muted">
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Invoice</th>
                                                        <th>Paid</th>
                                                        <th class="text-end">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="student in group.student_details" :key="student.student_id">
                                                        <tr class="theme-border">
                                                            <td class="small py-2" x-text="student.student_name"></td>
                                                            <td class="small py-2">£<span x-text="Number(student.invoice_amount || 0).toLocaleString()"></span></td>
                                                            <td class="small py-2 fw-bold text-success">£<span x-text="Number(student.paid_amount || 0).toLocaleString()"></span></td>
                                                            <td class="text-end py-2">
                                                                <span class="badge rounded-pill px-2 py-1 smaller" :class="getBadgeClass(student.invoice_status)" x-text="student.invoice_status || 'N/A'"></span>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="card-header theme-badge-bg border-bottom-0 p-4">
                    <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-history text-success me-2"></i> Payout History</h5>
                </div>
                <div class="card-body p-4">
                    <div class="timeline-container">
                        <template x-if="payments.length === 0">
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-wallet fa-2x mb-2 opacity-25"></i>
                                <p class="small mb-0">No payouts recorded yet</p>
                            </div>
                        </template>
                        <template x-for="p in payments" :key="p.id">
                            <div class="d-flex mb-4 pb-3 border-bottom theme-border">
                                <div class="bg-success bg-opacity-10 text-success p-2 rounded-circle me-3 align-self-start">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <div class="w-100">
                                    <div class="d-flex justify-content-between mb-1">
                                        <h6 class="fw-bold mb-0">£<span x-text="Number(p.amount).toLocaleString()"></span></h6>
                                        <span class="smaller text-muted" x-text="new Date(p.payment_date).toLocaleDateString()"></span>
                                    </div>
                                    <p class="smaller text-muted mb-1" x-text="p.notes || 'No notes provided'"></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark smaller py-1 px-2 border" x-text="p.payment_method"></span>
                                        <span class="smaller text-muted">By: <span x-text="p.confirmed_by_name || 'Admin'"></span></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="alert alert-info border-0 rounded-4 p-3 mt-4 mb-0">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-university text-info me-2"></i>
                            <h6 class="fw-bold mb-0 small">Payment Info</h6>
                        </div>
                        <div class="smaller">
                            <p class="mb-1"><strong>Method:</strong> <span x-text="salary.payment_method || 'N/A'"></span></p>
                            <p class="mb-0 text-truncate"><strong>Account:</strong> <span x-text="salary.bank_account || 'N/A'"></span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outgoing Transfers Info -->
            <template x-if="outgoingTransfersAmount > 0">
                <div class="card border-0 shadow-sm rounded-4 theme-card mt-4 overflow-hidden border-start border-4 border-warning">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1 text-warning"><i class="fas fa-exchange-alt me-2"></i> Salary Transfers</h6>
                                <p class="smaller text-muted mb-0">Portion of salary transferred to other teachers</p>
                            </div>
                            <h4 class="fw-bold text-warning mb-0">-£<span x-text="Number(outgoingTransfersAmount).toLocaleString()"></span></h4>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function salaryShow(config) {
    return {
        salary: config.salary,
        groups: config.groups,
        payments: config.payments,
        outgoingTransfersAmount: config.salary.outgoing_transfers_amount || {{ $outgoingTransfersAmount ?? 0 }},
        
        route(name, id) {
            return window.route(name, id);
        },
        
        getBadgeClass(status) {
            switch (status?.toLowerCase()) {
                case 'paid': return 'bg-success bg-opacity-10 text-success border border-success';
                case 'partial': return 'bg-warning bg-opacity-10 text-warning border border-warning';
                case 'unpaid': return 'bg-danger bg-opacity-10 text-danger border border-danger ';
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
    .transition-hover:hover { transform: translateY(-2px); }
</style>
@endsection
