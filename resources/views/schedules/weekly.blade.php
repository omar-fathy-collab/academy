@extends('layouts.authenticated')

@section('title', 'Weekly Schedule')

@section('content')
<div x-data="{ selectedRoom: '{{ $selectedRoom }}' }">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                <i class="fas fa-calendar-week me-2 text-primary"></i> Weekly Timetable
            </h1>
            <p class="text-muted small mb-0">View class occupancy across all rooms for the current week</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('schedules.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm border theme-border">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <a href="{{ route('schedules.print.weekly', ['room_id' => $selectedRoom]) }}" target="_blank" class="btn btn-dark rounded-pill px-4 shadow-sm">
                <i class="fas fa-print me-2"></i> Print View
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4">
        <div class="card-body p-4">
            <form action="{{ route('schedules.weekly') }}" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted small">Filter by Room</label>
                    <select name="room_id" class="form-select theme-card theme-border theme-text-main" x-model="selectedRoom" @change="$el.form.submit()">
                        <option value="">All Rooms</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->room_id }}">{{ $room->room_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Update View</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Weekly Grid -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden theme-card">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 timetable-table">
                <thead class="bg-light">
                    <tr>
                        <th class="time-column text-center py-3 bg-white sticky-col">Time</th>
                        @foreach($days as $key => $dayName)
                            <th class="text-center py-3 day-column">{{ $dayName }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $time)
                        <tr>
                            <td class="text-center py-4 fw-bold text-muted small bg-light bg-opacity-25 sticky-col">
                                {{ \Carbon\Carbon::parse($time)->format('h:i A') }}
                            </td>
                            @foreach($days as $dayKey => $dayName)
                                <td class="p-1 timetable-slot" data-day="{{ $dayKey }}" data-time="{{ $time }}">
                                    @foreach($schedules->filter(fn($s) => strtolower($s->day_of_week) === strtolower($dayKey)) as $schedule)
                                        @php
                                            $slotTime = \Carbon\Carbon::parse($time);
                                            $startTime = \Carbon\Carbon::parse($schedule->start_time);
                                            $endTime = \Carbon\Carbon::parse($schedule->end_time);
                                            
                                            $isStarting = $slotTime->format('H:i') === $startTime->format('H:i');
                                            $isPlaying = $slotTime->between($startTime, $endTime->subMinute());
                                        @endphp

                                        @if($isStarting)
                                            <div 
                                                class="schedule-block rounded-3 p-2 shadow-sm text-white"
                                                style="background-color: {{ $colors[$schedule->group->course_id ?? 0] ?? '#0d6efd' }};"
                                                title="{{ $schedule->group->group_name ?? 'Unknown' }} ({{ $schedule->room->room_name ?? 'N/A' }})"
                                            >
                                                <div class="fw-bold small lh-1 mb-1">{{ $schedule->group->group_name ?? 'Unknown Group' }}</div>
                                                <div class="extra-small opacity-75 lh-1">
                                                    {{ $startTime->format('h:i') }} - {{ $endTime->format('h:i A') }}
                                                </div>
                                                <div class="extra-small mt-1 fw-medium">
                                                    <i class="fas fa-door-open me-1"></i> {{ $schedule->room->room_name ?? 'N/A' }}
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .timetable-table { border-collapse: separate; border-spacing: 0; table-layout: fixed; min-width: 1000px; }
    .timetable-table th, .timetable-table td { border-color: var(--border-color) !important; }
    .time-column { width: 100px; z-index: 10; }
    .day-column { width: 130px; }
    .timetable-slot { height: 80px; position: relative; vertical-align: top; }
    .schedule-block {
        position: absolute;
        top: 4px; left: 4px; right: 4px;
        z-index: 5;
        border-left: 4px solid rgba(0,0,0,0.2);
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    .schedule-block:hover { transform: scale(1.02); z-index: 100; }
    .extra-small { font-size: 0.7rem; }
    .sticky-col { position: sticky; left: 0; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
</style>
@endsection
