@extends('layouts.authenticated')

@section('title', 'Create Salary Record')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data='salaryForm({
    teachers: @json($teachers, JSON_HEX_APOS),
    groups: @json($groups, JSON_HEX_APOS),
    initialMonth: "{{ date("Y-m") }}"
})' x-init="init()" x-cloak>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('salaries.index') }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border transition-hover">
                    <i class="fas fa-arrow-left fa-lg"></i>
                </a>
                <h2 class="fw-bold mb-0 theme-text-main">💰 Create New Salary</h2>
            </div>

            <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden">
                <form action="{{ route('salaries.store') }}" method="POST">
                    @csrf
                    <div class="card-body p-5">
                        <div class="row g-4">
                            <!-- Teacher & Month -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Select Teacher</label>
                                <select name="teacher_id" x-model="form.teacher_id" @change="updateTeacherDetails()" class="form-select border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" required>
                                    <option value="">-- Choose Teacher --</option>
                                    <template x-for="t in teachers" :key="t.teacher_id">
                                        <option :value="t.teacher_id" x-text="t.teacher_name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Salary Month</label>
                                <input type="month" name="month" x-model="form.month" class="form-control border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" required>
                            </div>

                            <!-- Group Selection -->
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase text-muted">Study Group</label>
                                <select name="group_id" x-model="form.group_id" @change="calculateOrganicShare()" class="form-select border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" required>
                                    <option value="">-- Select Group --</option>
                                    <template x-for="g in filteredGroups" :key="g.group_id">
                                        <option :value="g.group_id" x-text="g.course_name + ' - ' + g.group_name"></option>
                                    </template>
                                </select>
                                <p class="smaller text-muted mt-1 ps-1" x-show="selectedGroup">
                                    Price: £<span x-text="selectedGroup?.price"></span> | Teacher %: <span x-text="form.teacher_percentage"></span>%
                                </p>
                            </div>

                            <hr class="my-4 theme-border opacity-10">

                            <!-- Financial details -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Group Revenue</label>
                                <div class="input-group">
                                    <span class="input-group-text theme-badge-bg border theme-border text-muted">£</span>
                                    <input type="number" step="0.01" name="group_revenue" x-model="form.group_revenue" @input="calculateOrganicShare()" class="form-control border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Teacher Percentage</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="teacher_percentage" x-model="form.teacher_percentage" @input="calculateOrganicShare()" class="form-control border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" required>
                                    <span class="input-group-text theme-badge-bg border theme-border text-muted">%</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-uppercase text-muted">Teacher Share (Organic)</label>
                                <div class="input-group">
                                    <span class="input-group-text theme-badge-bg border theme-border text-muted">£</span>
                                    <input type="number" step="0.01" name="teacher_share" x-model="form.teacher_share" class="form-control border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main bg-opacity-10 bg-primary fw-bold" readonly>
                                </div>
                            </div>

                            <!-- Bonuses & Deductions -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted text-success">Bonuses</label>
                                <input type="number" step="0.01" name="bonuses" x-model="form.bonuses" @input="calculateNet()" class="form-control border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main border-success border-opacity-25" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted text-danger">Deductions</label>
                                <input type="number" step="0.01" name="deductions" x-model="form.deductions" @input="calculateNet()" class="form-control border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main border-danger border-opacity-25" value="0">
                            </div>

                            <!-- Net Salary -->
                            <div class="col-12 mt-4">
                                <div class="p-4 rounded-4 theme-badge-bg border theme-border text-center">
                                    <p class="small fw-bold text-uppercase text-muted mb-1">Final Net Salary</p>
                                    <h2 class="fw-bold mb-0 text-primary">£<span x-text="Number(form.net_salary).toLocaleString()"></span></h2>
                                    <input type="hidden" name="net_salary" :value="form.net_salary">
                                </div>
                            </div>

                            <!-- Status & Notes -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Status</label>
                                <select name="status" class="form-select border theme-border rounded-3 py-2 shadow-none theme-badge-bg theme-text-main" required>
                                    <option value="pending">Pending</option>
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small text-uppercase text-muted">Notes</label>
                                <textarea name="notes" rows="2" class="form-control border theme-border rounded-3 shadow-none theme-badge-bg theme-text-main" placeholder="Optional notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer theme-badge-bg border-top-0 p-4 text-end">
                        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-5 py-2 shadow-sm transition-hover">
                            <i class="fas fa-save me-2"></i> Save Salary Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function salaryForm(config) {
    return {
        teachers: config.teachers,
        groups: config.groups,
        form: {
            teacher_id: '',
            month: config.initialMonth,
            group_id: '',
            group_revenue: 0,
            teacher_percentage: 10,
            teacher_share: 0,
            bonuses: 0,
            deductions: 0,
            net_salary: 0
        },
        selectedGroup: null,
        
        init() {
            // Initial calc
        },
        
        get filteredGroups() {
            if (!this.form.teacher_id) return [];
            return this.groups.filter(g => g.teacher_id == this.form.teacher_id);
        },
        
        updateTeacherDetails() {
            const teacher = this.teachers.find(t => t.teacher_id == this.form.teacher_id);
            if (teacher) {
                this.form.teacher_percentage = teacher.salary_percentage || 0;
            }
            this.form.group_id = '';
            this.calculateOrganicShare();
        },
        
        calculateOrganicShare() {
            this.selectedGroup = this.groups.find(g => g.group_id == this.form.group_id);
            if (this.selectedGroup && this.form.teacher_id == this.selectedGroup.teacher_id) {
                this.form.teacher_percentage = this.selectedGroup.teacher_percentage || this.form.teacher_percentage;
            }
            
            this.form.teacher_share = Number(this.form.group_revenue || 0) * (Number(this.form.teacher_percentage || 0) / 100);
            this.calculateNet();
        },
        
        calculateNet() {
            this.form.net_salary = Number(this.form.teacher_share || 0) + Number(this.form.bonuses || 0) - Number(this.form.deductions || 0);
            this.form.net_salary = Math.max(0, this.form.net_salary).toFixed(2);
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
