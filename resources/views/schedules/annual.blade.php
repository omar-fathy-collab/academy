@extends('layouts.authenticated')

@section('title', 'Annual Schedule')

@section('content')
<div x-data="{ selectedRoom: '{{ $selectedRoom }}', year: '{{ $year }}' }">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 pb-3 border-bottom theme-border">
        <div>
            <h1 class="h3 fw-bold mb-1 theme-text-main">
                <i class="fas fa-calendar me-2 text-primary"></i> Annual Timetable
            </h1>
            <p class="text-muted small mb-0">High-level overview of academy room occupancy for the entire year</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('schedules.index') }}" class="btn btn-light rounded-pill px-4 shadow-sm border theme-border">
                Back to Dashboard
            </a>
            <div class="dropdown">
                <button class="btn btn-dark rounded-pill px-4 shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-calendar-day me-2"></i> Year: {{ $year }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                    @for($i = -2; $i <= 2; $i++)
                        @php $y = now()->addYears($i)->year; @endphp
                        <li><a class="dropdown-item" href="{{ route('schedules.annual', ['year' => $y, 'room_id' => $selectedRoom]) }}">{{ $y }}</a></li>
                    @endfor
                </ul>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 theme-card mb-4">
        <div class="card-body p-4">
            <form action="{{ route('schedules.annual') }}" method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="year" :value="year">
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
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Update Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Annual Grid -->
    <div class="row g-4">
        @for($m = 1; $m <= 12; $m++)
            @php
                $monthDate = \Carbon\Carbon::create($year, $m, 1);
                $monthSchedules = $schedules->filter(function($s) use ($monthDate) {
                    $start = \Carbon\Carbon::parse($s->start_date);
                    $end = \Carbon\Carbon::parse($s->end_date);
                    return $monthDate->between($start->startOfMonth(), $end->endOfMonth());
                });
            @endphp
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 theme-card h-100">
                    <div class="card-header border-0 bg-light p-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 theme-text-main">{{ $monthDate->format('F') }}</h6>
                        <span class="badge bg-primary rounded-pill small">{{ $monthSchedules->count() }} Classes</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="list-group list-group-flush small">
                            @forelse($monthSchedules->take(5) as $schedule)
                                <div class="list-group-item bg-transparent border-0 px-0 py-1 d-flex align-items-center">
                                    <div class="course-dot me-2 shadow-sm" style="background-color: {{ $colors[$schedule->group->course_id ?? 0] ?? '#0d6efd' }};"></div>
                                    <span class="text-truncate flex-grow-1 theme-text-main fw-medium">{{ $schedule->group->group_name ?? 'Unknown Group' }}</span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted fst-italic">No classes scheduled</div>
                            @endforelse
                            @if($monthSchedules->count() > 5)
                                <div class="text-center mt-2 small">
                                    <a href="{{ route('schedules.monthly', ['month' => $monthDate->format('Y-m'), 'room_id' => $selectedRoom]) }}" class="text-primary text-decoration-none">+ {{ $monthSchedules->count() - 5 }} more classes</a>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="card-footer border-0 bg-transparent p-3 pt-0">
                        <a href="{{ route('schedules.monthly', ['month' => $monthDate->format('Y-m'), 'room_id' => $selectedRoom]) }}" class="btn btn-outline-light btn-sm w-100 rounded-pill border theme-border theme-text-main">
                            View Month Full
                        </a>
                    </div>
                </div>
            </div>
        @endfor
    </div>
</div>

<style>
    .course-dot { width: 8px; height: 8px; border-radius: 50%; min-width: 8px; }
    .theme-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
    .theme-text-main { color: var(--text-main) !important; }
    .theme-border { border-color: var(--border-color) !important; }
</style>
@endsection
