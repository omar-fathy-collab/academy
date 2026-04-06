<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Schedule - Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: white; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .timetable-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .timetable-table th, .timetable-table td { border: 1px solid #dee2e6; height: 60px; vertical-align: top; padding: 4px; font-size: 10px; }
        .timetable-table th { background: #f8f9fa; height: 30px; text-align: center; }
        .time-col { width: 80px; text-align: center; font-weight: bold; }
        .schedule-block { 
            background: #eee; border-left: 3px solid #333; padding: 4px; border-radius: 4px;
            margin-bottom: 2px;
        }
        .text-bold { font-weight: bold; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .schedule-block { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print text-muted small">
        <div>ICT Academy Timetable System</div>
        <div>Room: {{ $rooms->find($selectedRoom)->room_name ?? 'All Rooms' }}</div>
    </div>

    <h2 class="text-center fw-bold mb-4">Academy Weekly Schedule</h2>
    <div class="text-center text-muted mb-4 small">Printed on: {{ now()->format('Y-m-d H:i') }}</div>

    <table class="timetable-table">
        <thead>
            <tr>
                <th class="time-col">Time</th>
                @foreach($days as $key => $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($timeSlots as $time)
                <tr>
                    <td class="time-col">{{ \Carbon\Carbon::parse($time)->format('h:i A') }}</td>
                    @foreach($days as $dayKey => $dayName)
                        <td>
                            @foreach($schedules->filter(fn($s) => strtolower($s->day_of_week) === strtolower($dayKey) && \Carbon\Carbon::parse($s->start_time)->format('H:i') === \Carbon\Carbon::parse($time)->format('H:i')) as $schedule)
                                <div class="schedule-block" style="border-left-color: {{ $colors[$schedule->group->course_id ?? 0] ?? '#000' }};">
                                    <div class="text-bold">{{ $schedule->group->group_name ?? 'Unknown Group' }}</div>
                                    <div>{{ $schedule->room->room_name ?? 'N/A' }}</div>
                                    <div class="small opacity-75">
                                        {{ \Carbon\Carbon::parse($schedule->start_time)->format('h:i') }} - {{ \Carbon\Carbon::parse($schedule->end_time)->format('h:i A') }}
                                    </div>
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-5 text-center small text-muted">
        © {{ now()->year }} ICT Academy - Secure Scheduling Module
    </div>
</body>
</html>
