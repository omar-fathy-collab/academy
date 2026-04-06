@extends('layouts.authenticated')

@section('title', $isUpgrade ? 'Upgrade Group' : 'Create Group')

@section('content')
<div x-data="{
    group_name: '{{ $group ? $group->group_name . ' - Upgraded' : '' }}',
    is_online: {{ $group && $group->is_online ? 'true' : 'false' }},
    is_free: {{ $group && $group->is_free ? 'true' : 'false' }},
    is_public: {{ $group && ($group->is_public ?? true) ? 'true' : 'false' }},
    course_id: '{{ $group->course_id ?? '' }}',
    subcourse_id: '{{ $group->subcourse_id ?? '' }}',
    teacher_id: '{{ $group->teacher_id ?? '' }}',
    start_date: '',
    end_date: '',
    price: {{ $group->price ?? 0 }},
    teacher_percentage: {{ $group->teacher_percentage ?? 1 }},
    room_id: '',
    day_of_week: '',
    start_time: '12:00',
    end_time: '14:00',
    selectedStudents: {{ json_encode($group ? $group->students->pluck('student_id') : []) }},
    
    subcourses: [],
    subcourseLoading: false,
    isManualName: false,
    searchTerm: '',
    courses: {{ json_encode($courses) }},
    students: {{ json_encode($students) }},
    
    init() {
        if (this.course_id) this.fetchSubcourses();
        this.$watch('course_id', () => {
            this.subcourse_id = '';
            this.fetchSubcourses();
            this.updateGroupName();
        });
        this.$watch('subcourse_id', () => this.updateGroupName());
        this.$watch('day_of_week', () => this.updateGroupName());
        this.$watch('start_time', () => this.updateGroupName());
        this.$watch('start_date', () => this.updateGroupName());
        this.$watch('is_free', (val) => { if(val) this.price = 0; });
    },
    
    async fetchSubcourses() {
        if (!this.course_id) {
            this.subcourses = [];
            return;
        }
        this.subcourseLoading = true;
        try {
            const response = await fetch(`/groups/get-subcourses/${this.course_id}`);
            const data = await response.json();
            this.subcourses = Array.isArray(data.subcourses) ? data.subcourses : [];
        } catch (e) {
            console.error('Error fetching subcourses', e);
            this.subcourses = [];
        } finally {
            this.subcourseLoading = false;
        }
    },
    
    updateGroupName() {
        if (this.isManualName) return;
        
        const course = this.courses.find(c => c.course_id == this.course_id);
        const subcourse = this.subcourses.find(s => s.subcourse_id == this.subcourse_id);
        
        let newName = '';
        if (course) {
            newName = course.course_name;
            if (subcourse) {
                newName += ` - ${subcourse.subcourse_name || `Part ${subcourse.subcourse_number}`}`;
            }
            
            const details = [];
            if (this.day_of_week) details.push(this.day_of_week.charAt(0).toUpperCase() + this.day_of_week.slice(1));
            if (this.start_time) details.push(`@ ${this.start_time}`);
            if (this.start_date) details.push(`- ${this.start_date}`);
            
            if (details.length > 0) {
                newName += ` (${details.join(' ')})`;
            }
        }
        this.group_name = newName;
    },
    
    toggleStudent(id) {
        const index = this.selectedStudents.indexOf(id);
        if (index > -1) this.selectedStudents.splice(index, 1);
        else this.selectedStudents.push(id);
    },
    
    get filteredStudents() {
        if (!this.searchTerm) return this.students;
        const term = this.searchTerm.toLowerCase();
        return this.students.filter(s => 
            s.student_name.toLowerCase().includes(term) || 
            (s.user && s.user.username && s.user.username.toLowerCase().includes(term))
        );
    }
}">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                @if($isUpgrade)
                    <i class="fas fa-level-up-alt me-2 text-primary"></i>Upgrade Group: {{ $group->group_name }}
                @else
                    <i class="fas fa-plus-circle me-2 text-primary"></i>Create New Group
                @endif
            </h1>
            <p class="text-muted small mb-0">Fill in the details to establish a new educational group.</p>
        </div>
        <div>
            <a href="{{ route('groups.index') }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
                <i class="fas fa-times me-2"></i> Cancel
            </a>
        </div>
    </div>

    <form action="{{ route('groups.store') }}" method="POST">
        @csrf
        @if($isUpgrade)
            <input type="hidden" name="upgrade_from" value="{{ $group->group_id }}">
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 theme-card p-4 mb-4">
                    <h5 class="fw-bold mb-4 theme-text-main"><i class="fas fa-info-circle me-2 text-primary"></i>Basic Information</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small theme-text-main">Group Name</label>
                            <input
                                type="text"
                                name="group_name"
                                class="form-control theme-card border theme-border theme-text-main @error('group_name') is-invalid @enderror"
                                x-model="group_name"
                                @input="isManualName = true"
                                placeholder="e.g. Beginners English - Mon/Wed"
                                required
                            >
                            @error('group_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-check form-switch custom-switch">
                                <input class="form-check-input" type="checkbox" name="is_online" id="is_online" value="1" x-model="is_online">
                                <label class="form-check-label fw-bold small theme-text-main" for="is_online">
                                    <i class="fas fa-globe me-2 text-info"></i> Online Group
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch custom-switch">
                                <input class="form-check-input" type="checkbox" name="is_free" id="is_free" value="1" x-model="is_free">
                                <label class="form-check-label fw-bold small theme-text-main" for="is_free">
                                    <i class="fas fa-hand-holding-heart me-2 text-success"></i> Free Group
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch custom-switch">
                                <input class="form-check-input" type="checkbox" name="is_public" id="is_public" value="1" x-model="is_public">
                                <label class="form-check-label fw-bold small theme-text-main" for="is_public">
                                    <i class="fas fa-eye me-2 text-primary"></i> Public Group
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small theme-text-main">Course</label>
                            <select name="course_id" class="form-select theme-card border theme-border theme-text-main" x-model="course_id" required>
                                <option value="">Select Course</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->course_id }}">{{ $course->course_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mt-4">
                            <label class="form-label fw-bold small theme-text-main">Subcourse (Optional)</label>
                            <select name="subcourse_id" class="form-select theme-card border theme-border theme-text-main" x-model="subcourse_id" :disabled="subcourseLoading || !course_id">
                                <option value="" x-text="subcourseLoading ? 'Loading...' : 'Select Subcourse'"></option>
                                <template x-for="sub in subcourses" :key="sub.subcourse_id">
                                    <option :value="sub.subcourse_id" x-text="sub.subcourse_name || `Part ${sub.subcourse_number}`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small theme-text-main">Teacher</label>
                            <select name="teacher_id" class="form-select theme-card border theme-border theme-text-main" x-model="teacher_id" required>
                                <option value="">Select Teacher</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->teacher_id }}">{{ $teacher->teacher_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 theme-card p-4 mb-4">
                    <h5 class="fw-bold mb-4 theme-text-main"><i class="fas fa-calendar-alt me-2 text-primary"></i>Schedule & Pricing</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small theme-text-main">Start Date</label>
                            <input type="date" name="start_date" class="form-control theme-card border theme-border theme-text-main" x-model="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small theme-text-main">End Date</label>
                            <input type="date" name="end_date" class="form-control theme-card border theme-border theme-text-main" x-model="end_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small theme-text-main">Day of Week</label>
                            <select name="day_of_week" class="form-select theme-card border theme-border theme-text-main" x-model="day_of_week" required>
                                <option value="">Select Day</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small theme-text-main">Start Time</label>
                            <input type="time" name="start_time" class="form-control theme-card border theme-border theme-text-main" x-model="start_time" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small theme-text-main">End Time</label>
                            <input type="time" name="end_time" class="form-control theme-card border theme-border theme-text-main" x-model="end_time" required>
                        </div>
                        <div class="col-md-6" x-show="!is_online">
                            <label class="form-label fw-bold small theme-text-main">Room</label>
                            <select name="room_id" class="form-select theme-card border theme-border theme-text-main" x-model="room_id" :required="!is_online">
                                <option value="">Select Room</option>
                                @foreach($rooms as $room)
                                    <option value="{{ $room->room_id }}">{{ $room->room_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small theme-text-main">Price (EGP)</label>
                            <input type="number" name="price" class="form-control theme-card border theme-border theme-text-main" x-model="price" :disabled="is_free" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small theme-text-main">Teacher %</label>
                            <input type="number" name="teacher_percentage" class="form-control theme-card border theme-border theme-text-main" x-model="teacher_percentage" min="0" max="100" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 theme-card h-100 overflow-hidden">
                    <div class="card-header border-0 bg-primary bg-opacity-10 p-4">
                        <h5 class="fw-bold mb-0 theme-text-main"><i class="fas fa-users-cog me-2 text-primary"></i>Student Selection</h5>
                    </div>
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-transparent border-end-0 theme-border"><i class="fas fa-search text-muted small"></i></span>
                            <input type="text" class="form-control border-start-0 theme-card theme-border theme-text-main" placeholder="Search students..." x-model="searchTerm">
                        </div>
                        
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" @click="selectedStudents = students.map(s => s.student_id)" class="btn btn-light btn-sm flex-grow-1 border theme-border theme-text-main rounded-pill">Select All</button>
                            <button type="button" @click="selectedStudents = []" class="btn btn-light btn-sm flex-grow-1 border theme-border theme-text-main rounded-pill">None</button>
                        </div>

                        <div class="flex-grow-1 overflow-auto pe-2" style="max-height: 500px;">
                            <template x-for="student in filteredStudents" :key="student.student_id">
                                <div 
                                    class="p-2 rounded-3 mb-2 border transition cursor-pointer d-flex align-items-center"
                                    :class="selectedStudents.includes(student.student_id) ? 'bg-primary bg-opacity-10 border-primary' : 'theme-border'"
                                    @click="toggleStudent(student.student_id)"
                                >
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" :name="'students[]'" :value="student.student_id" :checked="selectedStudents.includes(student.student_id)">
                                    </div>
                                    <div class="ms-2">
                                        <p class="mb-0 fw-bold small theme-text-main" x-text="student.student_name"></p>
                                        <p class="mb-0 text-muted smaller" x-text="student.user ? student.user.username : 'No username'"></p>
                                    </div>
                                    <div class="ms-auto" x-show="student.payment_status === 'unpaid'">
                                        <span class="badge bg-danger rounded-circle p-1" title="Unpaid Invoices">!</span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 pt-3 border-top theme-border">
                            <div class="d-flex justify-content-between theme-text-main mb-3">
                                <span>Selected:</span>
                                <span class="fw-bold" x-text="selectedStudents.length"></span>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 shadow" :disabled="selectedStudents.length === 0">
                                Create Group
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .smaller { font-size: 0.75rem; }
    .cursor-pointer { cursor: pointer; }
    .custom-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
    .custom-switch .form-check-label { cursor: pointer; padding-left: 0.5rem; padding-top: 0.2rem; }
</style>
@endsection
