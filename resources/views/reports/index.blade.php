@extends('layouts.authenticated')

@section('title', 'Academy Analytics Hub')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="reportsHub({
    revenueData: {{ json_encode($revenueData) }},
    groupsData: {{ json_encode($groupsData) }},
    monthlyStats: {{ json_encode($monthlyStats) }},
    stats: {
        totalRevenue: {{ $totalRevenue }},
        totalStudents: {{ $totalStudents }},
        activeStudents: {{ $activeStudents }},
        averageScore: {{ $averageScore }},
        attendanceRate: {{ $attendanceRate }}
    }
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-1">📊 Academy Analytics Hub</h2>
            <p class="text-muted mb-0">Centralized insights on performance, finance, and growth</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="dropdown d-inline-block">
                <button class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-file-export me-2"></i> Export Report
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 theme-card">
                    <li><a class="dropdown-item smaller" href="#"><i class="far fa-file-pdf me-2 text-danger"></i> Performance PDF</a></li>
                    <li><a class="dropdown-item smaller" href="#"><i class="far fa-file-excel me-2 text-success"></i> Financial Excel</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item smaller" href="#"><i class="fas fa-print me-2 text-primary"></i> Print Dashboard</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card h-100 p-3">
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                        <i class="fas fa-money-bill-wave fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted smaller fw-bold text-uppercase mb-0">30D Revenue</p>
                        <h4 class="fw-bold mb-0">£<span x-text="stats.totalRevenue.toLocaleString()"></span></h4>
                    </div>
                </div>
                <div class="smaller text-success mt-2">
                    <i class="fas fa-arrow-up me-1"></i> 12% vs last month
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card h-100 p-3">
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                        <i class="fas fa-user-graduate fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted smaller fw-bold text-uppercase mb-0">Active Students</p>
                        <h4 class="fw-bold mb-0" x-text="stats.activeStudents"></h4>
                    </div>
                </div>
                <div class="smaller text-muted mt-2">
                    Of <span x-text="stats.totalStudents"></span> total enrolled
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card h-100 p-3">
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle me-3">
                        <i class="fas fa-star fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted smaller fw-bold text-uppercase mb-0">Avg. Academic Score</p>
                        <h4 class="fw-bold mb-0"><span x-text="stats.averageScore.toFixed(1)"></span>%</h4>
                    </div>
                </div>
                <div class="smaller text-warning mt-2">
                    System-wide quiz average
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card h-100 p-3">
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle me-3">
                        <i class="fas fa-calendar-check fa-lg"></i>
                    </div>
                    <div>
                        <p class="text-muted smaller fw-bold text-uppercase mb-0">Attendance Rate</p>
                        <h4 class="fw-bold mb-0"><span x-text="stats.attendanceRate.toFixed(1)"></span>%</h4>
                    </div>
                </div>
                <div class="progress mt-3 overflow-visible" style="height: 6px;">
                    <div class="progress-bar bg-info" :style="'width: ' + stats.attendanceRate + '%'"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Revenue Over Time Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold theme-text-main mb-0"><i class="fas fa-chart-line text-primary me-2"></i> Financial Performance</h5>
                    <select class="form-select smaller rounded-pill theme-badge-bg theme-border px-3 w-auto">
                        <option>Last 12 Months</option>
                        <option>Current Quarter</option>
                        <option>Financial Year</option>
                    </select>
                </div>
                <div id="revenueChart" style="min-height: 350px;"></div>
            </div>
        </div>

        <!-- Quick Access Sub-Reports -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 h-100">
                <h5 class="fw-bold theme-text-main mb-4"><i class="fas fa-external-link-alt text-primary me-2"></i> Targeted Reports</h5>
                
                <div class="vstack gap-3">
                    <a href="{{ route('reports.financial') }}" class="p-3 theme-badge-bg rounded-4 border theme-border d-flex align-items-center transition-hover text-decoration-none">
                        <div class="bg-success text-white p-2 rounded-circle me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold theme-text-main mb-0">Financial Summary</h6>
                            <p class="smaller text-muted mb-0">Salaries, expenses & profits</p>
                        </div>
                        <i class="fas fa-chevron-right text-muted smaller"></i>
                    </a>

                    <a href="{{ route('reports.students') }}" class="p-3 theme-badge-bg rounded-4 border theme-border d-flex align-items-center transition-hover text-decoration-none">
                        <div class="bg-primary text-white p-2 rounded-circle me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold theme-text-main mb-0">Enrollment Trends</h6>
                            <p class="smaller text-muted mb-0">Demographics & growth</p>
                        </div>
                        <i class="fas fa-chevron-right text-muted smaller"></i>
                    </a>

                    <a href="{{ route('reports.quizzes') }}" class="p-4 rounded-4 bg-primary text-white d-flex align-items-center shadow-sm text-decoration-none opacity-hover">
                        <div class="bg-white bg-opacity-20 p-2 rounded-circle me-3">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-0">Academic Performance</h6>
                            <p class="smaller text-white-50 mb-0">Quiz results & success rates</p>
                        </div>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Monthly Breakdown Table -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mt-4">
                <div class="card-header theme-badge-bg border-bottom-0 p-4">
                    <h5 class="fw-bold mb-0 theme-text-main"><i class="far fa-calendar-alt text-danger me-2"></i> Monthly Historic Data</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="theme-badge-bg text-muted small text-uppercase">
                            <tr>
                                <th class="px-4 py-3">Month</th>
                                <th class="py-3">Gross Revenue</th>
                                <th class="py-3">Expenses</th>
                                <th class="py-3">Attendance</th>
                                <th class="py-3">Success Rate</th>
                                <th class="px-4 py-3">Enrollments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="stat in reversedMonthlyStats" :key="stat.month">
                                <tr class="theme-border">
                                    <td class="px-4 fw-bold theme-text-main" x-text="stat.month"></td>
                                    <td><span class="text-success fw-bold">£<span x-text="stat.revenue.toLocaleString()"></span></span></td>
                                    <td><span class="text-danger fw-bold">£<span x-text="stat.expenses.toLocaleString()"></span></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2" x-text="stat.attendance_rate.toFixed(1) + '%'"></span>
                                            <div class="progress" style="width: 60px; height: 4px;">
                                                <div class="progress-bar bg-info" :style="'width: ' + stat.attendance_rate + '%'"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill smaller" :class="stat.success_rate >= 75 ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'" x-text="stat.success_rate.toFixed(1) + '%'"></span>
                                    </td>
                                    <td class="px-4" x-text="stat.enrollments + ' new students'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function reportsHub(config) {
    return {
        revenueData: config.revenueData,
        groupsData: config.groupsData,
        monthlyStats: config.monthlyStats,
        stats: config.stats,
        
        get reversedMonthlyStats() {
            return [...this.monthlyStats].reverse();
        },
        
        init() {
            this.$nextTick(() => {
                this.renderCharts();
            });
        },
        
        renderCharts() {
            const revenueOptions = {
                series: [{
                    name: 'Revenue',
                    data: this.revenueData.map(d => d.amount)
                }],
                chart: {
                    type: 'area',
                    height: 350,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent'
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                colors: [getComputedStyle(document.documentElement).getPropertyValue('--primary-color') || '#0d6efd'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [20, 100, 100, 100]
                    }
                },
                xaxis: {
                    categories: this.revenueData.map(d => d.month),
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
            
            const chart = new ApexCharts(document.querySelector("#revenueChart"), revenueOptions);
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
    .transition-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .opacity-hover:hover { opacity: 0.9; }
</style>
@endsection
