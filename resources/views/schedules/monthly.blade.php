@extends('layouts.authenticated')

@section('title', 'Monthly Schedule')

@section('content')
<div x-data="{ 
    selectedRoom: '{{ $selectedRoom }}',
    month: '{{ $month }}',

    updateView() {
        let url = new URL(window.location.href);
        url.searchParams.set('room_id', this.selectedRoom);
        url.searchParams.set('month', this.month);
        window.location.href = url.toString();
    }
}">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                <i class="fas fa-calendar-alt me-2 text-primary"></i> Monthly Timetable
            </h1>
            <p class="text-muted small mb-0">Browse academy classes and room occupancy for a specific month</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('schedules.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm border theme-border">
                Back to Dashboard
            </a>
            <a href="{{ route('schedules.print.monthly', ['room_id' => $selectedRoom, 'month' => $month]) }}" target="_blank" class="btn btn-dark rounded-pill px-4 shadow-sm">
                <i class="fas fa-print me-2"></i> Print Month
            </a>
        </div>
    </div>

    <!-- Filters & Navigation -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small">Select Month</label>
                    <input type="month" class="form-control theme-card theme-border theme-text-main" x-model="month" @change="updateView()">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small">Filter by Room</label>
                    <select class="form-select theme-card theme-border theme-text-main" x-model="selectedRoom" @change="updateView()">
                        <option value="">All Rooms</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->room_id }}">{{ $room->room_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 ms-auto d-flex justify-content-end gap-2">
                    @php
                        $prevMonth = \Carbon\Carbon::parse($month)->subMonth()->format('Y-m');
                        $nextMonth = \Carbon\Carbon::parse($month)->addMonth()->format('Y-m');
                    @endphp
                    <a href="{{ route('schedules.monthly', ['month' => $prevMonth, 'room_id' => $selectedRoom]) }}" class="btn btn-outline-primary rounded-pill px-3">
                        <i class="fas fa-chevron-left me-2"></i> Previous
                    </a>
                    <a href="{{ route('schedules.monthly', ['month' => $nextMonth, 'room_id' => $selectedRoom]) }}" class="btn btn-outline-primary rounded-pill px-3">
                        Next <i class="fas fa-chevron-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Month Grid -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
        <div class="grid-calendar">
            <div class="calendar-header d-flex bg-light text-center py-2 fw-bold small text-muted text-uppercase">
                <div class="flex-grow-1">Sun</div>
                <div class="flex-grow-1">Mon</div>
                <div class="flex-grow-1">Tue</div>
                <div class="flex-grow-1">Wed</div>
                <div class="flex-grow-1">Thu</div>
                <div class="flex-grow-1">Fri</div>
                <div class="flex-grow-1">Sat</div>
            </div>
            <div class="calendar-body d-flex flex-wrap">
                @foreach($calendar as $day)
                    <div class="calendar-day border-bottom border-end theme-border {{ $day['isCurrentMonth'] ? '' : 'bg-light opacity-50' }} {{ $day['isToday'] ? 'today-glow' : '' }}" style="width: 14.28%; min-height: 120px; padding: 10px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold {{ $day['isToday'] ? 'text-primary h5 mb-0' : 'text-muted small' }}">
                                {{ $day['date']->format('d') }}
                            </span>
                        </div>
                        <div class="day-events">
                            @foreach($day['schedules'] as $schedule)
                                <div 
                                    class="event-pill mb-1 p-1 rounded-2 text-white extra-small fw-medium"
                                    style="background-color: {{ $colors[$schedule->group->course_id ?? 0] ?? '#0d6efd' }};"
                                    title="{{ $schedule->group->group_name ?? 'Unknown' }} ({{ $schedule->formatted_start }})"
                                >
                                    <div class="text-truncate">{{ $schedule->group->group_name ?? 'Unknown Group' }}</div>
                                    <div class="extra-small opacity-75">{{ $schedule->formatted_start }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<style>
    .grid-calendar { border: 1px solid var(--border-color); }
    .calendar-day { transition: background 0.2s ease; position: relative; }
    .calendar-day:hover { background: rgba(var(--bs-primary-rgb), 0.02); }
    .today-glow { background: rgba(var(--bs-primary-rgb), 0.05) !important; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); }
    .event-pill { cursor: pointer; border-left: 3px solid rgba(0,0,0,0.2); }
    .event-pill:hover { filter: brightness(1.1); transform: scale(1.02); }
    .extra-small { font-size: 0.65rem; line-height: 1.1; }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
</style>
@endsection
