@extends('layouts.authenticated')

@section('title', 'Student Enrollment & Growth')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="studentReports({
    enrollmentTrends: {{ json_encode($enrollmentTrends) }},
    topStudents: {{ json_encode($topStudents) }},
    popularGroups: {{ json_encode($popularGroups) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">👨‍🎓 Student Growth Analytics</h2>
            <p class="text-muted mb-0">Tracking enrollments, demographics, and high-performers</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                <button class="btn btn-light px-3 py-2 fw-bold smaller active">Last 30 Days</button>
                <button class="btn btn-light px-3 py-2 fw-bold smaller">Quarterly</button>
                <button class="btn btn-light px-3 py-2 fw-bold smaller">Yearly</button>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Enrollment Trends Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-user-plus text-primary me-2"></i> Enrollment Velocity</h5>
                <div id="enrollmentChart" style="min-height: 350px;"></div>
            </div>
        </div>

        <!-- Popular Courses / Groups -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 h-100">
                <h5 class="fw-bold mb-4"><i class="fas fa-fire text-orange me-2"></i> Hot Groups</h5>
                <div class="vstack gap-3">
                    <template x-for="group in popularGroups" :key="group.group_id">
                        <div class="p-3 theme-badge-bg rounded-4 border theme-border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0 theme-text-main" x-text="group.group_name"></h6>
                                <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary smaller" x-text="group.student_count + ' Students'"></span>
                            </div>
                            <div class="smaller text-muted">
                                <i class="fas fa-book-reader me-2"></i> <span x-text="group.course_name"></span><br>
                                <i class="fas fa-chalkboard-teacher me-2"></i> <span x-text="group.teacher_name"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Top Performing Students -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <div class="card-header border-bottom-0 theme-badge-bg p-4">
                    <h5 class="fw-bold mb-0 theme-text-main">🏆 Hall of Fame: Academically Top Students</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="theme-badge-bg text-muted small text-uppercase">
                            <tr>
                                <th class="px-4 py-3">Student Name</th>
                                <th class="py-3">Avg. Quiz Score</th>
                                <th class="py-3">Quizzes Attempted</th>
                                <th class="py-3">Attendance Consistency</th>
                                <th class="px-4 py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="student in topStudents" :key="student.student_id">
                                <tr class="theme-border">
                                    <td class="px-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-circle me-3">
                                                <i class="fas fa-medal"></i>
                                            </div>
                                            <div class="fw-bold theme-text-main" x-text="student.student_name"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold text-success me-2" x-text="parseFloat(student.avg_score).toFixed(1) + '%'"></span>
                                            <div class="progress" style="width: 60px; height: 4px;">
                                                <div class="progress-bar bg-success" :style="'width: ' + student.avg_score + '%'"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td x-text="student.quizzes_taken"></td>
                                    <td>
                                        <template x-if="student.attendance_percentage">
                                            <span class="badge rounded-pill smaller" :class="student.attendance_percentage >= 90 ? 'bg-info bg-opacity-10 text-info' : 'bg-warning bg-opacity-10 text-warning'" x-text="student.attendance_percentage + '%'"></span>
                                        </template>
                                        <template x-if="!student.attendance_percentage">
                                            <span class="text-muted smaller">N/A</span>
                                        </template>
                                    </td>
                                    <td class="px-4 text-end">
                                        <a :href="'/admin/students/' + student.student_id" class="btn btn-sm btn-light border theme-border rounded-pill px-3 shadow-sm fw-bold">
                                            Profile
                                        </a>
                                    </td>
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
function studentReports(config) {
    return {
        enrollmentTrends: config.enrollmentTrends,
        topStudents: config.topStudents,
        popularGroups: config.popularGroups,
        
        init() {
            this.$nextTick(() => {
                this.renderChart();
            });
        },
        
        renderChart() {
            const options = {
                series: [{
                    name: 'New Enrollments',
                    data: this.enrollmentTrends.map(d => d.enrollments || d.count || 0)
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    toolbar: { show: false },
                    background: 'transparent'
                },
                plotOptions: {
                    bar: {
                        borderRadius: 8,
                        columnWidth: '50%',
                    }
                },
                dataLabels: { enabled: false },
                colors: [getComputedStyle(document.documentElement).getPropertyValue('--primary-color') || '#0d6efd'],
                xaxis: {
                    categories: this.enrollmentTrends.map(d => d.period || d.date),
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                grid: {
                    borderColor: 'rgba(0,0,0,0.05)',
                    strokeDashArray: 4
                }
            };
            
            const chart = new ApexCharts(document.querySelector("#enrollmentChart"), options);
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
</style>
@endsection
