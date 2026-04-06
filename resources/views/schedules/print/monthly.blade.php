<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Schedule - Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: white; padding: 20px; font-family: sans-serif; }
        .calendar-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .calendar-grid th, .calendar-grid td { border: 1px solid #dee2e6; height: 100px; vertical-align: top; padding: 5px; overflow: hidden; }
        .calendar-grid th { height: 30px; text-align: center; background: #f8f9fa; font-weight: bold; font-size: 11px; }
        .day-header { font-weight: bold; margin-bottom: 5px; font-size: 12px; }
        .event-pill { 
            background: #f1f1f1; border-left: 3px solid #333; padding: 2px 4px; margin-bottom: 2px;
            font-size: 9px; line-height: 1.1; border-radius: 2px;
        }
        .text-bold { font-weight: bold; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .event-pill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print text-muted small">
        <div>ICT Academy Timetable System</div>
        <div>Room: {{ $rooms->find($selectedRoom)->room_name ?? 'All Rooms' }}</div>
    </div>

    <h2 class="text-center fw-bold mb-0">Academy Monthly Schedule</h2>
    <h4 class="text-center text-muted mb-4">{{ \Carbon\Carbon::parse($month)->format('F Y') }}</h4>

    <table class="calendar-grid">
        <thead>
            <tr>
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach(array_chunk($calendar, 7) as $week)
                <tr>
                    @foreach($week as $day)
                        <td class="{{ $day['isCurrentMonth'] ? '' : 'bg-light opacity-50' }}">
                            <div class="day-header">{{ $day['date']->format('d') }}</div>
                            @foreach($day['schedules'] as $schedule)
                                <div class="event-pill" style="border-left-color: {{ $colors[$schedule->group->course_id ?? 0] ?? '#000' }};">
                                    <div class="text-bold">{{ $schedule->group->group_name ?? 'Unknown Group' }}</div>
                                    <div>{{ $schedule->formatted_start }}</div>
                                    <div>{{ $schedule->room->room_name ?? 'N/A' }}</div>
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-5 text-center small text-muted">
        © {{ now()->year }} ICT Academy - Printed on: {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
