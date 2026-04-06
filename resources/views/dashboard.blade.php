@extends('layouts.authenticated')

@section('title', 'Premium Analytics Dashboard')

@section('content')
    {{-- Header Section --}}
    <div class="d-flex justify-content-between align-items-end mb-5" data-aos="fade-down">
        <div>
            <h1 class="fw-900 display-5 mb-1 outfit text-main tracking-tight">System <span class="text-primary glow-text">Insights</span></h1>
            <p class="text-muted fw-500 mb-0 opacity-75">Track all academy activities and financial performance in real-time.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="glass-pill px-3 py-2 shadow-sm d-flex align-items-center gap-2">
                <span class="pulse-dot bg-success"></span>
                <span class="tiny-plus fw-bold opacity-75">LIVE SYSTEM STATUS</span>
            </div>
        </div>
    </div>

    {{-- Premium Today's Pulse --}}
    <div class="row g-4 mb-5" data-aos="fade-up">
        <div class="col-12">
            <div class="d-flex align-items-center mb-3">
                <h5 class="fw-900 mb-0 theme-text-main"><i class="fas fa-bolt me-2 text-warning"></i>Today's Pulse</h5>
                <span class="badge bg-warning bg-opacity-10 text-warning ms-3 rounded-pill px-3 py-1 fw-bold tiny">LIVE UPDATES</span>
            </div>
            <div class="row g-3">
                @php
                    $pulseStats = [
                        ['title' => 'Daily Revenue', 'value' => '$' . number_format($profit_today), 'icon' => 'hand-holding-dollar', 'color' => 'success', 'desc' => 'Payments collected today'],
                        ['title' => 'Today\'s Sessions', 'value' => $today_sessions, 'icon' => 'calendar-check', 'color' => 'primary', 'desc' => 'Active sessions scheduled'],
                        ['title' => 'New Enrollments', 'value' => $today_enrollments, 'icon' => 'user-plus', 'color' => 'info', 'desc' => 'New students joined today'],
                        ['title' => 'Incoming Leads', 'value' => $today_leads, 'icon' => 'paper-plane', 'color' => 'warning', 'desc' => 'New enrollment requests'],
                        ['title' => 'Pending Grading', 'value' => $pending_grading_count, 'icon' => 'file-invoice', 'color' => 'danger', 'desc' => 'Submissions to score']
                    ];
                @endphp
                @foreach($pulseStats as $stat)
                    <div class="col-xl col-md-4 col-6">
                        <div class="pulse-card p-4 rounded-4 theme-card h-100 border-start border-4 border-{{ $stat['color'] }} shadow-sm transition-hover overflow-hidden position-relative">
                            <div class="position-absolute top-0 end-0 p-3 opacity-10">
                                <i class="fas fa-{{ $stat['icon'] }} fa-3x"></i>
                            </div>
                            <div class="position-relative z-1">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="icon-circle bg-{{ $stat['color'] }} bg-opacity-10 text-{{ $stat['color'] }} rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                        <i class="fas fa-{{ $stat['icon'] }} tiny"></i>
                                    </div>
                                    <span class="text-muted fw-bold text-uppercase tiny-plus">{{ $stat['title'] }}</span>
                                </div>
                                <h3 class="fw-900 theme-text-main mb-1">{{ $stat['value'] }}</h3>
                                <p class="text-muted extra-small mb-0 opacity-75">{{ $stat['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Academy Overview Metrics (Refined) --}}
    <div class="row g-3 mb-5">
        <div class="col-12">
            <h5 class="fw-900 mb-3 theme-text-main"><i class="fas fa-university me-2 text-primary"></i>Academy Lifecycle</h5>
        </div>
        @php
            $academicStats = [
                ['title' => 'Total Students', 'value' => number_format($students_count), 'icon' => 'user-graduate', 'color' => 'primary', 'trend' => 'Registered students'],
                ['title' => 'Instructors', 'value' => number_format($teachers_count), 'icon' => 'chalkboard-teacher', 'color' => 'info', 'trend' => 'Expert faculty'],
                ['title' => 'Active Groups', 'value' => number_format($groups_count), 'icon' => 'users', 'color' => 'success', 'trend' => 'Ongoing learning units'],
                ['title' => 'Courses', 'value' => number_format($courses_count), 'icon' => 'book-open', 'color' => 'warning', 'trend' => 'Full curriculum items'],
                ['title' => 'Quizzes', 'value' => number_format($total_quizzes), 'icon' => 'question-circle', 'color' => 'danger', 'trend' => 'Assessment units'],
                ['title' => 'Assignments', 'value' => number_format($total_assignments), 'icon' => 'file-signature', 'color' => 'secondary', 'trend' => 'Student tasks']
            ];
        @endphp

        @foreach($academicStats as $i => $stat)
            <div class="col-xl-2 col-lg-4 col-sm-6" data-aos="fade-up" data-aos-delay="{{ $i * 50 }}">
                <div class="card-soft p-4 text-center h-100 card-3d glass-effect theme-card border-bottom border-3 border-{{ $stat['color'] }}" style="border-radius: 20px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 bg-{{ $stat['color'] }} text-{{ $stat['color'] }} bg-opacity-10 shadow-sm transition-all" style="width: 60px; height: 60px; border: 1px solid rgba(var(--bs-{{ $stat['color'] }}-rgb), 0.2);">
                        <i class="fas fa-{{ $stat['icon'] }} fs-4"></i>
                    </div>
                    <h2 class="fw-900 outfit theme-text-main mb-1">{{ $stat['value'] }}</h2>
                    <span class="text-muted small fw-bold text-uppercase tiny-plus d-block mb-1">{{ $stat['title'] }}</span>
                    <span class="extra-small text-muted opacity-50">{{ $stat['trend'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Financial Metrics Grid (Redesigned) --}}
    <div class="d-flex align-items-center mb-4">
        <h5 class="fw-900 mb-0 theme-text-main"><i class="fas fa-wallet me-2 text-success"></i>Revenue & Financial Health</h5>
    </div>
    <div class="row g-4 mb-5">
        @php
            $stats = [
                ['title' => 'Gross Revenue', 'titleAr' => 'Total Income', 'value' => '$' . number_format($total_revenue), 'trend' => '+$' . number_format($monthly_revenue) . ' MTD', 'icon' => 'sack-dollar', 'color' => '#6366f1', 'badge' => 'bg-primary-subtle text-primary', 'series' => $weekly_revenue['series'] ?? []],
                ['title' => 'Total Expenses', 'titleAr' => 'All Costs', 'value' => '$' . number_format($total_expenses), 'trend' => 'Incl. Salaries', 'icon' => 'receipt', 'color' => '#f43f5e', 'badge' => 'bg-danger-subtle text-danger', 'series' => [20, 40, 30, 70, 45, 60, 55]],
                ['title' => 'Net Profit', 'titleAr' => 'Final Earnings', 'value' => '$' . number_format($net_profit), 'trend' => 'Final Earnings', 'icon' => 'chart-line', 'color' => '#10b981', 'badge' => 'bg-success-subtle text-success', 'series' => [30, 50, 40, 80, 60, 75, 70]],
                ['title' => 'Vault Balance', 'titleAr' => 'Cash on Hand', 'value' => '$' . number_format($vault_balance), 'trend' => 'Actual Cash', 'icon' => 'vault', 'color' => '#f59e0b', 'badge' => 'bg-warning-subtle text-warning', 'series' => [80, 75, 85, 90, 88, 92, 95]],
                ['title' => 'Total Outstanding', 'titleAr' => 'Remaining Dues', 'value' => '$' . number_format($total_debt), 'trend' => 'Student Debt', 'icon' => 'hand-holding-dollar', 'color' => '#8b5cf6', 'badge' => 'bg-secondary-subtle text-secondary', 'series' => [10, 20, 15, 25, 30, 20, 35]],
            ];
        @endphp

        @foreach($stats as $i => $stat)
            <div class="col-xl col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                <div class="card-soft h-100 card-3d glass-effect shadow-premium border theme-border">
                    <div class="d-flex justify-content-between align-items-start mb-3 position-relative z-1">
                        <div class="w-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge rounded-pill fw-bold {{ $stat['badge'] }} d-inline-block tiny-plus">{{ $stat['trend'] }}</span>
                                <div class="p-2 rounded-3 shadow-sm icon-box" style="background: linear-gradient(135deg, {{ $stat['color'] }} 0%, {{ $stat['color'] }}dd 100%); color: #fff; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-{{ $stat['icon'] }} small"></i>
                                </div>
                            </div>
                            <h3 class="fw-900 outfit mb-1 theme-text-main">
                                {{ $stat['value'] }}
                            </h3>
                            @if($stat['title'] === 'Net Profit')
                                <div class="mt-3 py-2 px-3 rounded-4 bg-light bg-opacity-10 border theme-border">
                                    <div class="d-flex justify-content-between extra-small fw-bold opacity-75 mb-1">
                                        <span class="theme-text-main">TODAY:</span>
                                        <span class="{{ $profit_today >= 0 ? 'text-success' : 'text-danger' }}">+${{ number_format($profit_today) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between extra-small fw-bold opacity-75">
                                        <span class="theme-text-main">MONTH:</span>
                                        <span class="{{ $profit_month >= 0 ? 'text-success' : 'text-danger' }}">+${{ number_format($profit_month) }}</span>
                                    </div>
                                </div>
                            @endif
                            <div class="d-flex flex-column mt-2">
                                <span class="text-muted fw-bold text-uppercase tiny letter-spacing-1">{{ $stat['title'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-auto pt-3 sparkline-container position-relative z-1">
                        <div id="sparkline-{{ $i }}"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Main Primary Row --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-8" data-aos="fade-right">
            <div class="card-soft h-100 card-3d">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-900 mb-0 text-main">Revenue Trajectory</h5>
                        <span class="text-muted smaller fw-bold">12-Month Performance Matrix</span>
                    </div>
                </div>
                <div id="revenue-trajectory-chart" style="height: 320px;"></div>
            </div>
        </div>

        <div class="col-xl-4" data-aos="fade-left">
            <div class="card-soft h-100 bg-gradient-soft-blue text-white overflow-hidden position-relative card-3d">
                <div class="position-relative z-1 mb-4">
                    <h5 class="fw-900 mb-0 outfit">Weekly Signups</h5>
                    <span class="opacity-75 smaller fw-bold">New Registrations Flow</span>
                </div>
                <div class="position-relative z-1">
                    <h2 class="display-4 fw-900 outfit mb-0">{{ array_sum($weekly_signups['series'] ?? []) }}</h2>
                    <span class="badge bg-white text-primary rounded-pill mt-2 fw-bold">Past 7 Days</span>
                </div>
                <div class="chart-wrapper-negative-margin mt-auto">
                    <div id="weekly-signups-chart" style="height: 160px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Secondary Row --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-4" data-aos="fade-up">
            <div class="card-soft h-100 card-3d">
                <h5 class="fw-900 mb-0 text-main">Course Popularity</h5>
                <p class="text-muted smaller fw-bold mb-3">By Active Groups</p>
                <div id="course-popularity-chart" style="height: 300px;"></div>
            </div>
        </div>

        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-soft h-100 d-flex flex-column card-3d">
                <h5 class="fw-900 mb-0 text-main">Enrollment Ratio</h5>
                <p class="text-muted smaller fw-bold mb-4">Active vs Waitlist vs Inactive</p>
                <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                    <div id="enrollment-ratio-chart" style="height: 280px;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card-soft h-100 d-flex flex-column card-3d">
                <h5 class="fw-900 mb-0 text-main">Task Engagement</h5>
                <p class="text-muted smaller fw-bold mb-4">Global Submission Rate</p>
                <div class="flex-grow-1 d-flex align-items-center justify-content-center">
                    <div id="task-engagement-chart" style="height: 280px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Financial Reports & Video Monitoring --}}
    <div class="row g-4 mb-4" data-aos="fade-up">
        <div class="col-xl-6">
            <div class="card-soft h-100 card-3d glass-effect border-primary border-opacity-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-900 mb-0 text-main">Video Engagement</h5>
                        <span class="text-muted smaller fw-bold">Monitoring Student Watch Time</span>
                    </div>
                    <a href="{{ route('videos.engagement') }}" class="btn btn-primary rounded-pill px-4 btn-sm fw-bold">
                        <i class="fas fa-chart-pie me-2"></i> Open Dashboard
                    </a>
                </div>
                <div class="p-4 bg-primary bg-opacity-10 rounded-4 text-center">
                    <i class="fas fa-video fa-3x text-primary mb-3 opacity-50"></i>
                    <p class="text-muted mb-0 small fw-600">
                        Track which students are watching session recordings, how much they've seen, and identify those falling behind in real-time.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card-soft h-100 card-3d glass-effect border-success border-opacity-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-900 mb-0 text-main">Financial Reports</h5>
                        <span class="text-muted smaller fw-bold">Export Data to Excel</span>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <a href="{{ route('invoices.export') }}" class="btn btn-outline-success w-100 py-3 rounded-4 fw-bold shadow-sm d-flex flex-column align-items-center gap-2">
                            <i class="fas fa-file-invoice fa-lg"></i>
                            <span>Export Invoices</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('expenses.export') }}" class="btn btn-outline-danger w-100 py-3 rounded-4 fw-bold shadow-sm d-flex flex-column align-items-center gap-2">
                            <i class="fas fa-file-invoice-dollar fa-lg"></i>
                            <span>Export Expenses</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tertiary Row: Cashflow & Leadership --}}
    <div class="row g-4 mb-5">
        <div class="col-xl-8" data-aos="fade-up">
            <div class="card-soft h-100 card-3d">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-900 mb-0 text-main">Cashflow Stream</h5>
                    <a href="/payments" class="btn btn-sm btn-link text-primary fw-bold text-decoration-none">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0">
                        <thead>
                            <tr class="border-bottom opacity-50">
                                <th class="fw-bold tiny text-uppercase text-muted pb-3">Student</th>
                                <th class="fw-bold tiny text-uppercase text-muted pb-3">Invoice Ref</th>
                                <th class="fw-bold tiny text-uppercase text-muted pb-3">Method</th>
                                <th class="fw-bold tiny text-uppercase text-muted text-end pb-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recent_payments as $payment)
                                <tr class="border-bottom-dashed hover-bg-soft">
                                    <td class="py-3 fw-bold text-main">{{ $payment->student_name }}</td>
                                    <td class="py-3 text-muted fw-600">#{{ $payment->invoice_number }}</td>
                                    <td class="py-3">
                                        <span class="badge bg-light-theme text-muted border border-theme rounded-pill px-3">
                                            <i class="fas fa-{{ $payment->payment_method === 'cash' ? 'money-bill' : 'credit-card' }} me-2"></i>
                                            {{ $payment->payment_method }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-end fw-900 text-success outfit">${{ number_format($payment->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colSpan="4" class="text-center text-muted py-4">No recent payments recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-soft h-100 card-3d overflow-hidden">
                <h5 class="fw-900 mb-0 text-main">Live Activity</h5>
                <p class="text-muted smaller fw-bold mb-4">Real-time Academy Pulse</p>

                <div class="timeline-container px-2">
                    @forelse($recent_activities as $activity)
                        <div class="timeline-item d-flex gap-3 mb-4 last-no-border">
                            <div class="timeline-icon-wrapper">
                                <div class="timeline-icon bg-primary-subtle text-primary rounded-circle shadow-sm">
                                    <i class="fas fa-{{ str_contains($activity->subject_type ?? '', 'Payment') ? 'receipt' : (str_contains($activity->subject_type ?? '', 'Student') ? 'user-plus' : 'edit') }} tiny"></i>
                                </div>
                                <div class="timeline-line"></div>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6 class="mb-0 fw-bold text-main tiny-plus">{{ $activity->user->profile->nickname ?? $activity->user->username ?? 'System' }}</h6>
                                    <span class="tiny text-muted fw-600">{{ $activity->created_at->format('H:i') }}</span>
                                </div>
                                <p class="mb-0 smaller text-muted opacity-75 mt-1 line-clamp-2">
                                    {{ $activity->description }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">No recent pulse detected.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .card-soft {
            background-color: var(--card-bg);
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.06);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255,255,255,0.05);
            position: relative;
            overflow: hidden;
        }
        .card-3d:hover {
            transform: translateY(-8px) perspective(1000px) rotateX(2deg);
            box-shadow: 0 25px 60px -15px rgba(0,0,0,0.12);
        }
        .text-main { color: var(--text-main); }
        .fw-900 { font-weight: 900; }
        .outfit { font-family: 'Outfit', sans-serif; }
        .tiny { font-size: 0.70rem; }
        .tiny-plus { font-size: 0.80rem; }
        .smaller { font-size: 0.85rem; }
        .bg-gradient-soft-blue {
            background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%) !important;
            box-shadow: 0 15px 35px -10px rgba(14, 165, 233, 0.4) !important;
        }
        .chart-wrapper-negative-margin {
            margin-left: -30px; margin-right: -30px; margin-bottom: -40px;
            position: relative; z-index: 0;
        }
        .timeline-icon {
            width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 2;
        }
        .timeline-line {
            position: absolute; top: 32px; left: 16px; bottom: -24px; width: 2px; background: var(--card-border); opacity: 0.3; z-index: 1;
        }
        .last-no-border .timeline-line { display: none; }
        .border-bottom-dashed { border-bottom: 1px dashed var(--card-border); }
        .transition-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
        }
        .extra-small { font-size: 0.65rem; }
        .letter-spacing-1 { letter-spacing: 1px; }
        .pulse-card {
            background: var(--card-bg);
            transition: all 0.3s ease;
        }
        .pulse-card:hover { border-color: var(--bs-primary) !important; }
        [data-bs-theme="dark"] .pulse-card { background: rgba(30, 41, 59, 0.5); backdrop-filter: blur(10px); }
        .theme-text-main { color: var(--text-main); }
        .theme-card { background-color: var(--card-bg); }
        .theme-border { border-color: var(--border-color) !important; }
        .icon-circle { border: 1px solid rgba(0,0,0,0.05); }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const theme = localStorage.getItem('app-theme') || 'light';
            const textMuted = '#64748b';
            const cardBorder = 'rgba(0,0,0,0.05)';

            const sparklineOptions = (color) => ({
                chart: { type: 'line', sparkline: { enabled: true }, animations: { enabled: true } },
                stroke: { curve: 'smooth', width: 3 },
                colors: [color],
                tooltip: { enabled: false }
            });

            @foreach($stats as $i => $stat)
                new ApexCharts(document.querySelector("#sparkline-{{ $i }}"), {
                    ...sparklineOptions('{{ $stat["color"] }}'),
                    series: [{ data: @json($stat['series']) }]
                }).render();
            @endforeach

            // Revenue Trajectory
            new ApexCharts(document.querySelector("#revenue-trajectory-chart"), {
                chart: { type: 'area', toolbar: { show: false }, background: 'transparent', fontFamily: 'Outfit, sans-serif' },
                stroke: { curve: 'smooth', width: 3 },
                colors: ['#4f46e5'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.0, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: @json($yearly_revenue_stats['labels']),
                    labels: { style: { colors: textMuted, fontWeight: 600 } }
                },
                yaxis: { labels: { style: { colors: textMuted, fontWeight: 600 }, formatter: (v) => `$${v.toLocaleString()}` } },
                grid: { borderColor: cardBorder, strokeDashArray: 4 },
                series: [{ name: 'Revenue', data: @json($yearly_revenue_stats['series']) }]
            }).render();

            // Weekly Signups
            new ApexCharts(document.querySelector("#weekly-signups-chart"), {
                chart: { type: 'line', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
                stroke: { curve: 'smooth', width: 4 },
                colors: ['#ffffff'],
                markers: { size: 5, colors: ['#0ea5e9'], strokeColors: '#fff', strokeWidth: 2 },
                xaxis: { categories: @json($weekly_signups['labels']), labels: { show: false } },
                yaxis: { show: false },
                grid: { show: false },
                series: [{ name: 'Signups', data: @json($weekly_signups['series']) }]
            }).render();

            // Course Popularity
            new ApexCharts(document.querySelector("#course-popularity-chart"), {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '50%' } },
                colors: ['#10b981'],
                xaxis: { categories: @json($course_distribution['labels']), labels: { show: false } },
                series: [{ name: 'Groups', data: @json($course_distribution['series']) }]
            }).render();

            // Enrollment Ratio
            new ApexCharts(document.querySelector("#enrollment-ratio-chart"), {
                chart: { type: 'donut', fontFamily: 'Outfit, sans-serif' },
                labels: @json($student_status_stats['labels']),
                colors: ['#3b82f6', '#f43f5e', '#f59e0b'],
                stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '75%', labels: { show: true } } } },
                series: @json($student_status_stats['series'])
            }).render();

            // Task Engagement
            new ApexCharts(document.querySelector("#task-engagement-chart"), {
                chart: { type: 'radialBar', fontFamily: 'Outfit, sans-serif' },
                plotOptions: { radialBar: { hollow: { size: '60%' } } },
                colors: ['#8b5cf6'],
                labels: ['Completion Rate'],
                series: [@json($task_completion_rates)]
            }).render();
        });
    </script>
@endpush
