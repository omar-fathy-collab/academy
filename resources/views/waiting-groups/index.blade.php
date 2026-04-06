@extends('layouts.authenticated')

@section('title', 'Academy Waiting Groups')

@section('content')
<div class="container-fluid py-4 theme-text-main" x-data="waitingGroupsHub({
    courses: {{ json_encode($courses) }},
    waitingGroups: {{ json_encode($waitingGroups) }},
    safeGroupIds: {{ json_encode($safeGroupIds) }}
})" x-cloak>
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="fw-bold theme-text-main mb-0">👥 Waiting Group Hub</h2>
            <p class="text-muted mb-0">Consolidated students awaiting class regularisation</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold me-2 shadow-sm" @click="expandAll = !expandAll">
                <i class="fas" :class="expandAll ? 'fa-compress-alt' : 'fa-expand-alt'"></i> 
                <span x-text="expandAll ? 'Collapse All' : 'Expand All'"></span>
            </button>
            <a href="{{ route('bookings.add-to-waiting-group') }}" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm transition-hover">
                <i class="fas fa-user-plus me-2"></i> Add Student Manually
            </a>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 d-flex flex-row align-items-center">
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"> {{ $totalStudents }} </h3>
                    <p class="text-muted smaller fw-bold text-uppercase mb-0">Total Waiting Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 d-flex flex-row align-items-center">
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                    <i class="fas fa-layer-group fa-lg"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"> {{ $totalGroups }} </h3>
                    <p class="text-muted smaller fw-bold text-uppercase mb-0">Active Waiting Groups</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 d-flex flex-row align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle me-3">
                    <i class="fas fa-exclamation-triangle fa-lg"></i>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"> {{ $studentsWithoutGrade }} </h3>
                    <p class="text-muted smaller fw-bold text-uppercase mb-0">Pending Placement Exam</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Course-wise Group Listing -->
    <div class="vstack gap-5">
        <template x-for="(groups, courseId) in waitingGroups" :key="courseId">
            <div class="course-section">
                <div class="d-flex align-items-center mb-3">
                    <h4 class="fw-bold theme-text-main mb-0 me-3" x-text="getCourseName(courseId)"></h4>
                    <div class="flex-grow-1 border-top theme-border opacity-50"></div>
                </div>

                <div class="row g-4">
                    <template x-for="(students, groupName) in groups" :key="groupName">
                        <div class="col-lg-6 col-xl-4">
                            <div class="card border-0 shadow-sm rounded-4 theme-card h-100 overflow-hidden">
                                <div class="card-header theme-badge-bg border-bottom-0 p-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-white rounded-pill px-2 py-1 smaller fw-bold text-primary me-2 shadow-sm" x-text="students.length"></div>
                                        <h6 class="fw-bold mb-0 theme-text-main" x-text="groupName"></h6>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light rounded-circle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v text-muted smaller"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 theme-card">
                                            <li><a class="dropdown-item smaller" :href="'/admin/waiting-groups/' + students[0].id + '/edit'"><i class="far fa-edit me-2"></i> Edit Group</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item smaller text-danger" @click="deleteGroup(students[0].id, groupName)"><i class="far fa-trash-alt me-2"></i> Delete Entire Group</button></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <div class="vstack gap-2">
                                        <template x-for="(student, index) in students" :key="student.id">
                                            <div class="p-2 rounded-3 theme-badge-bg border theme-border d-flex justify-content-between align-items-center transition-hover">
                                                <div class="d-flex align-items-center">
                                                    <span class="smaller font-monospace text-muted me-2" x-text="(index + 1) + '.'"></span>
                                                    <div>
                                                        <div class="smaller fw-bold theme-text-main" x-text="student.booking?.name || 'Anonymous'"></div>
                                                        <div class="smaller text-muted" x-text="student.assigned_level || 'No Level Set'"></div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge rounded-pill smaller me-2" 
                                                          :class="student.placement_exam_grade >= 80 ? 'bg-success bg-opacity-10 text-success' : (student.placement_exam_grade >= 50 ? 'bg-info bg-opacity-10 text-info' : 'bg-secondary bg-opacity-10 text-muted')"
                                                          x-text="(student.placement_exam_grade || '??') + '%'"></span>
                                                    <a :href="'/admin/bookings/' + student.booking_id + '/edit'" class="btn btn-sm btn-white border-0 shadow-none op-50">
                                                        <i class="fas fa-chevron-right smaller"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-top-0 p-3">
                                    <button class="btn btn-primary btn-sm w-100 rounded-pill fw-bold py-2 shadow-sm" @click="transferToRealGroup(groupName, courseId)">
                                        <i class="fas fa-graduation-cap me-2"></i> Finalize Group Enrollment
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function waitingGroupsHub(config) {
    return {
        courses: config.courses,
        waitingGroups: config.waitingGroups,
        safeGroupIds: config.safeGroupIds,
        expandAll: true,
        
        getCourseName(id) {
            const course = this.courses.find(c => c.course_id == id);
            return course ? course.course_name : 'Unknown Course';
        },
        
        deleteGroup(id, name) {
            Swal.fire({
                title: 'Confirm Group Deletion?',
                text: `This will remove all students from the waiting list for '${name}'. This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#fe515e',
                confirmButtonText: 'Yes, Delete All'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.post(`/admin/waiting-groups/${id}/delete-group`)
                        .then(() => {
                            window.location.reload();
                        });
                }
            });
        },
        
        transferToRealGroup(name, courseId) {
            Swal.fire({
                title: 'Finalize Enrollment',
                text: `Transfer all students from '${name}' into a real active group?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Proceed to Assignment'
            }).then(result => {
                if (result.isConfirmed) {
                    // Redirect to a bulk enrollment interface or group selection
                    // For now, redirect to the group editing page which has a student picker
                    window.location.href = `/admin/waiting-groups/${Object.values(this.waitingGroups[courseId][name])[0].id}/edit`;
                }
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
    .transition-hover:hover { transform: translateY(-3px); background-color: rgba(255, 255, 255, 0.05) !important; }
    .op-50 { opacity: 0.5; }
    .op-50:hover { opacity: 1; }
</style>
@endsection
