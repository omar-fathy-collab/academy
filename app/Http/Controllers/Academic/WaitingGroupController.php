<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Role;
use App\Models\SubCourse;
use App\Models\WaitingGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WaitingGroupController extends Controller
{
    /**
     * عرض جميع مجموعات الانتظار
     */
    public function index(Request $request)
    {
        $query = WaitingGroup::with(['course', 'subcourse', 'creator'])
            ->withCount(['waitingStudents as total_students'])
            ->withCount(['waitingStudents as waiting_count' => function ($q) {
                $q->where('status', 'waiting');
            }])
            ->withCount(['waitingStudents as approved_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->orderBy('created_at', 'desc');

        // Search by group name
        if ($request->filled('search')) {
            $query->where('group_name', 'like', '%'.$request->search.'%');
        }

        // Filter by course
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $waitingGroups = $query->paginate(15)->withQueryString();
        $courses = Course::all();

        // Summary Statistics
        $stats = [
            'total_groups' => WaitingGroup::count(),
            'active_groups' => WaitingGroup::where('status', 'active')->count(),
            'total_waiting_students' => \App\Models\WaitingStudent::where('status', 'waiting')->count(),
            'total_approved_students' => \App\Models\WaitingStudent::where('status', 'approved')->count(),
        ];

        return view('waiting_groups.index', [
            'waitingGroups' => $waitingGroups,
            'courses' => $courses,
            'stats' => $stats,
            'filters' => $request->only(['search', 'course_id', 'status']),
        ]);
    }

    /**
     * عرض نموذج إنشاء مجموعة جديدة
     */
    public function create()
    {
        $courses = Course::all();
        $subcourses = SubCourse::all();

        // جلب الطلاب الغير نشطين
        $availableStudents = \App\Models\Student::whereDoesntHave('groups')
            ->whereDoesntHave('waitingStudents', function ($query) {
                $query->whereIn('status', ['waiting', 'contacted', 'approved']);
            })
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('waiting_groups.create', compact('courses', 'subcourses', 'availableStudents'));
    }

    public function getWaitingGroups()
    {
        try {
            $groups = WaitingGroup::with('course')
                ->where('status', 'active')
                ->get()
                ->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->group_name,
                        'course_name' => $group->course ? $group->course->course_name : null,
                        'student_count' => $group->students_count ?? 0,
                    ];
                });

            return response()->json($groups);
        } catch (\Exception $e) {
            \Log::error('Error fetching waiting groups: '.$e->getMessage());

            return response()->json([], 500);
        }
    }

    public function addSingleStudent(Request $request, $groupId)
    {
        try {
            $request->validate([
                'student_id' => 'required|exists:students,student_id',
            ]);

            $waitingGroup = WaitingGroup::findOrFail($groupId);
            $student = \App\Models\Student::findOrFail($request->student_id);

            $existing = \App\Models\WaitingStudent::where('waiting_group_id', $groupId)
                ->where('student_id', $request->student_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا الطالب مضاف بالفعل لهذه المجموعة',
                ], 400);
            }

            $currentCount = \App\Models\WaitingStudent::where('waiting_group_id', $groupId)->count();
            if ($currentCount >= $waitingGroup->max_students) {
                return response()->json([
                    'success' => false,
                    'message' => 'المجموعة ممتلئة ولا يمكن إضافة المزيد من الطلاب',
                ], 400);
            }

            DB::beginTransaction();

            \App\Models\WaitingStudent::create([
                'waiting_group_id' => $groupId,
                'student_id' => $request->student_id,
                'user_id' => $student->user_id,
                'status' => 'waiting',
                'joined_at' => now(),
                'added_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الطالب إلى المجموعة بنجاح',
                'student_name' => $student->user->profile->nickname ?? $student->user->name ?? 'غير معروف',
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error adding student to waiting group: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        \Log::info('=== STORE METHOD CALLED ===');
        \Log::info('Request data:', $request->all());
        \Log::info('Student IDs from input:', ['raw' => $request->student_ids]);

        $request->validate([
            'group_name' => 'required|string|max:255|unique:waiting_groups,group_name',
            'course_id' => 'required|exists:courses,course_id',
            'max_students' => 'required|integer|min:1|max:100',
            'status' => 'required|in:active,inactive,full',
            'student_ids' => 'nullable|string',
        ]);

        \Log::info('Validation passed');

        DB::beginTransaction();
        try {
            $waitingGroup = WaitingGroup::create([
                'group_name' => $request->group_name,
                'course_id' => $request->course_id,
                'subcourse_id' => $request->subcourse_id ?: null,
                'description' => $request->description,
                'max_students' => $request->max_students,
                'status' => $request->status,
                'created_by' => auth()->id(),
            ]);

            \Log::info('Group created:', ['id' => $waitingGroup->id, 'name' => $waitingGroup->group_name]);

            // تحويل student_ids مع معالجة أفضل
            $studentIds = [];

            if ($request->has('student_ids') && ! empty($request->student_ids)) {
                $studentIdsString = trim($request->student_ids);
                \Log::info('Student IDs string:', ['string' => $studentIdsString]);

                if ($studentIdsString !== '') {
                    $studentIds = explode(',', $studentIdsString);
                    $studentIds = array_filter($studentIds, function ($id) {
                        return is_numeric(trim($id)) && (int) trim($id) > 0;
                    });
                    $studentIds = array_map('intval', $studentIds);
                    $studentIds = array_unique($studentIds);
                }
            }

            \Log::info('Parsed student IDs:', $studentIds);
            \Log::info('Number of student IDs:', ['count' => count($studentIds)]);

            if (! empty($studentIds)) {
                foreach ($studentIds as $studentId) {
                    $student = \App\Models\Student::find($studentId);

                    if (! $student) {
                        \Log::warning('Student not found:', ['id' => $studentId]);

                        continue;
                    }

                    // التحقق من أن الطالب ليس في مجموعة دراسية نشطة
                    $inActiveGroup = \App\Models\StudentGroup::where('student_id', $studentId)
                        ->whereHas('group', function ($query) {
                            $query->where('status', 'active');
                        })
                        ->exists();

                    // التحقق من أن الطالب ليس في مجموعة انتظار أخرى نشطة
                    $inWaitingGroup = \App\Models\WaitingStudent::where('student_id', $studentId)
                        ->whereIn('status', ['waiting', 'contacted', 'approved'])
                        ->exists();

                    if (! $inActiveGroup && ! $inWaitingGroup) {
                        \App\Models\WaitingStudent::create([
                            'waiting_group_id' => $waitingGroup->id,
                            'student_id' => $studentId,
                            'user_id' => $student->user_id,
                            'status' => 'waiting',
                            'joined_at' => now(),
                            'added_by' => auth()->id(),
                        ]);
                        \Log::info('Student added to waiting group:', ['student_id' => $studentId]);
                    } else {
                        \Log::info('Student skipped (already in active group or waiting list):', [
                            'student_id' => $studentId,
                            'in_active_group' => $inActiveGroup,
                            'in_waiting_group' => $inWaitingGroup,
                        ]);
                    }
                }
            } else {
                \Log::info('No students selected or student_ids is empty');
            }

            DB::commit();

            \Log::info('Transaction committed successfully');

            return redirect()->route('waiting-groups.show', $waitingGroup->id)
                ->with('success', 'تم إنشاء مجموعة الانتظار بنجاح');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Transaction failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * عرض مجموعة معينة
     */
    public function show($id)
    {
        $waitingGroup = WaitingGroup::with([
            'course',
            'subcourse',
            'creator.profile',
        ])
            ->withCount(['waitingStudents as total_students'])
            ->withCount(['waitingStudents as waiting_count' => function ($q) {
                $q->where('status', 'waiting');
            }])
            ->withCount(['waitingStudents as approved_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->findOrFail($id);

        $waitingStudents = \App\Models\WaitingStudent::with(['student.user.profile', 'addedBy.profile'])
            ->where('waiting_group_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get students not in any ACTIVE waiting group or regular group
        $availableStudents = \App\Models\Student::whereDoesntHave('waitingStudents', function ($query) {
            $query->whereIn('status', ['waiting', 'contacted', 'approved']);
        })
            ->whereDoesntHave('studentGroups', function ($query) {
                $query->whereHas('group', function ($q) {
                    $q->where('status', 'active');
                });
            })
            ->with('user.profile')
            ->get();

        return view('waiting_groups.show', [
            'waitingGroup' => $waitingGroup,
            'waitingStudents' => $waitingStudents,
            'availableStudents' => $availableStudents,
        ]);
    }

    /**a
     * Get inactive students (students not in active groups or waiting lists)
     */
    /**
     * Get inactive students (students not in active groups or waiting lists)
     */
    /**
     * Get inactive students (students not in active groups or waiting lists)
     */
    /**
     * Get inactive students (students not in active groups or waiting lists)
     */
    /**
     * Get inactive students (students not in active groups or waiting lists)
     */
    /**
     * Get inactive students (students not in active groups or waiting lists)
     */
    // In WaitingGroupController.php
    // In WaitingGroupController.php
    public function bulkAddStudents(Request $request, $id)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,student_id',
            'bulk_placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'bulk_assigned_level' => 'nullable|in:مبتدئ,متوسط,متقدم',
            'bulk_notes' => 'nullable|string|max:500',
        ]);

        $waitingGroup = WaitingGroup::findOrFail($id);

        // Count current students
        $currentStudentCount = \App\Models\WaitingStudent::where('waiting_group_id', $id)->count();

        // Check if group is full
        if ($currentStudentCount >= $waitingGroup->max_students) {
            return back()->with('error', 'المجموعة ممتلئة ولا يمكن إضافة المزيد من الطلاب');
        }

        $addedCount = 0;
        $failedStudents = [];

        foreach ($request->student_ids as $studentId) {
            // Get the student record to access user_id
            $student = \App\Models\Student::find($studentId);

            if (! $student) {
                $failedStudents[] = $studentId.' (غير موجود)';

                continue;
            }

            // Check if student already exists in this group
            $exists = \App\Models\WaitingStudent::where('waiting_group_id', $id)
                ->where('student_id', $studentId)
                ->exists();

            if (! $exists) {
                // Check if group still has capacity
                if ($currentStudentCount + $addedCount >= $waitingGroup->max_students) {
                    $failedStudents[] = $studentId.' (لا توجد مقاعد كافية)';

                    continue;
                }

                \App\Models\WaitingStudent::create([
                    'waiting_group_id' => $id,
                    'student_id' => $studentId,
                    'user_id' => $student->user_id,
                    'placement_exam_grade' => $request->bulk_placement_exam_grade,
                    'assigned_level' => $request->bulk_assigned_level,
                    'notes' => $request->bulk_notes,
                    'status' => 'waiting',
                    'joined_at' => now(), // Add joined_at
                    'added_by' => auth()->id(),
                ]);

                $addedCount++;
            }
        }

        // Don't update students_count since it's not a database column
        // The count will be calculated dynamically when accessed

        $message = "تم إضافة {$addedCount} طالب بنجاح";
        if (! empty($failedStudents)) {
            $message .= '. فشل إضافة '.count($failedStudents).' طالب: '.implode(', ', $failedStudents);
        }

        return redirect()->route('waiting-groups.show', $id)
            ->with('success', $message);
    }

    public function getInactiveStudents()
    {
        try {
            // جلب الطلاب غير النشطين
            $inactiveStudents = \App\Models\Student::with('user')
                ->whereDoesntHave('studentGroups', function ($query) {
                    $query->whereHas('group', function ($q) {
                        $q->where('status', 'active');
                    });
                })
                ->whereDoesntHave('waitingStudents', function ($query) {
                    // التعديل هنا: استبعاد الطلاب الموجودين في ANY مجموعة انتظار
                    $query->whereHas('waitingGroup', function ($q) {
                        $q->where('status', 'active'); // أو يمكن إزالة هذا الشرط إذا أردت استبعادهم من جميع مجموعات الانتظار
                    });
                })
                ->whereHas('user', function ($q) {
                    $q->where('role_id', Role::STUDENT_ID); // تأكد أنهم طلاب
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($student) {
                    $user = $student->user;

                    return [
                        'student_id' => $student->student_id,
                        'name' => $user
                            ? ($user->username ?? $user->name ?? 'غير معروف')
                            : 'غير معروف',
                        'username' => $user ? ($user->username ?? 'لا يوجد') : 'لا يوجد',
                        'email' => $user ? ($user->email ?? 'لا يوجد') : 'لا يوجد',
                        'phone' => $user && $user->profile
                            ? ($user->profile->phone_number ?? ($user->phone ?? 'لا يوجد'))
                            : 'لا يوجد',
                        'created_at' => $student->created_at
                            ? $student->created_at->format('Y-m-d')
                            : 'غير معروف',
                    ];
                });

            \Log::info('Inactive students count: '.count($inactiveStudents));

            return response()->json([
                'success' => true,
                'students' => $inactiveStudents,
                'count' => count($inactiveStudents),
                'message' => count($inactiveStudents) > 0
                    ? 'تم جلب '.count($inactiveStudents).' طالب بنجاح'
                    : 'لا يوجد طلاب غير نشطين',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getInactiveStudents: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
                'students' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * عرض نموذج تعديل مجموعة
     */
    public function edit($id)
    {
        $waitingGroup = WaitingGroup::findOrFail($id);
        $courses = Course::all();
        $subcourses = SubCourse::where('course_id', $waitingGroup->course_id)->get();

        return view('waiting_groups.edit', compact('waitingGroup', 'courses', 'subcourses'));
    }

    /**
     * تحديث مجموعة
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'group_name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
            'description' => 'nullable|string',
            'max_students' => 'required|integer|min:1|max:100',
            'status' => 'required|in:active,inactive,full',
        ]);

        $waitingGroup = WaitingGroup::findOrFail($id);

        DB::beginTransaction();
        try {
            $waitingGroup->update([
                'group_name' => $request->group_name,
                'course_id' => $request->course_id,
                'subcourse_id' => $request->subcourse_id,
                'description' => $request->description,
                'max_students' => $request->max_students,
                'status' => $request->status,
            ]);

            DB::commit();

            return redirect()->route('waiting-groups.show', $waitingGroup->id)
                ->with('success', 'تم تحديث مجموعة الانتظار بنجاح');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    // في WaitingGroupController.php
    public function searchStudents(Request $request, $id)
    {
        try {
            $query = \App\Models\Student::with([
                'user.profile',
                'studentGroups.group.sessions',
                'waitingStudents.waitingGroup',
            ])
                ->whereHas('user', function ($query) {
                    $query->where('role_id', Role::STUDENT_ID); // طلاب فقط
                });

            // فلترة حسب حالة النشاط (is_active)
            if ($request->has('is_active') && $request->is_active !== '') {
                if ($request->is_active == '0') {
                    // الطلاب غير النشطين
                    $query->where(function ($q) {
                        $q->whereDoesntHave('studentGroups')
                            ->orWhereHas('studentGroups.group', function ($q2) {
                                $q2->whereHas('sessions', function ($q3) {
                                    $q3->where('session_date', '<=', now()->subDays(90));
                                });
                            });
                    });
                } elseif ($request->is_active == '1') {
                    // الطلاب النشطين
                    $query->whereHas('studentGroups.group', function ($q) {
                        $q->whereHas('sessions', function ($q2) {
                            $q2->where('session_date', '>', now()->subDays(90));
                        });
                    });
                }
            }

            // فلترة حسب حالة المجموعات
            if ($request->has('group_status') && $request->group_status !== '') {
                if ($request->group_status == 'in_groups') {
                    $query->whereHas('studentGroups');
                } elseif ($request->group_status == 'not_in_groups') {
                    $query->whereDoesntHave('studentGroups');
                } elseif ($request->group_status == 'expired_groups') {
                    $query->whereHas('studentGroups.group.sessions', function ($q) {
                        $q->where('session_date', '<=', now()->subDays(90));
                    });
                }
            }

            // بحث بالاسم أو البريد
            if ($request->has('search') && ! empty($request->search)) {
                $searchTerm = '%'.$request->search.'%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('student_name', 'like', $searchTerm)
                        ->orWhereHas('user', function ($q2) use ($searchTerm) {
                            $q2->where('email', 'like', $searchTerm)
                                ->orWhere('username', 'like', $searchTerm);
                        })
                        ->orWhereHas('user.profile', function ($q3) use ($searchTerm) {
                            $q3->where('nickname', 'like', $searchTerm)
                                ->orWhere('phone_number', 'like', $searchTerm);
                        });
                });
            }

            // فلترة حسب تاريخ التسجيل
            if ($request->has('registration_date') && $request->registration_date !== '') {
                $query->whereDate('created_at', $request->registration_date);
            }

            $students = $query->orderBy('created_at', 'desc')->get();

            // تنسيق البيانات للإرجاع - مع معالجة القيم NULL
            $formattedStudents = $students->map(function ($student) {
                $user = $student->user;
                $profile = $user ? $user->profile : null;

                // معالجة مجموعات الطالب
                $groupsInfo = [];
                if ($student->studentGroups) {
                    foreach ($student->studentGroups as $sg) {
                        $group = $sg->group;
                        if ($group) {
                            $lastSession = $group->sessions ? $group->sessions->sortByDesc('session_date')->first() : null;

                            $groupsInfo[] = [
                                'group_name' => $group->group_name ?? 'غير معروف',
                                'course_name' => $group->course ? ($group->course->course_name ?? 'غير معروف') : 'غير معروف',
                                'status' => $group->status ?? 'غير معروف',
                                'last_session' => $lastSession && $lastSession->session_date
                                    ? $lastSession->session_date->format('Y-m-d')
                                    : 'لا يوجد جلسات',
                                'is_expired' => $lastSession && $lastSession->session_date
                                    ? $lastSession->session_date->lt(now()->subDays(90))
                                    : true,
                                'created_at' => $group->created_at
                                    ? $group->created_at->format('Y-m-d')
                                    : 'غير معروف',
                            ];
                        }
                    }
                }

                // معالجة مجموعات الانتظار
                $waitingGroupsInfo = [];
                if ($student->waitingStudents) {
                    foreach ($student->waitingStudents as $ws) {
                        $waitingGroup = $ws->waitingGroup;
                        if ($waitingGroup) {
                            $waitingGroupsInfo[] = [
                                'group_name' => $waitingGroup->group_name ?? 'غير معروف',
                                'status' => $ws->status ?? 'غير معروف',
                                'added_at' => $ws->created_at
                                    ? $ws->created_at->format('Y-m-d')
                                    : 'غير معروف',
                            ];
                        }
                    }
                }

                return [
                    'student_id' => $student->student_id,
                    'user_id' => $student->user_id,
                    'name' => $profile ? ($profile->nickname ?? $user->username ?? $student->student_name ?? 'غير معروف')
                            : ($user ? ($user->username ?? $student->student_name ?? 'غير معروف')
                            : ($student->student_name ?? 'غير معروف')),
                    'phone' => $profile ? ($profile->phone_number ?? 'لا يوجد') : 'لا يوجد',
                    'email' => $user ? ($user->email ?? 'لا يوجد') : 'لا يوجد',
                    'is_active' => $student->studentGroups && $student->studentGroups->count() > 0 &&
                                 $student->studentGroups->first()->group &&
                                 $student->studentGroups->first()->group->sessions &&
                                 $student->studentGroups->first()->group->sessions
                                     ->where('session_date', '>', now()->subDays(90))
                                     ->count() > 0,
                    'groups_count' => $student->studentGroups ? $student->studentGroups->count() : 0,
                    'waiting_groups_count' => $student->waitingStudents ? $student->waitingStudents->count() : 0,
                    'registration_date' => $student->created_at
                        ? $student->created_at->format('Y-m-d')
                        : 'غير معروف',
                    'groups' => $groupsInfo,
                    'waiting_groups' => $waitingGroupsInfo,
                ];
            });

            return response()->json([
                'success' => true,
                'count' => count($formattedStudents),
                'students' => $formattedStudents,
                'filters' => $request->all(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in searchStudents: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث: '.$e->getMessage(),
                'count' => 0,
                'students' => [],
            ], 500);
        }
    }
    /**
     * حذف مجموعة
     */
    /**
     * حذف مجموعة
     */
    /**
     * حذف مجموعة
     */

    /**
     * إضافة طالب لمجموعة
     */
    public function addStudent(Request $request, $groupId)
    {
        $request->validate([
            'student_id' => 'required|exists:students,student_id',
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'assigned_level' => 'nullable|in:مبتدئ,متوسط,متقدم',
            'notes' => 'nullable|string',
        ]);

        $waitingGroup = WaitingGroup::findOrFail($groupId);
        $student = \App\Models\Student::findOrFail($request->student_id);

        // التحقق من أن الطالب ليس مضافاً مسبقاً
        $existing = \App\Models\WaitingStudent::where('waiting_group_id', $groupId)
            ->where('student_id', $request->student_id)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->with('error', 'هذا الطالب مضاف بالفعل لهذه المجموعة');
        }

        DB::beginTransaction();
        try {
            \App\Models\WaitingStudent::create([
                'waiting_group_id' => $groupId,
                'student_id' => $request->student_id,
                'user_id' => $student->user_id,
                'placement_exam_grade' => $request->placement_exam_grade,
                'assigned_level' => $request->assigned_level,
                'notes' => $request->notes,
                'status' => 'waiting',
                'joined_at' => now(),
                'added_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'تم إضافة الطالب إلى المجموعة بنجاح');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * إزالة طالب من مجموعة
     */
    /**
     * إزالة طالب من مجموعة
     */
    /**
     * إزالة طالب من مجموعة
     */
    /**
     * إزالة طالب من مجموعة
     */
    // في WaitingGroupController.php

    /**
     * حذف مجموعة الانتظار (كاملة)
     */
    // في WaitingGroupController.php
    public function destroy($id)
    {
        $waitingGroup = WaitingGroup::findOrFail($id);

        DB::beginTransaction();
        try {
            $groupName = $waitingGroup->group_name;

            // احذف الطلاب أولاً
            \App\Models\WaitingStudent::where('waiting_group_id', $id)->delete();

            // ثم احذف المجموعة
            $waitingGroup->delete();

            DB::commit();

            // التوجيه إلى صفحة الفهرس
            return redirect()->route('waiting-groups.index')
                ->with('success', "تم حذف مجموعة الانتظار '{$groupName}' بنجاح");

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->route('waiting-groups.index')
                ->with('error', 'حدث خطأ أثناء حذف المجموعة: '.$e->getMessage());
        }
    }

    /**
     * إزالة طالب من مجموعة (حذف طالب واحد فقط)
     */
    // في WaitingGroupController.php
    /**
     * إزالة طالب من مجموعة (حذف طالب واحد فقط)
     */
    /**
     * إزالة طالب من مجموعة (حذف طالب واحد فقط)
     */
    public function removeStudent($groupId, $studentId)
    {
        DB::beginTransaction();
        try {
            // تحقق من وجود الطالب في المجموعة
            $waitingStudent = \App\Models\WaitingStudent::with('student.user')
                ->where('waiting_group_id', $groupId)
                ->where('student_id', $studentId)
                ->first();

            if (! $waitingStudent) {
                return redirect()->back()
                    ->with('error', 'الطالب غير موجود في هذه المجموعة');
            }

            // احفظ معلومات اليوزر قبل الحذف
            $userId = optional($waitingStudent->student->user)->id;
            $studentName = optional($waitingStudent->student->user)->name
                          ?? 'طالب';

            // حذف الطالب من المجموعة
            $waitingStudent->delete();

            // تحديث حالة اليوزر المرتبط باستخدام الدالة الموجودة في الـ User Model
            if ($userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    // استدعاء الدالة من الـ User Model
                    $user->updateActiveStatus();
                }
            }

            DB::commit();

            // رسالة تأكيد
            $message = "تم إزالة الطالب {$studentName} من المجموعة بنجاح";
            if ($userId && isset($user) && ! $user->is_active) {
                $message .= ' - تم تعيين حالة اليوزر كغير نشط';
            }

            return redirect()->back()
                ->with('success', $message)
                ->with('user_id', $userId);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error removing student from waiting group: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * التحقق من حالة اليوزر وتحديثها بناءً على وجود الطالب في مجموعات
     */
    public function checkAndUpdateUserStatus($userId)
    {
        try {
            $user = \App\Models\User::with([
                'student.studentGroups.group',
                'student.waitingStudents',
            ])->find($userId);

            if (! $user || ! $user->student) {
                return false;
            }

            $student = $user->student;

            // التحقق من المجموعات النشطة
            $inActiveGroup = $student->studentGroups
                ->where('group.status', 'active')
                ->count() > 0;

            // التحقق من مجموعات الانتظار
            $inWaitingGroup = $student->waitingStudents
                ->whereIn('status', ['waiting', 'contacted', 'approved'])
                ->count() > 0;

            // تحديد الحالة الجديدة
            $shouldBeActive = $inActiveGroup || $inWaitingGroup;

            // تحديث حالة اليوزر
            if (\Schema::hasColumn('users', 'is_active')) {
                $currentStatus = $user->is_active;
                $user->is_active = $shouldBeActive;

                if ($currentStatus != $shouldBeActive) {
                    $user->save();
                    \Log::info("User {$userId} status updated: is_active = ".($shouldBeActive ? 'true' : 'false'));

                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            \Log::error('Error checking user status: '.$e->getMessage());

            return false;
        }
    }

    /**
     * تحديث حالة الطالب ليصبح inactive إذا لم يكن في أي مجموعات
     */
    /**
     * تحديث حالة الطالب (في جدول users) ليصبح inactive إذا لم يكن في أي مجموعات
     */
    private function updateStudentInactiveStatus($studentId)
    {
        try {
            $student = \App\Models\Student::with([
                'studentGroups.group',
                'waitingStudents',
                'user', // مهم: جلب بيانات اليوزر المرتبط
            ])->find($studentId);

            if (! $student || ! $student->user) {
                \Log::warning("Student or user not found: {$studentId}");

                return false;
            }

            $userId = $student->user_id;

            // التحقق 1: هل الطالب في أي مجموعة دراسية نشطة؟
            $inActiveGroup = $student->studentGroups
                ->where('group.status', 'active')
                ->count() > 0;

            // التحقق 2: هل الطالب في أي مجموعة انتظار أخرى؟
            $inWaitingGroup = $student->waitingStudents
                ->whereIn('status', ['waiting', 'contacted', 'approved'])
                ->count() > 0;

            // إذا لم يكن الطالب في أي مجموعة نشطة ولا في أي مجموعة انتظار
            if (! $inActiveGroup && ! $inWaitingGroup) {
                // تحديث حالة اليوزر المرتبط بالطالب
                $user = $student->user;

                if (\Schema::hasColumn('users', 'is_active')) {
                    $user->is_active = false;
                    \Log::info("User {$userId} marked as inactive (is_active=false) for student {$studentId}.");
                } elseif (\Schema::hasColumn('users', 'status')) {
                    $user->status = 'inactive';
                    \Log::info("User {$userId} status set to 'inactive' for student {$studentId}.");
                }

                $user->save();

                // تسجيل النشاط
                try {
                    \App\Models\Activity::create([
                        'user_id' => auth()->id(),
                        'action' => 'user_marked_inactive',
                        'description' => "تم تعيين حالة المستخدم {$userId} كغير نشط بعد إزالة الطالب {$studentId} من مجموعة الانتظار",
                        'related_type' => 'user',
                        'related_id' => $userId,
                    ]);
                } catch (\Exception $e) {
                    \Log::info('Activity logging skipped: '.$e->getMessage());
                }

                return true;
            } else {
                \Log::info("User {$userId} remains active (student in group/waiting).", [
                    'in_active_group' => $inActiveGroup,
                    'in_waiting_group' => $inWaitingGroup,
                ]);
            }

            return false;

        } catch (\Exception $e) {
            \Log::error('Error updating user inactive status: '.$e->getMessage());

            return false;
        }
    }

    /**
     * تحديث حالة طالب في مجموعة
     */
    public function updateStudentStatus(Request $request, $groupId, $studentId)
    {
        $request->validate([
            'status' => 'required|in:waiting,contacted,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $waitingStudent = \App\Models\WaitingStudent::where('waiting_group_id', $groupId)
                ->where('student_id', $studentId)
                ->firstOrFail();

            $waitingStudent->update([
                'status' => $request->status,
                'notes' => $waitingStudent->notes ?
                    $waitingStudent->notes."\n".$request->notes :
                    $request->notes,
            ]);

            DB::commit();

            return redirect()->back()
                ->with('success', 'تم تحديث حالة الطالب بنجاح');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * نقل طالب من مجموعة لأخرى
     */
    public function transferStudent(Request $request, $groupId, $studentId)
    {
        $request->validate([
            'new_group_id' => 'required|exists:waiting_groups,id',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $waitingStudent = \App\Models\WaitingStudent::where('waiting_group_id', $groupId)
                ->where('student_id', $studentId)
                ->firstOrFail();

            // التحقق من أن الطالب ليس مضافاً مسبقاً في المجموعة الجديدة
            $existing = \App\Models\WaitingStudent::where('waiting_group_id', $request->new_group_id)
                ->where('student_id', $studentId)
                ->first();

            if ($existing) {
                return redirect()->back()
                    ->with('error', 'هذا الطالب مضاف بالفعل للمجموعة الجديدة');
            }

            // إنشاء سجل جديد في المجموعة الجديدة
            \App\Models\WaitingStudent::create([
                'waiting_group_id' => $request->new_group_id,
                'student_id' => $studentId,
                'user_id' => $waitingStudent->user_id,
                'placement_exam_grade' => $waitingStudent->placement_exam_grade,
                'assigned_level' => $waitingStudent->assigned_level,
                'notes' => $request->notes ?: 'تم النقل من مجموعة: '.$waitingStudent->waitingGroup->group_name,
                'status' => $waitingStudent->status,
                'joined_at' => now(),
                'added_by' => auth()->id(),
            ]);

            // حذف السجل القديم
            $waitingStudent->delete();

            DB::commit();

            return redirect()->route('waiting-groups.show', $request->new_group_id)
                ->with('success', 'تم نقل الطالب إلى المجموعة الجديدة بنجاح');

        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * جلب الصب كورسات حسب الكورس (لـ AJAX)
     */
    public function getSubcourses($courseId)
    {
        $subcourses = SubCourse::where('course_id', $courseId)
            ->orderBy('subcourse_number')
            ->get(['subcourse_id', 'subcourse_name', 'subcourse_number']);

        return response()->json($subcourses);
    }

    public function getAvailableStudents()
    {
        try {
            $students = \App\Models\Student::whereDoesntHave('waitingStudents', function ($query) {
                $query->whereIn('status', ['waiting', 'contacted', 'approved']);
            })
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($student) {
                    return [
                        'student_id' => $student->student_id,
                        'name' => $student->user ? $student->user->name : 'غير معروف',
                        'phone' => $student->user ? $student->user->phone : null,
                        'email' => $student->user ? $student->user->email : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'students' => $students,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الطلاب',
            ], 500);
        }
    }

    /**
     * إضافة طالب إلى مجموعة انتظار من صفحة الطلاب
     */
    /**
     * إضافة طالب إلى مجموعة انتظار من صفحة الطلاب
     * (يسمح بإضافته حتى لو كان في مجموعة نشطة)
     */
    // في App\Models\User.php
    public function updateActiveStatus()
    {
        $student = $this->student;

        if (! $student) {
            $this->is_active = false;
            $this->save();

            return;
        }

        // التحقق من المجموعات النشطة
        $inActiveGroup = $student->studentGroups()
            ->whereHas('group', function ($query) {
                $query->where('status', 'active');
            })->exists();

        // التحقق من مجموعات الانتظار
        $inWaitingGroup = $student->waitingStudents()
            ->whereIn('status', ['waiting', 'contacted', 'approved'])
            ->exists();

        // تحديث حالة النشاط
        $shouldBeActive = $inActiveGroup || $inWaitingGroup;

        if ($this->is_active != $shouldBeActive) {
            $this->is_active = $shouldBeActive;
            $this->save();
        }
    }

    public function addStudentFromStudentsPage(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|exists:students,student_id',
                'waiting_group_id' => 'required|exists:waiting_groups,id',
                'notes' => 'nullable|string|max:500',
            ]);

            $student = \App\Models\Student::findOrFail($request->student_id);
            $waitingGroup = WaitingGroup::findOrFail($request->waiting_group_id);

            // Check if student is already in this waiting group
            $exists = \App\Models\WaitingStudent::where('waiting_group_id', $waitingGroup->id)
                ->where('student_id', $student->student_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطالب مضاف بالفعل لهذه المجموعة',
                ]);
            }

            DB::beginTransaction();

            \App\Models\WaitingStudent::create([
                'waiting_group_id' => $waitingGroup->id,
                'student_id' => $student->student_id,
                'user_id' => $student->user_id,
                'added_by' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'waiting',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // Log activity (اختياري)
            try {
                Activity::create([
                    'user_id' => auth()->id(),
                    'action' => 'add_to_waiting_group',
                    'description' => 'Added student '.($student->username ?? $student->student_id).' to waiting group '.$waitingGroup->group_name,
                    'related_type' => 'waiting_group',
                    'related_id' => $waitingGroup->id,
                ]);
            } catch (\Exception $e) {
                // تجاهل خطأ الـ Activity إذا لم يكن موجوداً
                \Log::info('Activity logging skipped: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الطالب إلى مجموعة الانتظار بنجاح',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error adding student to waiting group from students page: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }
}
