@extends('layouts.authenticated')

@section('title', 'Edit Session: ' . $session->topic)

@section('content')
<div x-data="{
    topic: '{{ $session->topic }}',
    session_date: '{{ \Carbon\Carbon::parse($session->session_date)->format('Y-m-d') }}',
    start_time: '{{ $session->start_time }}',
    end_time: '{{ $session->end_time }}',
    notes: '{{ $session->notes }}',
    requires_proximity: {{ $session->requires_proximity ? 'true' : 'false' }},
    meetingLinks: {{ json_encode($session->meetings->map(fn($m) => ['id' => $m->id, 'title' => $m->title, 'link' => $m->meeting_link])) }},
    
    addMeeting() {
        this.meetingLinks.push({ title: `Room ${this.meetingLinks.length + 1}`, link: '' });
    },
    removeMeeting(index) {
        this.meetingLinks.splice(index, 1);
    }
}">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between mb-4 text-ltr">
                <div class="d-flex align-items-center">
                    <a href="{{ route('groups.show', $session->group->uuid ?? $session->group_id) }}" class="btn theme-card rounded-circle shadow-sm me-3 p-2 theme-text-main border theme-border">
                        <i class="fas fa-arrow-left fa-lg"></i>
                    </a>
                    <div>
                        <h2 class="fw-bold theme-text-main mb-1">Edit Session Details</h2>
                        <p class="text-muted small mb-0">Group: <span class="theme-text-main">{{ $session->group->group_name }}</span> | Session #<span class="theme-text-main">{{ $session->session_id }}</span></p>
                    </div>
                </div>
            </div>

            <!-- Form Card -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-ltr theme-card">
                <div class="card-body p-4 p-md-5">
                    <form action="{{ route('sessions.update', $session->uuid ?? $session->session_id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label fw-bold theme-text-main small text-uppercase">Session Topic</label>
                                <input
                                    type="text"
                                    name="topic"
                                    class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('topic') is-invalid @enderror"
                                    placeholder="e.g. Introduction to Programming"
                                    x-model="topic"
                                    required
                                >
                                @error('topic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold theme-text-main small text-uppercase">Session Date</label>
                                <input
                                    type="date"
                                    name="session_date"
                                    class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('session_date') is-invalid @enderror"
                                    x-model="session_date"
                                    required
                                >
                                @error('session_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold theme-text-main small text-uppercase">Start Time</label>
                                <input
                                    type="time"
                                    name="start_time"
                                    class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('start_time') is-invalid @enderror"
                                    x-model="start_time"
                                    required
                                >
                                @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold theme-text-main small text-uppercase">End Time</label>
                                <input
                                    type="time"
                                    name="end_time"
                                    class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('end_time') is-invalid @enderror"
                                    x-model="end_time"
                                    required
                                >
                                @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold theme-text-main small text-uppercase">Session Notes</label>
                                <textarea
                                    name="notes"
                                    class="form-control theme-badge-bg border-0 py-3 px-4 rounded-3 shadow-none theme-text-main @error('notes') is-invalid @enderror"
                                    rows="4"
                                    placeholder="Add any notes or topics covered in the session..."
                                    x-model="notes"
                                ></textarea>
                                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold theme-text-main mb-0">Meeting Links (Google Meet / Zoom)</h5>
                                    <button type="button" @click="addMeeting()" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fas fa-plus me-1"></i> Add Another Link
                                    </button>
                                </div>
                                
                                <div class="row g-3">
                                    <template x-show="meetingLinks.length === 0">
                                        <div class="col-12">
                                            <div class="p-4 text-center theme-badge-bg rounded-3 border-dashed border-secondary opacity-50">
                                                <i class="fas fa-video-slash mb-2 fa-2x"></i>
                                                <p class="small mb-0">No meeting links added. The default link will be used if available.</p>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-for="(m, i) in meetingLinks" :key="i">
                                        <div class="col-12">
                                            <div class="p-3 theme-badge-bg rounded-3 position-relative">
                                                <button type="button" @click="removeMeeting(i)" class="btn btn-sm btn-link text-danger position-absolute top-0 end-0 mt-2 me-2 p-0">
                                                    <i class="fas fa-times-circle fa-lg"></i>
                                                </button>
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <label class="small text-muted mb-1">Room / Group Name</label>
                                                        <input type="text" :name="'meetings['+i+'][title]'" class="form-control form-control-sm border-0" x-model="m.title">
                                                        <input type="hidden" :name="'meetings['+i+'][id]'" x-model="m.id">
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="small text-muted mb-1">Meeting Link</label>
                                                        <input type="url" :name="'meetings['+i+'][link]'" class="form-control form-control-sm border-0" x-model="m.link" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="form-check form-switch p-3 theme-badge-bg rounded-3 border-0">
                                    <input
                                        class="form-check-input ms-3 me-0 mt-1"
                                        style="float: left; margin-right: 1rem;"
                                        type="checkbox"
                                        name="requires_proximity"
                                        role="switch"
                                        id="requiresProximitySwitchEdit"
                                        x-model="requires_proximity"
                                        :checked="requires_proximity"
                                        value="1"
                                    >
                                    <div style="margin-left: 3.5rem;">
                                        <label class="form-check-label fw-bold theme-text-main d-block" for="requiresProximitySwitchEdit">
                                            Enable WiFi Proximity Attendance
                                        </label>
                                        <small class="text-muted d-block mt-1">
                                            When enabled, only students connected to your exact WiFi network can automatically register their attendance.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mt-5">
                                <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 shadow fw-bold text-uppercase">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .text-ltr { direction: ltr; }
    [x-cloak] { display: none !important; }
</style>
@endsection
