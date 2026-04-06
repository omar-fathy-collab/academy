<?php

// app/Http/Controllers/ScheduleController.php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Room;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SchedulesController extends Controller
{
    /**
     * Display all schedules in table view
     */
    public function index(Request $request)
    {
        // تحديد التاب النشط
        Schedule::deactivateExpiredSchedules();

        // تحديد التاب النشط
        $activeTab = $request->get('tab', 'schedules');

        // الـ Schedules (فقط إذا كان التاب schedules أو لم يتم تحديد تاب)
        $schedules = collect();
        if ($activeTab === 'schedules') {
            $schedules = Schedule::with(['group.teacher', 'group.course', 'room'])
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;

                    return $query->whereHas('group', function ($q) use ($search) {
                        $q->where('group_name', 'like', "%{$search}%");
                    })->orWhereHas('room', function ($q) use ($search) {
                        $q->where('room_name', 'like', "%{$search}%");
                    });
                })
                ->when($request->filled('room_id'), function ($query) use ($request) {
                    return $query->where('room_id', $request->room_id);
                })
                ->when($request->filled('day_of_week'), function ($query) use ($request) {
                    return $query->where('day_of_week', $request->day_of_week);
                })
                ->when($request->filled('status'), function ($query) use ($request) {
                    if ($request->status === 'active') {
                        return $query->where('is_active', 1);
                    } elseif ($request->status === 'inactive') {
                        return $query->where('is_active', 0);
                    }
                })
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->paginate(20)
                ->withQueryString();
        }

        // الـ Rooms
        $rooms = Room::when($request->filled('room_search'), function ($query) use ($request) {
            return $query->where('room_name', 'like', "%{$request->room_search}%")
                ->orWhere('location', 'like', "%{$request->room_search}%");
        })
            ->when($request->filled('room_status'), function ($query) use ($request) {
                if ($request->room_status === 'active') {
                    return $query->where('is_active', 1);
                } elseif ($request->room_status === 'inactive') {
                    return $query->where('is_active', 0);
                }
            })
            ->orderBy('room_name')
            ->paginate(20, ['*'], 'rooms_page')
            ->withQueryString();

        $allRooms = Room::where('is_active', 1)->get(); // للفلاتر
        $days = [
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
        ];

        $is_admin = auth()->user()->role_id == 1 || auth()->user()->role_id == 4; // admin or super-admin

        return view('schedules.index', compact('schedules', 'rooms', 'allRooms', 'days', 'activeTab', 'is_admin'));
    }

    private function getColorForCourse($courseId)
    {
        $colors = [
            '#3498db', '#e74c3c', '#2ecc71', '#f39c12',
            '#9b59b6', '#1abc9c', '#34495e', '#d35400',
            '#c0392b', '#8e44ad', '#27ae60', '#16a085',
        ];

        return $colors[$courseId % count($colors)];
    }

    // عدل الدوال عشان تضيف الألوان
    public function weeklyCalendar(Request $request)
    {
        $selectedRoom = $request->get('room_id');

        $days = [
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
        ];

        $timeSlots = $this->generateTimeSlots('08:00', '22:00', 60);

        // احصل على الـ schedules النشطة فقط (مع التحقق من تاريخ الجروب)
        $schedules = Schedule::with(['group.teacher', 'group.course', 'room'])
            ->where('is_active', 1) // استخدام الفلترة العامة
            ->when($selectedRoom, function ($query) use ($selectedRoom) {
                return $query->where('room_id', $selectedRoom);
            })
            ->get();

        $rooms = Room::where('is_active', 1)->get();

        // أضف colors array
        $colors = [];
        foreach ($schedules as $schedule) {
            $colors[$schedule->group->course_id] = $this->getColorForCourse($schedule->group->course_id);
        }

        return view('schedules.weekly', compact('days', 'timeSlots', 'schedules', 'rooms', 'selectedRoom', 'colors'));
    }

    private function formatScheduleTime($schedule)
    {
        $start = \Carbon\Carbon::parse($schedule->start_time);
        $end = \Carbon\Carbon::parse($schedule->end_time);

        // تحقق إذا كان الوقت صباحاً أو مساءً بشكل صحيح
        $startFormatted = $start->format('h:i A');
        $endFormatted = $end->format('h:i A');

        return [
            'start' => $startFormatted,
            'end' => $endFormatted,
            'duration' => $start->diffInHours($end) + ($start->diffInMinutes($end) % 60) / 60,
        ];
    }

    public function monthlyCalendar(Request $request)
    {
        $selectedRoom = $request->get('room_id');
        $month = $request->get('month', now()->format('Y-m'));

        // الحصول على الجداول النشطة في الشهر المحدد
        $schedules = Schedule::with(['group.teacher', 'group.course', 'room'])
            ->where('is_active', 1) // استخدام الفلترة العامة
            ->when($selectedRoom, function ($query) use ($selectedRoom) {
                return $query->where('room_id', $selectedRoom);
            })
            ->get()
            ->map(function ($schedule) {
                $timeData = $this->formatScheduleTime($schedule);
                $schedule->formatted_start = $timeData['start'];
                $schedule->formatted_end = $timeData['end'];
                $schedule->duration_hours = $timeData['duration'];

                return $schedule;
            });

        $rooms = Room::where('is_active', 1)->get();

        // أضف colors array
        $colors = [];
        foreach ($schedules as $schedule) {
            $colors[$schedule->group->course_id] = $this->getColorForCourse($schedule->group->course_id);
        }

        // أنشئ التقويم الشهري
        $calendar = $this->generateMonthlyCalendar($month, $schedules);

        return view('schedules.monthly', compact('schedules', 'rooms', 'selectedRoom', 'month', 'calendar', 'colors'));
    }

    /**
     * Generate monthly calendar - محدث
     */
    private function generateMonthlyCalendar($month, $schedules)
    {
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();
        $startDate = $startDate->copy()->startOfWeek(Carbon::SUNDAY);
        $endDate = $endDate->copy()->endOfWeek(Carbon::SATURDAY);

        $calendar = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayOfWeek = strtolower($current->englishDayOfWeek);

            // تصفية الجداول النشطة في هذا اليوم
            $daySchedules = $schedules->filter(function ($schedule) use ($current) {
                return $schedule->isActiveOnDate($current);
            });

            $calendar[] = [
                'date' => $current->copy(),
                'schedules' => $daySchedules,
                'isToday' => $current->isToday(),
                'isWeekend' => $current->isWeekend(),
                'isCurrentMonth' => $current->isSameMonth($month),
            ];

            $current->addDay();
        }

        return $calendar;
    }

    public function annualCalendar(Request $request)
    {
        $selectedRoom = $request->get('room_id');
        $year = $request->get('year', now()->year);

        $schedules = Schedule::with(['group.teacher', 'group.course', 'room'])
            ->where('is_active', 1) // استخدام الفلترة العامة
            ->when($selectedRoom, function ($query) use ($selectedRoom) {
                return $query->where('room_id', $selectedRoom);
            })
            ->get();

        $rooms = Room::where('is_active', 1)->get();

        // أضف colors array
        $colors = [];
        foreach ($schedules as $schedule) {
            $colors[$schedule->group->course_id] = $this->getColorForCourse($schedule->group->course_id);
        }

        return view('schedules.annual', compact('schedules', 'rooms', 'selectedRoom', 'year', 'colors'));
    }

    /**
     * Show form to create new schedule
     */
    // في ScheduleController - تحديث دالة create
    // دالة للتحقق من توافر الغرفة عبر AJAX
    // في ScheduleController
    public function checkAvailability(Request $request)
    {
        try {
            $request->validate([
                'room_id' => 'required|exists:rooms,room_id',
                'day_of_week' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
            ]);

            $excludeScheduleId = $request->exclude_schedule_id ?? null;

            $available = $this->checkRoomAvailability(
                $request->room_id,
                $request->day_of_week,
                $request->start_time,
                $request->end_time,
                $excludeScheduleId,
                $request->start_date,
                $request->end_date
            );

            // تسجيل المعلومات للتصحيح
            Log::info('Availability check:', [
                'room_id' => $request->room_id,
                'day' => $request->day_of_week,
                'time' => $request->start_time.' - '.$request->end_time,
                'date' => $request->start_date.' - '.$request->end_date,
                'exclude_schedule_id' => $excludeScheduleId,
                'available' => $available,
            ]);

            return response()->json([
                'available' => $available,
                'message' => $available ? 'Room is available' : 'Room is not available at this time',
                'debug' => [
                    'room_id' => $request->room_id,
                    'day' => $request->day_of_week,
                    'time' => $request->start_time.' - '.$request->end_time,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Availability check error:', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Validation error: '.$e->getMessage(),
            ], 422);
        }
    }

    public function create()
    {
        // جلب فقط الجروبات النشطة (التي لم تنتهي بعد)
        $activeGroups = Group::where('end_date', '>=', now()->format('Y-m-d'))
            ->with(['course', 'teacher'])
            ->get();

        $rooms = Room::where('is_active', 1)->get();
        $days = [
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
        ];

        return view('schedules.create', compact('activeGroups', 'rooms', 'days'));
    }

    // إضافة دالة جديدة لجلب بيانات الجروب
    public function getGroupData($groupId)
    {
        $group = Group::with(['course', 'teacher'])->find($groupId);

        if (! $group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        return response()->json([
            'start_date' => $group->start_date,
            'end_date' => $group->end_date,
            'course_name' => $group->course->course_name,
            'teacher_name' => $group->teacher->teacher_name,
        ]);
    }

    /**
     * Store new schedule
     */
    // في ScheduleController - تأكد من ال validation
    // في ScheduleController - تحديث دالة store
    // في ScheduleController - تحديث دالة store
    public function store(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,group_id',
            'room_id' => 'required|exists:rooms,room_id',
            'day_of_week' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'start_date' => 'required|date', // تأكد من إضافتهم في الفاليديشن
            'end_date' => 'required|date|after:start_date', // تأكد من إضافتهم في الفاليديشن
        ]);

        // التحقق من أن الجروب لا يزال نشطاً
        $group = Group::find($request->group_id);
        if (! $group || $group->end_date < now()->format('Y-m-d')) {
            return back()->withErrors(['group_id' => 'Cannot create schedule for expired group'])->withInput();
        }

        // Check room availability مع التواريخ
        if (! $this->checkRoomAvailability(
            $request->room_id,
            $request->day_of_week,
            $request->start_time,
            $request->end_time,
            null, // excludeScheduleId
            $request->start_date, // تمرير start_date
            $request->end_date    // تمرير end_date
        )) {
            return back()->withErrors(['room_id' => 'Room is not available at this time and date range'])->withInput();
        }

        try {
            Schedule::create([
                'group_id' => $request->group_id,
                'room_id' => $request->room_id,
                'day_of_week' => $request->day_of_week,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'start_date' => $request->start_date, // تأكد من حفظهم
                'end_date' => $request->end_date,     // تأكد من حفظهم
                'is_active' => 1,
            ]);

            return redirect()->route('schedules.index')->with('success', 'Schedule created successfully');
        } catch (\Exception $e) {
            Log::error('Error saving schedule:', ['error' => $e->getMessage(), 'request' => $request->all()]);

            return back()->withErrors(['error' => 'Error saving schedule: '.$e->getMessage()])->withInput();
        }
    }

    public function edit(Schedule $schedule)
    {
        $groups = Group::with(['course', 'teacher'])->get();
        $rooms = Room::where('is_active', 1)->get();
        $days = [
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
        ];

        // الحصول على التواريخ من الـ group إذا كانت فارغة في الـ schedule
        $defaultStartDate = $schedule->start_date ?? $schedule->group->start_date;
        $defaultEndDate = $schedule->end_date ?? $schedule->group->end_date;

        return view('schedules.edit', compact(
            'schedule',
            'groups',
            'rooms',
            'days',
            'defaultStartDate',
            'defaultEndDate'
        ));
    }

    /**
     * Update schedule
     */
    public function update(Request $request, Schedule $schedule)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,group_id',
            'room_id' => 'required|exists:rooms,room_id',
            'day_of_week' => 'required|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'start_date' => 'required|date', // جديد
            'end_date' => 'required|date|after:start_date', // جديد
        ]);

        // Check room availability (excluding current schedule) مع التاريخ
        if (! $this->checkRoomAvailability(
            $request->room_id,
            $request->day_of_week,
            $request->start_time,
            $request->end_time,
            $schedule->schedule_id,
            $request->start_date,
            $request->end_date
        )) {
            return back()->withErrors(['room_id' => 'Room is not available at this time and date range'])->withInput();
        }

        try {
            $schedule->update([
                'group_id' => $request->group_id,
                'room_id' => $request->room_id,
                'day_of_week' => $request->day_of_week,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'start_date' => $request->start_date, // جديد
                'end_date' => $request->end_date,     // جديد
            ]);

            return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error updating schedule: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * Update schedule
     */

    /**
     * Toggle schedule status
     */
    public function toggleStatus(Schedule $schedule)
    {
        try {
            $schedule->update([
                'is_active' => ! $schedule->is_active,
            ]);

            $status = $schedule->is_active ? 'activated' : 'deactivated';

            return redirect()->route('schedules.index')->with('success', "Schedule {$status} successfully");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error updating schedule']);
        }
    }

    /**
     * Delete schedule
     */
    public function destroy(Schedule $schedule)
    {
        try {
            $schedule->delete();

            return redirect()->route('schedules.index')->with('success', 'Schedule deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error deleting schedule']);
        }
    }

    /**
     * Check room availability
     */
    // في ScheduleController - تحديث دالة checkRoomAvailability
    public function printWeekly(Request $request)
    {
        $selectedRoom = $request->get('room_id');

        $days = [
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
        ];

        $timeSlots = $this->generateTimeSlots('08:00', '22:00', 60);

        $schedules = Schedule::with(['group.teacher', 'group.course', 'room'])
            ->where('is_active', 1)
            ->when($selectedRoom, function ($query) use ($selectedRoom) {
                return $query->where('room_id', $selectedRoom);
            })
            ->get();

        $rooms = Room::where('is_active', 1)->get();

        $colors = [];
        foreach ($schedules as $schedule) {
            $colors[$schedule->group->course_id] = $this->getColorForCourse($schedule->group->course_id);
        }

        return view('schedules.print.weekly', compact('days', 'timeSlots', 'schedules', 'rooms', 'selectedRoom', 'colors'));
    }

    /**
     * Print monthly calendar
     */
    public function printMonthly(Request $request)
    {
        $selectedRoom = $request->get('room_id');
        $month = $request->get('month', now()->format('Y-m'));

        $schedules = Schedule::with(['group.teacher', 'group.course', 'room'])
            ->where('is_active', 1)
            ->when($selectedRoom, function ($query) use ($selectedRoom) {
                return $query->where('room_id', $selectedRoom);
            })
            ->get()
            ->map(function ($schedule) {
                $timeData = $this->formatScheduleTime($schedule);
                $schedule->formatted_start = $timeData['start'];
                $schedule->formatted_end = $timeData['end'];
                $schedule->duration_hours = $timeData['duration'];

                return $schedule;
            });

        $rooms = Room::where('is_active', 1)->get();

        $colors = [];
        foreach ($schedules as $schedule) {
            $colors[$schedule->group->course_id] = $this->getColorForCourse($schedule->group->course_id);
        }

        $calendar = $this->generateMonthlyCalendar($month, $schedules);

        return view('schedules.print.monthly', compact('schedules', 'rooms', 'selectedRoom', 'month', 'calendar', 'colors'));
    }

    /**
     * Check if time slot is within schedule
     */
    private function isTimeInSchedule($timeSlot, $schedule)
    {
        $slotTime = \Carbon\Carbon::parse($timeSlot);
        $scheduleStart = \Carbon\Carbon::parse($schedule->start_time);
        $scheduleEnd = \Carbon\Carbon::parse($schedule->end_time);

        return $slotTime->between($scheduleStart, $scheduleEnd->subMinute());
    }

    private function checkRoomAvailability($roomId, $dayOfWeek, $startTime, $endTime, $excludeScheduleId = null, $startDate = null, $endDate = null)
    {
        $query = Schedule::where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', 1)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    // الحالة 1: الوقت الجديد يبدأ خلال وقت موجود
                    $q2->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            });

        // إذا كانت هناك تواريخ محددة، أضف شرط التداخل الزمني
        if ($startDate && $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    // الحالة: التواريخ تتداخل
                    $q2->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
            });
        }

        if ($excludeScheduleId) {
            $query->where('schedule_id', '!=', $excludeScheduleId);
        }

        $conflictingSchedules = $query->get();

        // للتصحيح فقط - عرض الجداول المتضاربة
        if ($conflictingSchedules->count() > 0) {
            Log::info('Conflicting schedules found:', [
                'room_id' => $roomId,
                'day' => $dayOfWeek,
                'time' => $startTime.' - '.$endTime,
                'date' => ($startDate ?? 'N/A').' - '.($endDate ?? 'N/A'),
                'conflicts' => $conflictingSchedules->pluck('schedule_id'),
                'excluded' => $excludeScheduleId,
            ]);
        }

        return $conflictingSchedules->count() === 0;
    }

    private function generateTimeSlots($start, $end, $interval)
    {
        $slots = [];
        $current = strtotime($start);
        $end = strtotime($end);

        while ($current <= $end) {
            $slots[] = date('H:i', $current); // استخدم H:i بدل h:i A
            $current = strtotime("+{$interval} minutes", $current);
        }

        return $slots;
    }
}
