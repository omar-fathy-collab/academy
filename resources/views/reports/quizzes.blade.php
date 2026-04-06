@extends('layouts.authenticated')

@section('title', 'Academic Performance & Quizzes')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="quizReports({
    quizStats: {{ json_encode($quizStats) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">📝 Quiz Performance Analytics</h2>
            <p class="text-muted mb-0">Detailed breakdown of scores, participation, and trends</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
             <div class="input-group border theme-border rounded-pill overflow-hidden theme-badge-bg d-inline-flex w-auto px-2">
                <span class="input-group-text bg-transparent border-0"><i class="fas fa-search text-muted smaller"></i></span>
                <input type="text" class="form-control border-0 bg-transparent py-2 shadow-none theme-text-main smaller" placeholder="Filter by quiz/group..." x-model="searchTerm">
            </div>
        </div>
    </div>

    <!-- Quiz Data Table -->
    <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="theme-badge-bg text-muted small text-uppercase">
                    <tr>
                        <th class="px-4 py-3">Quiz Title</th>
                        <th class="py-3">Session & Group</th>
                        <th class="py-3">Participants</th>
                        <th class="py-3">Avg. Score</th>
                        <th class="px-4 py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="quiz in filteredQuizzes" :key="quiz.quiz_id">
                        <tr class="theme-border">
                            <td class="px-4">
                                <div class="fw-bold theme-text-main" x-text="quiz.title"></div>
                                <div class="smaller text-muted" x-text="'ID: #' + quiz.quiz_id"></div>
                            </td>
                            <td>
                                <div class="smaller fw-bold theme-text-main" x-text="quiz.topic"></div>
                                <div class="smaller text-muted"><i class="fas fa-layer-group me-1"></i> <span x-text="quiz.group_name"></span></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 smaller fw-bold me-2" x-text="quiz.participants"></div>
                                    <span class="smaller text-muted">Students</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-3 py-1 smaller" 
                                      :class="quiz.avg_score >= 80 ? 'bg-success bg-opacity-10 text-success' : (quiz.avg_score >= 50 ? 'bg-warning bg-opacity-10 text-warning' : 'bg-danger bg-opacity-10 text-danger')"
                                      x-text="quiz.avg_score + '%'"></span>
                            </td>
                            <td class="px-4 text-end">
                                <a :href="'/admin/quizzes/' + quiz.quiz_id + '/results'" class="btn btn-sm btn-light border theme-border rounded-pill px-3 shadow-sm fw-bold">
                                    Full Results
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Trend Analysis Section -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-chart-pie text-secondary me-2"></i> Participation Distribution</h5>
                <div id="participationChart" style="min-height: 300px;"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-award text-warning me-2"></i> Success Summary</h5>
                <div class="p-3 theme-badge-bg rounded-4 border theme-border d-flex align-items-center mb-3">
                    <div class="bg-success text-white p-2 rounded-circle me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-success">High Pass Rate</h6>
                        <p class="smaller text-muted mb-0">Quizzes with >80% average score</p>
                    </div>
                </div>
                <div class="p-3 theme-badge-bg rounded-4 border theme-border d-flex align-items-center">
                    <div class="bg-danger text-white p-2 rounded-circle me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-danger">Review Recommended</h6>
                        <p class="smaller text-muted mb-0">Quizzes with <50% average score</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function quizReports(config) {
    return {
        quizStats: config.quizStats,
        searchTerm: '',
        
        get filteredQuizzes() {
            if (!this.searchTerm) return this.quizStats;
            const search = this.searchTerm.toLowerCase();
            return this.quizStats.filter(q => 
                q.title.toLowerCase().includes(search) || 
                q.group_name.toLowerCase().includes(search) ||
                q.topic.toLowerCase().includes(search)
            );
        },
        
        init() {
            this.$nextTick(() => {
                this.renderCharts();
            });
        },
        
        renderCharts() {
            const options = {
                series: this.quizStats.slice(0, 5).map(q => q.participants),
                chart: { type: 'donut', height: 300 },
                labels: this.quizStats.slice(0, 5).map(q => q.title),
                theme: { mode: document.body.classList.contains('dark-theme') ? 'dark' : 'light' },
                responsive: [{
                    breakpoint: 480,
                    options: { chart: { width: 200 }, legend: { position: 'bottom' } }
                }]
            };
            
            const chart = new ApexCharts(document.querySelector("#participationChart"), options);
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
