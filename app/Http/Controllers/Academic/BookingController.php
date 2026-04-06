<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Course;
use App\Models\SubCourse;
use App\Models\WaitingGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    // دالة لعرض فورم الحجز
    public function create()
    {
        $roleId = Auth::check() ? Auth::user()->role_id : null;
        $isAdmin = Auth::check() && Auth::user()->isAdmin();

        $waitingGroups = WaitingGroup::with(['course'])
            ->select('group_name', 'course_id')
            ->distinct()
            ->get()
            ->groupBy('group_name');

        $courses = Course::all();

        return view('bookings.create', [
            'roleId' => $roleId,
            'waitingGroups' => $waitingGroups,
            'courses' => $courses
        ]);
    }

    // دالة لعرض نموذج إضافة لمجموعة الانتظار
    public function showWaitingGroupForm($bookingId)
    {
        $booking = Booking::findOrFail($bookingId);

        $courses = Course::all();

        $existingGroups = WaitingGroup::with(['course', 'subcourse'])
            ->select('group_name', 'course_id', 'subcourse_id')
            ->selectRaw('COUNT(*) as students_count')
            ->groupBy('group_name', 'course_id', 'subcourse_id')
            ->having('students_count', '>', 0)
            ->orderBy('group_name')
            ->get();

        return view('bookings.add-to-waiting-group-form', [
            'booking' => $booking,
            'courses' => $courses,
            'existingGroups' => $existingGroups
        ]);
    }

    // دالة لإنشاء/إضافة لمجموعة الانتظار
    public function addToWaitingGroup(Request $request, $bookingId)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
            'group_name' => 'required|string|max:255',
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'assigned_level' => 'nullable|in:مبتدئ,متوسط,متقدم',
        ]);

        $booking = Booking::findOrFail($bookingId);
        $groupName = $request->group_name;

        try {
            DB::beginTransaction();

            $level = $request->assigned_level;
            if (! $level && $request->placement_exam_grade) {
                $level = $this->determineLevel($request->placement_exam_grade);
            } elseif (! $level) {
                $level = $this->determineLevel($booking->placement_exam_grade ?? 0);
            }

            // 1. Create or get user
            $user = \App\Models\User::firstOrCreate(
                ['email' => $booking->email],
                [
                    'name' => $booking->name,
                    'password' => \Illuminate\Support\Facades\Hash::make('student123'),
                    'role_id' => \App\Models\Role::STUDENT_ID, // student
                ]
            );

            if (! $user->profile) {
                \App\Models\Profile::create([
                    'user_id' => $user->id,
                    'phone_number' => $booking->phone,
                    'nickname' => $booking->name,
                ]);
            }

            // 2. Create or get student
            $student = \App\Models\Student::firstOrCreate(
                ['user_id' => $user->id],
                ['student_name' => $booking->name]
            );

            // 3. Update the booking to mark it as converted
            $booking->update(['student_id' => $student->student_id]);

            // 4. Find or Create the WaitingGroup
            $waitingGroup = WaitingGroup::firstOrCreate(
                [
                    'group_name' => $groupName,
                    'course_id' => $request->course_id,
                ],
                [
                    'subcourse_id' => $request->subcourse_id,
                    'status' => 'active',
                    'max_students' => 30, // default
                    'created_by' => Auth::id() ?? 1
                ]
            );

            // 5. Check if student already in this waiting group
            $existingWaitingStudent = \App\Models\WaitingStudent::where('waiting_group_id', $waitingGroup->id)
                ->where('student_id', $student->student_id)
                ->first();

            if ($existingWaitingStudent) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'هذا الطالب مضاف مسبقاً إلى هذه المجموعة!')
                    ->withInput();
            }

            // 6. Add student to the waiting list for this group
            \App\Models\WaitingStudent::create([
                'waiting_group_id' => $waitingGroup->id,
                'student_id' => $student->student_id,
                'user_id' => $user->id,
                'placement_exam_grade' => $request->placement_exam_grade ?? $booking->placement_exam_grade,
                'assigned_level' => $level,
                'status' => 'waiting',
                'joined_at' => now(),
                'added_by' => Auth::id() ?? 1,
            ]);

            DB::commit();

            return redirect()->route('bookings.index')
                ->with('success', 'تم إضافة الطالب إلى مجموعة الانتظار بنجاح!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    // دالة لنقل الطالب بين مجموعات الانتظار
    public function transferToWaitingGroup(Request $request, $bookingId)
    {
        $request->validate([
            'current_group_id' => 'required|exists:waiting_groups,id',
            'new_group_name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
            'transfer_notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $booking = Booking::findOrFail($bookingId);
            $student = $booking->student;

            if (!$student) {
                return redirect()->back()->with('error', 'يجب تحويل الحجز إلى طالب أولاً!');
            }

            // التحقق من أن الطالب ينتمي لهذه المجموعة
            $waitingStudent = \App\Models\WaitingStudent::where('waiting_group_id', $request->current_group_id)
                ->where('student_id', $student->student_id)
                ->first();

            if (!$waitingStudent) {
                return redirect()->back()
                    ->with('error', 'هذا الطالب لا ينتمي لهذه المجموعة!')
                    ->withInput();
            }

            // تحديد اسم المجموعة الجديدة
            $newGroupName = $request->new_group_name;

            // العثور على أو إنشاء المجموعة الجديدة
            $newGroup = WaitingGroup::firstOrCreate(
                [
                    'group_name' => $newGroupName,
                    'course_id' => $request->course_id,
                ],
                [
                    'subcourse_id' => $request->subcourse_id,
                    'status' => 'active',
                    'max_students' => 30, // default
                    'created_by' => Auth::id() ?? 1
                ]
            );

            // التحقق من عدم نقل الطالب لنفس المجموعة
            if ($newGroup->id == $request->current_group_id) {
                return redirect()->back()
                    ->with('error', 'الطالب موجود بالفعل في هذه المجموعة!')
                    ->withInput();
            }

            // التحقق من عدم وجود الطالب مسبقاً في المجموعة الجديدة
            $existingInNewGroup = \App\Models\WaitingStudent::where('waiting_group_id', $newGroup->id)
                ->where('student_id', $student->student_id)
                ->first();

            if ($existingInNewGroup) {
                return redirect()->back()
                    ->with('error', 'هذا الطالب مضاف مسبقاً إلى المجموعة الجديدة!')
                    ->withInput();
            }

            // تحديث بيانات المجموعة الحالية للطالب
            $waitingStudent->update([
                'waiting_group_id' => $newGroup->id,
                'notes' => $request->transfer_notes ?: $waitingStudent->notes,
            ]);

            DB::commit();

            return redirect()->route('bookings.edit', $booking->id)
                ->with('success', 'تم نقل الطالب إلى المجموعة الجديدة بنجاح!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    // دالة لعرض نموذج نقل المجموعة
    public function showTransferForm($bookingId, $groupId)
    {
        $booking = Booking::with('student.waitingStudents')->findOrFail($bookingId);
        $currentGroup = WaitingGroup::findOrFail($groupId);

        $student = $booking->student;

        // التحقق من أن المجموعة تابعة لهذا الطالب
        $belongsToStudent = false;
        if ($student) {
            $belongsToStudent = $student->waitingStudents->where('waiting_group_id', $groupId)->isNotEmpty();
        }

        if (!$belongsToStudent) {
            abort(403, 'غير مصرح بهذا الإجراء، الطالب لا ينتمي لهذه المجموعة');
        }

        $courses = Course::all();

        return view('bookings.transfer-group', [
            'booking' => $booking,
            'currentGroup' => $currentGroup,
            'courses' => $courses
        ]);
    }

    // دالة لعرض مجموعات الانتظار

    // دالة جديدة لجلب الصب كورسات حسب الكورس (للاستخدام مع AJAX)
    public function getSubcoursesByCourse($courseId)
    {
        $subcourses = SubCourse::where('course_id', $courseId)
            ->orderBy('subcourse_number')
            ->get(['subcourse_id', 'subcourse_name', 'subcourse_number']);

        return response()->json($subcourses);
    }

    // دالة لحفظ بيانات الحجز
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'age' => 'required|integer|min:1|max:120',
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'message' => 'nullable|string',
            'waiting_group_id' => 'nullable|exists:waiting_groups,id',
            'course_id' => 'nullable|exists:courses,course_id',
        ]);

        DB::beginTransaction();
        try {
            // Create the Booking
            $bookingData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'age' => $request->age,
                'date' => Carbon::now()->format('Y-m-d'),
                'time' => Carbon::now()->format('H:i'),
                'message' => $request->message,
                'placement_exam_grade' => $request->placement_exam_grade,
            ];

            $booking = Booking::create($bookingData);

            // If a group or course is specified, we convert to a student and add to waiting list
            if ($request->waiting_group_id || $request->course_id) {
                // Check if user/student exists or create new
                $user = \App\Models\User::firstOrCreate(
                    ['email' => $request->email],
                    [
                        'name' => $request->name,
                        'password' => \Illuminate\Support\Facades\Hash::make('student123'),
                        'role_id' => \App\Models\Role::STUDENT_ID, // student
                    ]
                );

                $student = \App\Models\Student::firstOrCreate(
                    ['user_id' => $user->id],
                    ['student_name' => $request->name]
                );

                if ($request->waiting_group_id) {
                    \App\Models\WaitingStudent::firstOrCreate(
                        [
                            'waiting_group_id' => $request->waiting_group_id,
                            'student_id' => $student->student_id,
                        ],
                        [
                            'user_id' => $user->id,
                            'status' => 'waiting',
                            'joined_at' => now(),
                            'added_by' => Auth::id(),
                        ]
                    );
                }
                
                $booking->update(['student_id' => $student->student_id]);
            }

            DB::commit();

            return redirect()->route('bookings.index')->with('success', 'Booking created successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Booking storage failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'An error occurred: '.$e->getMessage())
                ->withInput();
        }
    }

    // دالة لتحديد المستوى
    private function determineLevel($grade)
    {
        if (! $grade) {
            return 'غير محدد';
        }

        if ($grade >= 80) {
            return 'متقدم';
        }
        if ($grade >= 50) {
            return 'متوسط';
        }

        return 'مبتدئ';
    }

    // دالة لعرض جميع طلبات الحجز مع الفلترة
    public function index(Request $request)
    {
        $query = Booking::with('student.waitingStudents.waitingGroup')->latest();

        // Search by name, email, or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by waiting list status
        if ($request->filled('waiting_filter')) {
            if ($request->waiting_filter == 'in_groups') {
                $query->whereNotNull('student_id');
            } elseif ($request->waiting_filter == 'not_in_groups') {
                $query->whereNull('student_id');
            }
        }

        $bookings = $query->paginate(15)->withQueryString();

        // Summary Statistics
        $stats = [
            'total_bookings' => Booking::count(),
            'new_this_month' => Booking::whereMonth('created_at', now()->month)->count(),
            'in_waiting_groups' => Booking::whereNotNull('student_id')->count(),
            'pending_contact' => Booking::whereNull('student_id')->count(),
        ];

        return view('bookings.index', [
            'bookings' => $bookings,
            'stats' => $stats,
            'filters' => $request->only(['search', 'waiting_filter']),
        ]);
    }

    // دالة لعرض نموذج التعديل
    public function edit($id)
    {
        $booking = Booking::with('waitingGroups.course')->findOrFail($id);

        return view('bookings.edit', [
            'booking' => $booking
        ]);
    }

    // دالة لتحديث بيانات الحجز
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'age' => 'required|integer|min:1|max:120',
            'date' => 'required|date',
            'time' => 'required',
            'message' => 'nullable|string',
        ]);

        // إذا كان المستخدم ادمن، أضف التحقق من حقل التقييم
        if (Auth::check() && Auth::user()->isAdmin()) {
            $request->validate([
                'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            ]);
        }

        $booking = Booking::findOrFail($id);

        // بيانات التحديث
        $updateData = $request->only([
            'name', 'email', 'phone', 'age', 'date', 'time', 'message',
        ]);

        // إذا كان المستخدم ادمن، أضف حقل التقييم
        if (Auth::check() && Auth::user()->isAdmin()) {
            $updateData['placement_exam_grade'] = $request->placement_exam_grade;
        }

        $booking->update($updateData);

        return redirect()->route('bookings.index')
            ->with('success', 'تم تحديث الحجز بنجاح!');
    }

    // دالة لحذف الحجز
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return redirect()->route('bookings.index')
            ->with('success', 'تم حذف الحجز بنجاح!');
    }

    // دالة لتحديث درجة امتحان تحديد المستوي (للادمن فقط)
    public function updatePlacementGrade(Request $request, $id)
    {
        // تحقق إذا كان المستخدم ادمن
        if (! Auth::check() || ! Auth::user()->isAdmin()) {
            return redirect()->back()->with('error', 'ليس لديك صلاحية لتعديل درجات التقييم');
        }

        $request->validate([
            'placement_exam_grade' => 'required|numeric|min:0|max:100',
        ]);

        $booking = Booking::findOrFail($id);
        $booking->update([
            'placement_exam_grade' => $request->placement_exam_grade,
        ]);

        return redirect()->back()->with('success', 'تم تحديث درجة امتحان تحديد المستوي بنجاح');
    }

    public function removeFromWaitingGroup($id)
    {
        try {
            $waitingGroup = WaitingGroup::findOrFail($id);
            $waitingGroup->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الطالب من المجموعة بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    public function transferWaitingGroup(Request $request, $id)
    {
        $request->validate([
            'group_type' => 'required|in:existing,new',
            'existing_group_name' => 'nullable|string|max:255',
            'new_group_name' => 'nullable|string|max:255',
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
        ]);

        try {
            // التحقق من أن الحقول المطلوبة موجودة بناءً على نوع المجموعة
            if ($request->group_type == 'existing' && empty($request->existing_group_name)) {
                return redirect()->back()
                    ->with('error', 'يرجى اختيار مجموعة من القائمة')
                    ->withInput();
            }

            if ($request->group_type == 'new' && empty($request->new_group_name)) {
                return redirect()->back()
                    ->with('error', 'يرجى إدخال اسم المجموعة الجديدة')
                    ->withInput();
            }

            $waitingGroup = WaitingGroup::findOrFail($id);

            // تحديد اسم المجموعة الجديدة بناءً على النوع
            $newGroupName = $request->group_type == 'existing'
                ? $request->existing_group_name
                : $request->new_group_name;

            // تحقق إذا كان النقل لنفس المجموعة
            if ($waitingGroup->group_name == $newGroupName &&
                $waitingGroup->course_id == $request->course_id) {
                return redirect()->back()
                    ->with('error', 'الطالب موجود بالفعل في هذه المجموعة!')
                    ->withInput();
            }

            // تحديث بيانات المجموعة
            $waitingGroup->update([
                'group_name' => $newGroupName,
                'course_id' => $request->course_id,
                'subcourse_id' => $request->subcourse_id,
                'updated_at' => now(),
            ]);

            $message = $request->group_type == 'new'
                ? 'تم نقل الطالب وإنشاء مجموعة جديدة بنجاح!'
                : 'تم نقل الطالب إلى المجموعة المختارة بنجاح!';

            return redirect()->route('bookings.edit', $waitingGroup->booking_id)
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    // دالة لنقل طالب من مجموعة انتظار إلى مجموعة أخرى
    public function moveStudentToGroup(Request $request, $studentId)
    {
        $request->validate([
            'new_group_id' => 'required|exists:groups,group_id',
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
        ]);

        try {
            $waitingStudent = WaitingGroup::findOrFail($studentId);

            // إنشاء سجل جديد في الجروب الفعلي
            $group = \App\Models\Group::find($request->new_group_id);

            if (! $group) {
                return redirect()->back()
                    ->with('error', 'المجموعة المطلوبة غير موجودة!');
            }

            // إضافة الطالب للمجموعة
            /** @var \App\Models\Group $group */
            $group->students()->attach($waitingStudent->booking_id, [
                'enrollment_date' => now(),
                'status' => 'active',
            ]);

            // حذف الطالب من قائمة الانتظار
            $waitingStudent->delete();

            return redirect()->back()
                ->with('success', 'تم نقل الطالب إلى المجموعة بنجاح!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // دالة لإزالة طالب من مجموعة الانتظار
    public function removeStudentFromGroup($studentId)
    {
        try {
            $waitingStudent = WaitingGroup::findOrFail($studentId);
            $waitingStudent->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الطالب من المجموعة بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    // دالة لتعديل بيانات الطالب في مجموعة الانتظار
    public function editWaitingStudent(Request $request, $studentId)
    {
        $request->validate([
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'assigned_level' => 'nullable|string|in:مبتدئ,متوسط,متقدم',
            'notes' => 'nullable|string',
        ]);

        try {
            $waitingStudent = WaitingGroup::findOrFail($studentId);

            $updateData = [];
            if ($request->has('placement_exam_grade')) {
                $updateData['placement_exam_grade'] = $request->placement_exam_grade;
            }
            if ($request->has('assigned_level')) {
                $updateData['assigned_level'] = $request->assigned_level;
            }

            $waitingStudent->update($updateData);

            return redirect()->back()
                ->with('success', 'تم تحديث بيانات الطالب بنجاح!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // دالة لعرض نموذج إضافة طالب جديد لمجموعة الانتظار
    public function addStudentToWaitingGroupForm()
    {
        $courses = Course::all();
        $bookings = Booking::whereDoesntHave('waitingGroups')->get();

        return view('bookings.add-to-waiting-group', [
            'courses' => $courses,
            'bookings' => $bookings
        ]);
    }

    // دالة لإضافة طالب جديد لمجموعة الانتظار
    public function addNewStudentToWaitingGroup(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'group_name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
        ]);

        try {
            $booking = Booking::findOrFail($request->booking_id);

            // تحقق من عدم تكرار الطالب في نفس المجموعة
            $existing = WaitingGroup::where('booking_id', $booking->id)
                ->where('course_id', $request->course_id)
                ->where('group_name', $request->group_name)
                ->first();

            if ($existing) {
                return redirect()->back()
                    ->with('error', 'هذا الطالب مضاف مسبقاً إلى هذه المجموعة!');
            }

            WaitingGroup::create([
                'group_name' => $request->group_name,
                'course_id' => $request->course_id,
                'subcourse_id' => $request->subcourse_id,
                'booking_id' => $booking->id,
                'placement_exam_grade' => $booking->placement_exam_grade,
                'assigned_level' => $this->determineLevel($booking->placement_exam_grade),
                'is_active' => true,
            ]);

            return redirect()->route('waiting-groups.index')
                ->with('success', 'تم إضافة الطالب إلى مجموعة الانتظار بنجاح!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // دالة للبحث في طلبات الحجز
    public function search(Request $request)
    {
        $query = Booking::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $bookings = $query->latest()->get();

        return view('bookings.index', [
            'bookings' => $bookings,
            'search' => $request->search
        ]);
    }
    // دالة لعرض نموذج تعديل مجموعة الانتظار

    public function editWaitingGroup($id)
    {
        // البحث عن المجموعة باستخدام ID
        $waitingGroup = WaitingGroup::with(['booking', 'course', 'subcourse'])->find($id);

        if (! $waitingGroup) {
            return redirect()->route('waiting-groups.index')
                ->with('error', 'المجموعة غير موجودة');
        }

        // الحصول على اسم المجموعة من السجل
        $groupName = $waitingGroup->group_name;
        $courseId = $waitingGroup->course_id;

        // الحصول على جميع الطلاب في نفس المجموعة (بنفس الاسم ونفس الكورس)
        $students = WaitingGroup::with(['booking', 'course', 'subcourse'])
            ->where('group_name', $groupName)
            ->where('course_id', $courseId)
            ->get();

        $course = Course::find($courseId);
        $courses = Course::all();

        // الحصول على جميع الطلاب غير المضافين لهذه المجموعة
        $allStudents = Booking::whereDoesntHave('waitingGroups', function ($query) use ($groupName, $courseId) {
            $query->where('group_name', $groupName)
                ->where('course_id', $courseId);
        })->get();

        // الحصول على ID لأول سجل في المجموعة (سيتم استخدامه كمعرف للمجموعة)
        $groupId = $id;

        return view('waiting-groups.edit', [
            'groupId' => $groupId,
            'groupName' => $groupName,
            'waitingGroup' => $waitingGroup,
            'students' => $students,
            'course' => $course,
            'courses' => $courses,
            'allStudents' => $allStudents
        ]);
    }

    // دالة لتحديث مجموعة الانتظار
    public function updateWaitingGroup(Request $request, $id)
    {
        $request->validate([
            'new_group_name' => 'nullable|string|max:255',
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
        ]);

        try {
            // العثور على المجموعة باستخدام ID
            $waitingGroup = WaitingGroup::findOrFail($id);
            $oldGroupName = $waitingGroup->group_name;
            $oldCourseId = $waitingGroup->course_id;

            // تحديث اسم المجموعة إذا تم تغييره
            if ($request->filled('new_group_name') && $request->new_group_name != $oldGroupName) {
                // تحديث جميع الطلاب في نفس المجموعة
                WaitingGroup::where('group_name', $oldGroupName)
                    ->where('course_id', $oldCourseId)
                    ->update(['group_name' => $request->new_group_name]);

                $newGroupName = $request->new_group_name;
            } else {
                $newGroupName = $oldGroupName;
            }

            // تحديث الكورس والصب كورس لجميع الطلاب في المجموعة
            WaitingGroup::where('group_name', $newGroupName)
                ->where('course_id', $oldCourseId)
                ->update([
                    'course_id' => $request->course_id,
                    'subcourse_id' => $request->subcourse_id,
                ]);

            return redirect()->route('waiting-groups.edit', $id)
                ->with('success', 'تم تحديث المجموعة بنجاح!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // دالة لإزالة طالب من مجموعة الانتظار
    public function removeStudentFromWaitingGroup($groupId, $studentId)
    {
        try {
            // العثور على المجموعة باستخدام ID
            $mainGroup = WaitingGroup::findOrFail($groupId);
            $groupName = $mainGroup->group_name;
            $courseId = $mainGroup->course_id;

            // العثور على سجل الطالب في المجموعة
            $waitingGroup = WaitingGroup::where('group_name', $groupName)
                ->where('course_id', $courseId)
                ->where('booking_id', $studentId)
                ->firstOrFail();

            $waitingGroup->delete();

            return redirect()->back()
                ->with('success', 'تم إزالة الطالب من المجموعة بنجاح');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // دالة لإضافة طالب لمجموعة الانتظار
    public function addStudentToWaitingGroup(Request $request, $id)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'assigned_level' => 'nullable|string|in:مبتدئ,متوسط,متقدم',
        ]);

        try {
            // الحصول على بيانات المجموعة الرئيسية
            $mainGroup = WaitingGroup::findOrFail($id);
            $groupName = $mainGroup->group_name;
            $courseId = $mainGroup->course_id;
            $subcourseId = $mainGroup->subcourse_id;

            if (! $mainGroup) {
                return redirect()->back()
                    ->with('error', 'المجموعة غير موجودة');
            }

            // التحقق من أن الطالب غير مضاف مسبقاً
            $existingStudent = WaitingGroup::where('group_name', $groupName)
                ->where('course_id', $courseId)
                ->where('booking_id', $request->booking_id)
                ->first();

            if ($existingStudent) {
                return redirect()->back()
                    ->with('error', 'هذا الطالب مضاف مسبقاً للمجموعة');
            }

            // إضافة الطالب للمجموعة
            WaitingGroup::create([
                'group_name' => $groupName,
                'course_id' => $courseId,
                'subcourse_id' => $subcourseId,
                'booking_id' => $request->booking_id,
                'placement_exam_grade' => $request->placement_exam_grade,
                'assigned_level' => $request->assigned_level ?? $this->determineLevel($request->placement_exam_grade),
                'is_active' => true,
            ]);

            return redirect()->back()
                ->with('success', 'تم إضافة الطالب للمجموعة بنجاح');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    // في دالة showWaitingGroups
    // دالة لعرض جميع مجموعات الانتظار
    public function showWaitingGroups(Request $request)
    {
        // جلب جميع طلاب مجموعات الانتظار مع علاقاتهم
        $waitingStudents = WaitingGroup::with(['booking', 'subcourse'])
            ->orderBy('course_id')
            ->orderBy('group_name')
            ->orderBy('created_at')
            ->get();

        // تجميع البيانات حسب الكورس ثم حسب اسم المجموعة
        $waitingGroups = $waitingStudents->groupBy('course_id')
            ->map(function ($courseGroups) {
                return $courseGroups->groupBy('group_name');
            });

        // جلب جميع الكورسات
        $courses = Course::all();

        // إحصائيات
        $totalStudents = $waitingStudents->count();
        $totalGroups = $waitingStudents->unique('group_name')->count();
        $studentsWithoutGrade = $waitingStudents->whereNull('placement_exam_grade')->count();

        // إنشاء مصفوفة group IDs آمنة للاستخدام في الـ DOM
        $safeGroupIds = [];
        foreach ($waitingGroups as $courseId => $groups) {
            foreach ($groups as $groupName => $students) {
                $safeGroupIds[$groupName] = 'group_'.md5($groupName.$courseId);
            }
        }

        return view('waiting-groups.index', [
            'waitingGroups' => $waitingGroups,
            'courses' => $courses,
            'totalStudents' => $totalStudents,
            'totalGroups' => $totalGroups,
            'studentsWithoutGrade' => $studentsWithoutGrade,
            'safeGroupIds' => $safeGroupIds
        ]);
    }

    private function createSafeGroupName($groupName)
    {
        // استبدال جميع الأحخاص غير الآمنة
        $safeName = preg_replace('/[^a-zA-Z0-9_\x{0600}-\x{06FF}\s]/u', '', $groupName);
        $safeName = preg_replace('/\s+/', '_', $safeName); // استبدال المسافات بـ _

        return 'group_'.md5($safeName); // إضافة بادئة وتشفير
    }

    private function getOriginalGroupName($safeName)
    {
        // هذه دالة مساعدة لتحويل الاسم الآمن إلى الاسم الأصلي
        // يمكنك تعديلها حسب طريقة إنشاء safeGroupName الخاصة بك

        // إذا كان الاسم يبدأ بـ 'group_' ثم md5، فلن نستطيع استعادته
        // لذا سنخزن الاسم الأصلي في قاعدة بيانات منفصلة أو في متغير

        // بدلاً من ذلك، يمكننا تخزين التعيين في جلسة المستخدم
        $mapping = session('group_name_mapping', []);

        if (isset($mapping[$safeName])) {
            return $mapping[$safeName];
        }

        // محاولة العثور على أقرب تطابق
        $groups = WaitingGroup::select('group_name')
            ->distinct()
            ->get()
            ->pluck('group_name')
            ->toArray();

        foreach ($groups as $group) {
            $calculatedSafe = $this->createSafeGroupName($group);
            if ($calculatedSafe === $safeName) {
                $mapping[$safeName] = $group;
                session(['group_name_mapping' => $mapping]);

                return $group;
            }
        }

        return $safeName; // ارجع الاسم كما هو إذا لم نجد تطابقاً
    }

    public function deleteWaitingGroup($id)
    {
        try {
            // البحث عن المجموعة الرئيسية
            $mainGroup = WaitingGroup::findOrFail($id);
            $groupName = $mainGroup->group_name;
            $courseId = $mainGroup->course_id;

            // حذف جميع طلاب المجموعة
            $deletedCount = WaitingGroup::where('group_name', $groupName)
                ->where('course_id', $courseId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "تم حذف المجموعة '{$groupName}' بنجاح ($deletedCount طالب)",
                'deleted_count' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting waiting group: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }
}
