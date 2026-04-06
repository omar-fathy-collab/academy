@extends('layouts.authenticated')

@section('title', 'Award New Badge')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="badgeCreate({
    groups: {{ json_encode($groups) }}
})" x-cloak>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 theme-card p-4 p-md-5 bg-white">
                <div class="text-center mb-5">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3 shadow-sm">
                        <i class="fas fa-medal fa-3x"></i>
                    </div>
                    <h2 class="fw-bold theme-text-main text-dark">Award Academic Badge</h2>
                    <p class="text-muted small">Select a student from your groups to recognize their achievement with a formal badge.</p>
                </div>

                <form action="{{ route('teacher.certificates.store') }}" method="POST" dir="ltr">
                    @csrf
                    
                    <div class="row g-4 text-start">
                        <!-- Group Selection -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold smaller text-uppercase text-muted">Study Group</label>
                            <select name="group_id" class="form-select rounded-3 border theme-border py-2" required x-model="selectedGroupId" @change="updateStudents()">
                                <option value="">-- Select Group --</option>
                                <template x-for="group in groups" :key="group.group_id">
                                    <option :value="group.group_id" x-text="group.group_name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Date Selection -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold smaller text-uppercase text-muted">Award Date</label>
                            <input type="date" name="issue_date" class="form-control rounded-3 border theme-border py-2 text-dark" required value="{{ date('Y-m-d') }}">
                        </div>

                        <!-- Student Selection -->
                        <div class="col-12">
                            <label class="form-label fw-bold smaller text-uppercase text-muted">Recipient Student</label>
                            <select name="user_id" class="form-select rounded-3 border theme-border py-2" required x-model="selectedUserId" :disabled="!selectedGroupId">
                                <option value="">-- Select from group members --</option>
                                <template x-for="student in filteredStudents" :key="student.id">
                                    <option :value="student.user.id" x-text="student.user.profile.full_name + ' (' + student.user.email + ')'"></option>
                                </template>
                            </select>
                            <p class="extra-small text-muted mt-2" x-show="selectedGroupId && filteredStudents.length === 0">
                                <i class="fas fa-exclamation-circle me-1"></i> No students found in this group.
                            </p>
                        </div>

                        <!-- Remarks -->
                        <div class="col-12">
                            <label class="form-label fw-bold smaller text-uppercase text-muted">Academic Remark / Recognition</label>
                            <textarea name="remarks" class="form-control rounded-3 border theme-border py-2 text-dark" rows="3" placeholder="e.g. For outstanding participation and group leadership..."></textarea>
                        </div>
                    </div>

                    <div class="col-12 text-center mt-5">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm transition-hover" :disabled="!selectedUserId">
                            <i class="fas fa-paper-plane me-2"></i> Confirm & Award Badge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function badgeCreate(config) {
    return {
        groups: config.groups,
        selectedGroupId: '',
        selectedUserId: '',
        filteredStudents: [],
        
        updateStudents() {
            if (!this.selectedGroupId) {
                this.filteredStudents = [];
                return;
            }
            
            const group = this.groups.find(g => g.group_id == this.selectedGroupId);
            if (group && group.students) {
                // Ensure student has a user profile
                this.filteredStudents = group.students.filter(s => s.user && s.user.profile);
            } else {
                this.filteredStudents = [];
            }
            this.selectedUserId = '';
        }
    };
}
</script>

<style>
    .smaller { font-size: 0.72rem; }
    .extra-small { font-size: 0.65rem; }
    .transition-hover:hover { transform: translateY(-3px); transition: all 0.2s ease; }
</style>
@endsection
