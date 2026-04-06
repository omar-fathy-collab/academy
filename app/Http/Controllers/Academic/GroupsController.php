<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\DeletedInvoiceArchive;
use App\Models\DeletedInvoiceLog;
use App\Models\DeletedPaymentArchive;
use App\Models\DeletedPaymentLog;
use App\Models\Group;
use App\Models\GroupChangeLog;
use App\Models\GroupEnrollmentRequest;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Salary;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\StudentTransfer;
use App\Models\Subcourse;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Room;
use App\Models\WaitingGroup;
use App\Services\SalaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroupsController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        if (! $user || (! $user->isAdmin() && ! $user->isTeacher() && ! $user->isStudent())) {
            abort(403, 'Unauthorized');
        }

        $isStudent = $user->isStudent();


        // Get status filter from request
        $statusFilter = $request->get('status', '');
        $search = $request->get('search', '');

        // Start query with eager loading
        $query = Group::select([
            'group_id',
            'uuid',
            'group_name',
            'course_id',
            'subcourse_id',
            'teacher_id',
            'schedule',
            'price',
            'teacher_percentage',
            'start_date',
            'end_date',
        ])
            ->with([
                'course:course_id,course_name',
                'subcourse:subcourse_id,subcourse_name,subcourse_number',
                'teacher:teacher_id,teacher_name',
                'students:student_id,student_name',
            ]);

        // Filter by visibility for students
        if ($isStudent) {
            $query->where('is_public', true);
        }

        // Apply status filter if provided
        if (! empty($statusFilter)) {
            $today = Carbon::today();

            switch ($statusFilter) {
                case 'Not Started':
                    $query->whereDate('start_date', '>', $today);
                    break;

                case 'In Progress':
                    $query->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today)
                        ->whereRaw('DATEDIFF(end_date, ?) > 7', [$today->toDateString()]);
                    break;

                case 'Almost Done':
                    $query->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today)
                        ->whereRaw('DATEDIFF(end_date, ?) <= 7', [$today->toDateString()])
                        ->whereRaw('DATEDIFF(end_date, ?) >= 0', [$today->toDateString()]);
                    break;

                case 'Completed':
                    $query->whereDate('end_date', '<', $today);
                    break;
            }
        }

        // Apply search if provided
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('group_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('schedule', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('course', function ($q) use ($search) {
                        $q->where('course_name', 'LIKE', '%'.$search.'%');
                    })
                    ->orWhereHas('teacher', function ($q) use ($search) {
                        $q->where('teacher_name', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        $groups = $query->orderBy('group_id', 'DESC')
            ->paginate(15);

        $groups->getCollection()->transform(function ($group) use ($user, $isStudent) {
            $group->is_member = false;
            $group->enrollment_status = null; // null, pending, approved, rejected

            if ($isStudent && $user->student) {
                // Check if already a member
                $group->is_member = $group->students->contains('student_id', $user->student->student_id);

                // Check for pending/approved requests
                $request = GroupEnrollmentRequest::where('user_id', $user->id)
                    ->where('group_id', $group->group_id)
                    ->first();
                
                if ($request) {
                    $group->enrollment_status = $request->status;
                }
            }
            return $group;
        });

        $courses = Course::orderBy('course_name')->get(['course_id', 'course_name', 'uuid']);
        $teachers = Teacher::orderBy('teacher_name')->get(['teacher_id', 'teacher_name', 'salary_percentage', 'uuid']);
        $students = $user->role_id == 1 ? Student::with('user:id,username,uuid')->orderBy('student_name')->get(['student_id', 'student_name', 'user_id', 'uuid']) : [];

        // Pass filter value to view
        return view('groups.index', [
            'groups' => $groups,
            'courses' => $courses,
            'teachers' => $teachers,
            'students' => $students,
            'statusFilter' => $statusFilter,
            'is_admin' => $user->isAdmin(),
            'is_student' => $isStudent

        ]);
    }

    public function create()
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        // جلب الطلاب مع بيانات الدفع باستخدام دالة منفصلة
        $students = $this->getStudentsWithPaymentInfo();

        $courses = Course::orderBy('course_name')->get();
        $teachers = Teacher::orderBy('teacher_name')->get();
        $rooms = Room::where('is_active', 1)->get();

        $group = null;
        $isUpgrade = false;

        // التحقق إذا كان هناك طلب ترقية
        if (request()->has('upgrade_from')) {
            $upgradeGroupId = request()->get('upgrade_from');
            $group = Group::with(['students', 'course', 'subcourse', 'teacher'])
                ->find($upgradeGroupId);

            $isUpgrade = true;

            if (! $group) {
                return redirect()->route('groups.index')
                    ->with('error', 'Group not found for upgrade!');
            }
        }

        return view('groups.create', [
            'courses' => $courses,
            'students' => $students,
            'teachers' => $teachers,
            'rooms' => $rooms,
            'group' => $group,
            'isUpgrade' => $isUpgrade
        ]);
    }

    private function getGroupStatus($startDate, $endDate)
    {
        $today = Carbon::today();
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($today->lt($start)) {
            return 'Not Started';
        } elseif ($today->gt($end)) {
            return 'Completed';
        } else {
            $daysRemaining = $today->diffInDays($end, false);

            if ($daysRemaining <= 7) {
                return 'Almost Done';
            } else {
                return 'In Progress';
            }
        }
    }

    private function getStudentsWithPaymentInfo()
    {
        try {
            $students = Student::with(['user', 'invoices'])
                ->orderBy('student_name')
                ->get();

            $students = $students->map(function ($student) {
                // حساب القيم مع الأخذ في الاعتبار الخصم
                $totalRequired = $student->invoices->sum('amount');
                $totalDiscount = $student->invoices->sum('discount_amount');
                $totalPaid = $student->invoices->sum('amount_paid');

                // المبلغ المطلوب بعد الخصم
                $totalRequiredAfterDiscount = $totalRequired - $totalDiscount;

                $student->total_paid = $totalPaid;
                $student->total_required = $totalRequired;
                $student->total_discount = $totalDiscount;
                $student->total_required_after_discount = $totalRequiredAfterDiscount;
                $student->balance = max(0, $totalRequiredAfterDiscount - $totalPaid); // التأكد من عدم وجود قيم سالبة

                if ($student->invoices->isEmpty()) {
                    $student->payment_status = 'new_student';
                    $student->has_unpaid_invoices = false;
                } else {
                    $student->has_unpaid_invoices = $student->balance > 0;

                    // ✅ **التصحيح هنا**: تحديد حالة الدفع بناءً على الرصيد بعد الخصم
                    if ($student->balance <= 0) {
                        $student->payment_status = 'paid'; // إذا المتبقي صفر أو أقل = مدفوع
                    } elseif ($totalPaid == 0) {
                        $student->payment_status = 'unpaid';
                    } else {
                        $student->payment_status = 'partial';
                    }
                }

                return $student;
            });

            return $students;

        } catch (\Exception $e) {
            Log::error('Error getting students with payment info: ' . $e->getMessage());

            return collect();
        }
    }

    public function store(Request $request)
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        // Validation rules
        $request->validate([
            'group_name' => 'required|string|max:255',
            'is_online' => 'nullable|boolean',
            'course_id' => 'required|exists:courses,course_id',
            'teacher_id' => 'required|exists:teachers,teacher_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'price' => 'required|numeric|min:0',
            'is_free' => 'nullable|boolean',
            'teacher_percentage' => 'required|numeric|min:0|max:100',
            'room_id' => 'required_unless:is_online,1|nullable|exists:rooms,room_id',
            'day_of_week' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'students' => 'array',
            'students.*' => 'exists:students,student_id',
        ]);

        DB::beginTransaction();
        try {
            // 1. التحقق من توافر الغرفة قبل إنشاء الـ Group
            $roomAvailable = $this->checkRoomAvailability(
                $request->room_id,
                $request->day_of_week,
                $request->start_time,
                $request->end_time,
                null,
                $request->start_date,
                $request->end_date
            );

            if (! $roomAvailable) {
                throw new \Exception('Room is not available at this time and date range');
            }

            // 2. إعداد قائمة الطلاب
            $validStudentIds = [];
            if ($request->students !== null && is_array($request->students)) {
                $validStudentIds = $request->students;
            }

            // 3. إنشاء الـ Group
            $group = Group::create([
                'group_name' => $request->group_name,
                'is_online' => $request->boolean('is_online', false),
                'is_free' => $request->boolean('is_free', false),
                'is_public' => $request->boolean('is_public', true),
                'course_id' => $request->course_id,
                'subcourse_id' => $request->subcourse_id,
                'teacher_id' => $request->teacher_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'schedule' => $request->schedule,
                'price' => $request->price,
                'teacher_percentage' => $request->teacher_percentage,
            ]);

            Log::info('Group created successfully', [
                'group_id' => $group->group_id,
                'group_name' => $group->group_name,
                'teacher_id' => $group->teacher_id,
            ]);

            // 4. إنشاء Schedule للـ Group
            $this->createGroupSchedule($group, $request);

            // 5. إذا كان ترقية من جروب قديم
            $studentIds = [];
            if ($request->has('upgrade_from')) {
                $oldGroupId = $request->upgrade_from;
                $oldGroup = Group::find($oldGroupId);

                if ($oldGroup) {
                    $studentIds = $oldGroup->students->pluck('student_id')->toArray();

                    if (! empty($studentIds)) {
                        $group->students()->sync($studentIds);
                        $this->createInvoicesForStudents($group, $studentIds);
                    }

                    $oldGroup->update(['status' => 'upgraded']);

                    Log::info('Group upgraded successfully', [
                        'old_group_id' => $oldGroupId,
                        'new_group_id' => $group->group_id,
                        'students_transferred' => count($studentIds),
                    ]);
                }
            }

            // 6. إرفاق الطلاب إذا تم اختيارهم (للجروبات الجديدة)
            if ($request->students !== null && is_array($request->students) && ! $request->has('upgrade_from')) {
                $studentIds = $validStudentIds;
                $group->students()->sync($studentIds);
                $this->createInvoicesForStudents($group, $studentIds);
            }

            // 7. إنشاء راتب للمدرس
            if (! empty($studentIds)) {
                $this->createStrictSingleSalary($group);
            } else {
                $this->createStrictSingleSalary($group);
            }
            // في دالة store()
            if ($request->has('waiting_group_id')) {
                try {
                    $waitingGroup = WaitingGroup::find($request->waiting_group_id);

                    if ($waitingGroup) {
                        // تحديث مجموعة الانتظار فقط (بدون أخطاء)
                        $waitingGroup->update([
                            'is_active' => 0,
                            'converted_to_group_id' => $group->group_id,
                        ]);

                        // تحديث حالة الطلاب باستخدام DB facade مباشرة (لضمان بناء SQL صحيح)
                        DB::table('waiting_students')
                            ->where('waiting_group_id', $waitingGroup->id)
                            ->whereIn('student_id', $request->students ?? [])
                            ->update([
                                'status' => DB::raw("'converted'"), // استخدم DB::raw للتأكد من الاقتباس
                                'converted_at' => now(),
                                'converted_to_group_id' => $group->group_id,
                            ]);

                        Log::info("تم تحويل مجموعة الانتظار {$waitingGroup->group_name} إلى المجموعة {$group->group_name}");
                    }
                } catch (\Exception $e) {
                    Log::warning('Warning in waiting group conversion: ' . $e->getMessage());
                    // استمر في العملية حتى لو فشل هذا الجزء
                }
            }
            DB::commit();

            $message = $request->has('upgrade_from') ?
                'Group upgraded successfully with schedule!' :
                'Group added successfully with schedule!';

            return redirect()->route('groups.index')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating group: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Error creating group: '.$e->getMessage())
                ->withInput();
        }
    }
    /**
     * التحقق من توافر الغرفة
     */

    /**
     * Create or update salary record using SalaryService.
     */
    private function createStrictSingleSalary(Group $group)
    {
        return app(SalaryService::class)->syncSalaryForGroup($group);
    }

    /**
     * @deprecated Use SalaryService instead
     */
    private function updateExistingSalary(Salary $salary, Group $group)
    {
        return app(SalaryService::class)->syncSalaryForGroup($group, $salary->month);
    }

    /**
     * إنشاء راتب لشهر البدء فقط - بدون أي شهور أخرى
     */
    /**
     * @deprecated Use SalaryService instead
     */
    private function createFirstMonthSalaryOnly(Group $group)
    {
        return app(SalaryService::class)->syncSalaryForGroup($group);
    }

    /**
     * تحديث الراتب الموجود - للشهر الأول فقط
     */
    public function createSalaryForGroup(Request $request)
    {
        if (auth()->user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'teacher_id' => 'required|integer',
            'group_id' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $teacher = Teacher::findOrFail($request->teacher_id);
            $group = Group::with('students')->findOrFail($request->group_id);

            // التحقق أولاً إذا كان فيه رواتب موجودة لهذا الجروب
            $existingSalaries = Salary::where('group_id', $group->group_id)->get();

            if ($existingSalaries->count() > 0) {
                DB::rollback();

                return response()->json([
                    'success' => false,
                    'message' => '⚠️ يوجد رواتب مسجلة مسبقاً لهذا الجروب!',
                    'existing_salaries_count' => $existingSalaries->count(),
                    'existing_salaries' => $existingSalaries->map(function ($salary) {
                        return [
                            'salary_id' => $salary->salary_id,
                            'month' => $salary->month,
                            'teacher_share' => $salary->teacher_share,
                            'status' => $salary->status,
                            'created_at' => $salary->created_at->format('Y-m-d H:i:s'),
                        ];
                    }),
                ], 422);
            }

            // Get student IDs for this group
            $studentIds = $group->students->pluck('student_id')->toArray();

            // Use your existing method to create the salary
            $salary = $this->createStrictSingleSalary($group);

            // Get the final count after creation
            $finalSalaryCount = Salary::where('group_id', $group->group_id)->count();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '✅ تم إنشاء الراتب بنجاح!',
                'salary' => [
                    'salary_id' => $salary->salary_id,
                    'teacher_share' => $salary->teacher_share,
                    'student_count' => count($studentIds),
                    'total_salaries_for_group' => $finalSalaryCount,
                    'created_at' => $salary->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create salary for group: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ Error creating salary: '.$e->getMessage(),
            ], 500);
        }
    }

    private function updateExistingTeacherSalary(Group $group, array $studentIds = [])
    {
        try {
            // 1. تنظيف أي رواتب مكررة أولاً
            $existingSalaries = Salary::where('group_id', $group->group_id)->get();

            if ($existingSalaries->count() > 1) {
                Log::warning('Cleaning duplicate salaries during update', [
                    'group_id' => $group->group_id,
                    'count' => $existingSalaries->count(),
                ]);

                // احتفظ بأحدث راتب واحذف الباقي
                $latestSalary = $existingSalaries->sortByDesc('created_at')->first();
                Salary::where('group_id', $group->group_id)
                    ->where('salary_id', '!=', $latestSalary->salary_id)
                    ->delete();
            }

            // 2. البحث عن الراتب المتبقي (إن وجد)
            $salary = Salary::where('group_id', $group->group_id)->first();

            if (! $salary) {
                Log::info('No salary found during update, creating new single salary');

                return $this->createStrictSingleSalary($group);
            }

            // 3. تحديث الراتب الموجود فقط
            $studentCount = count($studentIds);
            $monthlyGroupRevenue = $group->price * $studentCount;
            $monthlyTeacherShare = round((($group->teacher_percentage ?? 0) / 100) * $monthlyGroupRevenue, 2);

            $salary->update([
                'group_revenue' => $monthlyGroupRevenue,
                'teacher_share' => $monthlyTeacherShare,
                'net_salary' => $monthlyTeacherShare,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            Log::info('Updated existing single salary', [
                'group_id' => $group->group_id,
                'salary_id' => $salary->salary_id,
                'new_monthly_teacher_share' => $monthlyTeacherShare,
                'student_count' => $studentCount,
                'group_price' => $group->price,
                'teacher_percentage' => $group->teacher_percentage,
                'final_count' => Salary::where('group_id', $group->group_id)->count(),
            ]);

            return $salary;

        } catch (\Exception $e) {
            Log::error('Failed to update teacher salary for group '.$group->group_id.': '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * إنشاء راتب واحد فقط للمدرس - بدون أي تكرار للمشهور
     */
    private function createSingleTeacherSalary(Group $group, array $studentIds = [])
    {
        try {
            // أولاً: تنظيف أي رواتب مكررة موجودة مسبقاً
            $existingSalaries = Salary::where('group_id', $group->group_id)->get();

            if ($existingSalaries->count() > 0) {
                Log::warning('Found existing salaries for group, deleting duplicates', [
                    'group_id' => $group->group_id,
                    'count' => $existingSalaries->count(),
                ]);

                // حذف كل الرواتب الموجودة لهذا الجروب
                Salary::where('group_id', $group->group_id)->delete();
            }

            // حساب الراتب لجميع الشهور مرة واحدة
            $studentCount = count($studentIds);
            $totalGroupRevenue = $group->price * $studentCount;
            $totalTeacherShare = round((($group->teacher_percentage ?? 0) / 100) * $totalGroupRevenue, 2);

            // استخدام شهر بداية الجروب كمرجع
            $salaryMonth = $group->start_date ?
                Carbon::parse($group->start_date)->format('Y-m') :
                Carbon::now()->format('Y-m');

            // إنشاء راتب واحد فقط
            $salary = Salary::create([
                'teacher_id' => $group->teacher_id,
                'month' => $salaryMonth,
                'group_id' => $group->group_id,
                'group_revenue' => $totalGroupRevenue,
                'teacher_share' => $totalTeacherShare,
                'deductions' => 0,
                'bonuses' => 0,
                'net_salary' => $totalTeacherShare,
                'status' => 'pending',
                'payment_date' => null,
                'updated_by' => auth()->id(),
            ]);

            Log::info('Created SINGLE salary for entire group', [
                'group_id' => $group->group_id,
                'salary_id' => $salary->salary_id,
                'total_teacher_share' => $totalTeacherShare,
                'student_count' => $studentCount,
                'salary_month' => $salaryMonth,
                'group_duration' => $group->start_date.' to '.$group->end_date,
            ]);

            return $salary;

        } catch (\Exception $e) {
            Log::error('Failed to create single teacher salary for group '.$group->group_id.': '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * إنشاء راتب واحد فقط للمدرس للجروب - بدون تكرار
     */

    // في دالة show أو إنشاء دالة جديدة
    public function show(Group $group)
    {
        $group->load([
            'course',
            'teacher',
            'students',
            'students.invoices' => function($query) use ($group) {
                $query->where('group_id', $group->group_id);
            },
            'students.user.profile', // إضافة تحميل البروفايل

            'sessions',
            'sessions.ratings', // إضافة تحميل التقييمات
        ]);

        // Check permissions
        if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
            abort(403, 'You do not have permission to view this group.');
        }

        // حساب متوسط تقييم كل طالب في الجروب
        $studentRatings = [];
        foreach ($group->students as $student) {
            $ratings = Rating::whereHas('session', function ($query) use ($group) {
                $query->where('group_id', $group->group_id);
            })
                ->where('student_id', $student->student_id)
                ->where('rating_type', 'session')
                ->get();

            $averageRating = $ratings->avg('rating_value');
            $totalSessions = $group->sessions->count();
            $ratedSessions = $ratings->count();

            $studentRatings[$student->student_id] = [
                'average_rating' => $averageRating ? round($averageRating, 1) : 'N/A',
                'total_sessions' => $totalSessions,
                'rated_sessions' => $ratedSessions,
                'ratings' => $ratings,
            ];
        }

        return view('groups.show', [
            'group' => $group,
            'studentRatings' => $studentRatings
        ]);
    }

    public function edit(Group $group)
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        $group->load(['students', 'schedules', 'course', 'subcourse']);
        
        // Extract schedule details & Format Dates/Times for HTML5 inputs
        $schedule = $group->schedules->first();
        if ($schedule) {
            $group->room_id = $schedule->room_id;
            $group->day_of_week = $schedule->day_of_week;
            // Ensure time is HH:MM
            $group->start_time = $schedule->start_time ? substr($schedule->start_time, 0, 5) : '12:00';
            $group->end_time = $schedule->end_time ? substr($schedule->end_time, 0, 5) : '14:00';
        }

        // Format Dates
        $group->formatted_start_date = $group->start_date ? $group->start_date->format('Y-m-d') : '';
        $group->formatted_end_date = $group->end_date ? $group->end_date->format('Y-m-d') : '';

        $courses = Course::orderBy('course_name', 'asc')->get(['course_id', 'course_name', 'uuid']);
        $teachers = Teacher::orderBy('teacher_name', 'asc')->get(['teacher_id', 'teacher_name', 'salary_percentage', 'uuid']);
        
        // Use the same detailed info as create
        $students = $this->getStudentsWithPaymentInfo();
        
        $rooms = Room::orderBy('room_name', 'asc')->get(['room_id', 'room_name']);
        
        // Load initial subcourses for this course
        $subcourses = Subcourse::where('course_id', $group->course_id)
            ->orderBy('subcourse_number', 'asc')
            ->get(['subcourse_id', 'subcourse_name', 'subcourse_number']);

        return view('groups.edit', [
            'group' => $group,
            'courses' => $courses,
            'subcourses' => $subcourses,
            'teachers' => $teachers,
            'students' => $students,
            'rooms' => $rooms
        ]);
    }

    public function update(Request $request, Group $group)
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'group_name' => 'required|string|max:255',
            'is_online' => 'nullable|boolean',
            'course_id' => 'required|integer',
            'subcourse_id' => 'nullable', // Changed to nullable to handle empty strings
            'teacher_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'schedule' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'is_free' => 'nullable|boolean',
            'teacher_percentage' => 'required|numeric|min:0|max:100',
            'room_id' => 'required_unless:is_online,1|nullable|integer|exists:rooms,room_id', // Added
            'day_of_week' => 'required|string', // Added
            'start_time' => 'required|string', // Added
            'end_time' => 'required|string', // Added
            'students' => 'nullable|array',
            'students.*' => 'integer',
        ]);

        $group->load('students');

        DB::beginTransaction();
        try {
            // الحصول على الطلاب الحاليين
            $currentStudentIds = $group->students->pluck('student_id')->toArray();
            $oldPrice = $group->price;
            $oldTeacherPercentage = $group->teacher_percentage;
            $oldTeacherId = $group->teacher_id;

            // تحديد الطلاب الجدد والملغاة
            $validStudentIds = $request->students ?? [];
            $addedStudents = array_diff($validStudentIds, $currentStudentIds);
            $removedStudents = array_diff($currentStudentIds, $validStudentIds);

            // ✅ **التحقق من وجود مدفوعات للطلاب المراد إزالتهم**
            if (! empty($removedStudents)) {
                foreach ($removedStudents as $studentId) {
                    if ($this->studentHasGroupPayments($studentId, $group->group_id)) {
                        $student = Student::find($studentId, ['*']);
                        $studentName = $student ? $student->student_name : 'الطالب';

                        DB::rollback();

                        return redirect()->back()->with('error', "لا يمكن إزالة الطالب '{$studentName}' لأنه لديه مدفوعات في فواتير هذا الجروب");
                    }
                }
            }

            // معالجة subcourse_id إذا كان فارغاً
            $subcourseId = $request->subcourse_id;
            if ($subcourseId === '' || $subcourseId === 'null') {
                $subcourseId = null;
            }

            // تحديث بيانات المجموعة
            $group->update([
                'group_name' => $request->group_name,
                'is_online' => $request->boolean('is_online', false),
                'course_id' => $request->course_id,
                'subcourse_id' => $subcourseId,
                'teacher_id' => $request->teacher_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'schedule' => $request->schedule,
                'price' => $request->price,
                'is_free' => $request->boolean('is_free', false),
                'teacher_percentage' => $request->teacher_percentage,
            ]);

            // ✅ **تحديث أو إنشاء الجدول الزمني**
            Schedule::where('group_id', '=', $group->group_id, 'and')->delete();
            $this->createGroupSchedule($group, $request);

            // ✅ **التصحيح 1: تحديث فواتير الطلاب إذا تغير السعر**
            if ($oldPrice != $request->price) {
                $this->updateStudentInvoicesForPriceChangeComprehensive($group, $oldPrice, $request->price);
            }

            // ✅ **التصحيح 2: تحديث teacher_id في الرواتب إذا تغير المدرس**
            if ($oldTeacherId != $request->teacher_id) {
                Salary::where('group_id', '=', $group->group_id)
                    ->update(['teacher_id' => $request->teacher_id]);
            }

            // تحديث ارتباطات الطلاب
            $group->students()->sync($validStudentIds);

            // ✅ **التصحيح 3: إنشاء فواتير للطلاب الجدد**
            if (! empty($addedStudents)) {
                $this->createInvoicesForStudents($group, $addedStudents);
            }

            // ✅ **التصحيح 4: حذف فواتير الطلاب المزالين (إذا لم يكن لديهم مدفوعات)**
            if (! empty($removedStudents)) {
                $this->deleteInvoicesForRemovedStudents($group, $removedStudents);
            }

            // ✅ **التصحيح 5: تحديث جميع الرواتب بشكل شامل**
            $this->updateTeacherSalaryComprehensive($group, $validStudentIds);

            DB::commit();

            return redirect()->route('groups.index')
                ->with('success', 'تم تحديث الجروب بنجاح مع تحديث جميع الفواتير والرواتب!');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating group: '.$e->getMessage(), [
                'group_id' => $group->group_id,
                'error' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'خطأ في تحديث الجروب: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * تسجيل تغييرات الجروب للأرشفة
     */
    private function logGroupChanges(Group $group, $oldData, $newData)
    {
        try {
            if (Schema::hasTable('group_change_logs')) {
                GroupChangeLog::create([
                    'group_id' => $group->group_id,
                    'changed_by' => auth()->id(),
                    'old_data' => $oldData,
                    'new_data' => $newData,
                    'changes' => array_diff_assoc($newData, $oldData),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log group changes: '.$e->getMessage());
        }
    }

    /**
     * حذف فواتير الطلاب المزالين
     */
    private function deleteInvoicesForRemovedStudents(Group $group, array $removedStudentIds)
    {
        try {
            $deletedCount = 0;

            foreach ($removedStudentIds as $studentId) {
                $invoices = Invoice::where('student_id', $studentId)
                    ->where('group_id', $group->group_id)
                    ->get();

                foreach ($invoices as $invoice) {
                    // حذف فقط إذا لم يكن هناك مدفوعات
                    if ($invoice->amount_paid == 0) {
                        // حذف أي مدفوعات مرتبطة أولاً
                        if (class_exists(Payment::class)) {
                            Payment::where('invoice_id', $invoice->invoice_id)->delete();
                        }

                        $invoice->delete();
                        $deletedCount++;

                        Log::info('Deleted invoice for removed student', [
                            'invoice_id' => $invoice->invoice_id,
                            'student_id' => $studentId,
                            'group_id' => $group->group_id,
                        ]);
                    }
                }
            }

            Log::info('Deleted invoices for removed students', [
                'group_id' => $group->group_id,
                'removed_students' => count($removedStudentIds),
                'deleted_invoices' => $deletedCount,
            ]);

            return $deletedCount;

        } catch (\Exception $e) {
            Log::error('Error deleting invoices for removed students: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث فواتير الطلاب عند تغيير سعر الجروب
     */
    private function updateStudentInvoicesForPriceChange(Group $group, $oldPrice, $newPrice)
    {
        try {
            $invoices = Invoice::where('group_id', $group->group_id)
                ->where('status', '!=', 'paid')
                ->get();

            $updatedCount = 0;

            foreach ($invoices as $invoice) {
                // احسب الفرق في السعر
                $priceDifference = $newPrice - $oldPrice;

                // تحديث مبلغ الفاتورة
                $newAmount = $invoice->amount + $priceDifference;

                // تأكد من أن المبلغ الجديد ليس أقل من المبلغ المدفوع
                if ($newAmount < $invoice->amount_paid) {
                    $newAmount = $invoice->amount_paid; // لا يمكن أن يكون المبلغ الجديد أقل من المدفوع
                }

                $invoice->update([
                    'amount' => $newAmount,
                    'notes' => $invoice->notes." | تم تحديث السعر من {$oldPrice} إلى {$newPrice} بتاريخ ".now()->format('Y-m-d'),
                ]);

                $updatedCount++;
            }

            Log::info('Updated student invoices for price change', [
                'group_id' => $group->group_id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'invoices_updated' => $updatedCount,
            ]);

            return $updatedCount;

        } catch (\Exception $e) {
            Log::error('Error updating student invoices for price change: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * التحقق من وجود مدفوعات للطالب في فواتير الجروب
     */
    private function studentHasGroupPayments($studentId, $groupId)
    {
        $invoices = Invoice::where('student_id', $studentId)
            ->where('group_id', $groupId)
            ->get();

        foreach ($invoices as $invoice) {
            if ($invoice->amount_paid > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * تحديث الراتب الموجود فقط - بدون إنشاء جديد
     */

    /**
     * تنظيف كل الرواتب المكررة للجروبات
     */
    public function cleanupAllDuplicateSalaries()
    {
        try {
            $duplicateGroups = DB::table('salaries')
                ->select('group_id', DB::raw('COUNT(*) as count'))
                ->groupBy('group_id')
                ->having('count', '>', 1)
                ->get();

            $deletedCount = 0;

            foreach ($duplicateGroups as $duplicate) {
                // احتفظ بأحدث راتب واحذف الباقي
                $latestSalary = Salary::where('group_id', $duplicate->group_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $deleted = Salary::where('group_id', $duplicate->group_id)
                    ->where('salary_id', '!=', $latestSalary->salary_id)
                    ->delete();

                $deletedCount += $deleted;
            }

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deletedCount} duplicate salaries!",
                'deleted_count' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cleaning duplicates: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء فواتير للطلاب الجدد
     */
    /**
     * جلب المجموعات المتاحة للنقل
     */
    /**
     * جلب المجموعات المتاحة للنقل
     */
    /**
     * جلب المجموعات المتاحة للنقل
     */
    /**
     * جلب المجموعات المتاحة للنقل
     */
    /**
     * جلب المجموعات المتاحة للنقل (التي لم تنتهي بعد)
     */
    public function getAvailableGroupsForTransfer(Request $request)
    {
        try {
            Log::info('getAvailableGroupsForTransfer called', [
                'params' => $request->all(),
                'user_id' => auth()->id(),
                'role_id' => auth()->user()->role_id,
            ]);

            $studentId = $request->query('student_id');
            $excludeGroupId = $request->query('exclude_group');

            // التحقق من الصلاحيات
            if (auth()->user()->role_id != 1) {
                Log::warning('Unauthorized access attempt', [
                    'user_id' => auth()->id(),
                    'role_id' => auth()->user()->role_id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بهذا الإجراء',
                ], 403);
            }

            // التحقق من وجود الطالب
            $student = Student::find($studentId, ['*']);
            if (! $student) {
                Log::warning('Student not found', ['student_id' => $studentId]);

                return response()->json([
                    'success' => false,
                    'message' => 'الطالب غير موجود',
                ], 404);
            }

            Log::info('Fetching groups for student', [
                'student_id' => $studentId,
                'student_name' => $student->student_name,
                'exclude_group' => $excludeGroupId,
            ]);

            // ✅ **التعديل المهم: جلب المجموعات التي لم تنتهي بعد**
            $today = date('Y-m-d');

            $groups = Group::query()
                ->with([
                    'course:course_id,course_name',
                    'teacher:teacher_id,teacher_name',
                    'students',
                ])
                ->where('group_id', '!=', $excludeGroupId)
                // ✅ فقط المجموعات التي تاريخ انتهائها أكبر من أو يساوي اليوم
                ->whereDate('end_date', '>=', $today)
                ->orderBy('start_date', 'asc')
                ->get();

            $result = [];

            foreach ($groups as $group) {
                try {
                    // التحقق مما إذا كان الطالب موجوداً بالفعل في هذه المجموعة
                    $isStudentInGroup = $group->students->contains('student_id', $studentId);

                    if (! $isStudentInGroup) {
                        $studentCount = $group->students->count();

                        // حساب الحالة
                        $status = $this->calculateGroupStatus(
                            $group->start_date,
                            $group->end_date
                        );

                        // ✅ **تجاهل المجموعات المنتهية**
                        if ($status === 'Completed') {
                            continue;
                        }

                        $result[] = [
                            'group_id' => $group->group_id,
                            'group_name' => $group->group_name ?? 'غير محدد',
                            'course_name' => $group->course->course_name ?? 'غير محدد',
                            'teacher_name' => $group->teacher->teacher_name ?? 'غير محدد',
                            'student_count' => $studentCount,
                            'price' => $group->price ?? 0,
                            'start_date' => $group->start_date ? $group->start_date->format('Y-m-d') : null,
                            'end_date' => $group->end_date ? $group->end_date->format('Y-m-d') : null,
                            'status' => $status,
                            'is_student_already_in_group' => $isStudentInGroup,
                            // ✅ **إضافة مؤشر لعدد الأيام المتبقية**
                            'days_remaining' => $this->calculateDaysRemaining($group->end_date),
                        ];
                    }
                } catch (\Exception $groupError) {
                    Log::error('Error processing group', [
                        'group_id' => $group->group_id ?? 'unknown',
                        'error' => $groupError->getMessage(),
                    ]);

                    // تخطي هذه المجموعة والمتابعة
                    continue;
                }
            }

            Log::info('Groups fetched successfully', [
                'total_groups' => count($groups),
                'available_groups' => count($result),
                'filter_applied' => 'end_date >= '.$today,
            ]);

            return response()->json([
                'success' => true,
                'groups' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading groups for transfer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_id' => $request->query('student_id'),
                'exclude_group' => $request->query('exclude_group'),
                'user_id' => auth()->id() ?? 'not authenticated',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب المجموعات: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * حساب الأيام المتبقية للمجموعة
     */
    private function calculateDaysRemaining($endDate)
    {
        if (! $endDate) {
            return null;
        }

        $end = Carbon::parse($endDate);
        $today = Carbon::today();

        if ($today->gt($end)) {
            return 0; // انتهت
        }

        return $today->diffInDays($end, false); // false لإظهار الفرق الموجب فقط
    }

    /**
     * تنفيذ نقل الطالب
     */
    /**
     * تنفيذ نقل الطالب
     */
    /**
     * تنفيذ نقل الطالب - الإصدار المصحح
     */
    /**
     * تنفيذ نقل الطالب - مع حذف الفاتورة القديمة تماماً
     */

    /**
     * أرشفة الفاتورة قبل الحذف - للنسخة المعدلة
     */
    private function archiveInvoiceBeforeDeletion(Invoice $invoice, $newGroupId, $transferDate)
    {
        try {
            if (Schema::hasTable('deleted_invoices_archive')) {
                DeletedInvoiceArchive::create([
                    'original_invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'student_id' => $invoice->student_id,
                    'old_group_id' => $invoice->group_id,
                    'new_group_id' => $newGroupId,
                    'amount' => $invoice->amount,
                    'amount_paid' => $invoice->amount_paid,
                    'discount_amount' => $invoice->discount_amount,
                    'discount_percent' => $invoice->discount_percent,
                    'status_before_deletion' => $invoice->status,
                    'notes' => $invoice->notes,
                    'deleted_reason' => 'student_transfer_with_payment_transfer',
                    'transfer_date' => $transferDate,
                    'deleted_by' => auth()->id(),
                    'original_created_at' => $invoice->created_at,
                    'deleted_at' => now(),
                ]);

                Log::info('Invoice archived before deletion', [
                    'invoice_id' => $invoice->invoice_id,
                    'new_group_id' => $newGroupId,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to archive invoice before deletion: '.$e->getMessage());
            // لا توقف العملية إذا فشل الأرشفة
        }
    }

    private function createNewInvoiceWithTransferredAmountOnly($studentId, $toGroup, $transferDate, $amountPaid, $discountAmount, $discountPercent, $fromGroup)
    {
        if ($toGroup->isFree()) {
            return null;
        }

        // ✅ **هنا التصحيح الجوهري**: سعر الفاتورة الجديدة = سعر المجموعة الجديدة فقط
        $invoiceAmount = $toGroup->price;

        $invoiceNumber = 'INV-'.date('Ymd-His').'-'.rand(100, 999);
        $dueDate = now()->addDays(30)->format('Y-m-d');

        // ✅ **حساب الحالة بناءً على المبلغ المدفوع المنقول فقط**
        $netAmount = $invoiceAmount - $discountAmount;

        Log::info('Calculating new invoice status:', [
            'invoice_amount' => $invoiceAmount,
            'discount_amount' => $discountAmount,
            'net_amount' => $netAmount,
            'amount_paid_transferred' => $amountPaid,
            'remaining' => $netAmount - $amountPaid,
        ]);

        if ($amountPaid >= $netAmount) {
            $status = 'paid';
        } elseif ($amountPaid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        // ✅ **ملاحظات واضحة تبين النقل فقط**
        $notes = '✅ فاتورة جديدة بعد النقل'."\n";
        $notes .= "📝 نقلت من: {$fromGroup->group_name} (#{$fromGroup->group_id})"."\n";
        $notes .= '💰 السعر الجديد: '.number_format($invoiceAmount, 2).' جنيه'."\n";

        if ($amountPaid > 0) {
            $notes .= '💳 المبلغ المنقول: '.number_format($amountPaid, 2).' جنيه'."\n";
        }

        if ($discountAmount > 0) {
            $notes .= '🎫 الخصم المنقول: '.number_format($discountAmount, 2).' جنيه'."\n";
        }

        $notes .= "📅 تاريخ النقل: {$transferDate}";

        // ✅ **إنشاء الفاتورة الجديدة فقط**
        $newInvoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $invoiceAmount,
            'amount_paid' => $amountPaid, // ✅ فقط المبلغ المنقول
            'discount_amount' => $discountAmount, // ✅ فقط الخصم المنقول
            'discount_percent' => $discountPercent,
            'due_date' => $dueDate,
            'status' => $status,
            'issued_date' => $transferDate,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('✅ NEW INVOICE CREATED - CLEAN TRANSFER:', [
            'invoice_id' => $newInvoice->invoice_id,
            'invoice_number' => $invoiceNumber,
            'invoice_amount' => $invoiceAmount,
            'amount_paid' => $amountPaid,
            'discount_amount' => $discountAmount,
            'status' => $status,
            'user_id' => auth()->id(),
            'note' => 'تم إنشاء فاتورة جديدة فقط بالمبالغ المنقولة',
        ]);

        return $newInvoice;
    }

    private function createFreshInvoice($studentId, $toGroup, $transferDate)
    {
        if ($toGroup->isFree()) {
            return null;
        }

        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

        $newInvoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $toGroup->price,
            'amount_paid' => 0, // فاتورة جديدة بدون مدفوعات
            'discount_amount' => 0,
            'discount_percent' => 0,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'pending',
            'issued_date' => $transferDate,
            'notes' => 'فاتورة جديدة بعد نقل الطالب (لم توجد فاتورة قديمة)',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $newInvoice;
    }

    /**
     * ✅ **الحل السريع**: تصحيح مباشر للمشكلة الحالية
     */
    public function fixDuplicatedTransfer($invoiceId)
    {
        DB::beginTransaction();
        try {
            $invoice = Invoice::findOrFail($invoiceId);

            Log::info('FIXING DUPLICATED TRANSFER - START', [
                'invoice_id' => $invoiceId,
                'current_amount' => $invoice->amount,
                'current_amount_paid' => $invoice->amount_paid,
                'problem' => 'المبلغ المدفوع 150 أصبح 270',
            ]);

            // التحقق إذا كان هناك تضاعف
            $originalPaidAmount = 150; // هذا هو المبلغ الأصلي الذي دفع
            $currentPaidAmount = $invoice->amount_paid;

            if ($currentPaidAmount > $originalPaidAmount) {
                // تصحيح المبلغ
                $invoice->amount_paid = $originalPaidAmount;

                // تحديث الحالة
                $netAmount = $invoice->amount - ($invoice->discount_amount ?? 0);
                if ($originalPaidAmount >= $netAmount) {
                    $invoice->status = 'paid';
                } elseif ($originalPaidAmount > 0) {
                    $invoice->status = 'partial';
                } else {
                    $invoice->status = 'pending';
                }

                $invoice->notes = $invoice->notes."\n✅ تم التصحيح: المبلغ المدفوع كان {$currentPaidAmount} وتم تصحيحه إلى {$originalPaidAmount} بتاريخ ".now()->format('Y-m-d H:i:s');
                $invoice->updated_by = auth()->id();
                $invoice->save();

                Log::info('FIX APPLIED SUCCESSFULLY', [
                    'invoice_id' => $invoiceId,
                    'old_amount_paid' => $currentPaidAmount,
                    'new_amount_paid' => $originalPaidAmount,
                    'difference_fixed' => $currentPaidAmount - $originalPaidAmount,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => '✅ تم تصحيح المبلغ المدفوع من '.$currentPaidAmount.' إلى '.$originalPaidAmount,
                    'invoice' => [
                        'id' => $invoice->invoice_id,
                        'number' => $invoice->invoice_number,
                        'amount_paid_before' => $currentPaidAmount,
                        'amount_paid_after' => $originalPaidAmount,
                    ],
                ]);
            }

            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على مشكلة تضاعف في هذا الفاتورة',
            ], 400);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error fixing duplicated transfer: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطأ في التصحيح: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * التحقق من عدم وجود تضاعف قبل النقل
     */
    private function validateNoDuplication($studentId, $fromGroupId, $toGroupId)
    {
        // 1. التحقق من الفاتورة القديمة
        $oldInvoice = Invoice::where('student_id', $studentId)
            ->where('group_id', $fromGroupId)
            ->first();

        // 2. التحقق من الفاتورة الجديدة (إذا كانت موجودة)
        $newInvoice = Invoice::where('student_id', $studentId)
            ->where('group_id', $toGroupId)
            ->first();

        $validation = [
            'has_old_invoice' => $oldInvoice ? true : false,
            'has_new_invoice' => $newInvoice ? true : false,
            'old_invoice_amount_paid' => $oldInvoice ? $oldInvoice->amount_paid : 0,
            'potential_duplication' => false,
            'message' => '',
        ];

        // 3. إذا كانت هناك فاتورة جديدة ومدفوعات، هذا خطأ!
        if ($newInvoice && $newInvoice->amount_paid > 0) {
            $validation['potential_duplication'] = true;
            $validation['message'] = '⚠️ تحذير: يوجد بالفعل فاتورة للمجموعة الجديدة بها مدفوعات!';
        }

        // 4. إذا كانت هناك فاتورة قديمة ومدفوعات، سننقلها فقط
        if ($oldInvoice && $oldInvoice->amount_paid > 0) {
            $validation['message'] .= ' سيتم نقل مبلغ '.$oldInvoice->amount_paid.' فقط.';
        }

        Log::info('DUPLICATION VALIDATION:', $validation);

        return $validation;
    }

    /**
     * نقل الفاتورة بشكل صحيح - النسخة النهائية المصححة
     */
    /**
     * التحقق النهائي بعد النقل
     */
    private function verifyTransferCorrectness($studentId, $fromGroupId, $toGroupId, $expectedAmountPaid)
    {
        try {
            Log::info('🔍 STARTING FINAL VERIFICATION', [
                'student_id' => $studentId,
                'from_group' => $fromGroupId,
                'to_group' => $toGroupId,
                'expected_amount_paid' => $expectedAmountPaid,
            ]);

            // 1. التأكد من حذف الفاتورة القديمة
            $oldInvoiceExists = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroupId)
                ->exists();

            if ($oldInvoiceExists) {
                Log::error('❌ FAILED: Old invoice still exists!');

                return false;
            }

            // 2. التأكد من وجود فاتورة جديدة واحدة فقط
            $newInvoices = Invoice::where('student_id', $studentId)
                ->where('group_id', $toGroupId)
                ->get();

            if ($newInvoices->count() !== 1) {
                Log::error('❌ FAILED: Should have exactly 1 new invoice, found: '.$newInvoices->count());

                return false;
            }

            $newInvoice = $newInvoices->first();

            // 3. التأكد من أن المبلغ المدفوع صحيح
            if ($newInvoice->amount_paid != $expectedAmountPaid) {
                Log::error('❌ FAILED: Amount paid mismatch!', [
                    'expected' => $expectedAmountPaid,
                    'actual' => $newInvoice->amount_paid,
                ]);

                return false;
            }

            // 4. التأكد من سعر المجموعة الجديدة
            $toGroup = Group::find($toGroupId);
            if ($newInvoice->amount != $toGroup->price) {
                Log::error('❌ FAILED: Invoice amount should match group price!', [
                    'invoice_amount' => $newInvoice->amount,
                    'group_price' => $toGroup->price,
                ]);

                return false;
            }

            // 5. ✅ التحقق من الحالة المالية
            $netAmount = $newInvoice->amount - $newInvoice->discount_amount;
            $remaining = $netAmount - $newInvoice->amount_paid;

            $correctStatus = 'pending';
            if ($remaining <= 0) {
                $correctStatus = 'paid';
            } elseif ($newInvoice->amount_paid > 0) {
                $correctStatus = 'partial';
            }

            if ($newInvoice->status !== $correctStatus) {
                Log::warning('⚠️ Status might need update', [
                    'current' => $newInvoice->status,
                    'correct' => $correctStatus,
                ]);
                $newInvoice->update(['status' => $correctStatus]);
            }

            Log::info('✅ VERIFICATION PASSED:', [
                'new_invoice_id' => $newInvoice->invoice_id,
                'amount' => $newInvoice->amount,
                'amount_paid' => $newInvoice->amount_paid,
                'discount' => $newInvoice->discount_amount,
                'status' => $newInvoice->status,
                'balance' => $remaining,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Verification error: '.$e->getMessage());

            return false;
        }
    }

    private function transferInvoiceComprehensively($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('✅ STARTING FINAL CORRECT TRANSFER', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_name,
                'to_group' => $toGroup->group_name,
                'transfer_date' => $transferDate,
                'user' => auth()->user()->name,
            ]);

            // 1. البحث عن الفاتورة القديمة
            $oldInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->first();

            if (! $oldInvoice) {
                Log::warning('❌ No old invoice found - creating fresh invoice');

                return $this->createFreshInvoice($studentId, $toGroup, $transferDate);
            }

            // 2. استخراج المدفوعات والخصم فقط
            $transferredAmountPaid = $oldInvoice->amount_paid ?? 0;
            $transferredDiscount = $oldInvoice->discount_amount ?? 0;
            $transferredDiscountPercent = $oldInvoice->discount_percent ?? 0;

            Log::info('📊 EXTRACTING DATA FROM OLD INVOICE:', [
                'old_invoice_id' => $oldInvoice->invoice_id,
                'amount' => $oldInvoice->amount,
                'amount_paid_to_transfer' => $transferredAmountPaid,
                'discount_to_transfer' => $transferredDiscount,
                'note' => 'هذه المبالغ فقط ستنتقل',
            ]);

            // 3. ✅ **التصحيح: استخدام الدالة الصحيحة للأرشفة**
            $this->archiveInvoiceForTransfer($oldInvoice, $toGroup->group_id);

            // 4. ✅ **التصحيح: حذف أي فواتير موجودة للمجموعة الجديدة أولاً**
            Invoice::where('student_id', $studentId)
                ->where('group_id', $toGroup->group_id)
                ->delete();

            Log::info('🧹 Cleaned any existing invoices for new group');

            // 5. ✅ **حذف الفاتورة القديمة بعد الأرشفة**
            $oldInvoice->delete();
            Log::info('🗑️ Old invoice deleted: '.$oldInvoice->invoice_id);

            // 6. ✅ **إنشاء الفاتورة الجديدة فقط**
            $newInvoice = $this->createNewInvoiceForTransfer(
                $studentId,
                $toGroup,
                $transferDate,
                $transferredAmountPaid,
                $transferredDiscount,
                $transferredDiscountPercent,
                $fromGroup
            );

            // 7. ✅ **تسجيل النتيجة**
            Log::info('✅ TRANSFER COMPLETED SUCCESSFULLY:', [
                'old_invoice_deleted' => $oldInvoice->invoice_id,
                'new_invoice_created' => $newInvoice->invoice_id,
                'amount_paid_transferred' => $transferredAmountPaid,
                'discount_transferred' => $transferredDiscount,
                'verification' => 'المبلغ المدفوع '.$transferredAmountPaid.' فقط انتقل',
            ]);

            return $newInvoice;

        } catch (\Exception $e) {
            Log::error('❌ ERROR in final transfer: '.$e->getMessage());
            throw $e;
        }
    }

    private function updateSalariesAfterTransfer($fromGroupId, $toGroupId)
    {
        try {
            Log::info('Updating salaries after transfer', [
                'from_group' => $fromGroupId,
                'to_group' => $toGroupId,
            ]);

            // تحديث راتب مجموعة المصدر
            $fromGroup = Group::with(['students', 'teacher'])->find($fromGroupId);
            if ($fromGroup) {
                $this->updateGroupSalary($fromGroup);
            }

            // تحديث راتب مجموعة الوجهة
            $toGroup = Group::with(['students', 'teacher'])->find($toGroupId);
            if ($toGroup) {
                $this->updateGroupSalary($toGroup);
            }

            Log::info('Salaries updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating salaries: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تسجيل النقل في السجلات
     */
    private function logStudentTransfer($studentId, $fromGroupId, $toGroupId, $transferDate, $notes)
    {
        try {
            if (Schema::hasTable('student_transfers')) {
                StudentTransfer::create([
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroupId,
                    'to_group_id' => $toGroupId,
                    'transfer_date' => $transferDate,
                    'notes' => $notes,
                    'transferred_by' => auth()->id(),
                ]);

                Log::info('Student transfer logged successfully');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to log student transfer: '.$e->getMessage());
            // لا توقف العملية إذا فشل التسجيل
        }
    }

    /**
     * أرشفة الفاتورة القديمة
     */
    private function archiveInvoiceForTransfer(Invoice $invoice, $newGroupId)
    {
        try {
            if (Schema::hasTable('deleted_invoices_archive')) {
                DeletedInvoiceArchive::create([
                    'original_invoice_id' => $invoice->invoice_id,
                    'invoice_number' => $invoice->invoice_number,
                    'student_id' => $invoice->student_id,
                    'old_group_id' => $invoice->group_id,
                    'new_group_id' => $newGroupId,
                    'amount' => $invoice->amount,
                    'amount_paid' => $invoice->amount_paid,
                    'discount_amount' => $invoice->discount_amount,
                    'discount_percent' => $invoice->discount_percent,
                    'status_before_deletion' => $invoice->status,
                    'deleted_reason' => 'student_transfer',
                    'deleted_by' => auth()->id(),
                    'original_created_at' => $invoice->created_at,
                    'deleted_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to archive invoice: '.$e->getMessage());
        }
    }

    /**
     * أرشفة المدفوعات
     */
    private function archivePaymentsForTransfer($invoiceId)
    {
        try {
            if (Schema::hasTable('deleted_payments_archive') && class_exists(Payment::class)) {
                $payments = Payment::where('invoice_id', $invoiceId)->get();

                foreach ($payments as $payment) {
                    DeletedPaymentArchive::create([
                        'original_payment_id' => $payment->payment_id,
                        'invoice_id' => $invoiceId,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'transaction_id' => $payment->transaction_id,
                        'payment_date' => $payment->payment_date,
                        'deleted_reason' => 'invoice_deleted_due_to_student_transfer',
                        'deleted_by' => auth()->id(),
                        'deleted_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to archive payments: '.$e->getMessage());
        }
    }

    public function validateTransferSalaries(Request $request)
    {
        try {
            $fromGroupId = $request->from_group_id;
            $toGroupId = $request->to_group_id;

            $fromGroup = Group::with(['students', 'teacher'])->findOrFail($fromGroupId);
            $toGroup = Group::with(['students', 'teacher'])->findOrFail($toGroupId);

            $results = [
                'from_group' => $this->calculateGroupSalaryDetails($fromGroup),
                'to_group' => $this->calculateGroupSalaryDetails($toGroup),
                'validation' => [],
            ];

            // التحقق من الرواتب
            $fromSalaries = Salary::where('group_id', $fromGroupId)->get();
            $toSalaries = Salary::where('group_id', $toGroupId)->get();

            $results['validation']['from_group_salaries_count'] = $fromSalaries->count();
            $results['validation']['to_group_salaries_count'] = $toSalaries->count();

            // التحقق من التطابق
            foreach ($fromSalaries as $salary) {
                $expectedRevenue = $fromGroup->price * ($fromGroup->students->count() + 1); // +1 لأننا لم نحذف الطالب بعد
                $expectedTeacherShare = round((($fromGroup->teacher_percentage ?? 0) / 100) * $expectedRevenue, 2);

                if ($salary->group_revenue != $expectedRevenue ||
                    $salary->teacher_share != $expectedTeacherShare) {
                    $results['validation']['issues'][] = [
                        'type' => 'salary_mismatch',
                        'group' => 'from_group',
                        'salary_id' => $salary->salary_id,
                        'current_revenue' => $salary->group_revenue,
                        'expected_revenue' => $expectedRevenue,
                        'current_share' => $salary->teacher_share,
                        'expected_share' => $expectedTeacherShare,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Salary validation completed',
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: '.$e->getMessage(),
            ], 500);
        }
    }

    private function calculateGroupSalaryDetails(Group $group)
    {
        $studentCount = $group->students->count();
        $totalRevenue = $group->price * $studentCount;
        $teacherPercentage = $group->teacher_percentage ?? ($group->teacher->salary_percentage ?? 0);
        $teacherShare = round(($teacherPercentage / 100) * $totalRevenue, 2);
        $academyShare = round($totalRevenue - $teacherShare, 2);

        return [
            'group_name' => $group->group_name,
            'teacher_name' => $group->teacher->teacher_name ?? 'N/A',
            'student_count' => $studentCount,
            'price_per_student' => $group->price,
            'total_revenue' => $totalRevenue,
            'teacher_percentage' => $teacherPercentage,
            'teacher_share' => $teacherShare,
            'academy_share' => $academyShare,
        ];
    }

    private function createNewInvoiceForTransfer($studentId, $toGroup, $transferDate, $amountPaid, $discountAmount, $discountPercent, $fromGroup = null, $oldInvoiceData = null)
    {
        if ($toGroup->isFree()) {
            return null;
        }

        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
        $dueDate = now()->addDays(30)->format('Y-m-d');
        $newAmount = $toGroup->price;

        // حساب الحالة الجديدة
        $netAmount = $newAmount - $discountAmount;
        if ($amountPaid >= $netAmount) {
            $status = 'paid';
        } elseif ($amountPaid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        // بناء الملاحظات
        $notes = "فاتورة بعد نقل الطالب من المجموعة: {$fromGroup->group_name} (#{$fromGroup->group_id})";
        if ($amountPaid > 0) {
            $notes .= ' - تم نقل مبلغ مدفوع: '.number_format($amountPaid, 2).' جنيه';
        }
        if ($discountAmount > 0) {
            $notes .= ' - تم نقل خصم: '.number_format($discountAmount, 2).' جنيه';
        }

        // إنشاء الفاتورة الجديدة
        $newInvoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $newAmount,
            'amount_paid' => $amountPaid,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'due_date' => $dueDate,
            'status' => $status,
            'issued_date' => $transferDate,
            'notes' => $notes,
            'created_by' => auth()->id(), // ✅ المستخدم الحالي هو منشئ الفاتورة الجديدة
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('New invoice created after transfer', [
            'invoice_id' => $newInvoice->invoice_id,
            'invoice_number' => $invoiceNumber,
            'new_amount' => $newAmount,
            'transferred_payment' => $amountPaid,
            'transferred_discount' => $discountAmount,
            'status' => $status,
            'created_by' => auth()->id(),
        ]);

        return $newInvoice;
    }

    /**
     * تحديث الفاتورة بعد النقل
     */
    /**
     * تحديث الفاتورة بعد النقل
     */
    /**
     * تحديث الفاتورة بعد النقل
     */
    /**
     * تحديث الفاتورة بعد النقل
     */
    /**
     * تحديث الفاتورة بعد النقل - الإصدار المصحح
     */
    /**
     * تحديث الفاتورة بعد النقل - الإصدار المصحح مع نقل المبلغ المدفوع
     */
    /**
     * حذف الفاتورة القديمة تماماً وإنشاء فاتورة جديدة
     */
    private function deleteOldInvoiceAndCreateNew($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('Deleting old invoice and creating new one', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'transfer_date' => $transferDate,
                'current_user' => auth()->id(),
            ]);

            // 1. البحث عن الفاتورة القديمة وحذفها
            $oldInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->whereIn('status', ['paid', 'partial', 'pending'])
                ->first();

            // حفظ بيانات الفاتورة القديمة قبل الحذف (للأرشفة)
            $oldInvoiceData = null;
            $amountPaid = 0;
            $discountAmount = 0;
            $discountPercent = 0;

            if ($oldInvoice) {
                $oldInvoiceData = [
                    'invoice_id' => $oldInvoice->invoice_id,
                    'invoice_number' => $oldInvoice->invoice_number,
                    'amount' => $oldInvoice->amount,
                    'amount_paid' => $oldInvoice->amount_paid,
                    'discount_amount' => $oldInvoice->discount_amount,
                    'discount_percent' => $oldInvoice->discount_percent,
                    'status' => $oldInvoice->status,
                    'created_by' => $oldInvoice->created_by,
                    'created_at' => $oldInvoice->created_at,
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now(),
                ];

                $amountPaid = $oldInvoice->amount_paid ?? 0;
                $discountAmount = $oldInvoice->discount_amount ?? 0;
                $discountPercent = $oldInvoice->discount_percent ?? 0;

                // ✅ **هنا نحذف المدفوعات المرتبطة أولاً (إذا كان لديك جدول payments)**
                if (class_exists(Payment::class)) {
                    $paymentCount = Payment::where('invoice_id', $oldInvoice->invoice_id)->count();
                    if ($paymentCount > 0) {
                        Log::info('Deleting related payments before invoice deletion', [
                            'invoice_id' => $oldInvoice->invoice_id,
                            'payment_count' => $paymentCount,
                        ]);

                        // حفظ بيانات المدفوعات قبل الحذف (اختياري)
                        $oldPayments = Payment::where('invoice_id', $oldInvoice->invoice_id)
                            ->get()
                            ->map(function ($payment) {
                                return [
                                    'payment_id' => $payment->payment_id,
                                    'amount' => $payment->amount,
                                    'payment_method' => $payment->payment_method,
                                    'transaction_id' => $payment->transaction_id,
                                    'payment_date' => $payment->payment_date,
                                    'created_at' => $payment->created_at,
                                ];
                            });

                        // أرشفة المدفوعات قبل الحذف
                        $this->archivePaymentsBeforeDeletion($oldPayments, $oldInvoice->invoice_id);

                        // حذف المدفوعات
                        Payment::where('invoice_id', $oldInvoice->invoice_id)->delete();
                    }
                }

                // ✅ **حذف الفاتورة القديمة تماماً**
                $oldInvoice->delete();

                Log::info('Old invoice COMPLETELY DELETED', [
                    'invoice_id' => $oldInvoiceData['invoice_id'],
                    'invoice_number' => $oldInvoiceData['invoice_number'],
                    'amount' => $oldInvoiceData['amount'],
                    'amount_paid' => $oldInvoiceData['amount_paid'],
                    'deleted_by' => auth()->id(),
                    'reason' => 'student_transfer_to_new_group',
                ]);

                // أرشفة بيانات الفاتورة المحذوفة
                $this->archiveDeletedInvoice($oldInvoiceData, $studentId, $fromGroup, $toGroup);

            } else {
                Log::info('No invoice found to delete for student in old group', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                ]);
            }

            // 2. التحقق من عدم وجود فاتورة نشطة للمجموعة الجديدة
            $existingInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $toGroup->group_id)
                ->whereIn('status', ['pending', 'partial', 'paid'])
                ->first();

            if (! $existingInvoice) {
                // 3. ✅ **إنشاء فاتورة جديدة تماماً**
                $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
                $dueDate = now()->addDays(30)->format('Y-m-d');
                $newAmount = $toGroup->price;

                // حساب الحالة الجديدة بناءً على المدفوعات المنقولة
                $newAmountPaid = $amountPaid;
                if ($amountPaid >= $newAmount) {
                    $status = 'paid';
                } elseif ($amountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'pending';
                }

                $description = 'رسوم مجموعة: '.$toGroup->group_name;
                $notes = "فاتورة جديدة بعد نقل الطالب من المجموعة {$fromGroup->group_name} (#{$fromGroup->group_id})";

                if ($amountPaid > 0) {
                    $notes .= " - تم نقل مبلغ {$amountPaid} من الفاتورة القديمة";
                }
                if ($discountAmount > 0) {
                    $notes .= " - تم نقل خصم بقيمة {$discountAmount}";
                }

                // ✅ **إنشاء الفاتورة الجديدة باسم المستخدم الحالي**
                $newInvoice = Invoice::create([
                    'student_id' => $studentId,
                    'group_id' => $toGroup->group_id,
                    'invoice_number' => $invoiceNumber,
                    'description' => $description,
                    'amount' => $newAmount,
                    'amount_paid' => $newAmountPaid,
                    'discount_amount' => $discountAmount,
                    'discount_percent' => $discountPercent,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'issued_date' => $transferDate,
                    'notes' => $notes,
                    'created_by' => auth()->id(), // ✅ المستخدم الحالي هو من أنشأ الفاتورة
                    'updated_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('New invoice created after deleting old one', [
                    'student_id' => $studentId,
                    'old_group_id' => $fromGroup->group_id,
                    'new_group_id' => $toGroup->group_id,
                    'new_invoice_id' => $newInvoice->invoice_id,
                    'new_invoice_number' => $invoiceNumber,
                    'new_amount' => $newAmount,
                    'transferred_amount_paid' => $newAmountPaid,
                    'invoice_status' => $status,
                    'created_by' => auth()->id(),
                    'old_invoice_deleted' => $oldInvoiceData ? true : false,
                ]);

                // 4. إذا كان هناك مدفوعات في الفاتورة القديمة، إعادة إنشائها للفاتورة الجديدة
                if ($amountPaid > 0 && $oldInvoiceData) {
                    $this->recreatePaymentsForNewInvoice($oldInvoiceData, $newInvoice, $transferDate);
                }

            } else {
                // إذا كانت هناك فاتورة موجودة، نضيف المدفوعات إليها
                $newAmountPaid = $existingInvoice->amount_paid + $amountPaid;
                $newDiscountAmount = $existingInvoice->discount_amount + $discountAmount;

                // تحديث حالة الفاتورة
                if ($newAmountPaid >= $existingInvoice->amount - $newDiscountAmount) {
                    $status = 'paid';
                } elseif ($newAmountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'pending';
                }

                $existingInvoice->update([
                    'amount_paid' => $newAmountPaid,
                    'discount_amount' => $newDiscountAmount,
                    'status' => $status,
                    'updated_by' => auth()->id(),
                    'notes' => $existingInvoice->notes." - تم إضافة مبلغ {$amountPaid} من مجموعة سابقة",
                ]);

                Log::info('Existing invoice updated with transferred payment (old invoice deleted)', [
                    'invoice_id' => $existingInvoice->invoice_id,
                    'old_amount_paid' => $existingInvoice->amount_paid,
                    'new_amount_paid' => $newAmountPaid,
                    'added_amount' => $amountPaid,
                    'updated_by' => auth()->id(),
                    'old_invoice_deleted' => true,
                ]);
            }

            Log::info('Invoice deletion and creation completed successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting old invoice and creating new: '.$e->getMessage(), [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'current_user' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * أرشفة الفاتورة المحذوفة (اختياري)
     */
    private function archiveDeletedInvoice($invoiceData, $studentId, $fromGroup, $toGroup)
    {
        try {
            if (Schema::hasTable('deleted_invoices_log')) {
                DeletedInvoiceLog::create([
                    'original_invoice_id' => $invoiceData['invoice_id'],
                    'invoice_number' => $invoiceData['invoice_number'],
                    'student_id' => $studentId,
                    'old_group_id' => $fromGroup->group_id,
                    'new_group_id' => $toGroup->group_id,
                    'amount' => $invoiceData['amount'],
                    'amount_paid' => $invoiceData['amount_paid'],
                    'discount_amount' => $invoiceData['discount_amount'],
                    'discount_percent' => $invoiceData['discount_percent'],
                    'status_before_deletion' => $invoiceData['status'],
                    'deletion_reason' => 'student_group_transfer',
                    'deleted_by' => auth()->id(),
                    'original_created_at' => $invoiceData['created_at'],
                    'deleted_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to archive deleted invoice: '.$e->getMessage());
        }
    }

    /**
     * أرشفة المدفوعات قبل الحذف (اختياري)
     */
    private function archivePaymentsBeforeDeletion($payments, $invoiceId)
    {
        try {
            if (Schema::hasTable('deleted_payments_log') && $payments->isNotEmpty()) {
                foreach ($payments as $payment) {
                    DeletedPaymentLog::create([
                        'original_payment_id' => $payment['payment_id'],
                        'invoice_id' => $invoiceId,
                        'amount' => $payment['amount'],
                        'payment_method' => $payment['payment_method'],
                        'transaction_id' => $payment['transaction_id'],
                        'payment_date' => $payment['payment_date'],
                        'deletion_reason' => 'invoice_deleted_due_to_student_transfer',
                        'deleted_by' => auth()->id(),
                        'deleted_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to archive payments: '.$e->getMessage());
        }
    }

    /**
     * إعادة إنشاء المدفوعات للفاتورة الجديدة
     */
    private function recreatePaymentsForNewInvoice($oldInvoiceData, $newInvoice, $transferDate)
    {
        try {
            if (! class_exists(Payment::class)) {
                return;
            }

            // يمكنك استعادة المدفوعات من الأرشيف إذا أردت
            Log::info('Payments need to be recreated for new invoice', [
                'new_invoice_id' => $newInvoice->invoice_id,
                'old_invoice_id' => $oldInvoiceData['invoice_id'],
                'amount_paid' => $oldInvoiceData['amount_paid'],
            ]);

            // إذا كان لديك نظام لاستعادة المدفوعات من الأرشيف
            // Payment::create([...]) etc.

        } catch (\Exception $e) {
            Log::warning('Failed to recreate payments: '.$e->getMessage());
        }
    }

    private function updateInvoiceForTransfer($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('Updating invoice for transfer - TRANSFERRING PAYMENT', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'transfer_date' => $transferDate,
            ]);

            // 1. البحث عن الفاتورة القديمة
            $oldInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->whereIn('status', ['paid', 'partial'])
                ->first();

            // حساب المبلغ المدفوع بالفعل (مع مراعاة الخصم)
            $amountPaid = 0;
            $discountAmount = 0;
            $discountPercent = 0;

            if ($oldInvoice) {
                // تسجيل بيانات الفاتورة قبل التعديل
                $oldInvoiceData = [
                    'invoice_id' => $oldInvoice->invoice_id,
                    'invoice_number' => $oldInvoice->invoice_number,
                    'original_amount' => $oldInvoice->amount,
                    'amount_paid' => $oldInvoice->amount_paid,
                    'discount_amount' => $oldInvoice->discount_amount,
                    'discount_percent' => $oldInvoice->discount_percent,
                    'status' => $oldInvoice->status,
                    'due_date' => $oldInvoice->due_date,
                    'created_at' => $oldInvoice->created_at,
                ];

                // 👇 **هذا هو التعديل الأساسي**: حفظ المبلغ المدفوع والخصم
                $amountPaid = $oldInvoice->amount_paid ?? 0;
                $discountAmount = $oldInvoice->discount_amount ?? 0;
                $discountPercent = $oldInvoice->discount_percent ?? 0;

                // 2. تحديث حالة الفاتورة القديمة لتكون ملغية
                $oldInvoice->update([
                    'status' => 'cancelled',
                    'notes' => "تم إلغاء الفاتورة بعد نقل الطالب إلى المجموعة {$toGroup->group_id}",
                    'updated_at' => now(),
                ]);

                Log::info('Old invoice marked as cancelled', [
                    'invoice_id' => $oldInvoice->invoice_id,
                    'old_amount' => $oldInvoiceData['original_amount'],
                    'amount_paid' => $amountPaid,
                    'discount_amount' => $discountAmount,
                    'old_group_id' => $fromGroup->group_id,
                    'reason' => 'student_transfer_to_new_group',
                ]);

            } else {
                Log::info('No active invoice found for student in old group', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                ]);
            }

            // 3. التحقق من عدم وجود فاتورة نشطة للمجموعة الجديدة
            $existingInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $toGroup->group_id)
                ->whereIn('status', ['pending', 'partial', 'paid'])
                ->first();

            if (! $existingInvoice) {
                // 4. إنشاء فاتورة جديدة للمجموعة الجديدة مع نقل المبلغ المدفوع
                $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
                $dueDate = now()->addDays(30)->format('Y-m-d');

                $newAmount = $toGroup->price;

                // 👇 **نقل المبلغ المدفوع والخصم إلى الفاتورة الجديدة**
                // حساب المبلغ المتبقي بعد الخصم في الفاتورة القديمة
                $oldNetAmount = ($oldInvoice->amount ?? 0) - ($oldInvoice->discount_amount ?? 0);
                $oldRemaining = max(0, $oldNetAmount - $amountPaid);

                // إذا كان هناك مدفوعات في الفاتورة القديمة، ننقلها للجديدة
                $newAmountPaid = $amountPaid;

                // إذا كان المبلغ المدفوع أكبر أو يساوي سعر المجموعة الجديدة
                if ($amountPaid >= $newAmount) {
                    $status = 'paid';
                } elseif ($amountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'pending';
                }

                $description = 'رسوم مجموعة: '.$toGroup->group_name;

                // 👇 **التعديل المهم**: نقل الخصم من الفاتورة القديمة إن وجد
                $notes = "فاتورة بعد نقل الطالب من المجموعة {$fromGroup->group_id}";
                if ($amountPaid > 0) {
                    $notes .= " - تم نقل مبلغ {$amountPaid} من المدفوعات السابقة";
                }
                if ($discountAmount > 0) {
                    $notes .= " - تم نقل خصم بقيمة {$discountAmount}";
                }

                $newInvoice = Invoice::create([
                    'student_id' => $studentId,
                    'group_id' => $toGroup->group_id,
                    'invoice_number' => $invoiceNumber,
                    'description' => $description,
                    'amount' => $newAmount,
                    'amount_paid' => $newAmountPaid,
                    'discount_amount' => $discountAmount, // 👈 نقل الخصم
                    'discount_percent' => $discountPercent, // 👈 نقل نسبة الخصم
                    'due_date' => $dueDate,
                    'status' => $status,
                    'issued_date' => $transferDate,
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('New invoice created with transferred payment', [
                    'student_id' => $studentId,
                    'old_group_id' => $fromGroup->group_id,
                    'new_group_id' => $toGroup->group_id,
                    'new_invoice_id' => $newInvoice->invoice_id,
                    'new_invoice_amount' => $newAmount,
                    'transferred_amount_paid' => $newAmountPaid,
                    'transferred_discount' => $discountAmount,
                    'invoice_status' => $status,
                    'invoice_number' => $invoiceNumber,
                ]);

                // 5. إذا كان هناك مدفوعات في الفاتورة القديمة، نسجلها في الفاتورة الجديدة
                if ($amountPaid > 0 && $oldInvoice) {
                    $this->transferPayments($oldInvoice, $newInvoice, $transferDate);
                }

            } else {
                // 👇 **إذا كانت هناك فاتورة موجودة، نضيف المدفوعات إليها**
                $newAmountPaid = $existingInvoice->amount_paid + $amountPaid;
                $newDiscountAmount = $existingInvoice->discount_amount + $discountAmount;

                // تحديث حالة الفاتورة بناءً على المبلغ المدفوع الجديد
                if ($newAmountPaid >= $existingInvoice->amount - $newDiscountAmount) {
                    $status = 'paid';
                } elseif ($newAmountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'pending';
                }

                $existingInvoice->update([
                    'amount_paid' => $newAmountPaid,
                    'discount_amount' => $newDiscountAmount,
                    'status' => $status,
                    'notes' => $existingInvoice->notes." - تم إضافة مبلغ {$amountPaid} من مجموعة أخرى",
                ]);

                Log::info('Existing invoice updated with transferred payment', [
                    'invoice_id' => $existingInvoice->invoice_id,
                    'old_amount_paid' => $existingInvoice->amount_paid,
                    'new_amount_paid' => $newAmountPaid,
                    'added_amount' => $amountPaid,
                ]);
            }

            Log::info('Invoice transfer completed successfully with payment transfer');

        } catch (\Exception $e) {
            Log::error('Error updating invoice for transfer: '.$e->getMessage(), [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * نقل المدفوعات من الفاتورة القديمة إلى الجديدة
     */
    private function transferPayments($oldInvoice, $newInvoice, $transferDate)
    {
        try {
            if (! class_exists(Payment::class)) {
                return;
            }

            $payments = Payment::where('invoice_id', $oldInvoice->invoice_id)->get();

            foreach ($payments as $payment) {
                // إنشاء سجل دفع جديد للفاتورة الجديدة
                Payment::create([
                    'invoice_id' => $newInvoice->invoice_id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'transaction_id' => $payment->transaction_id.'-TRANSFERRED',
                    'payment_date' => $payment->payment_date,
                    'notes' => "تم نقل هذا الدفع من الفاتورة رقم {$oldInvoice->invoice_number}",
                    'created_at' => $payment->created_at,
                    'updated_at' => now(),
                ]);
            }

            Log::info('Payments transferred successfully', [
                'from_invoice' => $oldInvoice->invoice_id,
                'to_invoice' => $newInvoice->invoice_id,
                'payments_count' => $payments->count(),
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to transfer payments: '.$e->getMessage());
            // لا توقف العملية إذا فشل نقل سجلات المدفوعات
        }
    }

    /**
     * تسجيل حذف الفاتورة للأرشفة
     */
    private function logInvoiceDeletionForTransfer($studentId, $fromGroup, $toGroup, $oldInvoiceData, $transferDate)
    {
        try {
            // إذا كان لديك جدول للأرشفة
            if (Schema::hasTable('deleted_invoices_archive')) {
                DeletedInvoiceArchive::create([
                    'original_invoice_id' => $oldInvoiceData['invoice_id'],
                    'invoice_number' => $oldInvoiceData['invoice_number'],
                    'student_id' => $studentId,
                    'group_id' => $fromGroup->group_id,
                    'amount' => $oldInvoiceData['amount'],
                    'amount_paid' => $oldInvoiceData['amount_paid'],
                    'discount_amount' => $oldInvoiceData['discount_amount'],
                    'discount_percent' => $oldInvoiceData['discount_percent'],
                    'status' => $oldInvoiceData['status'],
                    'due_date' => $oldInvoiceData['due_date'],
                    'deleted_reason' => 'student_transfer',
                    'transferred_to_group_id' => $toGroup->group_id,
                    'transfer_date' => $transferDate,
                    'deleted_by' => auth()->id(),
                    'original_created_at' => $oldInvoiceData['created_at'],
                    'deleted_at' => now(),
                ]);

                Log::info('Old invoice archived before deletion', [
                    'invoice_id' => $oldInvoiceData['invoice_id'],
                    'archive_reason' => 'student_transfer',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to archive deleted invoice: '.$e->getMessage());
            // لا توقف العملية إذا فشل الأرشفة
        }
    }

    /**
     * تحديث رواتب المدرسين بعد النقل
     */
    private function updateTeacherSalariesAfterTransfer($fromGroupId, $toGroupId, $studentId)
    {
        try {
            Log::info('Updating teacher salaries after transfer', [
                'from_group' => $fromGroupId,
                'to_group' => $toGroupId,
                'student_id' => $studentId,
            ]);

            // تحديث راتب مجموعة المصدر
            $fromGroup = Group::withCount('students')->find($fromGroupId);
            if ($fromGroup) {
                $this->updateSalaryForGroup($fromGroup);
            }

            // تحديث راتب مجموعة الوجهة
            $toGroup = Group::withCount('students')->find($toGroupId);
            if ($toGroup) {
                $this->updateSalaryForGroup($toGroup);
            }

            Log::info('Teacher salaries updated successfully after transfer');

        } catch (\Exception $e) {
            Log::error('Error updating salaries after transfer: '.$e->getMessage());
            throw $e;
        }
    }

    public function validateSalaries(Request $request)
    {
        try {
            $issues = [];

            // 1. التحقق من الرواتب المكررة
            $duplicateSalaries = DB::table('salaries')
                ->select('group_id', 'month', DB::raw('COUNT(*) as count'))
                ->groupBy('group_id', 'month')
                ->having('count', '>', 1)
                ->get();

            if ($duplicateSalaries->count() > 0) {
                $issues[] = [
                    'type' => 'duplicate_salaries',
                    'count' => $duplicateSalaries->count(),
                    'details' => $duplicateSalaries,
                ];
            }

            // 2. التحقق من تطابق حسابات الرواتب
            $incorrectCalculations = Salary::with(['group', 'teacher'])
                ->get()
                ->filter(function ($salary) {
                    if (! $salary->group) {
                        return false;
                    }

                    $studentCount = $salary->group->students()->count();
                    $expectedRevenue = $salary->group->price * $studentCount;

                    $teacherPercentage = $salary->group->teacher_percentage ??
                        ($salary->teacher->salary_percentage ?? 0);
                    $expectedTeacherShare = round(($teacherPercentage / 100) * $expectedRevenue, 2);

                    return $salary->group_revenue != $expectedRevenue ||
                           $salary->teacher_share != $expectedTeacherShare;
                });

            if ($incorrectCalculations->count() > 0) {
                $issues[] = [
                    'type' => 'incorrect_calculations',
                    'count' => $incorrectCalculations->count(),
                    'details' => $incorrectCalculations->map(function ($salary) {
                        return [
                            'salary_id' => $salary->salary_id,
                            'group_id' => $salary->group_id,
                            'current_revenue' => $salary->group_revenue,
                            'current_share' => $salary->teacher_share,
                        ];
                    }),
                ];
            }

            // 3. التحقق من مدفوعات الرواتب مقابل teacher_payments
            $paymentMismatches = DB::table('salaries as s')
                ->leftJoin('teacher_payments as tp', function ($join) {
                    $join->on('s.salary_id', '=', 'tp.salary_id')
                        ->where('tp.amount', '>', 0);
                })
                ->select('s.salary_id', 's.teacher_share', DB::raw('SUM(COALESCE(tp.amount, 0)) as total_paid'))
                ->groupBy('s.salary_id', 's.teacher_share')
                ->havingRaw('SUM(COALESCE(tp.amount, 0)) > s.teacher_share')
                ->get();

            if ($paymentMismatches->count() > 0) {
                $issues[] = [
                    'type' => 'payment_mismatches',
                    'count' => $paymentMismatches->count(),
                    'details' => $paymentMismatches,
                ];
            }

            return response()->json([
                'success' => true,
                'issues_found' => count($issues),
                'issues' => $issues,
                'message' => count($issues) > 0 ?
                    'Found issues that need attention' :
                    'All salaries are valid',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: '.$e->getMessage(),
            ], 500);
        }
    }

    private function updateSalaryForGroup(Group $group)
    {
        try {
            // جلب جميع الرواتب لهذا الجروب وتحديث كل منها
            $salaries = Salary::where('group_id', $group->group_id)->get();

            $studentCount = $group->students_count ?? $group->students()->count();
            $monthlyGroupRevenue = $group->price * $studentCount;

            $teacherPercentage = $group->teacher_percentage ?? 0;
            if ($teacherPercentage == 0) {
                $teacher = Teacher::find($group->teacher_id);
                $teacherPercentage = $teacher->salary_percentage ?? 0;
            }

            $monthlyTeacherShare = round(($teacherPercentage / 100) * $monthlyGroupRevenue, 2);

            foreach ($salaries as $salary) {
                $salary->update([
                    'group_revenue' => $monthlyGroupRevenue,
                    'teacher_share' => $monthlyTeacherShare,
                    'net_salary' => $monthlyTeacherShare,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Updated salary for group', [
                'group_id' => $group->group_id,
                'student_count' => $studentCount,
                'group_revenue' => $monthlyGroupRevenue,
                'teacher_share' => $monthlyTeacherShare,
                'salaries_updated' => $salaries->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update salary for group '.$group->group_id.': '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث رواتب المدرسين مع معالجة شاملة للتغييرات
     */
    private function updateTeacherSalaryComprehensive(Group $group, array $validStudentIds)
    {
        try {
            // بدلاً من حذف جميع الرواتب، قم بتحديث الرواتب الموجودة فقط
            $existingSalaries = Salary::where('group_id', $group->group_id)->get();

            if ($existingSalaries->isEmpty()) {
                // إذا لم توجد رواتب، أنشئ راتباً جديداً
                return $this->createStrictSingleSalary($group);
            }

            // تحديث كل راتب موجود
            foreach ($existingSalaries as $salary) {
                // حساب الراتب الجديد بناءً على عدد الطلاب الحالي
                $studentCount = count($validStudentIds);
                $monthlyGroupRevenue = $group->price * $studentCount;
                $monthlyTeacherShare = round((($group->teacher_percentage ?? 0) / 100) * $monthlyGroupRevenue, 2);

                $salary->update([
                    'group_revenue' => $monthlyGroupRevenue,
                    'teacher_share' => $monthlyTeacherShare,
                    'net_salary' => $monthlyTeacherShare,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Teacher salaries updated successfully', [
                'group_id' => $group->group_id,
                'salaries_updated' => $existingSalaries->count(),
                'student_count' => count($validStudentIds),
            ]);

            return $existingSalaries;

        } catch (\Exception $e) {
            Log::error('Failed to update teacher salary comprehensively: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث فواتير الطلاب عند تغيير سعر الجروب مع معالجة الخصم
     */
    private function updateStudentInvoicesForPriceChangeComprehensive(Group $group, $oldPrice, $newPrice)
    {
        try {
            $invoices = Invoice::where('group_id', $group->group_id)->get();

            $updatedCount = 0;

            foreach ($invoices as $invoice) {
                // حساب الفرق في السعر
                $priceDifference = $newPrice - $oldPrice;

                // احسب النسبة المئوية للخصم القديم
                $oldDiscountPercent = ($oldPrice > 0) ?
                    (($invoice->discount_amount / $oldPrice) * 100) : 0;

                // احسب المبلغ الجديد بعد الخصم
                $newAmount = $invoice->amount + $priceDifference;

                // احسب خصم جديد بناءً على النسبة القديمة
                $newDiscountAmount = round(($oldDiscountPercent / 100) * $newAmount, 2);

                // التأكد من أن المبلغ الجديد ليس أقل من المبلغ المدفوع
                $remainingAmount = $newAmount - $newDiscountAmount;
                if ($remainingAmount < $invoice->amount_paid) {
                    $newDiscountAmount = $newAmount - $invoice->amount_paid;
                    if ($newDiscountAmount < 0) {
                        $newDiscountAmount = 0;
                    }
                }

                // تحديث الفاتورة
                $invoice->update([
                    'amount' => $newAmount,
                    'discount_amount' => $newDiscountAmount,
                    'discount_percent' => round($oldDiscountPercent, 2),
                    'notes' => ($invoice->notes ?? '')." | تم تحديث السعر من {$oldPrice} إلى {$newPrice} مع تعديل الخصم بتاريخ ".now()->format('Y-m-d H:i:s'),
                ]);

                // تحديث حالة الفاتورة
                $this->updateInvoiceStatus($invoice);

                $updatedCount++;
            }

            Log::info('Updated student invoices comprehensively', [
                'group_id' => $group->group_id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'invoices_updated' => $updatedCount,
            ]);

            return $updatedCount;

        } catch (\Exception $e) {
            Log::error('Error updating student invoices comprehensively: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث حالة الفاتورة بناءً على المبلغ المدفوع والمتبقي
     */
    private function updateInvoiceStatus(Invoice $invoice)
    {
        $netAmount = $invoice->amount - $invoice->discount_amount;
        $remaining = $netAmount - $invoice->amount_paid;

        if ($remaining <= 0) {
            $status = 'paid';
        } elseif ($invoice->amount_paid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        $invoice->update(['status' => $status]);

        return $status;
    }

    private function createInvoicesForStudents(Group $group, array $studentIds)
    {
        try {
            $students = Student::whereIn('student_id', $studentIds)->get();

            foreach ($students as $student) {
                // التحقق من عدم وجود فاتورة سابقة لهذا الطالب في نفس المجموعة
                $existingInvoice = Invoice::where('student_id', $student->student_id)
                    ->where('group_id', $group->group_id)
                    ->first();

                if (! $existingInvoice) {
                    $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

                    Invoice::create([
                        'student_id' => $student->student_id,
                        'group_id' => $group->group_id,
                        'invoice_number' => $invoiceNumber,
                        'description' => 'Group fee: '.$group->group_name,
                        'amount' => $group->price,
                        'amount_paid' => 0,
                        'due_date' => $group->start_date ? $group->start_date : now()->addDays(7)->toDateString(),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info('Created new invoice for student', [
                        'student_id' => $student->student_id,
                        'group_id' => $group->group_id,
                        'amount' => $group->price,
                    ]);
                }
            }

            Log::info('Created invoices for new students', [
                'group_id' => $group->group_id,
                'student_count' => count($studentIds),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create invoices for new students in group '.$group->group_id.': '.$e->getMessage());
            throw $e;
        }
    }

    public function destroy($id)
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        $group = Group::findOrFail($id);

        DB::beginTransaction();
        try {
            // 1. حذف جميع المدفوعات المرتبطة بالفواتير الخاصة بالمجموعة
            $invoiceIds = DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->pluck('invoice_id');

            if ($invoiceIds->isNotEmpty()) {
                // حذف المدفوعات المرتبطة بالفواتير
                DB::table('payments')
                    ->whereIn('invoice_id', $invoiceIds)
                    ->delete();
            }

            // 2. حذف سجلات teacher_payments المرتبطة بالرواتب أولاً
            $salaryIds = DB::table('salaries')
                ->where('group_id', $group->group_id)
                ->pluck('salary_id');

            if ($salaryIds->isNotEmpty()) {
                // حذف مدفوعات المدرسين المرتبطة بالرواتب أولاً
                if (Schema::hasTable('teacher_payments')) {
                    DB::table('teacher_payments')
                        ->whereIn('salary_id', $salaryIds)
                        ->delete();
                }

                // حذف الرواتب بعد حذف المدفوعات المرتبطة
                DB::table('salaries')
                    ->where('group_id', $group->group_id)
                    ->delete();
            }

            // 3. حذف جميع سجلات الحضور المرتبطة بالجلسات الخاصة بالمجموعة
            $sessionIds = $group->sessions()->pluck('session_id');
            if ($sessionIds->isNotEmpty()) {
                DB::table('attendance')->whereIn('session_id', $sessionIds)->delete();
            }

            // 4. حذف جميع التقييمات المرتبطة بالجلسات
            if ($sessionIds->isNotEmpty()) {
                DB::table('ratings')->whereIn('session_id', $sessionIds)->delete();
            }

            // 5. حذف جميع مواد الجلسات
            if ($sessionIds->isNotEmpty()) {
                DB::table('session_materials')->whereIn('session_id', $sessionIds)->delete();
            }

            // 6. حذف جميع الواجبات والمسابقات المرتبطة بالجلسات
            $assignmentIds = DB::table('assignments')
                ->whereIn('session_id', $sessionIds)
                ->pluck('assignment_id');

            if ($assignmentIds->isNotEmpty()) {
                // حذف تسليمات الواجبات
                DB::table('assignment_submissions')
                    ->whereIn('assignment_id', $assignmentIds)
                    ->delete();

                // حذف الواجبات
                DB::table('assignments')
                    ->whereIn('session_id', $sessionIds)
                    ->delete();
            }

            // 7. حذف المسابقات والبيانات المرتبطة بها
            $quizIds = DB::table('quizzes')
                ->whereIn('session_id', $sessionIds)
                ->pluck('quiz_id');

            if ($quizIds->isNotEmpty()) {
                // حذف الأسئلة والخيارات والإجابات
                $questionIds = DB::table('questions')
                    ->whereIn('quiz_id', $quizIds)
                    ->pluck('question_id');

                if ($questionIds->isNotEmpty()) {
                    // حذف إجابات المسابقات
                    if (Schema::hasTable('quiz_answers')) {
                        $optionIds = DB::table('options')
                            ->whereIn('question_id', $questionIds)
                            ->pluck('option_id');

                        if ($optionIds->isNotEmpty()) {
                            DB::table('quiz_answers')
                                ->whereIn('option_id', $optionIds)
                                ->delete();
                        }
                    }

                    // حذف الخيارات
                    DB::table('options')
                        ->whereIn('question_id', $questionIds)
                        ->delete();

                    // حذف الأسئلة
                    DB::table('questions')
                        ->whereIn('quiz_id', $quizIds)
                        ->delete();
                }

                // حذف محاولات المسابقات
                if (Schema::hasTable('quiz_attempts')) {
                    DB::table('quiz_attempts')
                        ->whereIn('quiz_id', $quizIds)
                        ->delete();
                }

                // حذف المسابقات
                DB::table('quizzes')
                    ->whereIn('session_id', $sessionIds)
                    ->delete();
            }

            // 8. حذف جميع الجلسات
            $group->sessions()->delete();

            // 9. حذف الفواتير المرتبطة بالمجموعة
            DB::table('invoices')
                ->where('group_id', $group->group_id)
                ->delete();

            // 10. فصل جميع الطلاب عن المجموعة
            $group->students()->detach();

            // 11. أخيراً: حذف المجموعة نفسها
            $group->delete();

            DB::commit();

            return redirect()->route('groups.index')->with('success', 'Group and all related records deleted successfully!');

        } catch (\Exception $e) {
            DB::rollback();

            $errorMessage = 'Error deleting group: '.$e->getMessage();

            Log::error('Force delete group failed: '.$e->getMessage(), [
                'group_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $limit = 15;

            // استخدام select محدد لتقليل البيانات المطلوبة
            $query = Group::select([
                'group_id',
                'group_name',
                'course_id',
                'subcourse_id',
                'teacher_id',
                'schedule',
                'price',
                'teacher_percentage',
                'start_date',
                'end_date',
            ])
                ->with([
                    'course:course_id,course_name',
                    'subcourse:subcourse_id,subcourse_name,subcourse_number,course_id',
                    'teacher:teacher_id,teacher_name',
                    'students:student_id,student_name',
                ]);

            // Search functionality - محسنة للأداء
            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('group_name', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('course', function ($q) use ($search) {
                            $q->where('course_name', 'LIKE', '%'.$search.'%');
                        })
                        ->orWhereHas('subcourse', function ($q) use ($search) {
                            $q->where('subcourse_name', 'LIKE', '%'.$search.'%');
                        })
                        ->orWhereHas('teacher', function ($q) use ($search) {
                            $q->where('teacher_name', 'LIKE', '%'.$search.'%');
                        })
                        ->orWhere('schedule', 'LIKE', '%'.$search.'%');
                });
            }

            // جلب العدد الإجمالي قبل التصفية
            $total = $query->count();

            // استخدام get بدلاً من paginate للأداء
            $groups = $query->orderBy('group_id', 'DESC')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get();

            // معالجة البيانات بشكل أكثر كفاءة
            $groupsData = $this->processGroupsData($groups);

            return response()->json([
                'groups' => $groupsData,
                'total' => $total,
                'page' => (int) $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in groups fetch: '.$e->getMessage());

            return response()->json([
                'error' => 'Server error: '.$e->getMessage(),
                'groups' => [],
                'total' => 0,
                'page' => 1,
                'limit' => $limit,
            ], 500);
        }
    }

    /**
     * معالجة بيانات المجموعات بشكل منفصل لأداء أفضل
     */
    private function processGroupsData($groups)
    {
        $today = Carbon::today();
        $groupsData = [];

        foreach ($groups as $group) {
            try {
                $studentCount = $group->students->count();
                $price = $group->price ?? 0;
                $teacherPercentage = $group->teacher_percentage ?? 0;

                $totalRevenue = $price * $studentCount;
                $teacherShare = round(($teacherPercentage / 100) * $totalRevenue, 2);

                // حساب الحالة
                $status = $this->calculateGroupStatus($group->start_date, $group->end_date, $today);

                // اسم الـ subcourse
                $subcourseName = $group->subcourse ?
                    ($group->subcourse->subcourse_name ?:
                     'Part '.$group->subcourse->subcourse_number) :
                    'N/A';

                $groupsData[] = [
                    'group_id' => $group->group_id,
                    'group_name' => $group->group_name ?? 'N/A',
                    'course_name' => $group->course->course_name ?? 'N/A',
                    'subcourse_name' => $subcourseName,
                    'teacher_name' => $group->teacher->teacher_name ?? 'N/A',
                    'schedule' => $group->schedule ?? 'N/A',
                    'price' => $price,
                    'teacher_percentage' => $teacherPercentage,
                    'teacher_share' => $teacherShare,
                    'academy_share' => round($totalRevenue - $teacherShare, 2),
                    'student_count' => $studentCount,
                    'total_revenue' => $totalRevenue,
                    'start_date' => $group->start_date?->format('Y-m-d'),
                    'end_date' => $group->end_date?->format('Y-m-d'),
                    'status' => $status,
                ];
            } catch (\Exception $e) {
                Log::error('Error processing group: '.$group->group_id, ['error' => $e->getMessage()]);

                $groupsData[] = [
                    'group_id' => $group->group_id ?? 'error',
                    'group_name' => 'Error loading group',
                    'course_name' => 'Error',
                    'subcourse_name' => 'Error',
                    'teacher_name' => 'Error',
                    'schedule' => 'Error',
                    'price' => 0,
                    'teacher_percentage' => 0,
                    'teacher_share' => 0,
                    'academy_share' => 0,
                    'student_count' => 0,
                    'total_revenue' => 0,
                    'start_date' => null,
                    'end_date' => null,
                    'status' => 'Error',
                ];
            }
        }

        return $groupsData;
    }

    public function getGroup($id)
    {
        $group = Group::with(['students', 'subcourse'])->findOrFail($id, [
            'group_id', 'group_name', 'course_id', 'subcourse_id', 'teacher_id',
            'schedule', 'start_date', 'end_date', 'price', 'teacher_percentage',
        ]);

        $studentCount = $group->students->count();
        $totalRevenue = $group->price * $studentCount;
        $teacherShare = round((($group->teacher_percentage ?? 0) / 100) * $totalRevenue, 2);

        return response()->json([
            'group_id' => $group->group_id,
            'group_name' => $group->group_name,
            'course_id' => $group->course_id,
            'subcourse_id' => $group->subcourse_id,
            'teacher_id' => $group->teacher_id,
            'schedule' => $group->schedule,
            'start_date' => $group->start_date?->format('Y-m-d'),
            'end_date' => $group->end_date?->format('Y-m-d'),
            'price' => $group->price,
            'teacher_percentage' => $group->teacher_percentage ?? 0,
            'teacher_share' => $teacherShare,
            'academy_share' => round($totalRevenue - $teacherShare, 2),
            'student_count' => $studentCount,
            'total_revenue' => $totalRevenue,
            'students' => $group->students->pluck('student_id')->toArray(),
        ]);
    }

    public function getSubcoursesByCourse($courseId)
    {
        try {
            $courseQuery = Course::query();
            if (is_numeric($courseId)) {
                $course = $courseQuery->where('course_id', $courseId)->first();
            } else {
                $course = $courseQuery->where('uuid', $courseId)->first();
            }

            if (! $course) {
                return response()->json(['subcourses' => []]);
            }

            $subcourses = Subcourse::where('course_id', $course->course_id)
                ->with('course:course_id,course_name')
                ->orderBy('subcourse_number')
                ->get()
                ->map(function ($subcourse) {
                    $displayName = $subcourse->subcourse_name ??
                        ($subcourse->course->course_name.' - Part '.$subcourse->subcourse_number);

                    return [
                        'subcourse_id' => $subcourse->subcourse_id,
                        'subcourse_number' => $subcourse->subcourse_number,
                        'subcourse_name' => $subcourse->subcourse_name,
                        'course_name' => $subcourse->course->course_name,
                        'display_name' => $displayName,
                    ];
                });

            return response()->json([
                'success' => true,
                'subcourses' => $subcourses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading subcourses: '.$e->getMessage(),
            ], 500);
        }
    }

    public function searchStudents(Request $request)
    {
        try {
            $searchTerm = $request->get('q', '');

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'results' => [],
                ]);
            }

            $students = Student::whereHas('user', function ($query) use ($searchTerm) {
                $query->where('username', 'LIKE', "%{$searchTerm}%");
            })
                ->orWhere('student_id', 'LIKE', "%{$searchTerm}%")
                ->with('user:user_id,username')
                ->select('student_id as id', 'student_name as text')
                ->limit(10)
                ->get();

            // تعديل النتيجة لتضمين username
            $formattedStudents = $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'text' => $student->user->username.' - '.$student->text,
                ];
            });

            return response()->json([
                'success' => true,
                'results' => $formattedStudents,
            ]);

        } catch (\Exception $e) {
            Log::error('Student search error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Search failed: '.$e->getMessage(),
                'results' => [],
            ], 500);
        }
    }

    public function addSession(Request $request, Group $group)
    {
        // Check if user is teacher and owns this group
        if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
            abort(403, 'You do not have permission to add sessions to this group.');
        }

        $request->validate([
            'session_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'topic' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'requires_proximity' => 'boolean',
            'meetings' => 'nullable|array',
            'meetings.*.title' => 'required|string|max:255',
            'meetings.*.link' => 'required|url',
        ]);

        // Flexible date checks - allow but log warning if outside window
        $sessionDate = \Carbon\Carbon::parse($request->session_date);
        $groupStart = \Carbon\Carbon::parse($group->start_date);
        $groupEnd = \Carbon\Carbon::parse($group->end_date);

        if ($sessionDate->lt($groupStart) || $sessionDate->gt($groupEnd)) {
             // We allow sessions outside the official range for flexibility (e.g. makeup sessions)
             Log::info("Session added outside official group date range for group {$group->group_id}");
        }

        // Prevent duplicate session (same group, date, start, end)
        $exists = $group->sessions()->where('session_date', $request->session_date)
            ->where('start_time', $request->start_time)
            ->where('end_time', $request->end_time)
            ->exists();
        if ($exists) {
            return redirect()->back()->with('error', 'A session with the same date and time already exists for this group.');
        }

        $session = $group->sessions()->create([
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'topic' => $request->topic,
            'notes' => $request->notes,
            'requires_proximity' => $request->has('requires_proximity') ? $request->requires_proximity : true,
            'created_by' => auth()->id(),
        ]);

        if ($request->has('meetings')) {
            foreach ($request->meetings as $meetingData) {
                \App\Models\SessionMeeting::create([
                    'session_id' => $session->session_id,
                    'title' => $meetingData['title'],
                    'meeting_link' => $meetingData['link']
                ]);
            }
        }

        return redirect()->back()->with('success', 'Session added successfully!');
    }

    public function editSession(Group $group, $sessionId)
    {
        // Check permissions
        if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
            abort(403, 'You do not have permission to edit sessions in this group.');
        }

        $session = $group->sessions()->findOrFail($sessionId);

        return view('sessions.edit', compact('group', 'session'));
    }

    public function updateSession(Request $request, Group $group, $sessionId)
    {
        // Check permissions
        if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
            abort(403, 'You do not have permission to update sessions in this group.');
        }

        $session = $group->sessions()->findOrFail($sessionId);

        $request->validate([
            'session_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'topic' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'requires_proximity' => 'boolean',
        ]);

        // Check if session date is within group date range
        if ($request->session_date < $group->start_date || $request->session_date > $group->end_date) {
            return redirect()->back()->with('error', "Session date ({$request->session_date}) is outside the group date range ({$group->start_date} - {$group->end_date}).");
        }

        $session->update([
            'session_date' => $request->session_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'topic' => $request->topic,
            'notes' => $request->notes,
            'requires_proximity' => $request->has('requires_proximity') ? $request->requires_proximity : true,
        ]);

        return redirect()->route('groups.show', $group)->with('success', 'Session updated successfully!');
    }

    public function deleteSession(Request $request, $sessionId)
    {
        try {
            $session = Session::findOrFail($sessionId);
            $group = $session->group;

            // Check permissions
            if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete sessions from this group.',
                ], 403);
            }

            // Additional checks for teachers
            if (auth()->user()->role_id == 2) {
                $session_date = $session->session_date;
                $today = Carbon::today();
                $is_session_today_or_future = ($session_date >= $today);
                $has_ratings = $session->ratings()->where('rating_type', 'session')->exists();

                if (! $is_session_today_or_future || $has_ratings) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete this session. It may be in the past or has ratings.',
                    ], 422);
                }
            }

            // Use transaction for safety
            DB::beginTransaction();

            // Step 1: Delete quiz-related data (deepest level first)
            $quizIds = DB::table('quizzes')
                ->where('session_id', $session->session_id)
                ->pluck('quiz_id');

            if ($quizIds->isNotEmpty()) {
                // Get all question IDs for these quizzes
                $questionIds = DB::table('questions')
                    ->whereIn('quiz_id', $quizIds)
                    ->pluck('question_id');

                if ($questionIds->isNotEmpty()) {
                    // Delete from quiz_answers if table exists
                    if (Schema::hasTable('quiz_answers')) {
                        $optionIds = DB::table('options')
                            ->whereIn('question_id', $questionIds)
                            ->pluck('option_id');

                        if ($optionIds->isNotEmpty()) {
                            DB::table('quiz_answers')
                                ->whereIn('option_id', $optionIds)
                                ->delete();
                        }
                    }

                    // Delete options
                    DB::table('options')
                        ->whereIn('question_id', $questionIds)
                        ->delete();

                    // Delete questions
                    DB::table('questions')
                        ->whereIn('quiz_id', $quizIds)
                        ->delete();
                }

                // Delete quiz attempts if table exists
                if (Schema::hasTable('quiz_attempts')) {
                    DB::table('quiz_attempts')
                        ->whereIn('quiz_id', $quizIds)
                        ->delete();
                }

                // Delete quizzes
                DB::table('quizzes')
                    ->where('session_id', $session->session_id)
                    ->delete();
            }

            // Step 2: Delete assignment-related data
            $assignmentIds = DB::table('assignments')
                ->where('session_id', $session->session_id)
                ->pluck('assignment_id');

            if ($assignmentIds->isNotEmpty()) {
                // Delete assignment submissions
                DB::table('assignment_submissions')
                    ->whereIn('assignment_id', $assignmentIds)
                    ->delete();

                // Delete assignments
                DB::table('assignments')
                    ->where('session_id', $session->session_id)
                    ->delete();
            }

            // Step 3: Delete attendance records
            DB::table('attendance')
                ->where('session_id', $session->session_id)
                ->delete();

            // Step 4: Delete ratings
            DB::table('ratings')
                ->where('session_id', $session->session_id)
                ->delete();

            // Step 5: Delete session materials
            DB::table('session_materials')
                ->where('session_id', $session->session_id)
                ->delete();

            // Step 6: Finally delete the session
            $session->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Session and all related content deleted successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting session: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error deleting session: '.$e->getMessage(),
            ], 500);
        }
    }

    public function details(Request $request)
    {
        $groupId = $request->query('id');

        $group = Group::with(['course', 'teacher', 'students', 'sessions.assignments.submissions'])->findOrFail($groupId);

        // Check permissions - teachers can only see their own groups
        if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
            abort(403, 'You do not have permission to view this group.');
        }

        return view('groups.details', compact('group'));
    }

    public function getStudentRatings(Group $group, $studentId)
    {
        try {
            // التحقق من الصلاحيات
            if (auth()->user()->role_id == 2 && auth()->id() != $group->teacher->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $ratings = Rating::with('session')
                ->whereHas('session', function ($query) use ($group) {
                    $query->where('group_id', $group->group_id);
                })
                ->where('student_id', '=', $studentId)
                ->where('rating_type', '=', 'session')
                ->get()
                ->map(function ($rating) {
                    return [
                        'session_date' => $rating->session->session_date->format('M d, Y'),
                        'rating_value' => $rating->rating_value,
                        'comments' => $rating->comments,
                    ];
                });

            $averageRating = $ratings->avg('rating_value');

            return response()->json([
                'success' => true,
                'ratings' => $ratings,
                'average_rating' => $averageRating ? round($averageRating, 1) : 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createInvoice(Request $request)
    {
        if (auth()->user()->role_id != 1) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'teacher_id' => 'required|integer',
            'group_id' => 'required|integer',
            'students' => 'required|array',
            'students.*' => 'integer',
        ]);

        DB::beginTransaction();
        try {
            $teacher = Teacher::findOrFail($request->teacher_id);
            $group = Group::findOrFail($request->group_id);
            $students = $request->students;

            // إنشاء فواتير للطلاب المحددين
            $createdInvoices = [];
            foreach ($students as $studentId) {
                $student = Student::find($studentId);
                if ($student) {
                    // التحقق من عدم وجود فاتورة سابقة
                    $existingInvoice = Invoice::where('student_id', $studentId)
                        ->where('group_id', $group->group_id)
                        ->first();

                    if (! $existingInvoice) {
                        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

                        $invoice = Invoice::create([
                            'student_id' => $studentId,
                            'group_id' => $group->group_id,
                            'invoice_number' => $invoiceNumber,
                            'description' => 'Group fee: '.$group->group_name,
                            'amount' => $group->price,
                            'amount_paid' => 0,
                            'due_date' => $group->start_date ?: now()->addDays(7)->toDateString(),
                            'status' => 'pending',
                        ]);

                        $createdInvoices[] = $invoice;
                    }
                }
            }

            // تحديث راتب المدرس
            $this->updateTeacherSalaryForInvoices($group, $students);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($createdInvoices).' invoice(s) created successfully!',
                'invoices' => $createdInvoices,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating invoices: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error creating invoices: '.$e->getMessage(),
            ], 500);
        }
    }

    private function updateTeacherSalaryForInvoices(Group $group, array $studentIds)
    {
        try {
            $studentCount = count($studentIds);
            $groupRevenue = $group->price * $studentCount;
            $teacherShare = round((($group->teacher_percentage ?? 0) / 100) * $groupRevenue, 2);

            // البحث عن سجل الراتب الحالي
            $salary = Salary::where('group_id', $group->group_id)->first();

            if ($salary) {
                $salary->update([
                    'group_revenue' => $groupRevenue,
                    'teacher_share' => $teacherShare,
                    'net_salary' => $teacherShare,
                    'updated_by' => auth()->id(),
                ]);
            } else {
                Salary::create([
                    'teacher_id' => $group->teacher_id,
                    'month' => date('Y-m'),
                    'group_id' => $group->group_id,
                    'group_revenue' => $groupRevenue,
                    'teacher_share' => $teacherShare,
                    'deductions' => 0,
                    'bonuses' => 0,
                    'net_salary' => $teacherShare,
                    'status' => 'pending',
                    'payment_date' => null,
                    'updated_by' => auth()->id(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update teacher salary for invoices: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * جلب الجروبات حسب المدرس
     */
    public function getGroupsByTeacher($teacherId)
    {
        try {
            $groups = Group::where('teacher_id', $teacherId)
                ->with([
                    'course:course_id,course_name',
                    'subcourse:subcourse_id,subcourse_name,subcourse_number',
                    'students',
                ])
                ->get(['group_id', 'group_name', 'course_id', 'subcourse_id', 'teacher_id', 'price', 'teacher_percentage']);

            return response()->json([
                'success' => true,
                'groups' => $groups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading groups: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء راتب لجروب محدد - مع التحقق من التكرار
     */

    /**
     * جلب عدد الرواتب الموجودة لجروب معين
     */
    public function getSalaryCount($groupId)
    {
        try {
            $count = Salary::where('group_id', $groupId)->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'count' => 0,
            ]);
        }
    }

    // في App\Http\Controllers\GroupsController.php

    /**
     * التحقق من أن الطالب دافع جميع فواتيره
     */
    private function checkStudentInvoices($studentId)
    {
        try {
            // جلب جميع الفواتير غير المدفوعة للطالب
            $unpaidInvoices = Invoice::where('student_id', $studentId)
                ->where('status', '!=', 'paid')
                ->where('amount_paid', '<', DB::raw('amount'))
                ->exists();

            return ! $unpaidInvoices;

        } catch (\Exception $e) {
            Log::error('Error checking student invoices: '.$e->getMessage());

            return false;
        }
    }

    /**
     * التحقق من صلاحيات المستخدم
     */
    private function isSuperAdmin()
    {
        return auth()->user()->role_id == 1; // افترضنا أن 1 هو السوبر أدمن
    }

    // دالة لإنشاء schedule للجروب مباشرة
    private function createGroupSchedule(Group $group, Request $request)
    {
        try {
            // التحقق من توافر الغرفة في نفس التوقيت
            if (! $this->checkRoomAvailability(
                $request->room_id,
                $request->day_of_week,
                $request->start_time,
                $request->end_time,
                null,
                $group->start_date,
                $group->end_date
            )) {
                throw new \Exception('Room is not available at this time');
            }

            // إنشاء Schedule للجروب
            $schedule = Schedule::create([
                'group_id' => $group->group_id,
                'room_id' => $request->room_id,
                'day_of_week' => $request->day_of_week,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'start_date' => $group->start_date,
                'end_date' => $group->end_date,
                'is_active' => 1,
            ]);

            return $schedule;
        } catch (\Exception $e) {
            Log::error('Failed to create schedule for group: '.$e->getMessage());
            throw $e;
        }
    }

    // دالة للتحقق من توافر الغرفة
    private function checkRoomAvailability($roomId, $dayOfWeek, $startTime, $endTime, $excludeScheduleId = null, $startDate = null, $endDate = null)
    {
        if (!$roomId) return true; // Online groups don't have rooms
        $query = Schedule::where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', 1);

        // إذا كانت هناك تواريخ محددة
        if ($startDate && $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
            });
        }

        // التحقق من التعارض في الوقت
        $query->where(function ($q) use ($startTime, $endTime) {
            $q->where(function ($q2) use ($startTime, $endTime) {
                $q2->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            });
        });

        if ($excludeScheduleId) {
            $query->where('schedule_id', '!=', $excludeScheduleId);
        }

        return $query->count() === 0;
    }

    /**
     * API endpoint for checking schedule availability (AJAX)
     */
    public function checkScheduleAvailability(Request $request)
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

            // استخدم الدالة الموجودة checkRoomAvailability
            $available = $this->checkRoomAvailability(
                $request->room_id,
                $request->day_of_week,
                $request->start_time,
                $request->end_time,
                null, // excludeScheduleId
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'available' => $available,
                'message' => $available ? 'Room is available' : 'Room is not available at this time',
            ]);

        } catch (\Exception $e) {
            Log::error('Schedule availability check error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Validation error: '.$e->getMessage(),
            ], 422);
        }
    }

    public function getAllWaitingGroups()
    {
        try {
            $groups = DB::table('waiting_groups')
                ->select([
                    'id',
                    'group_name',
                    'course_id',
                    'subcourse_id',
                    'booking_id',
                    'placement_exam_grade',
                    'assigned_level',
                    'is_active',
                    'created_at',
                    'updated_at',
                ])
                ->where('is_active', 1)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'groups' => $groups,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading waiting groups: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * عرض نموذج إنشاء مجموعة جديدة من مجموعة انتظار
     */
    public function createFromWaiting($waitingGroupId)
    {
        try {
            // جلب مجموعة الانتظار
            $waitingGroup = WaitingGroup::with([
                'course',
                'subcourse',
                'waitingStudents.student.user',
                'waitingStudents.student.studentGroups.group',
            ])->findOrFail($waitingGroupId);

            // جلب الطلاب من مجموعة الانتظار
            $waitingStudents = $waitingGroup->waitingStudents;

            // استخراج IDs الطلاب
            $studentIds = $waitingStudents->pluck('student_id')->toArray();

            // جلب بيانات الطلاب الكاملة
            $students = Student::whereIn('student_id', $studentIds)
                ->with(['user', 'invoices'])
                ->get();

            // جلب باقي البيانات المطلوبة للنموذج
            $courses = Course::all();
            $teachers = Teacher::all();
            $rooms = Room::where('is_active', 1)->get();

            // حساب بيانات التكلفة والتقديرات
            foreach ($students as $student) {
                $student->total_paid = $student->invoices()->sum('amount_paid');
                $student->total_required = $student->invoices()->sum('amount');
                $student->has_debt = $student->total_paid < $student->total_required;
            }

            // إعداد بيانات افتراضية من مجموعة الانتظار
            $defaultData = [
                'group_name' => $waitingGroup->group_name.' - مفعلة',
                'course_id' => $waitingGroup->course_id,
                'subcourse_id' => $waitingGroup->subcourse_id,
                'description' => "تم التفعيل من مجموعة الانتظار: {$waitingGroup->group_name}",
                'students' => $studentIds,
                'waiting_group_id' => $waitingGroupId, // إضافة معرف مجموعة الانتظار
            ];

            return view('groups.create', compact(
                'courses',
                'teachers',
                'students',
                'rooms',
                'defaultData',
                'waitingGroup'
            ));

        } catch (\Exception $e) {
            Log::error('Error creating group from waiting: '.$e->getMessage());

            return redirect()->route('waiting-groups.index')
                ->with('error', 'حدث خطأ في تحويل مجموعة الانتظار: '.$e->getMessage());
        }
    }
    /**
     * حساب حالة المجموعة ديناميكياً بناءً على التاريخ
     */
    private function calculateGroupStatus($startDate, $endDate, $today = null)
    {
        if (! $startDate || ! $endDate) {
            return 'Unknown';
        }

        $today = Carbon::today();
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($today->lt($start)) {
            return 'Not Started';
        } elseif ($today->gt($end)) {
            return 'Completed';
        } else {
            // حساب الأيام المتبقية بدقة
            $daysRemaining = $today->diffInDays($end, false); // false للحصول على الفرق مع الأخذ في الاعتبار السالب

            if ($daysRemaining <= 7 && $daysRemaining >= 0) {
                return 'Almost Done';
            } elseif ($daysRemaining > 7) {
                return 'In Progress';
            } else {
                // إذا كان daysRemaining سالب (مستحيل لأننا في شرط $today->gt($end) يغطيه)
                return 'Completed';
            }
        }
    }

    /**
     * تحديث الفاتورة بعد النقل - مع إضافة created_by للمستخدم الحالي
     */
    private function updateInvoiceForTransferWithLoginFix($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('Updating invoice for transfer - WITH LOGIN FIX', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'transfer_date' => $transferDate,
                'current_user' => auth()->id(), // ✅ تسجيل المستخدم الحالي
            ]);

            // 1. البحث عن الفاتورة القديمة
            $oldInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->whereIn('status', ['paid', 'partial', 'pending'])
                ->first();

            // حساب المبلغ المدفوع والخصم
            $amountPaid = 0;
            $discountAmount = 0;
            $discountPercent = 0;

            if ($oldInvoice) {
                // حفظ بيانات الفاتورة القديمة
                $oldInvoiceData = [
                    'invoice_id' => $oldInvoice->invoice_id,
                    'invoice_number' => $oldInvoice->invoice_number,
                    'original_amount' => $oldInvoice->amount,
                    'amount_paid' => $oldInvoice->amount_paid,
                    'discount_amount' => $oldInvoice->discount_amount,
                    'discount_percent' => $oldInvoice->discount_percent,
                    'status' => $oldInvoice->status,
                    'created_by' => $oldInvoice->created_by, // ✅ حفظ من أنشأ القديمة
                    'created_at' => $oldInvoice->created_at,
                ];

                $amountPaid = $oldInvoice->amount_paid ?? 0;
                $discountAmount = $oldInvoice->discount_amount ?? 0;
                $discountPercent = $oldInvoice->discount_percent ?? 0;

                // 2. ✅ **تحديث الفاتورة القديمة لتكون ملغية**
                $oldInvoice->update([
                    'status' => 'cancelled',
                    'notes' => "تم إلغاء الفاتورة بعد نقل الطالب إلى المجموعة {$toGroup->group_id}",
                    'updated_at' => now(),
                    'updated_by' => auth()->id(), // ✅ تحديث من قام بالإلغاء
                ]);

                Log::info('Old invoice marked as cancelled', [
                    'invoice_id' => $oldInvoice->invoice_id,
                    'old_amount' => $oldInvoiceData['original_amount'],
                    'amount_paid' => $amountPaid,
                    'old_created_by' => $oldInvoiceData['created_by'],
                    'cancelled_by' => auth()->id(),
                    'reason' => 'student_transfer_to_new_group',
                ]);

            } else {
                Log::info('No active invoice found for student in old group', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                ]);
            }

            // 3. التحقق من عدم وجود فاتورة نشطة للمجموعة الجديدة
            $existingInvoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $toGroup->group_id)
                ->whereIn('status', ['pending', 'partial', 'paid'])
                ->first();

            if (! $existingInvoice) {
                // 4. ✅ **إنشاء فاتورة جديدة مع تعيين created_by للمستخدم الحالي**
                $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
                $dueDate = now()->addDays(30)->format('Y-m-d');
                $newAmount = $toGroup->price;

                // حساب الحالة الجديدة
                $newAmountPaid = $amountPaid;
                if ($amountPaid >= $newAmount) {
                    $status = 'paid';
                } elseif ($amountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'pending';
                }

                $description = 'رسوم مجموعة: '.$toGroup->group_name;
                $notes = "فاتورة بعد نقل الطالب من المجموعة {$fromGroup->group_id}";
                if ($amountPaid > 0) {
                    $notes .= " - تم نقل مبلغ {$amountPaid} من المدفوعات السابقة";
                }
                if ($discountAmount > 0) {
                    $notes .= " - تم نقل خصم بقيمة {$discountAmount}";
                }

                // ✅ **هذا هو التصحيح المهم**: تعيين created_by للمستخدم الحالي
                $newInvoice = Invoice::create([
                    'student_id' => $studentId,
                    'group_id' => $toGroup->group_id,
                    'invoice_number' => $invoiceNumber,
                    'description' => $description,
                    'amount' => $newAmount,
                    'amount_paid' => $newAmountPaid,
                    'discount_amount' => $discountAmount,
                    'discount_percent' => $discountPercent,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'issued_date' => $transferDate,
                    'notes' => $notes,
                    'created_by' => auth()->id(), // ✅ تعيين المستخدم الحالي كمنشئ
                    'updated_by' => auth()->id(), // ✅ تعيين المستخدم الحالي كحدث
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('New invoice created with current user as creator', [
                    'student_id' => $studentId,
                    'old_group_id' => $fromGroup->group_id,
                    'new_group_id' => $toGroup->group_id,
                    'new_invoice_id' => $newInvoice->invoice_id,
                    'new_invoice_amount' => $newAmount,
                    'transferred_amount_paid' => $newAmountPaid,
                    'invoice_status' => $status,
                    'created_by' => auth()->id(), // ✅ تسجيل من أنشأ الجديدة
                    'invoice_number' => $invoiceNumber,
                ]);

                // 5. إذا كان هناك مدفوعات في الفاتورة القديمة، نسجلها
                if ($amountPaid > 0 && $oldInvoice) {
                    $this->transferPaymentsWithLogin($oldInvoice, $newInvoice, $transferDate);
                }

            } else {
                // إذا كانت هناك فاتورة موجودة، نضيف المدفوعات إليها
                $newAmountPaid = $existingInvoice->amount_paid + $amountPaid;
                $newDiscountAmount = $existingInvoice->discount_amount + $discountAmount;

                // تحديث حالة الفاتورة
                if ($newAmountPaid >= $existingInvoice->amount - $newDiscountAmount) {
                    $status = 'paid';
                } elseif ($newAmountPaid > 0) {
                    $status = 'partial';
                } else {
                    $status = 'pending';
                }

                $existingInvoice->update([
                    'amount_paid' => $newAmountPaid,
                    'discount_amount' => $newDiscountAmount,
                    'status' => $status,
                    'updated_by' => auth()->id(), // ✅ تحديث من قام بالتعديل
                    'notes' => $existingInvoice->notes." - تم نقل مبلغ {$amountPaid} من مجموعة أخرى",
                ]);

                Log::info('Existing invoice updated with transferred payment', [
                    'invoice_id' => $existingInvoice->invoice_id,
                    'old_amount_paid' => $existingInvoice->amount_paid,
                    'new_amount_paid' => $newAmountPaid,
                    'added_amount' => $amountPaid,
                    'updated_by' => auth()->id(),
                ]);
            }

            Log::info('Invoice transfer completed successfully with login fix');

        } catch (\Exception $e) {
            Log::error('Error updating invoice for transfer: '.$e->getMessage(), [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'current_user' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * نقل المدفوعات مع تحديث created_by
     */
    private function transferPaymentsWithLogin($oldInvoice, $newInvoice, $transferDate)
    {
        try {
            if (! class_exists(Payment::class)) {
                return;
            }

            $payments = Payment::where('invoice_id', $oldInvoice->invoice_id)->get();

            foreach ($payments as $payment) {
                // إنشاء سجل دفع جديد مع تعيين created_by للمستخدم الحالي
                Payment::create([
                    'invoice_id' => $newInvoice->invoice_id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'transaction_id' => $payment->transaction_id.'-TRANSFERRED',
                    'payment_date' => $payment->payment_date,
                    'notes' => "تم نقل هذا الدفع من الفاتورة رقم {$oldInvoice->invoice_number}",
                    'created_by' => auth()->id(), // ✅ تعيين المستخدم الحالي
                    'created_at' => $payment->created_at,
                    'updated_at' => now(),
                ]);
            }

            Log::info('Payments transferred with login fix', [
                'from_invoice' => $oldInvoice->invoice_id,
                'to_invoice' => $newInvoice->invoice_id,
                'payments_count' => $payments->count(),
                'transferred_by' => auth()->id(),
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to transfer payments: '.$e->getMessage());
            // لا توقف العملية إذا فشل نقل سجلات المدفوعات
        }
    }

    public function fetchData(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', '');
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 15);

        // الفلاتر المتقدمة
        $priceRange = $request->input('price_range', '');
        $teacherId = $request->input('teacher_id', '');
        $dayOfWeek = $request->input('day_of_week', '');
        $duration = $request->input('duration', '');
        $courseId = $request->input('course_id', '');
        $studentCount = $request->input('student_count', '');
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');

        $today = Carbon::today();

        $query = Group::with(['course', 'subcourse', 'teacher', 'students'])
            ->select('groups.*');

        // البحث الأساسي
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('group_name', 'LIKE', "%{$search}%")
                    ->orWhere('schedule', 'LIKE', "%{$search}%")
                    ->orWhereHas('course', function ($q) use ($search) {
                        $q->where('course_name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('teacher', function ($q) use ($search) {
                        $q->where('teacher_name', 'LIKE', "%{$search}%");
                    });
            });
        }

        // فلتر الحالة
        if (! empty($status)) {
            switch ($status) {
                case 'Not Started':
                    $query->whereDate('start_date', '>', $today);
                    break;
                case 'In Progress':
                    $query->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today)
                        ->whereRaw('DATEDIFF(end_date, ?) > 7', [$today->toDateString()]);
                    break;
                case 'Almost Done':
                    $query->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today)
                        ->whereRaw('DATEDIFF(end_date, ?) <= 7', [$today->toDateString()])
                        ->whereRaw('DATEDIFF(end_date, ?) >= 0', [$today->toDateString()]);
                    break;
                case 'Completed':
                    $query->whereDate('end_date', '<', $today);
                    break;
            }
        }

        // الفلاتر المتقدمة

        // فلتر السعر
        if (! empty($priceRange)) {
            switch ($priceRange) {
                case '0-1000':
                    $query->whereBetween('price', [0, 1000]);
                    break;
                case '1001-3000':
                    $query->whereBetween('price', [1001, 3000]);
                    break;
                case '3001-5000':
                    $query->whereBetween('price', [3001, 5000]);
                    break;
                case '5001-10000':
                    $query->whereBetween('price', [5001, 10000]);
                    break;
                case '10001+':
                    $query->where('price', '>', 10000);
                    break;
            }
        }

        // فلتر المدرس
        if (! empty($teacherId)) {
            $query->where('teacher_id', $teacherId);
        }

        // فلتر اليوم (من الجدول الزمني)
        if (! empty($dayOfWeek)) {
            $query->whereRaw('LOWER(schedule) LIKE ?', ["%{$dayOfWeek}%"]);
        }

        // فلتر المدة
        if (! empty($duration)) {
            $query->whereRaw('DATEDIFF(end_date, start_date) > 0');

            switch ($duration) {
                case 'short':
                    $query->whereRaw('DATEDIFF(end_date, start_date) <= 14'); // 1-2 أسابيع
                    break;
                case 'medium':
                    $query->whereRaw('DATEDIFF(end_date, start_date) BETWEEN 15 AND 42'); // 3-6 أسابيع
                    break;
                case 'long':
                    $query->whereRaw('DATEDIFF(end_date, start_date) > 42'); // أكثر من 6 أسابيع
                    break;
            }
        }

        // فلتر الكورس
        if (! empty($courseId)) {
            $query->where('course_id', $courseId);
        }

        // فلتر عدد الطلاب
        if (! empty($studentCount)) {
            $query->withCount('students')->having('students_count', '>', 0);

            switch ($studentCount) {
                case '0-5':
                    $query->having('students_count', '<=', 5);
                    break;
                case '6-10':
                    $query->having('students_count', '>=', 6)
                        ->having('students_count', '<=', 10);
                    break;
                case '11-20':
                    $query->having('students_count', '>=', 11)
                        ->having('students_count', '<=', 20);
                    break;
                case '21+':
                    $query->having('students_count', '>=', 21);
                    break;
            }
        }

        // فلتر تاريخ البدء
        if (! empty($startDate)) {
            $query->whereDate('start_date', '>=', $startDate);
        }

        // فلتر تاريخ الانتهاء
        if (! empty($endDate)) {
            $query->whereDate('end_date', '<=', $endDate);
        }

        // الحصول على العدد الكلي
        $total = $query->count();

        // الترتيب
        $query->orderByRaw('
        CASE 
            WHEN start_date <= ? AND end_date >= ? THEN 1
            WHEN start_date > ? THEN 2
            ELSE 3
        END ASC,
        start_date ASC
    ', [$today, $today, $today])
            ->skip(($page - 1) * $limit)
            ->take($limit);

        $groups = $query->get();

        // معالجة البيانات
        $processedGroups = $groups->map(function ($group) use ($today) {
            $status = $this->calculateGroupStatus($group->start_date, $group->end_date, $today);

            return [
                'group_id' => $group->group_id,
                'group_name' => $group->group_name,
                'course_name' => $group->course->course_name ?? 'N/A',
                'subcourse_name' => $group->subcourse ?
                    ($group->subcourse->subcourse_name ?? "Part {$group->subcourse->subcourse_number}") :
                    'N/A',
                'teacher_name' => $group->teacher->teacher_name ?? 'N/A',
                'schedule' => $group->schedule,
                'price' => $group->price,
                'student_count' => $group->students->count(),
                'start_date' => $group->start_date,
                'end_date' => $group->end_date,
                'status' => $status,
                'teacher_percentage' => $group->teacher_percentage,
            ];
        });

        return response()->json([
            'groups' => $processedGroups,
            'total' => $total,
            'page' => (int) $page,
            'limit' => (int) $limit,
            'total_pages' => ceil($total / $limit),
            'filters_applied' => [
                'price_range' => $priceRange,
                'teacher_id' => $teacherId,
                'day_of_week' => $dayOfWeek,
                'duration' => $duration,
                'course_id' => $courseId,
                'student_count' => $studentCount,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * تنفيذ نقل الطالب - النسخة النهائية المصححة
     */
    /**
     * تنفيذ نقل الطالب - النسخة المحسنة
     */
    /**
     * تنفيذ نقل الطالب - النسخة النهائية مع تحديث الرواتب
     */
    public function transferStudent(Request $request)
    {
        // التحقق من الصلاحيات
        if (auth()->user()->role_id != 1) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بهذا الإجراء',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // التحقق من صحة البيانات
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,student_id',
                'from_group_id' => 'required|exists:groups,group_id',
                'to_group_id' => 'required|exists:groups,group_id',
                'transfer_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صالحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $studentId = $request->student_id;
            $fromGroupId = $request->from_group_id;
            $toGroupId = $request->to_group_id;
            $transferDate = $request->transfer_date ?? now()->format('Y-m-d');
            $notes = $request->notes ?? 'تم نقل الطالب بين المجموعات';

            Log::info('Starting SIMPLE student transfer (only change group_id)', [
                'student_id' => $studentId,
                'from_group_id' => $fromGroupId,
                'to_group_id' => $toGroupId,
                'user_id' => auth()->id(),
            ]);

            // 1. التأكد من أن المجموعات مختلفة
            if ($fromGroupId == $toGroupId) {
                throw new \Exception('لا يمكن نقل الطالب لنفس المجموعة');
            }

            $student = Student::findOrFail($studentId);
            $fromGroup = Group::with('students')->findOrFail($fromGroupId);
            $toGroup = Group::with('students')->findOrFail($toGroupId);

            // 2. التحقق من أن الطالب موجود في المجموعة الأصلية
            if (! $fromGroup->students->contains('student_id', $studentId)) {
                throw new \Exception('الطالب غير موجود في المجموعة الأصلية');
            }

            // 3. التحقق من أن الطالب غير موجود مسبقاً في المجموعة الجديدة
            if ($toGroup->students->contains('student_id', $studentId)) {
                throw new \Exception('الطالب موجود بالفعل في المجموعة الجديدة');
            }

            // 4. جلب الفاتورة القديمة
            $invoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->where('status', '!=', 'cancelled')
                ->first();

            $oldInvoiceData = null;
            if ($invoice) {
                $oldInvoiceData = [
                    'old_group_id' => $invoice->group_id,
                    'old_amount' => $invoice->amount,
                    'old_status' => $invoice->status,
                    'amount_paid' => $invoice->amount_paid,
                    'discount_amount' => $invoice->discount_amount,
                ];

                // 5. ✅ **فقط تغيير group_id في الفاتورة نفسها + تحديث السعر**
                $priceDifference = $toGroup->price - $fromGroup->price;
                $newInvoiceAmount = $toGroup->price;

                // تحديث الفاتورة الحالية (بدون إنشاء جديدة)
                $invoice->update([
                    'group_id' => $toGroup->group_id, // ✅ تغيير group_id فقط
                    'amount' => $newInvoiceAmount, // ✅ تحديث السعر الجديد
                    'description' => 'رسوم مجموعة: '.$toGroup->group_name,
                    'notes' => ($invoice->notes ?? '').
                             "\n🔄 تم نقل الطالب من مجموعة #{$fromGroup->group_id} إلى #{$toGroup->group_id}".
                             "\n📅 تاريخ النقل: {$transferDate}".
                             "\n💰 تغيير السعر: ".number_format((float) $fromGroup->price, 2).
                             ' → '.number_format((float) $toGroup->price, 2).' جنيه'.
                             ($priceDifference > 0 ? ' (+'.number_format((float) $priceDifference, 2).')' :
                              ($priceDifference < 0 ? ' ('.number_format((float) $priceDifference, 2).')' : '')).
                             "\n💳 المبلغ المدفوع: ".number_format((float) $invoice->amount_paid, 2).' جنيه'.
                             "\n📝 ملاحظات النقل: {$notes}",
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

                // 6. ✅ **تحديث حالة الفاتورة بناءً على السعر الجديد**
                $this->updateInvoiceStatusAfterPriceChange($invoice, $fromGroup->price, $toGroup->price);
            } else {
                Log::warning('No invoice found for student, creating new one', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroupId,
                ]);

                // إذا مفيش فاتورة، ننشئ واحدة جديدة
                $invoice = $this->createNewSimpleInvoice($studentId, $toGroup, $transferDate);
            }

            // 7. ✅ **تحديث الطلاب في الجدول الوسيط (pivot table)**
            $fromGroup->students()->detach($studentId);
            $toGroup->students()->attach($studentId);

            // 8. ✅ **تحديث الرواتب (group_revenue)**
            $salaryUpdates = $this->updateSalariesForSimpleTransfer($fromGroup, $toGroup);

            // 9. ✅ **تسجيل النقل**
            $this->logSimpleTransfer($studentId, $fromGroup, $toGroup, $transferDate, $notes, $oldInvoiceData);

            DB::commit();

            Log::info('Student transferred SUCCESSFULLY - SIMPLE VERSION', [
                'student_id' => $studentId,
                'student_name' => $student->student_name,
                'from_group' => $fromGroup->group_name,
                'to_group' => $toGroup->group_name,
                'invoice_updated' => $oldInvoiceData ? 'نعم' : 'لا',
                'salary_updates' => $salaryUpdates,
            ]);

            return response()->json([
                'success' => true,
                'message' => '✅ تم نقل الطالب بنجاح',
                'data' => [
                    'student_id' => $studentId,
                    'student_name' => $student->student_name,
                    'from_group' => [
                        'id' => $fromGroup->group_id,
                        'name' => $fromGroup->group_name,
                        'price' => $fromGroup->price,
                        'remaining_students' => $fromGroup->students()->count(),
                        'revenue_change' => $salaryUpdates['from']['revenue_change'] ?? 0,
                    ],
                    'to_group' => [
                        'id' => $toGroup->group_id,
                        'name' => $toGroup->group_name,
                        'price' => $toGroup->price,
                        'total_students' => $toGroup->students()->count(),
                        'revenue_change' => $salaryUpdates['to']['revenue_change'] ?? 0,
                    ],
                    'invoice' => [
                        'id' => $invoice->invoice_id,
                        'new_group_id' => $toGroup->group_id,
                        'old_group_id' => $fromGroup->group_id,
                        'old_amount' => $oldInvoiceData['old_amount'] ?? $fromGroup->price,
                        'new_amount' => $invoice->amount,
                        'amount_paid' => $invoice->amount_paid,
                        'status' => $invoice->status,
                        'price_difference' => $toGroup->price - $fromGroup->price,
                    ],
                    'salaries' => $salaryUpdates,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('ERROR in simple student transfer: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '❌ حدث خطأ أثناء نقل الطالب: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * جلب بيانات الرواتب قبل التحديث
     */
    private function getSalariesBeforeTransfer($fromGroup, $toGroup)
    {
        $fromSalary = Salary::where('group_id', $fromGroup->group_id)->first();
        $toSalary = Salary::where('group_id', $toGroup->group_id)->first();

        return [
            'from' => [
                'revenue' => number_format((float) ($fromSalary->group_revenue ?? 0), 2),
                'teacher_share' => number_format((float) ($fromSalary->teacher_share ?? 0), 2),
                'student_count' => $fromGroup->students()->count(),
            ],
            'to' => [
                'revenue' => number_format((float) ($toSalary->group_revenue ?? 0), 2),
                'teacher_share' => number_format((float) ($toSalary->teacher_share ?? 0), 2),
                'student_count' => $toGroup->students()->count(),
            ],
        ];
    }

    private function updateInvoiceStatusAfterPriceChange($invoice, $oldPrice, $newPrice)
    {
        try {
            // حساب الرصيد بعد التحديث
            $netAmount = $invoice->amount - ($invoice->discount_amount ?? 0);
            $remaining = max(0, $netAmount - $invoice->amount_paid);

            // تحديث الحالة
            $newStatus = 'pending';
            if ($remaining <= 0) {
                $newStatus = 'paid';
            } elseif ($invoice->amount_paid > 0) {
                $newStatus = 'partial';
            }

            if ($invoice->status != $newStatus) {
                $invoice->update([
                    'status' => $newStatus,
                    'notes' => $invoice->notes.
                             "\n🔄 تم تحديث الحالة من '{$invoice->status}' إلى '{$newStatus}' بسبب تغيير السعر",
                ]);

                Log::info('Invoice status updated after price change', [
                    'invoice_id' => $invoice->invoice_id,
                    'old_status' => $invoice->status,
                    'new_status' => $newStatus,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'remaining' => $remaining,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error updating invoice status: '.$e->getMessage());
            // لا توقف العملية
        }
    }

    private function updateSalariesForSimpleTransfer($fromGroup, $toGroup)
    {
        try {
            // إعادة تحميل الطلاب بعد التحديث
            $fromGroup->load('students');
            $toGroup->load('students');

            $fromStudentCount = $fromGroup->students->count();
            $toStudentCount = $toGroup->students->count();

            // ✅ **حساب الإيرادات الجديدة**
            $fromNewRevenue = $fromGroup->price * $fromStudentCount;  // بعد خروج الطالب
            $toNewRevenue = $toGroup->price * $toStudentCount;        // بعد دخول الطالب

            Log::info('Calculating new revenues', [
                'from_group' => [
                    'price' => $fromGroup->price,
                    'old_student_count' => $fromStudentCount + 1, // +1 لأننا لم نحسب بعد detach
                    'new_student_count' => $fromStudentCount,
                    'old_revenue' => $fromGroup->price * ($fromStudentCount + 1),
                    'new_revenue' => $fromNewRevenue,
                    'revenue_change' => -$fromGroup->price,
                ],
                'to_group' => [
                    'price' => $toGroup->price,
                    'old_student_count' => $toStudentCount - 1, // -1 لأننا لم نحسب بعد attach
                    'new_student_count' => $toStudentCount,
                    'old_revenue' => $toGroup->price * ($toStudentCount - 1),
                    'new_revenue' => $toNewRevenue,
                    'revenue_change' => +$toGroup->price,
                ],
            ]);

            // تحديث راتب المجموعة القديمة
            $fromSalaryUpdate = $this->updateSingleGroupSalary($fromGroup, $fromNewRevenue);

            // تحديث راتب المجموعة الجديدة
            $toSalaryUpdate = $this->updateSingleGroupSalary($toGroup, $toNewRevenue);

            return [
                'from' => [
                    'salary_id' => $fromSalaryUpdate['salary_id'],
                    'old_revenue' => $fromSalaryUpdate['old_revenue'],
                    'new_revenue' => $fromNewRevenue,
                    'revenue_change' => $fromNewRevenue - $fromSalaryUpdate['old_revenue'],
                    'student_count' => $fromStudentCount,
                    'price_per_student' => $fromGroup->price,
                ],
                'to' => [
                    'salary_id' => $toSalaryUpdate['salary_id'],
                    'old_revenue' => $toSalaryUpdate['old_revenue'],
                    'new_revenue' => $toNewRevenue,
                    'revenue_change' => $toNewRevenue - $toSalaryUpdate['old_revenue'],
                    'student_count' => $toStudentCount,
                    'price_per_student' => $toGroup->price,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error updating salaries for simple transfer: '.$e->getMessage());
            throw $e;
        }
    }

    private function updateSingleGroupSalary($group, $newRevenue)
    {
        try {
            $salary = Salary::where('group_id', $group->group_id)->first();

            if (! $salary) {
                Log::warning('No salary found for group, creating new one', ['group_id' => $group->group_id]);

                // حساب حصة المدرس
                $teacherPercentage = $group->teacher_percentage ?? 0;
                if ($teacherPercentage == 0 && $group->teacher) {
                    $teacherPercentage = $group->teacher->salary_percentage ?? 0;
                }

                $teacherShare = round(($teacherPercentage / 100) * $newRevenue, 2);

                $salary = Salary::create([
                    'teacher_id' => $group->teacher_id,
                    'month' => now()->format('Y-m'),
                    'group_id' => $group->group_id,
                    'group_revenue' => $newRevenue,
                    'teacher_share' => $teacherShare,
                    'deductions' => 0,
                    'bonuses' => 0,
                    'net_salary' => $teacherShare,
                    'status' => 'pending',
                    'updated_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return [
                    'salary_id' => $salary->salary_id,
                    'old_revenue' => 0,
                    'old_teacher_share' => 0,
                ];
            }

            $oldRevenue = $salary->group_revenue;

            // حساب حصة المدرس الجديدة
            $teacherPercentage = $group->teacher_percentage ?? 0;
            if ($teacherPercentage == 0 && $group->teacher) {
                $teacherPercentage = $group->teacher->salary_percentage ?? 0;
            }

            $newTeacherShare = round(($teacherPercentage / 100) * $newRevenue, 2);

            // تحديث الراتب
            $salary->update([
                'group_revenue' => $newRevenue,
                'teacher_share' => $newTeacherShare,
                'net_salary' => $newTeacherShare,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            Log::info('Salary updated for simple transfer', [
                'group_id' => $group->group_id,
                'salary_id' => $salary->salary_id,
                'old_revenue' => $oldRevenue,
                'new_revenue' => $newRevenue,
                'revenue_change' => $newRevenue - $oldRevenue,
                'old_teacher_share' => $salary->teacher_share,
                'new_teacher_share' => $newTeacherShare,
            ]);

            return [
                'salary_id' => $salary->salary_id,
                'old_revenue' => $oldRevenue,
                'old_teacher_share' => $salary->teacher_share,
            ];

        } catch (\Exception $e) {
            Log::error('Error updating single group salary: '.$e->getMessage());
            throw $e;
        }
    }

    private function createNewSimpleInvoice($studentId, $toGroup, $transferDate)
    {
        if ($toGroup->isFree()) {
            return null;
        }

        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $toGroup->price,
            'amount_paid' => 0,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'pending',
            'issued_date' => $transferDate,
            'notes' => "فاتورة جديدة بعد نقل الطالب\n📅 تاريخ النقل: {$transferDate}",
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('New simple invoice created', [
            'invoice_id' => $invoice->invoice_id,
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'amount' => $toGroup->price,
        ]);

        return $invoice;
    }

    private function logSimpleTransfer($studentId, $fromGroup, $toGroup, $transferDate, $notes, $invoiceData)
    {
        try {
            if (Schema::hasTable('student_transfers')) {
                StudentTransfer::create([
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                    'to_group_id' => $toGroup->group_id,
                    'transfer_date' => $transferDate,
                    'notes' => $notes,
                    'invoice_data' => json_encode($invoiceData),
                    'transfer_type' => 'simple_group_id_change',
                    'transferred_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Simple transfer logged', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to log simple transfer: '.$e->getMessage());
        }
    }

    /**
     * نقل الفاتورة مع التحقق من الاختلافات المالية
     */
    private function transferStudentInvoiceComplete($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('TRANSFERRING STUDENT - COMPLETE VERSION', [
                'student_id' => $studentId,
                'from_group_price' => $fromGroup->price,
                'to_group_price' => $toGroup->price,
                'price_difference' => $toGroup->price - $fromGroup->price,
            ]);

            // 1. البحث عن الفاتورة القديمة
            $invoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if (! $invoice) {
                Log::warning('No invoice found for transfer, creating new one', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                ]);

                // إذا لم توجد فاتورة، ننشئ واحدة جديدة بسعر المجموعة الجديدة
                $newInvoice = $this->createCompleteInvoice($studentId, $toGroup);

                return [
                    'invoice_id' => $newInvoice->invoice_id,
                    'price_changed' => true,
                    'financial_status' => 'new_student',
                    'new_invoice_amount' => $toGroup->price,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'student_balance' => 0,
                    'price_difference' => 0,
                ];
            }

            Log::info('Found existing invoice to transfer', [
                'invoice_id' => $invoice->invoice_id,
                'old_group_id' => $invoice->group_id,
                'new_group_id' => $toGroup->group_id,
                'old_amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'discount_amount' => $invoice->discount_amount,
                'old_status' => $invoice->status,
            ]);

            // 2. حساب الاختلاف في السعر
            $priceDifference = $toGroup->price - $fromGroup->price;
            $oldAmount = $invoice->amount;
            $amountPaid = $invoice->amount_paid;
            $discountAmount = $invoice->discount_amount;

            // 3. حساب المبلغ الجديد للفاتورة
            $newInvoiceAmount = $toGroup->price;

            // 4. حساب الرصيد المالي للطالب في الفاتورة القديمة
            $oldNetAmount = $oldAmount - $discountAmount;
            $oldBalance = max(0, $oldNetAmount - $amountPaid);
            $oldOverpayment = max(0, $amountPaid - $oldNetAmount);

            // 5. إنشاء فاتورة جديدة مع البيانات المحدثة
            $newInvoice = $this->createCompleteInvoiceWithTransfer(
                $studentId,
                $toGroup,
                $transferDate,
                $amountPaid,
                $discountAmount,
                $invoice->discount_percent,
                $fromGroup,
                $priceDifference,
                $oldBalance,
                $oldOverpayment
            );

            // 6. تحديث الفاتورة القديمة لتكون ملغية
            $invoice->update([
                'status' => 'cancelled',
                'notes' => ($invoice->notes ?? '').
                         "\n🚫 تم إلغاء الفاتورة بعد نقل الطالب إلى المجموعة #{$toGroup->group_id} بتاريخ {$transferDate}",
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            // 7. حساب رصيد الطالب الحالي في الفاتورة الجديدة
            $newNetAmount = $newInvoice->amount - $newInvoice->discount_amount;
            $newBalance = max(0, $newNetAmount - $newInvoice->amount_paid);
            $newOverpayment = max(0, $newInvoice->amount_paid - $newNetAmount);

            $financialStatus = 'balanced';
            if ($newBalance > 0) {
                $financialStatus = 'has_debt';
            } elseif ($newOverpayment > 0) {
                $financialStatus = 'has_credit';
            }

            Log::info('Complete invoice transfer completed', [
                'old_invoice_id' => $invoice->invoice_id,
                'new_invoice_id' => $newInvoice->invoice_id,
                'price_changed' => $priceDifference != 0,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'financial_status' => $financialStatus,
                'old_amount' => $oldAmount,
                'new_amount' => $newInvoiceAmount,
                'amount_paid' => $amountPaid,
                'price_difference' => $priceDifference,
            ]);

            return [
                'invoice_id' => $newInvoice->invoice_id,
                'price_changed' => $priceDifference != 0,
                'financial_status' => $financialStatus,
                'new_invoice_amount' => $newInvoiceAmount,
                'amount_paid' => $amountPaid,
                'status' => $newInvoice->status,
                'student_balance' => $newBalance,
                'student_credit' => $newOverpayment,
                'price_difference' => $priceDifference,
            ];

        } catch (\Exception $e) {
            Log::error('Error in complete invoice transfer: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث الرواتب بعد نقل الطالب
     */
    private function updateGroupSalariesAfterTransfer($fromGroup, $toGroup)
    {
        try {
            $fromGroup->load('students');
            $toGroup->load('students');

            $fromStudentCount = $fromGroup->students->count();
            $toStudentCount = $toGroup->students->count();

            $fromGroupRevenue = $fromGroup->price * $fromStudentCount;
            $toGroupRevenue = $toGroup->price * $toStudentCount;

            $fromTeacherPercentage = $fromGroup->teacher_percentage ?? 0;
            if ($fromTeacherPercentage == 0 && $fromGroup->teacher) {
                $fromTeacherPercentage = $fromGroup->teacher->salary_percentage ?? 0;
            }

            $toTeacherPercentage = $toGroup->teacher_percentage ?? 0;
            if ($toTeacherPercentage == 0 && $toGroup->teacher) {
                $toTeacherPercentage = $toGroup->teacher->salary_percentage ?? 0;
            }

            $fromTeacherShare = round(($fromTeacherPercentage / 100) * $fromGroupRevenue, 2);
            $toTeacherShare = round(($toTeacherPercentage / 100) * $toGroupRevenue, 2);

            // تحديث راتب المجموعة القديمة
            $fromSalaryUpdate = $this->updateGroupSalary($fromGroup);

            // تحديث راتب المجموعة الجديدة
            $toSalaryUpdate = $this->updateGroupSalary($toGroup);

            Log::info('Group salaries updated after transfer', [
                'from_group' => [
                    'id' => $fromGroup->group_id,
                    'old_revenue' => $fromSalaryUpdate['old_revenue'],
                    'new_revenue' => $fromGroupRevenue,
                    'student_count' => $fromStudentCount,
                    'revenue_change' => $fromGroupRevenue - $fromSalaryUpdate['old_revenue'],
                ],
                'to_group' => [
                    'id' => $toGroup->group_id,
                    'old_revenue' => $toSalaryUpdate['old_revenue'],
                    'new_revenue' => $toGroupRevenue,
                    'student_count' => $toStudentCount,
                    'revenue_change' => $toGroupRevenue - $toSalaryUpdate['old_revenue'],
                ],
            ]);

            return [
                'from' => [
                    'salary_id' => $fromSalaryUpdate['salary_id'],
                    'old_revenue' => $fromSalaryUpdate['old_revenue'],
                    'new_revenue' => $fromGroupRevenue,
                    'revenue_change' => $fromGroupRevenue - $fromSalaryUpdate['old_revenue'],
                    'student_count' => $fromStudentCount,
                    'teacher_percentage' => $fromTeacherPercentage,
                    'old_teacher_share' => $fromSalaryUpdate['old_teacher_share'],
                    'new_teacher_share' => $fromTeacherShare,
                ],
                'to' => [
                    'salary_id' => $toSalaryUpdate['salary_id'],
                    'old_revenue' => $toSalaryUpdate['old_revenue'],
                    'new_revenue' => $toGroupRevenue,
                    'revenue_change' => $toGroupRevenue - $toSalaryUpdate['old_revenue'],
                    'student_count' => $toStudentCount,
                    'teacher_percentage' => $toTeacherPercentage,
                    'old_teacher_share' => $toSalaryUpdate['old_teacher_share'],
                    'new_teacher_share' => $toTeacherShare,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error updating group salaries: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث راتب مجموعة محددة
     */
    private function updateGroupSalary(Group $group)
    {
        return app(SalaryService::class)->syncSalaryForGroup($group);
    }

    /**
     * إنشاء فاتورة جديدة بعد النقل مع التحقق المالي
     */
    private function createCompleteInvoiceWithTransfer($studentId, $toGroup, $transferDate, $amountPaid, $discountAmount, $discountPercent, $fromGroup, $priceDifference, $oldBalance, $oldOverpayment)
    {
        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
        $dueDate = now()->addDays(30)->format('Y-m-d');

        $newAmount = $toGroup->price;

        // حساب الحالة بناءً على المبلغ المدفوع والخصم
        $netAmount = $newAmount - $discountAmount;
        $remaining = max(0, $netAmount - $amountPaid);
        $overpayment = max(0, $amountPaid - $netAmount);

        // تحديد حالة الفاتورة
        if ($remaining <= 0) {
            $status = 'paid';
        } elseif ($amountPaid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        // بناء الملاحظات التفصيلية
        $notes = "🧾 فاتورة جديدة بعد نقل الطالب\n";
        $notes .= "📅 تاريخ النقل: {$transferDate}\n";
        $notes .= "🔄 نقل من: {$fromGroup->group_name} (#{$fromGroup->group_id})\n";
        $notes .= '💰 سعر المجموعة القديمة: '.number_format($fromGroup->price, 2)." جنيه\n";
        $notes .= '💰 سعر المجموعة الجديدة: '.number_format($toGroup->price, 2)." جنيه\n";

        if ($priceDifference > 0) {
            $notes .= '📈 زيادة في السعر: +'.number_format($priceDifference, 2)." جنيه\n";
        } elseif ($priceDifference < 0) {
            $notes .= '📉 نقصان في السعر: '.number_format($priceDifference, 2)." جنيه\n";
        } else {
            $notes .= "⚖️ السعر متساوي\n";
        }

        // إضافة ملاحظة عن تأثير الرواتب
        $notes .= "\n📊 تأثير على الرواتب:\n";
        $notes .= '• تم خصم '.number_format($fromGroup->price, 2).' جنيه من إيرادات مجموعة '.$fromGroup->group_name."\n";
        $notes .= '• تم إضافة '.number_format($toGroup->price, 2).' جنيه لإيرادات مجموعة '.$toGroup->group_name."\n";

        if ($amountPaid > 0) {
            $notes .= '💳 المبلغ المدفوع: '.number_format($amountPaid, 2)." جنيه\n";
        }

        if ($discountAmount > 0) {
            $notes .= '🎫 الخصم: '.number_format($discountAmount, 2)." جنيه\n";
        }

        // إضافة حالة الطالب المالية
        if ($oldBalance > 0) {
            $notes .= '⚠️ الطالب عليه دين من المجموعة السابقة: '.number_format($oldBalance, 2)." جنيه\n";
        } elseif ($oldOverpayment > 0) {
            $notes .= '✅ الطالب له رصيد من المجموعة السابقة: '.number_format($oldOverpayment, 2)." جنيه\n";
        }

        if ($remaining > 0) {
            $notes .= '💸 المبلغ المتبقي للدفع: '.number_format($remaining, 2)." جنيه\n";
        } elseif ($overpayment > 0) {
            $notes .= '➕ الطالب له رصيد إضافي: '.number_format($overpayment, 2)." جنيه\n";
        } else {
            $notes .= "✅ الحساب سليم - لا مدفوعات متبقية\n";
        }

        // إنشاء الفاتورة
        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $newAmount,
            'amount_paid' => $amountPaid,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'due_date' => $dueDate,
            'status' => $status,
            'issued_date' => $transferDate,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Complete invoice created for transfer', [
            'invoice_id' => $invoice->invoice_id,
            'student_id' => $studentId,
            'new_group_id' => $toGroup->group_id,
            'amount' => $newAmount,
            'amount_paid' => $amountPaid,
            'discount_amount' => $discountAmount,
            'status' => $status,
            'remaining_balance' => $remaining,
            'overpayment' => $overpayment,
        ]);

        return $invoice;
    }

    /**
     * تسجيل النقل الكامل
     */
    private function logTransferComplete($studentId, $fromGroup, $toGroup, $transferDate, $notes, $salaryUpdates)
    {
        try {
            if (Schema::hasTable('student_transfers')) {
                StudentTransfer::create([
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                    'to_group_id' => $toGroup->group_id,
                    'transfer_date' => $transferDate,
                    'notes' => $notes,
                    'salary_updates' => json_encode($salaryUpdates),
                    'transfer_type' => 'complete_transfer',
                    'transferred_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Complete transfer logged with salary updates', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
                'salary_changes' => $salaryUpdates,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to log complete transfer: '.$e->getMessage());
        }
    }

    /**
     * إنشاء فاتورة جديدة بسيطة
     */
    private function createCompleteInvoice($studentId, $toGroup)
    {
        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $toGroup->price,
            'amount_paid' => 0,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'pending',
            'issued_date' => now()->format('Y-m-d'),
            'notes' => 'فاتورة جديدة بعد نقل الطالب (بدون أي بيانات مالية سابقة)',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $invoice;
    }

    /**
     * نقل فاتورة الطالب مع التحقق من الاختلافات المالية
     */
    private function transferStudentInvoiceEnhanced($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('TRANSFERRING STUDENT - ENHANCED VERSION', [
                'student_id' => $studentId,
                'from_group_price' => $fromGroup->price,
                'to_group_price' => $toGroup->price,
                'price_difference' => $toGroup->price - $fromGroup->price,
            ]);

            // 1. البحث عن الفاتورة القديمة
            $invoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if (! $invoice) {
                Log::warning('No invoice found for transfer, creating new one', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                ]);

                // إذا لم توجد فاتورة، ننشئ واحدة جديدة بسعر المجموعة الجديدة
                $newInvoice = $this->createEnhancedInvoice($studentId, $toGroup);

                return [
                    'invoice_id' => $newInvoice->invoice_id,
                    'price_changed' => true,
                    'financial_status' => 'new_student',
                    'new_invoice_amount' => $toGroup->price,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'student_balance' => 0,
                    'price_difference' => 0,
                ];
            }

            Log::info('Found existing invoice to transfer', [
                'invoice_id' => $invoice->invoice_id,
                'old_group_id' => $invoice->group_id,
                'new_group_id' => $toGroup->group_id,
                'old_amount' => $invoice->amount,
                'amount_paid' => $invoice->amount_paid,
                'discount_amount' => $invoice->discount_amount,
                'old_status' => $invoice->status,
            ]);

            // 2. حساب الاختلاف في السعر
            $priceDifference = $toGroup->price - $fromGroup->price;
            $oldAmount = $invoice->amount;
            $amountPaid = $invoice->amount_paid;
            $discountAmount = $invoice->discount_amount;

            // 3. حساب المبلغ الجديد للفاتورة
            $newInvoiceAmount = $toGroup->price;

            // 4. حساب الرصيد المالي للطالب في الفاتورة القديمة
            $oldNetAmount = $oldAmount - $discountAmount;
            $oldBalance = max(0, $oldNetAmount - $amountPaid);
            $oldOverpayment = max(0, $amountPaid - $oldNetAmount);

            // 5. إنشاء فاتورة جديدة مع البيانات المحدثة
            $newInvoice = $this->createEnhancedInvoiceWithTransfer(
                $studentId,
                $toGroup,
                $transferDate,
                $amountPaid,
                $discountAmount,
                $invoice->discount_percent,
                $fromGroup,
                $priceDifference,
                $oldBalance,
                $oldOverpayment
            );

            // 6. تحديث الفاتورة القديمة لتكون ملغية
            $invoice->update([
                'status' => 'cancelled',
                'notes' => ($invoice->notes ?? '').
                         "\n🚫 تم إلغاء الفاتورة بعد نقل الطالب إلى المجموعة #{$toGroup->group_id} بتاريخ {$transferDate}",
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            // 7. حساب رصيد الطالب الحالي في الفاتورة الجديدة
            $newNetAmount = $newInvoice->amount - $newInvoice->discount_amount;
            $newBalance = max(0, $newNetAmount - $newInvoice->amount_paid);
            $newOverpayment = max(0, $newInvoice->amount_paid - $newNetAmount);

            $financialStatus = 'balanced';
            if ($newBalance > 0) {
                $financialStatus = 'has_debt';
            } elseif ($newOverpayment > 0) {
                $financialStatus = 'has_credit';
            }

            Log::info('Enhanced invoice transfer completed', [
                'old_invoice_id' => $invoice->invoice_id,
                'new_invoice_id' => $newInvoice->invoice_id,
                'price_changed' => $priceDifference != 0,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'financial_status' => $financialStatus,
                'old_amount' => $oldAmount,
                'new_amount' => $newInvoiceAmount,
                'amount_paid' => $amountPaid,
                'price_difference' => $priceDifference,
            ]);

            return [
                'invoice_id' => $newInvoice->invoice_id,
                'price_changed' => $priceDifference != 0,
                'financial_status' => $financialStatus,
                'new_invoice_amount' => $newInvoiceAmount,
                'amount_paid' => $amountPaid,
                'status' => $newInvoice->status,
                'student_balance' => $newBalance,
                'student_credit' => $newOverpayment,
                'price_difference' => $priceDifference,
            ];

        } catch (\Exception $e) {
            Log::error('Error in enhanced invoice transfer: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * إنشاء فاتورة جديدة بعد النقل مع التحقق المالي
     */
    private function createEnhancedInvoiceWithTransfer($studentId, $toGroup, $transferDate, $amountPaid, $discountAmount, $discountPercent, $fromGroup, $priceDifference, $oldBalance, $oldOverpayment)
    {
        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
        $dueDate = now()->addDays(30)->format('Y-m-d');

        $newAmount = $toGroup->price;

        // حساب الحالة بناءً على المبلغ المدفوع والخصم
        $netAmount = $newAmount - $discountAmount;
        $remaining = max(0, $netAmount - $amountPaid);
        $overpayment = max(0, $amountPaid - $netAmount);

        // تحديد حالة الفاتورة
        if ($remaining <= 0) {
            $status = 'paid';
        } elseif ($amountPaid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        // بناء الملاحظات التفصيلية
        $notes = "🧾 فاتورة جديدة بعد نقل الطالب\n";
        $notes .= "📅 تاريخ النقل: {$transferDate}\n";
        $notes .= "🔄 نقل من: {$fromGroup->group_name} (#{$fromGroup->group_id})\n";
        $notes .= '💰 سعر المجموعة القديمة: '.number_format($fromGroup->price, 2)." جنيه\n";
        $notes .= '💰 سعر المجموعة الجديدة: '.number_format($toGroup->price, 2)." جنيه\n";

        if ($priceDifference > 0) {
            $notes .= '📈 زيادة في السعر: +'.number_format($priceDifference, 2)." جنيه\n";
        } elseif ($priceDifference < 0) {
            $notes .= '📉 نقصان في السعر: '.number_format($priceDifference, 2)." جنيه\n";
        } else {
            $notes .= "⚖️ السعر متساوي\n";
        }

        if ($amountPaid > 0) {
            $notes .= '💳 المبلغ المدفوع: '.number_format($amountPaid, 2)." جنيه\n";
        }

        if ($discountAmount > 0) {
            $notes .= '🎫 الخصم: '.number_format($discountAmount, 2)." جنيه\n";
        }

        // إضافة حالة الطالب المالية
        if ($oldBalance > 0) {
            $notes .= '⚠️ الطالب عليه دين من المجموعة السابقة: '.number_format($oldBalance, 2)." جنيه\n";
        } elseif ($oldOverpayment > 0) {
            $notes .= '✅ الطالب له رصيد من المجموعة السابقة: '.number_format($oldOverpayment, 2)." جنيه\n";
        }

        if ($remaining > 0) {
            $notes .= '💸 المبلغ المتبقي للدفع: '.number_format($remaining, 2)." جنيه\n";
        } elseif ($overpayment > 0) {
            $notes .= '➕ الطالب له رصيد إضافي: '.number_format($overpayment, 2)." جنيه\n";
        } else {
            $notes .= "✅ الحساب سليم - لا مدفوعات متبقية\n";
        }

        // إنشاء الفاتورة
        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $newAmount,
            'amount_paid' => $amountPaid,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'due_date' => $dueDate,
            'status' => $status,
            'issued_date' => $transferDate,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Enhanced invoice created for transfer', [
            'invoice_id' => $invoice->invoice_id,
            'student_id' => $studentId,
            'new_group_id' => $toGroup->group_id,
            'amount' => $newAmount,
            'amount_paid' => $amountPaid,
            'discount_amount' => $discountAmount,
            'status' => $status,
            'remaining_balance' => $remaining,
            'overpayment' => $overpayment,
        ]);

        return $invoice;
    }

    /**
     * إنشاء فاتورة جديدة بسيطة
     */
    private function createEnhancedInvoice($studentId, $toGroup)
    {
        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $toGroup->price,
            'amount_paid' => 0,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'pending',
            'issued_date' => now()->format('Y-m-d'),
            'notes' => 'فاتورة جديدة بعد نقل الطالب (بدون أي بيانات مالية سابقة)',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $invoice;
    }

    /**
     * تسجيل عملية النقل
     */
    private function logTransfer($studentId, $fromGroup, $toGroup, $transferDate, $notes)
    {
        try {
            if (Schema::hasTable('student_transfers')) {
                StudentTransfer::create([
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                    'to_group_id' => $toGroup->group_id,
                    'transfer_date' => $transferDate,
                    'notes' => $notes,
                    'transfer_type' => 'simple_group_id_change',
                    'transferred_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Transfer logged successfully', [
                'student_id' => $studentId,
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to log transfer: '.$e->getMessage());
        }
    }

    /**
     * نقل فاتورة الطالب بشكل صحيح - هذا هو التصحيح الرئيسي
     */
    private function transferStudentInvoice($studentId, $fromGroup, $toGroup, $transferDate)
    {
        try {
            Log::info('TRANSFERRING STUDENT - SIMPLIFIED VERSION', [
                'student_id' => $studentId,
                'from_group_id' => $fromGroup->group_id,
                'to_group_id' => $toGroup->group_id,
            ]);

            // 1. البحث عن الفاتورة القديمة
            $invoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $fromGroup->group_id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if (! $invoice) {
                Log::warning('No invoice found for transfer', [
                    'student_id' => $studentId,
                    'from_group_id' => $fromGroup->group_id,
                ]);

                // إذا لم توجد فاتورة، ننشئ واحدة جديدة بسعر المجموعة الجديدة
                return $this->createSimpleInvoice($studentId, $toGroup);
            }

            Log::info('Found existing invoice to transfer', [
                'invoice_id' => $invoice->invoice_id,
                'old_group_id' => $invoice->group_id,
                'new_group_id' => $toGroup->group_id,
                'amount_paid' => $invoice->amount_paid,
                'discount_amount' => $invoice->discount_amount,
            ]);

            // 2. تحديث الفاتورة الحالية فقط بتغيير group_id
            $oldGroupId = $invoice->group_id;

            $invoice->update([
                'group_id' => $toGroup->group_id,
                'description' => 'رسوم مجموعة: '.$toGroup->group_name,
                'notes' => ($invoice->notes ?? '').
                         "\n✅ تم نقل الطالب من المجموعة #{$oldGroupId} إلى #{$toGroup->group_id} بتاريخ {$transferDate}",
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            Log::info('Invoice updated successfully - ONLY group_id changed', [
                'invoice_id' => $invoice->invoice_id,
                'old_group_id' => $oldGroupId,
                'new_group_id' => $toGroup->group_id,
                'amount_paid_unchanged' => $invoice->amount_paid,
                'discount_unchanged' => $invoice->discount_amount,
                'status_unchanged' => $invoice->status,
            ]);

            return $invoice;

        } catch (\Exception $e) {
            Log::error('Error in simplified invoice transfer: '.$e->getMessage());
            throw $e;
        }
    }

    private function createSimpleInvoice($studentId, $toGroup)
    {
        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);

        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $toGroup->price,
            'amount_paid' => 0,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'status' => 'pending',
            'issued_date' => now()->format('Y-m-d'),
            'notes' => 'فاتورة جديدة بعد نقل الطالب (بدون أي بيانات مالية سابقة)',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $invoice;
    }

    /**
     * إنشاء فاتورة جديدة بعد النقل
     */
    private function createNewInvoice($studentId, $toGroup, $transferDate, $transferredAmount, $transferredDiscount, $discountPercent, $fromGroup)
    {
        $invoiceNumber = 'INV-'.date('Ymd').'-'.rand(1000, 9999);
        $dueDate = now()->addDays(30)->format('Y-m-d');

        $newAmount = $toGroup->price;

        // حساب الحالة بناءً على المبلغ المنقول
        $netAmount = $newAmount - $transferredDiscount;
        $remaining = $netAmount - $transferredAmount;

        if ($remaining <= 0) {
            $status = 'paid';
        } elseif ($transferredAmount > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        // بناء الملاحظات
        $notes = "✅ فاتورة جديدة بعد نقل الطالب\n";
        $notes .= "📅 تاريخ النقل: {$transferDate}\n";
        $notes .= "🔄 نقل من: {$fromGroup->group_name} (#{$fromGroup->group_id})\n";
        $notes .= '💵 السعر الجديد: '.number_format($newAmount, 2).' جنيه';

        if ($transferredAmount > 0) {
            $notes .= "\n💰 المبلغ المنقول: ".number_format($transferredAmount, 2).' جنيه';
        }

        if ($transferredDiscount > 0) {
            $notes .= "\n🎫 الخصم المنقول: ".number_format($transferredDiscount, 2).' جنيه';
        }

        // إنشاء الفاتورة
        $invoice = Invoice::create([
            'student_id' => $studentId,
            'group_id' => $toGroup->group_id,
            'invoice_number' => $invoiceNumber,
            'description' => 'رسوم مجموعة: '.$toGroup->group_name,
            'amount' => $newAmount,
            'amount_paid' => $transferredAmount,
            'discount_amount' => $transferredDiscount,
            'discount_percent' => $discountPercent,
            'due_date' => $dueDate,
            'status' => $status,
            'issued_date' => $transferDate,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('New invoice created for transfer', [
            'invoice_id' => $invoice->invoice_id,
            'student_id' => $studentId,
            'new_group_id' => $toGroup->group_id,
            'amount' => $newAmount,
            'transferred_amount' => $transferredAmount,
            'status' => $status,
        ]);

        return $invoice;
    }

    /**
     * تحديث الفاتورة الموجودة
     */
    private function updateExistingInvoice($invoice, $additionalAmount, $additionalDiscount)
    {
        $newAmountPaid = $invoice->amount_paid + $additionalAmount;
        $newDiscount = $invoice->discount_amount + $additionalDiscount;

        // تحديث الحالة
        $netAmount = $invoice->amount - $newDiscount;
        $remaining = $netAmount - $newAmountPaid;

        if ($remaining <= 0) {
            $status = 'paid';
        } elseif ($newAmountPaid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }

        // تحديث الفاتورة
        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'discount_amount' => $newDiscount,
            'status' => $status,
            'updated_by' => auth()->id(),
            'notes' => ($invoice->notes ?? '').
                     "\n💰 تم إضافة مبلغ: ".number_format($additionalAmount, 2).
                     ' من مجموعة سابقة (نقل طالب)',
        ]);

        Log::info('Existing invoice updated with transferred amount', [
            'invoice_id' => $invoice->invoice_id,
            'additional_amount' => $additionalAmount,
            'new_total_paid' => $newAmountPaid,
            'new_status' => $status,
        ]);

        return $invoice;
    }

    /**
     * تحديث رواتب المدرسين بعد النقل
     */
    private function updateTeacherSalariesForTransfer($fromGroup, $toGroup)
    {
        try {
            // تحديث راتب المدرس في المجموعة القديمة
            $this->updateGroupSalary($fromGroup);

            // تحديث راتب المدرس في المجموعة الجديدة
            $this->updateGroupSalary($toGroup);

            Log::info('Teacher salaries updated after transfer', [
                'from_group' => $fromGroup->group_id,
                'to_group' => $toGroup->group_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating teacher salaries: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * تسجيل عملية النقل
     */

    /**
     * دالة تصحيح مشكلة teacher_payments
     */
    public function fixTeacherPaymentsIssue(Request $request)
    {
        DB::beginTransaction();
        try {
            $studentId = $request->student_id;
            $groupId = $request->group_id;

            Log::info('Fixing teacher payments issue', [
                'student_id' => $studentId,
                'group_id' => $groupId,
            ]);

            // 1. البحث عن الفاتورة الحالية للطالب في المجموعة
            $invoice = Invoice::where('student_id', $studentId)
                ->where('group_id', $groupId)
                ->where('status', '!=', 'cancelled')
                ->first();

            if (! $invoice) {
                throw new \Exception('No invoice found for this student in the group');
            }

            // 2. التحقق من مدفوعات المدرس المرتبطة
            $salary = Salary::where('group_id', $groupId)->first();

            if ($salary) {
                // 3. التحقق من وجود teacher_payments مرتبطة
                $teacherPayments = DB::table('teacher_payments')
                    ->where('salary_id', $salary->salary_id)
                    ->get();

                Log::info('Found teacher payments', [
                    'salary_id' => $salary->salary_id,
                    'payments_count' => $teacherPayments->count(),
                    'payments' => $teacherPayments->toArray(),
                ]);

                // 4. إذا كان هناك مشكلة، نقوم بتحديث المدفوعات
                $totalPaid = $teacherPayments->sum('amount');
                $expectedSalary = $salary->teacher_share;

                if ($totalPaid != $expectedSalary) {
                    Log::warning('Salary mismatch detected', [
                        'total_paid' => $totalPaid,
                        'expected_salary' => $expectedSalary,
                        'difference' => $expectedSalary - $totalPaid,
                    ]);

                    // يمكنك إضافة منطق التصحيح هنا
                    // مثلاً: تعديل teacher_payments أو إنشاء دفعة جديدة
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من مدفوعات المدرس',
                'data' => [
                    'invoice' => $invoice,
                    'salary' => $salary ?? null,
                    'teacher_payments_count' => $teacherPayments->count() ?? 0,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'خطأ في التصحيح: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * دالة للتحقق من تكامل البيانات بعد النقل
     */
    public function verifyTransferIntegrity(Request $request)
    {
        try {
            $studentId = $request->student_id;
            $fromGroupId = $request->from_group_id;
            $toGroupId = $request->to_group_id;

            $verification = [
                'student_info' => [],
                'old_group_info' => [],
                'new_group_info' => [],
                'invoices' => [],
                'salaries' => [],
                'teacher_payments' => [],
                'issues' => [],
            ];

            // 1. معلومات الطالب
            $student = Student::find($studentId);
            if ($student) {
                $verification['student_info'] = [
                    'id' => $student->student_id,
                    'name' => $student->student_name,
                ];
            }

            // 2. معلومات المجموعة القديمة
            $fromGroup = Group::with(['students', 'teacher'])->find($fromGroupId);
            if ($fromGroup) {
                $verification['old_group_info'] = [
                    'id' => $fromGroup->group_id,
                    'name' => $fromGroup->group_name,
                    'teacher' => $fromGroup->teacher->teacher_name ?? 'N/A',
                    'student_count' => $fromGroup->students->count(),
                ];

                // فواتير المجموعة القديمة
                $oldInvoices = Invoice::where('group_id', $fromGroupId)
                    ->where('student_id', $studentId)
                    ->get();

                $verification['invoices']['old'] = $oldInvoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->invoice_id,
                        'number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'amount' => $invoice->amount,
                        'paid' => $invoice->amount_paid,
                    ];
                });
            }

            // 3. معلومات المجموعة الجديدة
            $toGroup = Group::with(['students', 'teacher'])->find($toGroupId);
            if ($toGroup) {
                $verification['new_group_info'] = [
                    'id' => $toGroup->group_id,
                    'name' => $toGroup->group_name,
                    'teacher' => $toGroup->teacher->teacher_name ?? 'N/A',
                    'student_count' => $toGroup->students->count(),
                ];

                // فواتير المجموعة الجديدة
                $newInvoices = Invoice::where('group_id', $toGroupId)
                    ->where('student_id', $studentId)
                    ->get();

                $verification['invoices']['new'] = $newInvoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->invoice_id,
                        'number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'amount' => $invoice->amount,
                        'paid' => $invoice->amount_paid,
                    ];
                });
            }

            // 4. التحقق من الرواتب
            if ($fromGroup) {
                $fromSalary = Salary::where('group_id', $fromGroupId)->first();
                if ($fromSalary) {
                    $verification['salaries']['old'] = [
                        'id' => $fromSalary->salary_id,
                        'teacher_share' => $fromSalary->teacher_share,
                        'status' => $fromSalary->status,
                    ];
                }
            }

            if ($toGroup) {
                $toSalary = Salary::where('group_id', $toGroupId)->first();
                if ($toSalary) {
                    $verification['salaries']['new'] = [
                        'id' => $toSalary->salary_id,
                        'teacher_share' => $toSalary->teacher_share,
                        'status' => $toSalary->status,
                    ];
                }
            }

            // 5. التحقق من مدفوعات المدرسين
            $allSalaries = Salary::whereIn('group_id', [$fromGroupId, $toGroupId])->get();
            $salaryIds = $allSalaries->pluck('salary_id')->toArray();

            if (! empty($salaryIds)) {
                $teacherPayments = DB::table('teacher_payments')
                    ->whereIn('salary_id', $salaryIds)
                    ->get();

                $verification['teacher_payments'] = $teacherPayments->map(function ($payment) {
                    return [
                        'payment_id' => $payment->payment_id,
                        'salary_id' => $payment->salary_id,
                        'amount' => $payment->amount,
                        'payment_date' => $payment->payment_date,
                    ];
                });
            }

            // 6. البحث عن المشاكل
            if ($verification['invoices']['old']->count() > 0) {
                $activeOldInvoices = $verification['invoices']['old']->filter(function ($inv) {
                    return $inv['status'] != 'cancelled';
                });

                if ($activeOldInvoices->count() > 0) {
                    $verification['issues'][] = 'يوجد فواتير غير ملغاة في المجموعة القديمة';
                }
            }

            if ($verification['invoices']['new']->count() == 0) {
                $verification['issues'][] = 'لا توجد فواتير في المجموعة الجديدة';
            }

            return response()->json([
                'success' => true,
                'verification' => $verification,
                'has_issues' => count($verification['issues']) > 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during verification: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Student: Request to join a group
     */
    public function requestJoin(Request $request, $id)
    {
        $user = auth()->user();
        $group = Group::where('uuid', $id)->orWhere('group_id', $id)->firstOrFail();

        // Check if already member
        $student = $user->student;
        if ($student && $group->students()->where('student_group.student_id', $student->student_id)->exists()) {
            return back()->with('error', 'You are already a member of this group.');
        }

        // Check for existing pending request
        if (GroupEnrollmentRequest::where('user_id', $user->id)->where('group_id', $group->group_id)->where('status', 'pending')->exists()) {
            return back()->with('error', 'You already have a pending request for this group.');
        }

        $request->validate([
            'screenshot' => ($group->is_free || $group->price <= 0) ? 'nullable' : 'required|image|max:5120',
        ]);

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->store('enrollment_screenshots', 'public');
        }

        GroupEnrollmentRequest::create([
            'user_id' => $user->id,
            'group_id' => $group->group_id,
            'screenshot_path' => $screenshotPath,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        return back()->with('success', 'Your join request has been submitted and is pending approval.');
    }

    /**
     * Admin: View pending enrollment requests
     */
    public function enrollmentRequests()
    {
        $user = auth()->user();
        $query = GroupEnrollmentRequest::with(['user', 'group.course'])
            ->where('status', 'pending');

        if ($user->isTeacher() && $user->teacher) {
            $query->whereHas('group', function($q) use ($user) {
                $q->where('teacher_id', $user->teacher->teacher_id);
            });
        }

        $requests = $query->latest()->get();

        // Mark these requests as viewed if not already marked
        GroupEnrollmentRequest::whereIn('id', $requests->pluck('id'))
            ->whereNull('viewed_at')
            ->update(['viewed_at' => now()]);

        return view('admin.groups.enrollment-management', [
            'requests' => $requests
        ]);
    }

    /**
     * Student: View my enrollment requests
     */
    public function myEnrollmentRequests()
    {
        $user = auth()->user();
        $requests = GroupEnrollmentRequest::with(['group.course', 'group.teacher.user'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return view('student.portal.enrollment-requests', [
            'requests' => $requests
        ]);
    }

    /**
     * Admin: Approve/Reject enrollment request
     */
    public function updateEnrollmentStatus(Request $request, $id)
    {
        $en_request = GroupEnrollmentRequest::findOrFail($id);
        $status = $request->status; // approved, rejected

        if (!in_array($status, ['approved', 'rejected'])) {
            abort(400);
        }

        $en_request->status = $status;
        $en_request->notes = $request->notes;
        $en_request->save();

        if ($status === 'approved') {
            $group = $en_request->group;
            $user = $en_request->user;
            
            // Ensure user has a student profile
            $student = $user->student;
            if (!$student) {
                // Try to find student by UID if relationship isn't loaded/working
                $student = \DB::table('students')->where('user_id', $user->id)->first();
            }

            if (!$student) {
                return back()->with('error', 'User does not have a student profile. Please create one first.');
            }

            $studentId = is_object($student) ? ($student->student_id ?? $student->id) : $student;

            // Attach to group
            if (!$group->students()->where('student_group.student_id', $studentId)->exists()) {
                $group->students()->attach($studentId);

                // Create Invoice and Update Teacher Salary if it's a paid group
                if (!$group->isFree()) {
                    $invoiceNumber = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
                    
                    \App\Models\Invoice::create([
                        'student_id' => $studentId,
                        'group_id' => $group->group_id,
                        'invoice_number' => $invoiceNumber,
                        'description' => 'رسوم مجموعة: ' . $group->group_name,
                        'amount' => $group->price,
                        'amount_paid' => $group->price, // Assuming manual verification means it's paid
                        'status' => 'paid',
                        'issued_date' => now(),
                        'due_date' => now(),
                        'created_by' => Auth::id(),
                    ]);

                    // Update Teacher Salary
                    $month = now()->format('Y-m');
                    $salary = \App\Models\Salary::firstOrCreate(
                        [
                            'teacher_id' => $group->teacher_id,
                            'group_id' => $group->group_id,
                            'month' => $month
                        ],
                        [
                            'group_revenue' => 0,
                            'teacher_share' => 0,
                            'deductions' => 0,
                            'bonuses' => 0,
                            'net_salary' => 0,
                            'status' => 'unpaid'
                        ]
                    );

                    $salary->group_revenue += $group->price;
                    $teacherPercentage = $group->teacher_percentage ?: 0;
                    $salary->teacher_share = $salary->group_revenue * ($teacherPercentage / 100);
                    $salary->net_salary = $salary->teacher_share + $salary->bonuses - $salary->deductions;
                    $salary->save();
                }
            }
        }

        return back()->with('success', 'Enrollment request ' . $status . ' successfully.');
    }
}
