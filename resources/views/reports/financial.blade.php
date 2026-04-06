@extends('layouts.authenticated')

@section('title', 'Detailed Financial Report')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="financialReport({
    dailyData: {{ json_encode($dailyData) }},
    weeklyData: {{ json_encode($weeklyData) }},
    monthlyData: {{ json_encode($monthlyData) }},
    summary: {{ json_encode($summary) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">💰 Financial Deep-Dive</h2>
            <p class="text-muted mb-0">Detailed breakdown of revenue, expenses, and profitability</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
             <form action="{{ route('reports.export.financial') }}" method="GET" class="d-inline-block">
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="report_type" value="{{ $reportType }}">
                <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                    <i class="far fa-file-excel me-2"></i> Export Excel
                </button>
            </form>
        </div>
    </div>

    <!-- Period Selector -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4">
        <div class="card-body p-3">
            <form action="{{ route('reports.financial') }}" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label smaller fw-bold opacity-50">Report Period</label>
                    <select name="period" class="form-select border theme-border theme-badge-bg theme-text-main rounded-3 py-2">
                        <option value="daily" {{ $period == 'daily' ? 'selected' : '' }}>Daily View</option>
                        <option value="weekly" {{ $period == 'weekly' ? 'selected' : '' }}>Weekly View</option>
                        <option value="monthly" {{ $period == 'monthly' ? 'selected' : '' }}>Monthly View</option>
                        <option value="yearly" {{ $period == 'yearly' ? 'selected' : '' }}>Yearly View</option>
                        <option value="custom" {{ $period == 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label smaller fw-bold opacity-50">Report Type</label>
                    <select name="report_type" class="form-select border theme-border theme-badge-bg theme-text-main rounded-3 py-2">
                        <option value="summary" {{ $reportType == 'summary' ? 'selected' : '' }}>Summary Dashboard</option>
                        <option value="detailed" {{ $reportType == 'detailed' ? 'selected' : '' }}>Detailed Tranasctions</option>
                    </select>
                </div>
                <div class="col-md-4">
                     <div class="d-flex gap-2">
                        <div class="flex-grow-1">
                            <label class="form-label smaller fw-bold opacity-50">Base Date</label>
                            <input type="date" name="date" class="form-control border theme-border theme-badge-bg theme-text-main rounded-3 py-2" value="{{ $date }}">
                        </div>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 mt-auto fw-bold">Update</button>
                     </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold mb-1">STATED REVENUE</p>
                <h4 class="fw-bold mb-0 text-success">£<span x-text="summary.grand_total_revenue.toLocaleString()"></span></h4>
                <div class="smaller text-success mt-1">Invoices + Direct Payments</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold mb-1">TOTAL OUTFLOW</p>
                <h4 class="fw-bold mb-0 text-danger">£<span x-text="summary.grand_total_expenses.toLocaleString()"></span></h4>
                <div class="smaller text-danger mt-1">Expenses + Salaries</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold mb-1">NET PROFIT</p>
                <h4 class="fw-bold mb-0 text-primary">£<span x-text="summary.net_profit.toLocaleString()"></span></h4>
                <div class="smaller text-primary mt-1">Remaining Operating Capital</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3">
                <p class="text-muted smaller fw-bold mb-1">PROFIT MARGIN</p>
                <h4 class="fw-bold mb-0"><span x-text="summary.profit_margin.toFixed(1)"></span>%</h4>
                <div class="progress mt-2" style="height: 4px;">
                    <div class="progress-bar bg-primary" :style="'width: ' + summary.profit_margin + '%'"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Visual Breakdown -->
        <div class="col-lg-12">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                <h5 class="fw-bold theme-text-main mb-4"><i class="fas fa-chart-area text-primary me-2"></i> Revenue vs Expenses Timeline</h5>
                <div id="financialChart" style="min-height: 400px;"></div>
            </div>
        </div>

        @if($reportType == 'detailed')
            <!-- Detailed Transaction Tables -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="card-header theme-badge-bg border-0 p-4">
                        <h5 class="fw-bold mb-0">Detailed Transactions Ledger</h5>
                    </div>
                    <div class="card-body p-0">
                         <ul class="nav nav-tabs border-0 px-4 theme-badge-bg" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active fw-bold border-0 py-3" data-bs-toggle="tab" href="#payments-tab">Direct Payments</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fw-bold border-0 py-3" data-bs-toggle="tab" href="#expenses-tab">Business Expenses</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fw-bold border-0 py-3" data-bs-toggle="tab" href="#salaries-tab">Teacher Payouts</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div id="payments-tab" class="tab-pane active">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="theme-badge-bg text-muted small text-uppercase">
                                            <tr>
                                                <th class="px-4">Date</th>
                                                <th>Student</th>
                                                <th>Invoice</th>
                                                <th>Method</th>
                                                <th class="px-4 text-end">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($detailedData['payments'] as $payment)
                                                <tr class="theme-border">
                                                    <td class="px-4 font-monospace smaller">{{ $payment->payment_date }}</td>
                                                    <td class="fw-bold">{{ $payment->student_name }}</td>
                                                    <td class="smaller">#{{ $payment->invoice_number }}</td>
                                                    <td><span class="badge bg-light text-dark rounded-pill">{{ $payment->payment_method }}</span></td>
                                                    <td class="px-4 text-end fw-bold text-success">£{{ number_format($payment->amount, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Repeat for others... -->
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Data Table Summary -->
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="theme-badge-bg text-muted small text-uppercase">
                                <tr>
                                    <th class="px-4 py-3">Time Period</th>
                                    <th class="py-3">Gross Revenue</th>
                                    <th class="py-3">Operating Expenses</th>
                                    <th class="py-3">Net Profit</th>
                                    <th class="px-4 py-3">Profitability</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in currentTableData" :key="item.date || item.week_start || item.month">
                                    <tr class="theme-border">
                                        <td class="px-4 fw-bold theme-text-main" x-text="item.display_date || item.display_week || item.display_month"></td>
                                        <td class="text-success fw-bold">£<span x-text="item.total_revenue.toLocaleString()"></span></td>
                                        <td class="text-danger fw-bold">£<span x-text="item.total_expenses.toLocaleString()"></span></td>
                                        <td class="text-primary fw-bold">£<span x-text="item.net_profit.toLocaleString()"></span></td>
                                        <td class="px-4">
                                            <span class="badge rounded-pill px-3 py-1 smaller" :class="item.net_profit > 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'">
                                                <span x-text="item.total_revenue > 0 ? ((item.net_profit / item.total_revenue) * 100).toFixed(1) : '0.0'"></span>%
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
function financialReport(config) {
    return {
        dailyData: config.dailyData,
        weeklyData: config.weeklyData,
        monthlyData: config.monthlyData,
        summary: config.summary,
        period: '{{ $period }}',
        
        get currentTableData() {
            if (this.period === 'daily') return this.dailyData;
            if (this.period === 'weekly') return this.weeklyData;
            return this.monthlyData;
        },
        
        init() {
            this.$nextTick(() => {
                this.renderChart();
            });
        },
        
        renderChart() {
            const data = this.currentTableData;
            const options = {
                series: [{
                    name: 'Total Revenue',
                    data: data.map(d => d.total_revenue)
                }, {
                    name: 'Total Outflow',
                    data: data.map(d => d.total_expenses)
                }],
                chart: {
                    type: 'area',
                    height: 400,
                    toolbar: { show: false },
                    background: 'transparent'
                },
                colors: ['#22c55e', '#ef4444'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.3,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: {
                    categories: data.map(d => d.display_date || d.display_week || d.display_month),
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: (val) => '£' + val.toLocaleString()
                    }
                },
                grid: {
                    borderColor: 'rgba(0,0,0,0.05)',
                    strokeDashArray: 4
                },
                theme: {
                    mode: document.body.classList.contains('dark-theme') ? 'dark' : 'light'
                }
            };
            
            const chart = new ApexCharts(document.querySelector("#financialChart"), options);
            chart.render();
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
    .nav-tabs .nav-link { color: var(--text-muted); }
    .nav-tabs .nav-link.active { background-color: var(--card-bg); border-bottom: 2px solid var(--primary-color) !important; color: var(--primary-color); }
</style>
@endsection
