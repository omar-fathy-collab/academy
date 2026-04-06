<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    public function create()
    {
        $teachers = DB::table('teachers')->get();
        return view('courses.create', [
            'teachers' => $teachers
        ]);
    }


    public function fetchCourses(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $page = $request->get('page', 1);
            $limit = 15;

            $query = Course::withCount('subcourses');

            if (! empty($search)) {
                $query->where('course_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }

            $courses = $query->orderBy('course_id', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'courses' => $courses->items(),
                'total' => $courses->total(),
                'page' => $courses->currentPage(),
                'limit' => $limit,
                'totalPages' => $courses->lastPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching courses: '.$e->getMessage());

            return response()->json([
                'courses' => [],
                'total' => 0,
                'page' => 1,
                'limit' => $limit,
                'totalPages' => 1,
                'error' => 'Failed to load courses',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'subcourses' => 'nullable|array',
            'subcourses.*.number' => 'required|integer|min:1',
            'subcourses.*.name' => 'required|string|max:255',
            'subcourses.*.duration' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $course = \App\Models\Course::create([
                'course_name' => $request->course_name,
                'description' => $request->description,
                'price' => $request->price ?? 0,
                'is_free' => $request->has('is_free') ? $request->is_free : ($request->price > 0 ? false : true),
                'is_public' => $request->has('is_public') ? $request->is_public : false,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
            ]);

            if ($request->has('subcourses')) {
                foreach ($request->subcourses as $subcourse) {
                    if (! empty($subcourse['name']) && ! empty($subcourse['number'])) {
                        \App\Models\SubCourse::create([
                            'course_id' => $course->course_id,
                            'subcourse_number' => $subcourse['number'],
                            'subcourse_name' => $subcourse['name'],
                            'duration_hours' => $subcourse['duration'] ?? 0,
                            'description' => $subcourse['name'],
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('courses')->with('success', 'تم إضافة الكورس بنجاح!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding course: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء إضافة الكورس: '.$e->getMessage())
                ->withInput();
        }
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        
        $courses = DB::table('courses')
            ->leftJoin('student_course', 'courses.course_id', '=', 'student_course.course_id')
            ->leftJoin('subcourses', 'courses.course_id', '=', 'subcourses.course_id')
            ->select(
                'courses.course_id',
                'courses.course_name',
                'courses.description',
                'courses.created_at',
                'courses.updated_at',
                DB::raw('COUNT(DISTINCT student_course.student_id) as student_count'),
                DB::raw('COUNT(DISTINCT subcourses.subcourse_id) as subcourse_count')
            )
            ->when($search, function($query, $search) {
                $query->where('courses.course_name', 'like', "%{$search}%")
                      ->orWhere('courses.description', 'like', "%{$search}%");
            })
            ->groupBy(
                'courses.course_id',
                'courses.course_name',
                'courses.description',
                'courses.created_at',
                'courses.updated_at'
            )
            ->orderByDesc('courses.course_id')
            ->paginate(15)
            ->withQueryString();

        return view('courses.index', [
            'courses' => $courses,
            'filters' => [
                'search' => $search
            ]
        ]);
    }

    public function fetch(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $page = max(1, (int) $request->get('page', 1));
            $limit = 15;
            $offset = ($page - 1) * $limit;

            $cacheKey = "courses_fetch_{$search}_{$page}";

            $data = cache()->remember($cacheKey, 30, function () use ($search, $page, $limit, $offset) {
                $query = DB::table('courses')
                    ->leftJoin('student_course', 'courses.course_id', '=', 'student_course.course_id')
                    ->leftJoin('subcourses', 'courses.course_id', '=', 'subcourses.course_id')
                    ->select(
                        'courses.course_id',
                        'courses.course_name',
                        'courses.description',
                        'courses.created_at',
                        'courses.updated_at',
                        DB::raw('COUNT(DISTINCT student_course.student_id) as student_count'),
                        DB::raw('COUNT(DISTINCT subcourses.subcourse_id) as subcourse_count')
                    )
                    ->groupBy(
                        'courses.course_id',
                        'courses.course_name',
                        'courses.description',
                        'courses.created_at',
                        'courses.updated_at'
                    );

                if ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('courses.course_name', 'like', "%$search%")
                            ->orWhere('courses.description', 'like', "%$search%")
                            ->orWhere('courses.course_id', 'like', "%$search%");
                    });
                }

                $total = $query->count();
                $courses = $query->orderByDesc('courses.course_id')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();

                $totalPages = ceil($total / $limit);

                return [
                    'courses' => $courses,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => $totalPages,
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('Error in fetch method: '.$e->getMessage());

            return response()->json([
                'courses' => [],
                'total' => 0,
                'page' => 1,
                'limit' => 15,
                'totalPages' => 1,
                'error' => 'Failed to load courses',
            ], 500);
        }
    }

    public function edit($id)
    {
        $course = DB::table('courses')->where('course_id', $id)->first();

        if (! $course) {
            abort(404, 'Course not found');
        }

        $course->subcourses = DB::table('subcourses')
            ->where('course_id', $id)
            ->orderBy('subcourse_number')
            ->get();

        return view('courses.create', [
            'course' => $course
        ]);
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // تحديث بيانات الكورس الأساسية
            DB::table('courses')->where('course_id', $id)->update([
                'course_name' => $request->course_name,
                'description' => $request->description,
                'price' => $request->price ?? 0,
                'is_free' => $request->has('is_free') ? $request->is_free : ($request->price > 0 ? false : true),
                'is_public' => $request->has('is_public') ? $request->is_public : false,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
                'updated_at' => now(),
            ]);

            // تحديث السيكورسات الموجودة
            if ($request->has('existing_subcourse_ids')) {
                foreach ($request->existing_subcourse_ids as $index => $subcourseId) {
                    if (! empty($subcourseId) && isset($request->subcourses['existing'][$index])) {
                        $subcourseData = $request->subcourses['existing'][$index];

                        DB::table('subcourses')->where('subcourse_id', $subcourseId)->update([
                            'subcourse_number' => $subcourseData['number'],
                            'subcourse_name' => $subcourseData['name'],
                            'duration_hours' => $subcourseData['duration'],
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // حذف السيكورسات المحددة
            if ($request->has('deleted_subcourse_ids') && ! empty($request->deleted_subcourse_ids)) {
                $deletedIds = explode(',', $request->deleted_subcourse_ids);
                foreach ($deletedIds as $deletedId) {
                    if (! empty($deletedId)) {
                        DB::table('subcourses')->where('subcourse_id', $deletedId)->delete();
                    }
                }
            }

            // إضافة سيكورسات جديدة
            if ($request->has('subcourses') && isset($request->subcourses['new'])) {
                foreach ($request->subcourses['new'] as $newSubcourse) {
                    if (! empty($newSubcourse['name']) && ! empty($newSubcourse['number']) && ! empty($newSubcourse['duration'])) {
                        \App\Models\SubCourse::create([
                            'course_id' => $id,
                            'subcourse_number' => $newSubcourse['number'],
                            'subcourse_name' => $newSubcourse['name'],
                            'description' => $newSubcourse['name'],
                            'duration_hours' => $newSubcourse['duration'],
                        ]);
                    }
                }
            }

            // مسح الكاش بعد التحديث
            cache()->forget('courses_index');
            cache()->forget('courses_fetch__1'); // مسح البحث الفارغ
            cache()->forget('courses_fetch__2'); // مسح الصفحة الثانية إن وجدت

            DB::commit();

            return redirect()->route('courses')->with('success', 'Course and subcourses updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error updating course: '.$e->getMessage());
        }
    }

    public function deleteSubcourse(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // حذف السيكورس
            DB::table('subcourses')->where('subcourse_id', $id)->delete();

            // مسح الكاش
            cache()->forget('courses_index');

            DB::commit();

            return redirect()->back()->with('success', 'The subcourse was deleted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting subcourse: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء حذف السيكورس: '.$e->getMessage());
        }
    }

    // في CourseController - أضف هذه الدالة
    public function destroy($id)
    {
        try {
            DB::transaction(function () use ($id) {
                // 1. أولاً احذف جميع الـ subcourses المرتبطة بالكورس
                DB::table('subcourses')->where('course_id', $id)->delete();

                // 2. ثم احذف الكورس نفسه
                DB::table('courses')->where('course_id', $id)->delete();
            });

            // مسح الكاش
            cache()->forget('courses_index');
            cache()->forget('courses_fetch__1');
            cache()->forget('courses_fetch__2');

            return redirect()->back()->with('success', 'Course and all related subcourses deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting course: '.$e->getMessage());

            return back()->with('error', 'Failed to delete course: '.$e->getMessage());
        }
    }

    public function showSubcourses($courseId)
    {
        $course = DB::table('courses')->where('course_id', $courseId)->first();

        if (! $course) {
            abort(404, 'Course not found');
        }

        $subcourses = DB::table('subcourses')
            ->where('course_id', $courseId)
            ->orderBy('subcourse_number')
            ->get();

        return view('courses.subcourses', [
            'course' => $course,
            'subcourses' => $subcourses
        ]);
    }

    public function publicIndex(Request $request)
    {
        $search = $request->get('search');
        
        $courses = Course::where('is_public', true)
            ->where('is_active', true)
            ->withCount('subcourses')
            ->when($search, function($query, $search) {
                $query->where('course_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderByDesc('course_id')
            ->paginate(12)
            ->withQueryString();

        return view('courses.public-index', [
            'courses' => $courses,
            'filters' => [
                'search' => $search
            ],
            'auth' => [
                'user' => auth()->user()
            ]
        ]);
    }

    public function publicShow($id)
    {
        $course = Course::with(['subcourses' => function($q) {
            $q->orderBy('subcourse_number');
        }])
        ->where('is_public', true)
        ->findOrFail($id);

        return view('courses.public-show', [
            'course' => $course,
            'auth' => [
                'user' => auth()->user()
            ]
        ]);
    }

    public function enroll(Request $request, $id)
    {
        $course = Course::findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login')->with('info', 'يجب تسجيل الدخول أولاً للمتابعة.');
        }

        if ($course->is_free) {
            // Check if student record exists
            $student = \App\Models\Student::where('user_id', $user->id)->first();
            if (!$student) {
                return back()->with('error', 'يجب إكمال بيانات ملفك الشخصي كطالب أولاً.');
            }

            // Enroll directly
            DB::table('student_course')->updateOrInsert([
                'student_id' => $student->student_id,
                'course_id' => $course->course_id
            ], [
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return redirect()->route('student.dashboard.index')->with('success', 'تم الاشتراك في الكورس بنجاح!');
        } else {
            // Create enrollment request
            $enrollRequest = \App\Models\EnrollmentRequest::updateOrCreate([
                'user_id' => $user->id,
                'course_id' => $course->course_id,
                'status' => 'pending'
            ], [
                'amount' => $course->price,
                'notes' => 'طلب اشتراك من الصفحة العامة',
                'updated_at' => now()
            ]);

            // Attempt to generate Fawry Pay Payload
            $fawryService = app(\App\Services\FawryService::class);
            $fawryPayload = $fawryService->generatePaymentUrl($enrollRequest);

            return back()->with([
                'success' => 'تم إنشاء طلب الاشتراك. يمكنك الدفع الآن.',
                'fawry_payload' => $fawryPayload
            ]);
        }
    }
}
