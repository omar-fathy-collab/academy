@extends('layouts.authenticated')

@section('title', 'Register New Inquiry')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="bookingForm({
    courses: {{ json_encode($courses) }},
    waitingGroups: {{ json_encode($waitingGroups) }}
})" x-cloak>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 theme-card p-4 p-md-5">
                <div class="text-center mb-5">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3 shadow-sm">
                        <i class="fas fa-calendar-plus fa-2x"></i>
                    </div>
                    <h2 class="fw-bold theme-text-main">Register Student Inquiry</h2>
                    <p class="text-muted">Fill in the details for a new enrollment booking</p>
                </div>

                <form action="{{ route('bookings.store') }}" method="POST" class="row g-4">
                    @csrf
                    
                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Full Name</label>
                        <input type="text" name="name" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required placeholder="Enter student name">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Email Address</label>
                        <input type="email" name="email" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required placeholder="email@example.com">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Phone Number</label>
                        <input type="text" name="phone" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required placeholder="01xxxxxxxxx">
                    </div>

                    <div class="col-md-6 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Age</label>
                        <input type="number" name="age" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required min="1">
                    </div>

                    <div class="col-12 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Placement Exam Grade (if applicable)</label>
                        <input type="number" name="placement_exam_grade" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" step="0.01" min="0" max="100">
                    </div>

                    <div class="col-12 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Student Message or Additional Notes</label>
                        <textarea name="message" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" rows="3"></textarea>
                    </div>

                    <hr class="theme-border my-4">

                    <div class="col-12">
                        <div class="p-3 theme-badge-bg rounded-4 border theme-border">
                            <h6 class="fw-bold mb-3"><i class="fas fa-user-plus text-primary me-2"></i> Quick Distribution (Optional)</h6>
                            <p class="smaller text-muted mb-4">Adding these will automatically convert the booking to a student and add them to the selected waiting group.</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label smaller fw-bold">Select Course</label>
                                    <select name="course_id" class="form-select rounded-3 border theme-border" x-model="selectedCourseId">
                                        <option value="">-- No Direct Enrollment --</option>
                                        <template x-for="course in courses" :key="course.course_id">
                                            <option :value="course.course_id" x-text="course.course_name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label smaller fw-bold">Target Waiting Group</label>
                                    <select name="waiting_group_id" class="form-select rounded-3 border theme-border" :disabled="!selectedCourseId">
                                        <option value="">-- Select Group (After Course) --</option>
                                        <template x-for="(groups, groupName) in filteredWaitingGroups" :key="groupName">
                                            <option :value="groups[0].id" x-text="groupName"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm transition-hover">
                            Register Inquiry & Proceed
                        </button>
                        <a href="{{ route('bookings.index') }}" class="btn btn-link text-muted mt-2 d-block small">Back to List</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function bookingForm(config) {
    return {
        courses: config.courses,
        waitingGroups: config.waitingGroups,
        selectedCourseId: '',
        
        get filteredWaitingGroups() {
            if (!this.selectedCourseId) return {};
            
            let filtered = {};
            for (let name in this.waitingGroups) {
                let matches = this.waitingGroups[name].filter(g => g.course_id == this.selectedCourseId);
                if (matches.length > 0) {
                    filtered[name] = matches;
                }
            }
            return filtered;
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
    .transition-hover:hover { transform: translateY(-3px); }
</style>
@endsection
