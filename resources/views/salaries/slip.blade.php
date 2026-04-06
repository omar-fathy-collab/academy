<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $salary->month }} - {{ $salary->teacher->teacher_name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/all.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --bg-light: #f8f9fa;
        }
        body { font-family: 'Inter', sans-serif; background-color: #e9ecef; color: #333; }
        .slip-container { max-width: 850px; margin: 40px auto; background: white; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); padding: 50px; position: relative; overflow: hidden; }
        .slip-header { border-bottom: 2px solid #f1f1f1; padding-bottom: 30px; margin-bottom: 30px; }
        .logo-placeholder { font-size: 24px; font-weight: 800; color: var(--primary-color); letter-spacing: -1px; }
        .status-stamp { position: absolute; top: 40px; right: 50px; transform: rotate(15deg); opacity: 0.15; font-size: 60px; font-weight: 900; text-transform: uppercase; border: 8px solid; padding: 10px 30px; border-radius: 15px; pointer-events: none; }
        .status-paid { color: var(--success-color); border-color: var(--success-color); }
        .section-title { font-size: 14px; font-weight: 800; color: var(--secondary-color); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; border-left: 4px solid var(--primary-color); padding-left: 10px; }
        .table-custom thead { background-color: var(--bg-light); }
        .table-custom th { border: none; color: var(--secondary-color); font-size: 12px; }
        .total-row { background-color: var(--primary-color); color: white; border-radius: 10px; }
        .qr-code { width: 100px; height: 100px; background: #eee; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 10px; text-align: center; color: #999; }
        @media print {
            body { background: white; margin: 0; }
            .slip-container { box-shadow: none; margin: 0; width: 100%; max-width: none; border-radius: 0; padding: 30px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print text-center mt-4">
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow">
            <i class="fas fa-print me-2"></i> Print Salary Slip
        </button>
    </div>

    <div class="slip-container">
        @if($salary->status === 'paid')
            <div class="status-stamp status-paid">PAID</div>
        @endif

        <div class="slip-header d-flex justify-content-between align-items-start">
            <div>
                <div class="logo-placeholder mb-2">{{ config('app.name') }}</div>
                <p class="text-muted small mb-0">Professional Tech Academy<br>10th of Ramadan City, Egypt</p>
            </div>
            <div class="text-end">
                <h2 class="fw-bold mb-1">SALARY SLIP</h2>
                <p class="text-muted small mb-0">Month: <span class="fw-bold text-dark">{{ $salary->month }}</span></p>
                <p class="text-muted small mb-0">Ref: #SLP-{{ $salary->salary_id }}-{{ date('Ymd') }}</p>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-6">
                <div class="section-title">Teacher Information</div>
                <h5 class="fw-bold mb-1">{{ $salary->teacher->teacher_name }}</h5>
                <p class="text-muted small mb-1"><i class="fas fa-envelope me-2"></i>{{ $salary->teacher->user->email ?? 'N/A' }}</p>
                @if($salary->teacher->user->profile->phone_number ?? false)
                    <p class="text-muted small mb-0"><i class="fas fa-phone me-2"></i>{{ $salary->teacher->user->profile->phone_number }}</p>
                @endif
            </div>
            <div class="col-md-6 text-md-end">
                <div class="section-title">Payment Details</div>
                <p class="text-muted small mb-1">Date: <span class="text-dark fw-bold">{{ $salary->payment_date ? \Carbon\Carbon::parse($salary->payment_date)->format('d M, Y') : 'Pending' }}</span></p>
                <p class="text-muted small mb-0">Method: <span class="text-dark fw-bold px-2 py-1 bg-light rounded">{{ ucfirst(str_replace('_', ' ', $salary->teacher->payment_method ?? 'Cash')) }}</span></p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="section-title">Earnings & Adjustments</div>
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Description</th>
                                <th class="text-end pe-4">Amount (EGP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold">Base Group Earnings</div>
                                    <div class="smaller text-muted">{{ $salary->group->group_name ?? 'Individual/Manual' }}</div>
                                </td>
                                <td class="text-end pe-4 fw-bold">£{{ number_format($calculatedValues['teacher_share'], 2) }}</td>
                            </tr>
                            
                            @foreach($bonuses as $bonus)
                            <tr>
                                <td class="ps-4 py-3 text-success">
                                    <i class="fas fa-plus-circle me-2"></i> Bonus: {{ $bonus->reason ?: 'Incentive' }}
                                </td>
                                <td class="text-end pe-4 text-success fw-bold">+£{{ number_format($bonus->amount, 2) }}</td>
                            </tr>
                            @endforeach

                            @foreach($incomingTransfers as $transfer)
                            <tr>
                                <td class="ps-4 py-3 text-info">
                                    <i class="fas fa-exchange-alt me-2"></i> Transfer from {{ $transfer->teacher_name }}
                                </td>
                                <td class="text-end pe-4 text-info fw-bold">+£{{ number_format($transfer->transfer_amount, 2) }}</td>
                            </tr>
                            @endforeach

                            @foreach($deductions as $deduction)
                            <tr>
                                <td class="ps-4 py-3 text-danger">
                                    <i class="fas fa-minus-circle me-2"></i> Deduction: {{ $deduction->reason ?: 'Penalty' }}
                                </td>
                                <td class="text-end pe-4 text-danger fw-bold">-£{{ number_format($deduction->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="total-row shadow-sm">
                                <td class="ps-4 py-3 fw-bold fs-5">Net Payable Amount</td>
                                <td class="text-end pe-4 fw-bold fs-5">£{{ number_format($salary->total_paid, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="row align-items-center mt-5 pt-4 border-top">
            <div class="col-md-8">
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i> This is a computer-generated salary slip and does not require a physical signature. For any discrepancies, please reach out to the financial department within 3 business days.
                </p>
            </div>
            <div class="col-md-4 d-flex justify-content-md-end">
                <div class="text-center">
                    <div class="qr-code mb-2 mx-auto">
                        <i class="fas fa-qrcode fs-1"></i><br>SECURE SLIP
                    </div>
                    <div class="smaller text-muted" style="font-size: 8px;">VERIFIED BY {{ config('app.name') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center text-muted small pb-5">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </div>

</body>
</html>
