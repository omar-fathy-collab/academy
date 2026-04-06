@extends('layouts.authenticated')

@section('title', 'Schedules Management')

@section('content')
<div x-data="{ 
    ...ajaxTable(),
    activeTab: '{{ $activeTab }}',

    switchTab(tab) {
        this.activeTab = tab;
        let url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }
}">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                <i class="fas fa-calendar-alt me-2 text-primary"></i> Schedules & Rooms
            </h1>
            <p class="text-muted small mb-0">Manage and monitor academy timetable and classroom occupancy</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-sm-0">
            <a href="{{ route('schedules.weekly') }}" class="btn btn-outline-primary shadow-sm rounded-pill px-3">
                <i class="fas fa-calendar-week me-1"></i> Weekly View
            </a>
            <a href="{{ route('schedules.monthly') }}" class="btn btn-outline-primary shadow-sm rounded-pill px-3">
                <i class="fas fa-calendar-alt me-1"></i> Monthly View
            </a>
            @if(isset($is_admin) && $is_admin)
                <a href="{{ route('rooms.create') }}" class="btn btn-success shadow-sm rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i> Add Room
                </a>
            @endif
            <a href="{{ route('schedules.create') }}" class="btn btn-primary shadow-sm rounded-pill px-3">
                <i class="fas fa-plus me-1"></i> Add Schedule
            </a>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4 border-bottom-0" role="tablist">
        <li class="nav-item">
            <button class="nav-link" :class="{ 'active': activeTab === 'schedules' }" @click="switchTab('schedules')">
                <i class="fas fa-th-list me-2"></i> Schedules Dashboard
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" :class="{ 'active': activeTab === 'rooms' }" @click="switchTab('rooms')">
                <i class="fas fa-door-open me-2"></i> Rooms & Facilities
            </button>
        </li>
    </ul>

    <div class="tab-content position-relative">
        <!-- Loading Overlay -->
        <div class="ajax-loading-overlay" :class="loading ? 'active' : ''">
            <div class="spinner-border text-primary" role="status"></div>
        </div>

        <!-- Schedules Tab -->
        <div x-show="activeTab === 'schedules'" x-cloak class="ajax-content" id="schedules-tab-content">
            <!-- Filters -->
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 mt-2">
                <div class="card-body p-4">
                    <form class="row g-3 ajax-form" action="{{ route('schedules.index') }}" method="GET" @submit.prevent>
                        <input type="hidden" name="tab" value="schedules">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 theme-border"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 theme-card theme-border theme-text-main" placeholder="Search groups..." value="{{ request('search') }}" @input.debounce.500ms="updateList">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="room_id" class="form-select theme-card theme-border theme-text-main" @change="updateList">
                                <option value="" {{ request('room_id') == '' ? 'selected' : '' }}>All Rooms</option>
                                @foreach($allRooms as $room)
                                    <option value="{{ $room->room_id }}" {{ request('room_id') == $room->room_id ? 'selected' : '' }}>{{ $room->room_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="day_of_week" class="form-select theme-card theme-border theme-text-main" @change="updateList">
                                <option value="" {{ request('day_of_week') == '' ? 'selected' : '' }}>All Days</option>
                                @foreach($days as $key => $day)
                                    <option value="{{ $key }}" {{ request('day_of_week') == $key ? 'selected' : '' }}>{{ $day }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select theme-card theme-border theme-text-main" @change="updateList">
                                <option value="" {{ request('status') == '' ? 'selected' : '' }}>All Status</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Schedules Table -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Group Details</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Room</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Timing</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Duration</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Status</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $schedule)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="fw-bold theme-text-main">{{ $schedule->group->group_name ?? 'No Group' }}</div>
                                        <div class="small text-muted">
                                            {{ $schedule->group->course->course_name ?? 'No Course' }} • 
                                            {{ $schedule->group->teacher->teacher_name ?? 'No Teacher' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="badge bg-info bg-opacity-10 text-info fw-bold border border-info px-2 py-1">
                                            <i class="fas fa-door-open me-1"></i> {{ $schedule->room->room_name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="mb-1">
                                            <span class="badge bg-primary px-2">{{ $days[$schedule->day_of_week] ?? $schedule->day_of_week }}</span>
                                            <span class="ms-2 fw-bold theme-text-main">
                                                {{ \Carbon\Carbon::parse($schedule->start_time)->format('h:i A') }} - {{ \Carbon\Carbon::parse($schedule->end_time)->format('h:i A') }}
                                            </span>
                                        </div>
                                        <div class="text-muted small">
                                            {{ \Carbon\Carbon::parse($schedule->start_date)->format('M d') }} to {{ \Carbon\Carbon::parse($schedule->end_date)->format('M d, Y') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="badge bg-light text-dark border">
                                            @php
                                                $start = \Carbon\Carbon::parse($schedule->start_time);
                                                $end = \Carbon\Carbon::parse($schedule->end_time);
                                                $hours = $start->diffInHours($end);
                                                $minutes = $start->diffInMinutes($end) % 60;
                                            @endphp
                                            <i class="far fa-clock me-1 text-muted"></i> {{ $hours }}h {{ $minutes > 0 ? $minutes.'m' : '' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($schedule->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">Active</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2 py-1">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('schedules.edit', $schedule->schedule_id) }}" class="btn btn-light btn-sm rounded-circle border theme-border">
                                                <i class="fas fa-edit text-warning"></i>
                                            </a>
                                            <form action="{{ route('schedules.destroy', $schedule->schedule_id) }}" method="POST" onsubmit="return confirm('Delete this schedule?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-light btn-sm rounded-circle border theme-border">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No schedules found matching criteria.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($schedules instanceof \Illuminate\Pagination\AbstractPaginator && $schedules->hasPages())
                    <div class="card-footer bg-transparent border-top p-4 d-flex justify-content-center" @click="navigate">
                        {{ $schedules->links() }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Rooms Tab -->
        <div x-show="activeTab === 'rooms'" x-cloak class="ajax-content" id="rooms-tab-content">
            <!-- Room Filters -->
            <div class="card border-0 shadow-sm rounded-4 theme-card mb-4 mt-2">
                <div class="card-body p-4">
                    <form class="row g-3 ajax-form" action="{{ route('schedules.index') }}" method="GET" @submit.prevent>
                        <input type="hidden" name="tab" value="rooms">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-end-0 theme-border"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="room_search" class="form-control border-start-0 theme-card theme-border theme-text-main" placeholder="Search room name or location..." value="{{ request('room_search') }}" @input.debounce.500ms="updateList">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="room_status" class="form-select theme-card theme-border theme-text-main" @change="updateList">
                                <option value="" {{ request('room_status') == '' ? 'selected' : '' }}>All Status</option>
                                <option value="active" {{ request('room_status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('room_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        @if($is_admin)
                        <div class="col-md-5 text-end">
                            <a href="{{ route('rooms.create') }}" class="btn btn-success shadow-sm rounded-pill px-4">
                                <i class="fas fa-plus-circle me-1"></i> Add New Room
                            </a>
                        </div>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Rooms Table -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light bg-opacity-50">
                            <tr>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Room</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Capacity</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Location</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder">Status</th>
                                <th class="px-4 py-3 text-uppercase text-secondary small fw-bolder text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rooms as $room)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                                <i class="fas fa-door-open fa-lg"></i>
                                            </div>
                                            <div class="fw-bold theme-text-main">{{ $room->room_name }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="badge bg-light text-dark border px-3 py-2">
                                            <i class="fas fa-users text-muted me-2"></i> {{ $room->capacity }} Students
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-muted">
                                        {{ $room->location ?? 'Not Specified' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($room->is_active)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">Active</span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2 py-1">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('rooms.edit', $room->uuid ?? $room->room_id) }}" class="btn btn-light btn-sm rounded-circle border theme-border">
                                                <i class="fas fa-edit text-warning"></i>
                                            </a>
                                            <form action="{{ route('rooms.destroy', $room->room_id) }}" method="POST" onsubmit="return confirm('Delete this room?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-light btn-sm rounded-circle border theme-border">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No rooms found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($rooms instanceof \Illuminate\Pagination\AbstractPaginator && $rooms->hasPages())
                    <div class="card-footer bg-transparent border-top p-4 d-flex justify-content-center" @click="navigate">
                        {{ $rooms->appends(['tab' => 'rooms'])->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs .nav-link {
        color: #6c757d;
        font-weight: 600;
        padding: 1rem 1.5rem;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        background: transparent;
    }
    .nav-tabs .nav-link:hover {
        color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), 0.05);
        border-bottom-color: rgba(var(--bs-primary-rgb), 0.1);
    }
    .nav-tabs .nav-link.active {
        color: var(--bs-primary);
        border-bottom-color: var(--bs-primary);
    }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
    [x-cloak] { display: none !important; }
</style>
@endsection
