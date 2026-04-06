@extends('layouts.authenticated')

@section('title', 'Monthly Student Evaluations')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="monthlyRatings({
    group: {{ json_encode($group) }},
    students: {{ json_encode($students) }},
    existingRatings: {{ json_encode($existingRatings) }},
    month: {{ $month }},
    year: {{ $year }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">📅 Monthly Grading</h2>
            <p class="text-muted mb-0">Recording student performance for <span x-text="monthName + ' ' + year"></span></p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
             <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                <a :href="'?group_id=' + group.group_id + '&month=' + prevMonth + '&year=' + prevYear" class="btn btn-light px-3 py-2 fw-bold smaller"><i class="fas fa-chevron-left me-1"></i> Previous Month</a>
                <a :href="'?group_id=' + group.group_id + '&month=' + nextMonth + '&year=' + nextYear" class="btn btn-light px-3 py-2 fw-bold smaller">Next Month <i class="fas fa-chevron-right ms-1"></i></a>
            </div>
        </div>
    </div>

    <!-- Group Info -->
    <div class="card border-0 shadow-sm rounded-4 theme-card p-4 mb-4">
        <div class="row align-items-center" dir="ltr text-start">
            <div class="col-md-8">
                <h5 class="fw-bold theme-text-main mb-1" x-text="group.group_name"></h5>
                <div class="smaller text-muted"><i class="fas fa-book-reader me-2"></i> <span x-text="group.course_name"></span></div>
            </div>
            <div class="col-md-4 text-end">
                <div class="badge rounded-pill bg-primary bg-opacity-10 text-primary px-4 py-2 fs-6 fw-bold">
                    <span x-text="students.length"></span> Students
                </div>
            </div>
        </div>
    </div>

    <!-- Grading Table -->
    <form action="{{ route('ratings.save-monthly') }}" method="POST">
        @csrf
        <input type="hidden" name="group_id" :value="group.group_id">
        <input type="hidden" name="month" :value="month">
        <input type="hidden" name="year" :value="year">

        <div class="card border-0 shadow-sm rounded-4 theme-card overflow-hidden mb-5">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-start" dir="ltr">
                    <thead class="theme-badge-bg text-muted small text-uppercase">
                        <tr>
                            <th class="px-4 py-3">Student Name</th>
                            <th class="py-3 text-center">Rating (0-5)</th>
                            <th class="px-4 py-3 text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="student in students" :key="student.student_id">
                            <tr class="theme-border">
                                <td class="px-4">
                                    <div class="fw-bold theme-text-main" x-text="student.student_name"></div>
                                    <div class="smaller text-muted" x-text="student.student_phone || 'No phone available'"></div>
                                </td>
                                <td class="text-center" style="width: 300px;">
                                    <div class="d-inline-flex align-items-center gap-3">
                                        <div class="ratings-stars d-flex text-light">
                                            <template x-for="i in 5">
                                                <i class="fas fa-star fa-lg cursor-pointer transition-all" 
                                                   :class="i <= (ratings[student.student_id] || 0) ? 'text-warning scale-110' : 'text-light'"
                                                   @click="setRating(student.student_id, i)"></i>
                                            </template>
                                        </div>
                                        <input type="hidden" :name="'ratings[' + student.student_id + ']'" x-model="ratings[student.student_id]">
                                        <div class="badge bg-light text-dark fs-6" x-text="(ratings[student.student_id] || 0) + ' / 5'"></div>
                                    </div>
                                </td>
                                <td class="px-4 text-end">
                                    <template x-if="existingRatings[student.student_id]">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 smaller">Previously evaluated</span>
                                    </template>
                                    <template x-if="!existingRatings[student.student_id]">
                                        <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill px-3 py-1 smaller">Not evaluated yet</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="card-footer theme-badge-bg border-top-0 p-4 text-center">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm transition-hover">
                    <i class="fas fa-save me-2"></i> Save all evaluations for this month
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function monthlyRatings(config) {
    return {
        group: config.group,
        students: config.students,
        existingRatings: config.existingRatings,
        ratings: { ...config.existingRatings },
        month: config.month,
        year: config.year,
        
        get monthName() {
            const names = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            return names[this.month - 1];
        },
        
        get prevMonth() { return this.month === 1 ? 12 : this.month - 1; },
        get prevYear() { return this.month === 1 ? this.year - 1 : this.year; },
        get nextMonth() { return this.month === 12 ? 1 : this.month + 1; },
        get nextYear() { return this.month === 12 ? this.year + 1 : this.year; },
        
        setRating(studentId, val) {
            this.ratings[studentId] = val;
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
    .cursor-pointer { cursor: pointer; }
    .transition-all { transition: all 0.2s ease; }
    .scale-110 { transform: scale(1.2); }
</style>
@endsection
