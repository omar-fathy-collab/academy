@extends('layouts.authenticated')

@section('title', 'Group: ' . $group->group_name)

@section('content')
<div x-data="{ 
    activeTab: 'students',
    showAddSession: false,
    
    // New Session Form State
    session_date: new Date().toISOString().split('T')[0],
    start_time: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
    end_time: new Date(new Date().getTime() + 120*60000).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
    topic: '',
    notes: '',
    requires_proximity: true,
    meetings: [],
    
    addMeeting() {
        this.meetings.push({ title: `Room ${this.meetings.length + 1}`, link: '' });
    },
    removeMeeting(index) {
        this.meetings.splice(index, 1);
    }
}">
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">{{ $group->group_name }}</h1>
            <p class="text-muted small mb-0">
                <span class="badge bg-primary me-2">{{ $group->course->course_name ?? 'N/A' }}</span>
                {{ $group->start_date }} - {{ $group->end_date }}
            </p>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()->role_id === 1)
                <a href="{{ route('groups.edit', $group->uuid) }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border">
                    <i class="fas fa-edit me-2 text-primary"></i> Edit Group
                </a>
            @endif
            <a href="{{ route('groups.index') }}" class="btn theme-card btn-sm px-4 rounded-pill shadow-sm border theme-border theme-text-main">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 text-center h-100">
                <p class="text-muted small mb-1">Teacher</p>
                <h6 class="fw-bold mb-0 theme-text-main">{{ $group->teacher->teacher_name ?? 'N/A' }}</h6>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 text-center h-100">
                <p class="text-muted small mb-1">Students</p>
                <h6 class="fw-bold mb-0 theme-text-main">{{ $group->students->count() }} Enrolled</h6>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 text-center h-100">
                <p class="text-muted small mb-1">Price</p>
                <h6 class="fw-bold mb-0 text-success">{{ $group->price }} EGP</h6>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 theme-card p-3 text-center h-100">
                <p class="text-muted small mb-1">Schedule</p>
                <h6 class="fw-bold mb-0 text-primary">{{ $group->schedule }}</h6>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card mb-4 mt-4">
        <div class="card-header theme-card-header border-0 p-0">
            <ul class="nav nav-tabs nav-fill border-bottom-0">
                <li class="nav-item">
                    <button
                        class="nav-link py-3 border-0 rounded-0 fw-bold transition"
                        :class="activeTab === 'students' ? 'active theme-tab-active border-bottom border-primary border-3' : 'theme-text-main opacity-75'"
                        @click="activeTab = 'students'"
                    >
                        <i class="fas fa-users me-2"></i> Students
                    </button>
                </li>
                <li class="nav-item">
                    <button
                        class="nav-link py-3 border-0 rounded-0 fw-bold transition"
                        :class="activeTab === 'sessions' ? 'active theme-tab-active border-bottom border-primary border-3' : 'theme-text-main opacity-75'"
                        @click="activeTab = 'sessions'"
                    >
                        <i class="fas fa-calendar-check me-2"></i> Sessions ({{ $group->sessions->count() }})
                    </button>
                </li>
                @if(auth()->user()->role_id === 1)
                    <li class="nav-item">
                        <button
                            class="nav-link py-3 border-0 rounded-0 fw-bold transition"
                            :class="activeTab === 'payments' ? 'active theme-tab-active border-bottom border-primary border-3' : 'theme-text-main opacity-75'"
                            @click="activeTab = 'payments'"
                        >
                            <i class="fas fa-money-bill me-2"></i> Payments Status
                        </button>
                    </li>
                @endif
            </ul>
        </div>
        <div class="card-body p-4">
            <!-- Students Tab -->
            <div x-show="activeTab === 'students'">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Student</th>
                                <th>Phone</th>
                                <th>Rating</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group->students as $student)
                                <tr>
                                    <td class="fw-bold">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light p-2 rounded-circle me-2">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                            {{ $student->student_name }}
                                        </div>
                                    </td>
                                    <td>{{ $student->user->profile->phone_number ?? 'N/A' }}</td>
                                    <td>
                                        @if(isset($studentRatings[$student->student_id]) && $studentRatings[$student->student_id]['average_rating'] !== 'N/A')
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">
                                                {{ $studentRatings[$student->student_id]['average_rating'] }}/5
                                            </span>
                                        @else
                                            <span class="text-muted small">No ratings</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('student.info.show', ['uuid' => $student->uuid]) }}" class="btn btn-light btn-sm rounded-pill px-3 border">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sessions Tab -->
            <div x-show="activeTab === 'sessions'">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 theme-text-main">Group Sessions</h5>
                    @if(auth()->user()->role_id === 1 || auth()->user()->role_id === 2)
                        <button @click="showAddSession = true" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                            <i class="fas fa-plus me-2"></i> Add Session
                        </button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle theme-text-main">
                        <thead class="theme-badge-bg">
                            <tr class="theme-border">
                                <th class="theme-border">Date</th>
                                <th class="theme-border">Time</th>
                                <th class="theme-border">Topic</th>
                                <th class="text-end theme-border">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group->sessions as $session)
                                <tr class="theme-border">
                                    <td class="fw-bold theme-border">{{ $session->session_date }}</td>
                                    <td class="theme-border"><span class="badge theme-badge-bg theme-text-main border theme-border">{{ $session->start_time }} - {{ $session->end_time }}</span></td>
                                    <td class="theme-border">{{ $session->topic }}</td>
                                    <td class="text-end theme-border">
                                        <div class="btn-group btn-group-sm rounded-pill overflow-hidden border theme-border shadow-sm">
                                            <a href="{{ route('sessions.show', $session->uuid ?? $session->session_id) }}" class="btn theme-card"><i class="fas fa-eye text-info"></i></a>
                                            @if(auth()->user()->role_id === 1)
                                                <a href="{{ route('sessions.edit', $session->uuid ?? $session->session_id) }}" class="btn theme-card border-start theme-border"><i class="fas fa-edit text-warning"></i></a>
                                                <form action="{{ route('sessions.destroy', $session->session_id) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn theme-card border-start theme-border"><i class="fas fa-trash text-danger"></i></button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments Tab -->
            @if(auth()->user()->role_id === 1)
                <div x-show="activeTab === 'payments'">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle theme-text-main">
                            <thead class="theme-badge-bg">
                                <tr class="theme-border">
                                    <th class="theme-border">Student</th>
                                    <th class="theme-border">Required</th>
                                    <th class="theme-border">Paid</th>
                                    <th class="theme-border">Balance</th>
                                    <th class="text-end theme-border">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group->students as $student)
                                    @php
                                        $invoice = $student->invoices->first();
                                        $required = $invoice ? $invoice->final_amount : $group->price;
                                        $paid = $invoice ? $invoice->amount_paid : 0;
                                        $balance = $required - $paid;
                                    @endphp
                                    <tr class="theme-border" @refresh-table.window="location.reload()">
                                        <td class="fw-bold theme-border">{{ $student->student_name }}</td>
                                        <td class="theme-border">{{ number_format($required, 2) }} <span class="small text-muted">EGP</span></td>
                                        <td class="theme-border">{{ number_format($paid, 2) }} <span class="small text-muted">EGP</span></td>
                                        <td class="theme-border">
                                            @if($balance > 0)
                                                <span class="text-danger fw-bold">{{ number_format($balance, 2) }} EGP</span>
                                            @else
                                                <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Paid Off</span>
                                            @endif
                                        </td>
                                        <td class="text-end theme-border">
                                            @if($balance > 0)
                                                <a 
                                                    href="{{ $invoice ? route('student.payment.show', ['invoice_id' => $invoice->invoice_id]) : '#' }}" 
                                                    class="btn btn-success btn-sm rounded-pill px-3 shadow-sm fw-bold {{ !$invoice ? 'disabled opacity-50' : '' }}"
                                                >
                                                    <i class="fas fa-hand-holding-usd me-1"></i> Collect
                                                </a>
                                            @else
                                                <button class="btn btn-light btn-sm rounded-pill px-3 border theme-border disabled opacity-50">
                                                    Fully Paid
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Add Session Modal -->
    <div x-show="showAddSession" class="custom-modal-backdrop" style="display:none;" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 theme-card">
                <div class="modal-header border-0 bg-primary text-white p-4 rounded-top-4">
                    <h5 class="modal-title fw-bold">Add New Session</h5>
                    <button type="button" class="btn-close btn-close-white" @click="showAddSession = false"></button>
                </div>
                <form action="{{ route('groups.sessions.store', ['group' => $group->uuid]) }}" method="POST" class="p-4">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold small theme-text-main">Date</label>
                        <input
                            type="date"
                            name="session_date"
                            class="form-control theme-card border theme-border theme-text-main"
                            x-model="session_date"
                            required
                        >
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small theme-text-main">Start Time</label>
                            <input
                                type="time"
                                name="start_time"
                                class="form-control theme-card border theme-border theme-text-main"
                                x-model="start_time"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small theme-text-main">End Time</label>
                            <input
                                type="time"
                                name="end_time"
                                class="form-control theme-card border theme-border theme-text-main"
                                x-model="end_time"
                                required
                            >
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small theme-text-main">Topic</label>
                        <input
                            type="text"
                            name="topic"
                            class="form-control theme-card border theme-border theme-text-main"
                            x-model="topic"
                            placeholder="Lesson topic"
                            required
                        >
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small theme-text-main">Notes (Optional)</label>
                        <textarea
                            name="notes"
                            class="form-control theme-card border theme-border theme-text-main"
                            x-model="notes"
                            rows="2"
                        ></textarea>
                    </div>
                    <div class="mb-4 form-check form-switch">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="requires_proximity"
                            role="switch"
                            id="requiresProximitySwitch"
                            value="1"
                            x-model="requires_proximity"
                        >
                        <label class="form-check-label fw-bold small theme-text-main" for="requiresProximitySwitch">
                            Require Physical Proximity (QR Attendance)
                        </label>
                    </div>

                    <!-- Meeting Links Section -->
                    <div class="mb-4 border-top pt-3 theme-border">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold small theme-text-main mb-0">Meeting Links (Google Meet)</label>
                            <button 
                                type="button" 
                                @click="addMeeting()"
                                class="btn btn-sm btn-outline-primary rounded-pill px-2"
                                style="font-size: 0.7rem;"
                            >
                                <i class="fas fa-plus me-1"></i> Add Link
                            </button>
                        </div>
                        
                        <div class="row g-2">
                            <template x-for="(meeting, index) in meetings" :key="index">
                                <div class="col-12">
                                    <div class="p-2 theme-badge-bg rounded-3 position-relative border theme-border bg-light">
                                        <button 
                                            type="button" 
                                            @click="removeMeeting(index)"
                                            class="btn btn-sm btn-link text-danger position-absolute top-0 end-0 p-0 me-2 mt-1"
                                        >
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                        <div class="row g-2 pe-4">
                                            <div class="col-4">
                                                <input
                                                    type="text"
                                                    :name="'meetings['+index+'][title]'"
                                                    class="form-control form-control-sm theme-card border-0 bg-transparent"
                                                    placeholder="Room name"
                                                    x-model="meeting.title"
                                                    required
                                                >
                                            </div>
                                            <div class="col-5">
                                                <input
                                                    type="url"
                                                    :name="'meetings['+index+'][link]'"
                                                    class="form-control form-control-sm theme-card border-0 bg-transparent"
                                                    placeholder="Meeting link"
                                                    x-model="meeting.link"
                                                    required
                                                >
                                            </div>
                                            <div class="col-3">
                                                <input
                                                    type="time"
                                                    :name="'meetings['+index+'][end_time]'"
                                                    class="form-control form-control-sm theme-card border-0 bg-transparent"
                                                    x-model="meeting.end_time"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 shadow">
                            Create Session
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-card-header { background-color: var(--card-bg) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    .theme-badge-bg { background-color: var(--bg-main) !important; }
    .theme-tab-active { background-color: var(--bg-main) !important; color: var(--bs-primary) !important; }
    .transition { transition: all 0.3s ease; }
    
    .nav-tabs .nav-link { color: var(--text-main); opacity: 0.6; }
    .nav-tabs .nav-link.active { color: var(--bs-primary); opacity: 1; }
    [x-cloak] { display: none !important; }

    .custom-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow-y: auto;
        padding: 20px;
    }
    .custom-modal-backdrop .modal-dialog {
        margin: 0;
        width: 100%;
        max-width: 500px;
    }
</style>
@endsection
