@extends('layouts.authenticated')

@section('title', 'Manage Waiting Group: ' . $groupName)

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="waitingGroupEdit({
    students: {{ json_encode($students) }},
    allStudents: {{ json_encode($allStudents) }},
    courses: {{ json_encode($courses) }},
    course: {{ json_encode($course) }},
    groupId: {{ $groupId }}
})" x-cloak>
    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Group Details & Student List -->
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 p-md-5 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 smaller mb-2">Waiting Student Cohort</span>
                        <h2 class="fw-bold theme-text-main" x-text="course.course_name + ': ' + groupName"></h2>
                        <p class="text-muted smaller mb-0">Managing <span x-text="students.length"></span> students in this waiting list</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="theme-badge-bg text-muted small text-uppercase">
                            <tr>
                                <th class="px-3 py-3">Student</th>
                                <th class="py-3">Level</th>
                                <th class="py-3">Placement</th>
                                <th class="px-3 py-3 text-end">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="student in students" :key="student.id">
                                <tr class="theme-border">
                                    <td class="px-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white smaller rounded-circle me-3" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" x-text="student.booking?.name ? student.booking.name.charAt(0) : '?'"></div>
                                            <div>
                                                <div class="fw-bold theme-text-main" x-text="student.booking?.name"></div>
                                                <div class="smaller text-muted" x-text="student.booking?.phone"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm rounded-3 border-0 theme-badge-bg theme-text-main" x-model="student.assigned_level">
                                            <option value="Beginner">Beginner</option>
                                            <option value="Intermediate">Intermediate</option>
                                            <option value="Advanced">Advanced</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm rounded-3 overflow-hidden theme-badge-bg border theme-border" style="max-width: 100px;">
                                            <input type="number" class="form-control border-0 bg-transparent text-center" x-model="student.placement_exam_grade">
                                            <span class="input-group-text border-0 bg-transparent opacity-50 smaller">%</span>
                                        </div>
                                    </td>
                                    <td class="px-3 text-end">
                                        <div class="btn-group shadow-sm rounded-pill overflow-hidden border theme-border">
                                            <button @click="updateStudent(student)" class="btn btn-sm btn-light border-0 px-2" title="Save Changes">
                                                <i class="fas fa-check text-success"></i>
                                            </button>
                                            <button @click="removeFromGroup(student)" class="btn btn-sm btn-light border-0 px-2" title="Remove from list">
                                                <i class="far fa-trash-alt text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 p-4 rounded-4 bg-primary text-white d-flex align-items-center justify-content-between shadow-sm">
                    <div>
                        <h5 class="fw-bold mb-1">Finalize Enrollment</h5>
                        <p class="smaller mb-0 opacity-75">Ready to move these students into an active group?</p>
                    </div>
                    <button class="btn btn-white btn-sm rounded-pill px-4 fw-bold shadow-sm" @click="showGroupAssignmentModal()">
                        <i class="fas fa-graduation-cap me-2 text-primary"></i> Assign to Real Group
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Add Students Section -->
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 mb-4">
                <h5 class="fw-bold mb-4">Add More Students</h5>
                
                <div class="mb-4">
                    <label class="form-label smaller fw-bold text-uppercase opacity-50">Select Existing Booking</label>
                    <select class="form-select rounded-3 border theme-border theme-badge-bg theme-text-main py-2" x-model="selectedBookingId">
                        <option value="">-- Choose student --</option>
                        <template x-for="b in allStudents" :key="b.id">
                            <option :value="b.id" x-text="b.name + ' (' + b.phone + ')'"></option>
                        </template>
                    </select>
                </div>

                <button class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm mb-3" @click="addStudent()">
                    <i class="fas fa-plus me-2"></i> Add to List
                </button>
                
                <p class="smaller text-muted text-center mb-0">Only bookings not currently in this group are listed.</p>
            </div>

            <!-- Group Configuration -->
            <div class="card border-0 shadow-sm rounded-4 theme-card p-4 theme-badge-bg">
                <h5 class="fw-bold mb-4">Group Configuration</h5>
                <form action="{{ route('waiting-groups.update-metadata', $groupId) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label smaller fw-bold opacity-50">Group Name</label>
                        <input type="text" name="new_group_name" class="form-control rounded-3 border theme-border" value="{{ $groupName }}">
                    </div>
                    <div class="mb-4">
                        <label class="form-label smaller fw-bold opacity-50">Course Track</label>
                        <select name="course_id" class="form-select rounded-3 border theme-border">
                            @foreach($courses as $c)
                                <option value="{{ $c->course_id }}" {{ $c->course_id == $course->course_id ? 'selected' : '' }}>{{ $c->course_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-light w-100 rounded-pill py-2 fw-bold shadow-sm text-primary">
                        Update Metadata
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function waitingGroupEdit(config) {
    return {
        students: config.students,
        allStudents: config.allStudents,
        courses: config.courses,
        course: config.course,
        groupId: config.groupId,
        selectedBookingId: '',
        
        updateStudent(student) {
            axios.post(`/admin/waiting-groups/student/${student.id}/edit`, {
                placement_exam_grade: student.placement_exam_grade,
                assigned_level: student.assigned_level
            }).then(() => {
                Toast.fire({ icon: 'success', title: 'Student updated successfully' });
            });
        },
        
        removeFromGroup(student) {
            if(confirm('Remove student from this waiting list?')) {
                axios.post(`/admin/waiting-groups/student/${student.id}/remove`)
                    .then(() => {
                        window.location.reload();
                    });
            }
        },
        
        addStudent() {
            if (!this.selectedBookingId) return;
            
            axios.post('/admin/waiting-groups/add-student', {
                booking_id: this.selectedBookingId,
                group_name: '{{ $groupName }}',
                course_id: this.course.course_id
            }).then(() => {
                window.location.reload();
            }).catch(error => {
                Swal.fire('Error', error.response.data.message || 'Action failed', 'error');
            });
        },
        
        showGroupAssignmentModal() {
            // This would trigger another modal to select a REAL group
            Swal.fire({
                title: 'Transfer to Real Group',
                text: 'Select the target active academic group',
                input: 'select',
                inputOptions: {
                    // This should be populated via AJAX/config
                    '1': 'Morning Group A',
                    '2': 'Evening Group B'
                },
                showCancelButton: true,
                confirmButtonText: 'Consolidate & Close Waiting List'
            });
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
