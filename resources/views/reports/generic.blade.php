@extends('layouts.authenticated')

@section('title', $title)

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="genericReport({
    data: {{ json_encode($data) }},
    title: '{{ $title }}',
    label: '{{ $label }}'
})" x-cloak>
    <div class="row mb-5 align-items-center">
        <div class="col-md-7">
            <h2 class="fw-bold theme-text-main mb-1" x-text="title"></h2>
            <p class="text-muted mb-0">Analysis of <span x-text="label.toLowerCase()"></span> from {{ $startDate }} to {{ $endDate }}</p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-arrow-left me-2"></i> Analytics Hub
            </a>
            <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="window.print()">
                <i class="fas fa-print me-2"></i> Print Data
            </button>
        </div>
    </div>

    <!-- Data Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 h-100">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">CURRENT AVERAGE</p>
                <div class="d-flex align-items-baseline">
                    <h2 class="fw-bold mb-0" x-text="currentAverageDisplay"></h2>
                    <span class="ms-2 smaller text-success"><i class="fas fa-arrow-up"></i> 4.2%</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 h-100">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">HIGHEST RECORDED</p>
                <h2 class="fw-bold mb-0" x-text="maxValDisplay"></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 h-100">
                <p class="text-muted smaller fw-bold text-uppercase mb-1">LOWEST RECORDED</p>
                <h2 class="fw-bold mb-0" x-text="minValDisplay"></h2>
            </div>
        </div>
    </div>

    <!-- Interactive Trend Chart -->
    <div class="card border-0 shadow-sm rounded-4 theme-card p-4 mb-5">
        <h5 class="fw-bold mb-4 px-2"><i class="fas fa-chart-line text-primary me-2"></i> Trend Progression</h5>
        <div id="trendChart" style="min-height: 400px;"></div>
    </div>

    <!-- Raw Data View -->
    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
        <div class="card-header border-bottom-0 theme-badge-bg p-4">
            <h5 class="fw-bold mb-0">Record Ledger</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="theme-badge-bg text-muted small text-uppercase">
                    <tr>
                        <th class="px-4 py-3">Reporting Period</th>
                        <th class="px-4 py-3 text-end" x-text="label"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="item in data" :key="item.period">
                        <tr class="theme-border">
                            <td class="px-4 fw-bold theme-text-main" x-text="item.period"></td>
                            <td class="px-4 text-end fw-bold theme-text-main">
                                <span x-text="formatVal(item)"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function genericReport(config) {
    return {
        data: config.data,
        title: config.title,
        label: config.label,
        
        get currentAverageDisplay() {
            if (this.data.length === 0) return '0.0';
            const sum = this.data.reduce((a, b) => a + (b.attendance_rate || b.avg_score || b.enrollments || 0), 0);
            const avg = sum / this.data.length;
            return avg.toFixed(1) + (this.label.includes('Rate') || this.label.includes('Score') ? '%' : '');
        },
        
        get maxValDisplay() {
            if (this.data.length === 0) return '0.0';
            const max = Math.max(...this.data.map(d => d.attendance_rate || d.avg_score || d.enrollments || 0));
            return max.toFixed(1) + (this.label.includes('Rate') || this.label.includes('Score') ? '%' : '');
        },

        get minValDisplay() {
            if (this.data.length === 0) return '0.0';
            const min = Math.min(...this.data.map(d => d.attendance_rate || d.avg_score || d.enrollments || 0));
            return min.toFixed(1) + (this.label.includes('Rate') || this.label.includes('Score') ? '%' : '');
        },

        formatVal(item) {
            const val = item.attendance_rate || item.avg_score || item.enrollments || 0;
            return val.toFixed(1) + (this.label.includes('Rate') || this.label.includes('Score') ? '%' : '');
        },
        
        init() {
            this.$nextTick(() => {
                this.renderChart();
            });
        },
        
        renderChart() {
            const options = {
                series: [{
                    name: this.label,
                    data: this.data.map(d => d.attendance_rate || d.avg_score || d.enrollments || 0)
                }],
                chart: {
                    type: 'area',
                    height: 400,
                    toolbar: { show: false },
                    zoom: { enabled: false }
                },
                colors: [getComputedStyle(document.documentElement).getPropertyValue('--primary-color') || '#0d6efd'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.3,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: {
                    categories: this.data.map(d => d.period),
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: (val) => val.toFixed(1) + (this.label.includes('Rate') || this.label.includes('Score') ? '%' : '')
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
            
            const chart = new ApexCharts(document.querySelector("#trendChart"), options);
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
    @media print {
        .btn, .nav, .card-header { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #eee !important; }
    }
</style>
@endsection
