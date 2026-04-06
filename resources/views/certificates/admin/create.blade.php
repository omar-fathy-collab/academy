@extends('layouts.authenticated')

@section('title', 'Issue New Academic Certificate')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="certificateCreate({
    students: {{ json_encode($students) }},
    groups: {{ json_encode($groups) }}
})" x-cloak>
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-lg rounded-4 theme-card p-4 p-md-5">
                <div class="text-center mb-5">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3 shadow-sm">
                        <i class="fas fa-certificate fa-3x"></i>
                    </div>
                    <h2 class="fw-bold theme-text-main">Award New Certificate</h2>
                    <p class="text-muted">Manually issue a formal academic credential to a student</p>
                </div>

                <form action="{{ route('certificates.store') }}" method="POST" class="row g-4" dir="ltr">
                    @csrf
                    
                    <div class="col-md-7 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Select Student</label>
                        <select name="user_id" class="form-select rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required x-model="selectedUserId" @change="updateGroups()">
                            <option value="">-- Select from student list --</option>
                            <template x-for="student in students" :key="student.id">
                                <option :value="student.id" x-text="student.username + ' (' + student.email + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="col-md-5 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" required value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="col-12 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Link to Group (Optional)</label>
                        <select name="group_id" class="form-select rounded-3 border theme-border theme-badge-bg theme-text-main py-2" x-model="selectedGroupId" :disabled="!selectedUserId">
                            <option value="">-- Individual Issue (No Group) --</option>
                            <template x-for="group in filteredGroups" :key="group.group_id">
                                <option :value="group.group_id" x-text="group.group_name + ' - ' + (group.course?.course_name || 'Individual Track')"></option>
                            </template>
                        </select>
                        <p class="smaller text-muted mt-2">Linking to a group automatically calculates attendance and grades for the certificate.</p>
                    </div>

                    <div class="col-12 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Course Track</label>
                        <select name="course_id" class="form-select rounded-3 border theme-border theme-badge-bg theme-text-main py-2" x-model="selectedCourseId">
                            <option value="">-- Select Track (Derived from Group if selected) --</option>
                            @foreach($groups->unique('course_id') as $g)
                                @if($g->course)
                                    <option value="{{ $g->course_id }}">{{ $g->course->course_name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 text-start">
                        <label class="form-label fw-bold smaller text-uppercase">Additional Remarks / Justification</label>
                        <textarea name="remarks" class="form-control rounded-3 border theme-border theme-badge-bg theme-text-main py-2" rows="3"></textarea>
                    </div>

                    <hr class="theme-border my-4">

                    <div class="col-12">
                        <div class="p-4 theme-badge-bg rounded-4 border theme-border d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-1">Advanced Verification</h6>
                                <p class="smaller text-muted mb-0">System will automatically generate a unique serial number and QR code for public verification.</p>
                            </div>
                            <div class="bg-white p-2 rounded shadow-sm">
                                <i class="fas fa-qrcode fa-2x opacity-25"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm transition-hover">
                            Confirm & Generate Certificate
                        </button>
                        <a href="{{ route('certificates.index') }}" class="btn btn-link text-muted mt-2 d-block small">Back to Certificate Hub</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function certificateCreate(config) {
    return {
        students: config.students,
        groups: config.groups,
        selectedUserId: '',
        selectedGroupId: '',
        selectedCourseId: '',
        filteredGroups: [],
        
        init() {
            // Check for query params if coming from approval
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('user_id')) {
                this.selectedUserId = urlParams.get('user_id');
                this.updateGroups();
            }
        },
        
        updateGroups() {
            if (!this.selectedUserId) {
                this.filteredGroups = [];
                return;
            }
            
            // Find student to see their groups
            const student = this.students.find(s => s.id == this.selectedUserId);
            if (student && student.student && student.student.groups) {
                this.filteredGroups = student.student.groups;
            } else {
                this.filteredGroups = [];
            }
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
