@extends('layouts.authenticated')

@section('title', 'Payment History')

@section('content')
<div class="container-fluid py-4 p-0">
    <!-- Header -->
    <div class="mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">Transaction History</h2>
            <p class="text-muted mb-0">Review all your past payments and their verification status.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('student.payments') }}" class="btn btn-white shadow-sm rounded-pill px-4 fw-bold border bg-white">
                <i class="fas fa-arrow-left me-2 text-primary"></i> Back to Invoices
            </a>
        </div>
    </div>

    <!-- History Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-history text-primary me-2"></i> Payment Records
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">ID</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Date</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted">Invoice</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Amount</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Method</th>
                            <th class="px-4 py-3 border-0 small text-uppercase fw-bold text-muted text-center">Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="fw-bold text-dark">#{{ $payment->payment_id }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-dark small fw-bold">{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</div>
                                    <div class="text-muted extra-small">{{ \Carbon\Carbon::parse($payment->payment_date)->format('h:i A') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('student.invoice.view', $payment->invoice_id) }}" class="text-primary text-decoration-none fw-bold small">
                                        Inv #{{ $payment->invoice->invoice_number }}
                                    </a>
                                    <div class="text-muted extra-small">{{ $payment->invoice->group->group_name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="fw-bold text-dark">EGP {{ number_format($payment->amount, 2) }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="badge bg-light text-dark rounded-pill px-3 py-2 border fw-bold small">
                                        {{ strtoupper($payment->payment_method) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($payment->receipt_image)
                                        <a href="{{ asset('storage/' . $payment->receipt_image) }}" target="_blank" class="text-primary fs-5">
                                            <i class="fas fa-image"></i>
                                        </a>
                                    @else
                                        <span class="text-muted small italic">None</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-5 text-center text-muted">
                                    <i class="fas fa-history fa-4x mb-3 opacity-25"></i>
                                    <p class="mb-0">No payment history found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .extra-small { font-size: 0.75rem; }
    .italic { font-style: italic; }
</style>
@endpush
