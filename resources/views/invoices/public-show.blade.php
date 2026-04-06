@extends('layouts.guest')

@section('title', 'Invoice: ' . $invoice->invoice_number)

@section('content')
<div class="row justify-content-center py-5">
    <div class="col-lg-8">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-dark p-4 p-md-5 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <img src="{{ $site_settings['logo'] }}" alt="Logo" class="img-fluid me-3" style="max-height: 50px;">
                    <div>
                        <h4 class="fw-bold text-white mb-0">{{ $site_settings['site_name'] }}</h4>
                        <p class="text-white-50 small mb-0">Professional Academy Billing</p>
                    </div>
                </div>
                <div class="text-md-end d-none d-md-block">
                    <h5 class="fw-bold text-white mb-0">INVOICE</h5>
                    <p class="text-white-50 small mb-0">No. {{ $invoice->invoice_number }}</p>
                </div>
            </div>

            <div class="card-body p-4 p-md-5">
                <div class="row mb-5">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <p class="text-muted small fw-bold text-uppercase mb-2">Billed To:</p>
                        <h5 class="fw-bold mb-1">{{ $invoice->student->student_name }}</h5>
                        <p class="text-muted mb-0">{{ $invoice->student->user->email ?? 'N/A' }}</p>
                        <p class="text-muted mb-0">{{ $invoice->student->phone ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="text-muted small fw-bold text-uppercase mb-2">Invoice Details:</p>
                        <p class="mb-1 fw-bold">Date: <span class="text-muted fw-normal">{{ \Carbon\Carbon::parse($invoice->created_at)->format('M d, Y') }}</span></p>
                        <p class="mb-1 fw-bold">Due Date: <span class="text-muted fw-normal">{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</span></p>
                        <p class="mb-0 fw-bold">Group: <span class="text-muted fw-normal">{{ $invoice->group->group_name }}</span></p>
                    </div>
                </div>

                <div class="table-responsive mb-5">
                    <table class="table table-borderless">
                        <thead class="bg-light border-bottom">
                            <tr>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-end" style="width: 150px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-bottom">
                                <td class="px-4 py-4">
                                    <h6 class="fw-bold mb-1">{{ $invoice->description }}</h6>
                                    <p class="text-muted small mb-0">Service provided by {{ $site_settings['site_name'] }}</p>
                                </td>
                                <td class="px-4 py-4 text-end fw-bold">
                                    {{ number_format($invoice->amount, 2) }} <span class="small">EGP</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row justify-content-end text-end">
                    <div class="col-md-6 col-lg-5">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold">{{ number_format($invoice->amount, 2) }} EGP</span>
                        </div>
                        @if($invoice->discount_amount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-success">Discount:</span>
                                <span class="fw-bold text-success">-{{ number_format($invoice->discount_amount, 2) }} EGP</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between mb-4 border-top pt-2">
                            <h5 class="fw-bold">Total Amount:</h5>
                            <h5 class="fw-bold">{{ number_format($invoice->final_amount, 2) }} EGP</h5>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Amount Paid:</span>
                            <span class="fw-bold">{{ number_format($invoice->amount_paid, 2) }} EGP</span>
                        </div>
                        <div class="d-flex justify-content-between p-3 rounded-4 {{ $invoice->status === 'paid' ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' }}">
                            <h5 class="fw-bold mb-0">Balance Due:</h5>
                            <h5 class="fw-bold mb-0">{{ number_format($invoice->balance_due, 2) }} EGP</h5>
                        </div>
                    </div>
                </div>

                <div class="border-top mt-5 pt-5 text-center text-muted">
                    <h6 class="fw-bold text-dark mb-2">Thank you for being part of our Academy!</h6>
                    <p class="small mb-0">If you have any questions regarding this invoice, please contact our support team.</p>
                </div>
            </div>
            
            <div class="card-footer bg-light p-4 text-center d-print-none">
                <button onclick="window.print()" class="btn btn-dark rounded-pill px-5 shadow-sm">
                    <i class="fas fa-print me-2"></i> Print or Save as PDF
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
