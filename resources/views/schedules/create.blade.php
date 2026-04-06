@extends('layouts.authenticated')

@section('title', 'Add New Schedule')

@section('content')
<div x-data="scheduleForm({
    availableGroups: {{ $activeGroups->map(fn($g) => ['id' => $g->group_id, 'name' => $g->group_name, 'start' => $g->start_date, 'end' => $g->end_date, 'course' => $g->course->course_name])->toJson() }},
    checkUrl: '{{ route('schedules.check-availability') }}'
})">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Schedule
            </h1>
            <p class="text-muted small mb-0">Create a new recurring class timetable for an academy group</p>
        </div>
        <a href="{{ route('schedules.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm border theme-border">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <!-- Main Creation Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 theme-card">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('schedules.store') }}" method="POST">
                        @csrf
                        
                        <!-- Group Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Academy Group <span class="text-danger">*</span></label>
                            <select name="group_id" class="form-select theme-card theme-border theme-text-main py-2" x-model="formData.group_id" @change="onGroupChange()" required>
                                <option value="">-- Select a Group --</option>
                                <template x-for="group in availableGroups">
                                    <option :value="group.id" x-text="group.name"></option>
                                </template>
                            </select>
                            @error('group_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Room Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Classroom (Room) <span class="text-danger">*</span></label>
                                <select name="room_id" class="form-select theme-card theme-border theme-text-main py-2" x-model="formData.room_id" @change="validateAvailability()" required>
                                    <option value="">-- Select Room --</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->room_id }}">{{ $room->room_name }} (Cap. {{ $room->capacity }})</option>
                                    @endforeach
                                </select>
                                @error('room_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <!-- Day Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Weekly Day <span class="text-danger">*</span></label>
                                <select name="day_of_week" class="form-select theme-card theme-border theme-text-main py-2" x-model="formData.day_of_week" @change="validateAvailability()" required>
                                    <option value="">-- Select Day --</option>
                                    @foreach($days as $key => $day)
                                        <option value="{{ $key }}">{{ $day }}</option>
                                    @endforeach
                                </select>
                                @error('day_of_week') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- Start Time -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Start Time <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" class="form-control theme-card theme-border theme-text-main py-2" x-model="formData.start_time" @change="validateAvailability()" required>
                            </div>

                            <!-- End Time -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">End Time <span class="text-danger">*</span></label>
                                <input type="time" name="end_time" class="form-control theme-card theme-border theme-text-main py-2" x-model="formData.end_time" @change="validateAvailability()" required>
                            </div>
                        </div>

                        <!-- Date Range (Read-only or synced with Group) -->
                        <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Start Date (Auto-sync)</label>
                                <input type="date" name="start_date" class="form-control theme-card theme-border theme-text-main bg-light" x-model="formData.start_date" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">End Date (Auto-sync)</label>
                                <input type="date" name="end_date" class="form-control theme-card theme-border theme-text-main bg-light" x-model="formData.end_date" readonly>
                            </div>
                        </div>

                        <!-- Availability Checker Response -->
                        <div x-show="checkingAvailability" class="alert alert-info py-2 rounded-3 mb-4 d-flex align-items-center">
                            <i class="fas fa-spinner fa-spin me-2"></i> Checking room availability...
                        </div>

                        <div x-show="availabilityMessage" :class="isAvailable ? 'alert-success' : 'alert-danger'" class="alert py-3 rounded-3 mb-4 fw-bold">
                            <i :class="isAvailable ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'" class="me-2"></i>
                            <span x-text="availabilityMessage"></span>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow" :disabled="!isAvailable || checkingAvailability">
                                <i class="fas fa-save me-2"></i> Save Schedule
                            </button>
                            <a href="{{ route('schedules.index') }}" class="btn btn-link text-muted">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Group Preview Sidebar -->
        <div class="col-lg-4">
            <div x-show="selectedGroup" class="card border-0 shadow-sm rounded-4 theme-card h-100" x-transition>
                <div class="card-header border-0 bg-primary bg-opacity-10 p-4">
                    <h5 class="fw-bold theme-text-main mb-0">Group Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="small text-muted d-block">Course Name</label>
                        <h6 class="fw-bold theme-text-main" x-text="selectedGroup?.course"></h6>
                    </div>
                    <div class="mb-4">
                        <label class="small text-muted d-block">Time Period</label>
                        <div class="d-flex align-items-center theme-text-main fw-bold">
                            <span x-text="formData.start_date"></span>
                            <i class="fas fa-arrow-right mx-2 text-muted x-small"></i>
                            <span x-text="formData.end_date"></span>
                        </div>
                    </div>
                    <div class="mb-4 p-3 bg-light rounded-3 theme-border border">
                        <p class="small text-muted mb-2">Duration per Session</p>
                        <h5 class="mb-0 text-primary fw-bold" x-text="calculateDuration()">--</h5>
                    </div>
                    <div class="alert alert-warning small border-0 py-2">
                        <i class="fas fa-info-circle me-1"></i> Schedules are automatically deactivated after the end date.
                    </div>
                </div>
            </div>
            
            <div x-show="!selectedGroup" class="card border-0 shadow-sm rounded-4 theme-card h-100 d-flex align-items-center justify-content-center p-5 text-center bg-light opacity-50">
                <div>
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted small">Select a group to see details and start scheduling.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function scheduleForm(config) {
    return {
        availableGroups: config.availableGroups,
        checkingAvailability: false,
        isAvailable: false,
        availabilityMessage: '',
        selectedGroup: null,
        formData: {
            group_id: '',
            room_id: '',
            day_of_week: '',
            start_time: '',
            end_time: '',
            start_date: '',
            end_date: ''
        },

        onGroupChange() {
            this.selectedGroup = this.availableGroups.find(g => g.id == this.formData.group_id);
            if (this.selectedGroup) {
                this.formData.start_date = this.selectedGroup.start;
                this.formData.end_date = this.selectedGroup.end;
                this.validateAvailability();
            } else {
                this.formData.start_date = '';
                this.formData.end_date = '';
                this.isAvailable = false;
                this.availabilityMessage = '';
            }
        },

        calculateDuration() {
            if (!this.formData.start_time || !this.formData.end_time) return 'N/A';
            let start = new Date('2000-01-01 ' + this.formData.start_time);
            let end = new Date('2000-01-01 ' + this.formData.end_time);
            let diff = (end - start) / 1000 / 60; // minutes
            if (diff <= 0) return 'Invalid range';
            let hours = Math.floor(diff / 60);
            let mins = diff % 60;
            return `${hours}h ${mins > 0 ? mins+'m' : ''}`;
        },

        async validateAvailability() {
            const fd = this.formData;
            if (!fd.room_id || !fd.day_of_week || !fd.start_time || !fd.end_time || !fd.start_date || !fd.end_date) {
                this.isAvailable = false;
                this.availabilityMessage = '';
                return;
            }

            // Basic time validation
            if (fd.start_time >= fd.end_time) {
                this.isAvailable = false;
                this.availabilityMessage = 'End time must be after start time.';
                return;
            }

            this.checkingAvailability = true;
            try {
                const response = await fetch(config.checkUrl + '?' + new URLSearchParams(fd));
                const data = await response.json();
                this.isAvailable = data.available;
                this.availabilityMessage = data.message;
            } catch (e) {
                console.error(e);
                this.availabilityMessage = 'Error checking room status.';
            } finally {
                this.checkingAvailability = false;
            }
        }
    }
}
</script>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    [x-cloak] { display: none !important; }
    .x-small { font-size: 0.6rem; }
</style>
@endsection
