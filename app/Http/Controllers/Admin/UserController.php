<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminType;
use App\Models\AuditLog;
use App\Models\Department;
// use App\Models\PlacementExam; // Removed as model is missing
use App\Models\Profile;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserNote;
use App\Traits\Impersonate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use Impersonate;

    public function impersonateUser(Request $request, User $user)
    {
        try {
            $success = $this->impersonate($user->id);

            if ($success) {
                $redirectUrl = $this->getDashboardByRole($user->role_id);
                
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'event' => 'impersonation_start',
                    'description' => "Admin started impersonating user ID: {$user->id} ({$user->username})",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // For cross-namespace redirects (like going to /student-dashboard),
                // Inertia::location forces a full page reload which is safer after impersonation.
                return redirect($redirectUrl);
            } else {
                return redirect()->back()->with('error', 'ليس لديك صلاحية لانتحال هذا المستخدم');
            }
        } catch (\Exception $e) {
            Log::error('Impersonation failed: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء انتحال الهوية: ' . $e->getMessage());
        }
    }

    private function getDashboardByRole($roleId)
    {
        switch ($roleId) {
            case 1: // Admin
                return route('dashboard');
            case 2: // Teacher
                return route('teacher.dashboard') ?: url('/teacher_dashboard');
            case 3: // Student
                return route('student.dashboard') ?: url('/student_dashboard');
            default:
                return route('dashboard');
        }
    }

    /**
     * إنهاء الانتحال
     */
    public function stopImpersonate(Request $request)
    {
        try {
            $success = $this->stopImpersonating();

            if ($success) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'event' => 'impersonation_stop',
                    'description' => 'Admin stopped impersonation',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Return Inertia location because logging out from the impersonated user 
                // and logging back into the admin often requires full reload of permissions 
                // to correctly enter another dashboard.
                return redirect()->route('dashboard');
            } else {
                return redirect()->back()
                    ->with('error', 'لم تكن تنتحل هوية أحد');
            }
        } catch (\Exception $e) {
            Log::error('Stop impersonation failed: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء العودة للهوية الأصلية');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function index()
    {
        $users = User::with(['role', 'profile', 'adminType', 'roles'])
            ->when(request()->filled('search'), function($q) {
                $search = request()->search;
                $q->where(function($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('profile', function($profileQuery) use ($search) {
                            $profileQuery->where('nickname', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when(request()->filled('role_id'), function($q) {
                $q->where('role_id', request()->role_id);
            })
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();
        $roles = Role::all();
        $adminTypes = AdminType::all();
        $spatieRoles = \Spatie\Permission\Models\Role::all();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'adminTypes' => $adminTypes,
            'spatieRoles' => $spatieRoles,
        ]);
    }

    public function create(Request $request)
    {
        $roles = Role::all();
        $departments = Department::all();
        $adminTypes = AdminType::all();

        $bookingData = null;

        // إذا كان هناك booking_id في الرابط
        if ($request->has('booking_id')) {
            $booking = \App\Models\Booking::find($request->booking_id);

            if ($booking) {
                $bookingData = [
                    'name' => $booking->name,
                    'email' => $booking->email,
                    'phone' => $booking->phone,
                    'age' => $booking->age,
                    'booking_id' => $booking->id,
                ];
            }
        }

        $spatieRoles = \Spatie\Permission\Models\Role::all();

        return view('users.create', [
            'roles' => $roles,
            'departments' => $departments,
            'adminTypes' => $adminTypes,
            'bookingData' => $bookingData,
            'spatieRoles' => $spatieRoles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('User store request received', $request->all());

        // Validation rules
        $rules = [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'nickname' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'spatie_roles' => 'required|array|min:1',
            'spatie_roles.*' => 'exists:roles,name',
        ];

        $request->validate($rules);

        // Determine Legacy Roles from Spatie Roles
        $spatieRoles = $request->spatie_roles;
        $roleId = 3; // Default Student
        $adminTypeId = null;

        if (in_array('super-admin', $spatieRoles)) {
            $roleId = 1;
            $adminTypeId = 1; // Full admin
        } elseif (in_array('teacher', $spatieRoles)) {
            $roleId = 2;
        } elseif (in_array('student', $spatieRoles)) {
            $roleId = 3;
        } else {
            // Any other role implies admin panel access
            $roleId = 1;
            $adminTypeId = 2; // Partial admin
            if (!in_array('admin', $spatieRoles)) {
                $spatieRoles[] = 'admin'; // base admin role
            }
        }

        DB::beginTransaction();
        try {
            // Create user
            $userData = [
                'username' => $request->username,
                'email' => $request->email,
                'pass' => Hash::make($request->password),
                'role_id' => $roleId,
                'admin_type_id' => $adminTypeId,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ];

            $user = User::create($userData);

            // Sync Spatie Roles directly
            $user->syncRoles($spatieRoles);

            // Create profile
            $profileData = [
                'user_id' => $user->id,
                'nickname' => $request->nickname,
                'phone_number' => $request->phone_number,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
            ];

            Profile::create($profileData);

            // Handle role-specific records
            if ($user->isTeacher()) { // Teacher
                Teacher::create([
                    'user_id' => $user->id,
                    'teacher_name' => $request->nickname,
                ]);
            } elseif ($user->isStudent()) { // Student
                Student::create([
                    'user_id' => $user->id,
                    'student_name' => $request->nickname,
                    'enrollment_date' => $request->enrollment_date ?? now(),
                ]);
            }

            DB::commit();

            Log::info('User created successfully', ['user_id' => $user->id]);

            return redirect()->route('users.index')
                ->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create user: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function getStudentsByCourse($courseId)
    {
        try {
            $students = \App\Models\Student::where(function ($query) use ($courseId) {
                // طلاب ليس لديهم مجموعات انتظار نشطة
                $query->whereDoesntHave('waitingStudents', function ($q) {
                    $q->whereIn('status', ['waiting', 'contacted', 'approved']);
                });

                // أو طلاب لديهم حجوزات في هذا الكورس
                $query->orWhereHas('bookings', function ($q) use ($courseId) {
                    $q->where('course_id', $courseId);
                });
            })
                ->with(['user', 'bookings' => function ($query) use ($courseId) {
                    $query->where('course_id', $courseId);
                }])
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
     * Display the specified resource.
     */
    public function get($id)
    {
        $user = User::with('profile', 'role')->findOrFail($id);

        // إرجاع كلمة المرور الحقيقية (غير مشفرة)
        $user->plain_password = $user->pass; // هذا سيعيد كلمة المرور المشفرة

        // إذا كنت تريد كلمة مرور افتراضية للعرض فقط
        // $user->plain_password = '********';

        $roles = Role::all();
        $adminTypes = AdminType::all();

        $spatieRoles = \Spatie\Permission\Models\Role::all();

        return view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'adminTypes' => $adminTypes,
            'spatieRoles' => $spatieRoles,
        ]);
    }

    public function show(User $user)
    {
        $user->load(['role', 'profile', 'adminType']);

        return view('users.show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $user->load(['role', 'profile', 'adminType']);
        $roles = Role::all();
        $adminTypes = AdminType::all();
        $spatieRoles = \Spatie\Permission\Models\Role::all();

        return view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'adminTypes' => $adminTypes,
            'spatieRoles' => $spatieRoles,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        Log::info('User update request received', [
            'user_id' => $user->id,
            'has_profile_picture' => $request->hasFile('profile_picture'),
            'file_valid' => $request->hasFile('profile_picture') ? $request->file('profile_picture')->isValid() : false,
            'file_name' => $request->hasFile('profile_picture') ? $request->file('profile_picture')->getClientOriginalName() : 'None',
            'all_files' => array_keys($request->allFiles()),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
            'is_ajax' => $request->ajax(),
            'wants_json' => $request->wantsJson(),
        ]);

        // Custom validation rules for update
        $rules = [
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'is_active' => 'nullable|boolean',
            'nickname' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'spatie_roles' => 'required|array|min:1',
            'spatie_roles.*' => 'exists:roles,name',
        ];

        // Password validation - only if user is trying to change it
        if ($request->filled('pass') && $request->pass !== '********') {
            $rules['pass'] = [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z]).{8,}$/',
            ];
        }

        $messages = [
            'pass.regex' => 'كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل وحرف واحد كبير (Uppercase) على الأقل.',
            'spatie_roles.required' => 'يجب اختيار صلاحية واحدة على الأقل للمستخدم.',
        ];

        $request->validate($rules, $messages);

        // Determine Legacy Roles from Spatie Roles
        $spatieRoles = $request->spatie_roles;
        $roleId = 3; // Default Student
        $adminTypeId = null;

        if (in_array('super-admin', $spatieRoles)) {
            $roleId = 1;
            $adminTypeId = 1; // Full admin
        } elseif (in_array('teacher', $spatieRoles)) {
            $roleId = 2;
        } elseif (in_array('student', $spatieRoles)) {
            $roleId = 3;
        } else {
            // Any other role implies admin panel access
            $roleId = 1;
            $adminTypeId = 2; // Partial admin
            if (!in_array('admin', $spatieRoles)) {
                $spatieRoles[] = 'admin'; // base admin role
            }
        }

        DB::beginTransaction();
        try {
            $userData = [
                'username' => $request->username,
                'email' => $request->email,
                'role_id' => $roleId,
                'admin_type_id' => $adminTypeId,
                'is_active' => $request->boolean('is_active'),
            ];

            // Handle password update
            if ($request->filled('pass') && $request->pass !== '********') {
                $userData['pass'] = Hash::make($request->pass);
            }

            $user->update($userData);

            // Sync Spatie Roles directly
            $user->syncRoles($spatieRoles);

            // Update or create profile
            $profileData = [
                'nickname' => $request->nickname,
                'phone_number' => $request->phone_number,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
            ];

            // Handle profile picture upload
            if ($request->hasFile('profile_picture') && $request->file('profile_picture')->isValid()) {
                Log::info('Processing profile picture upload', [
                    'user_id' => $user->id,
                    'file_name' => $request->file('profile_picture')->getClientOriginalName(),
                    'file_size' => $request->file('profile_picture')->getSize(),
                ]);

                $target_dir = public_path('uploads/profile_pictures');

                if (! file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                    Log::info('Created uploads directory', ['path' => $target_dir]);
                }

                $file = $request->file('profile_picture');
                $file_extension = strtolower($file->getClientOriginalExtension());
                $new_filename = 'user_'.$user->id.'_'.time().'.'.$file_extension;
                $target_file = $target_dir.DIRECTORY_SEPARATOR.$new_filename;

                // Check file size (max 2MB)
                if ($file->getSize() > 2000000) {
                    Log::warning('File too large', ['user_id' => $user->id, 'size' => $file->getSize()]);
                    throw new \Exception('File size must be less than 2MB');
                }

                // Check file type
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (! in_array($file_extension, $allowed_extensions)) {
                    Log::warning('Invalid file extension', ['user_id' => $user->id, 'extension' => $file_extension]);
                    throw new \Exception('Only JPG, JPEG, PNG & GIF files are allowed');
                }

                // Delete old image if exists
                if ($user->profile && $user->profile->profile_picture_url) {
                    $oldPath = public_path($user->profile->profile_picture_url);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                        Log::info('Deleted old profile picture', ['user_id' => $user->id, 'old_path' => $oldPath]);
                    }
                }

                // Upload new file
                try {
                    $file->move($target_dir, $new_filename);

                    if (file_exists($target_file)) {
                        $profileData['profile_picture_url'] = '/uploads/profile_pictures/'.$new_filename;
                        Log::info('Profile picture uploaded successfully', [
                            'user_id' => $user->id,
                            'filename' => $new_filename,
                            'path' => $target_file,
                            'size' => filesize($target_file),
                        ]);
                    } else {
                        throw new \Exception('File was not saved to target location');
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to move uploaded file', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'target_dir' => $target_dir,
                        'target_file' => $target_file,
                    ]);
                    throw $e;
                }
            } elseif ($user->profile && $user->profile->profile_picture_url) {
                // Keep existing picture if no new one uploaded
                $profileData['profile_picture_url'] = $user->profile->profile_picture_url;
                Log::info('Keeping existing profile picture', ['user_id' => $user->id]);
            } else {
                Log::info('No profile picture to handle', ['user_id' => $user->id]);
            }

            // Update or create profile
            if ($user->profile) {
                $user->profile->update($profileData);
                Log::info('Profile updated', ['user_id' => $user->id, 'profile_data' => $profileData]);
            } else {
                $profileData['user_id'] = $user->id;
                Profile::create($profileData);
                Log::info('Profile created', ['user_id' => $user->id, 'profile_data' => $profileData]);
            }

            DB::commit();
            Log::info('User update completed successfully', ['user_id' => $user->id]);

            return redirect()->route('users.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('User update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update user: '.$e->getMessage(),
                ]);
            }

            return redirect()->back()->with('error', 'Failed to update user: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * Remove the specified resource from storage.
     */
    /**
     * Remove the specified resource from storage.
     */
    /**
     * Remove the specified resource from storage. (الطريقة المبسطة)
     */
    /**
     * Remove the specified resource from storage.
     */
    /**
     * Remove the specified resource from storage. (الحل النهائي)
     */
    public function destroy(Request $request, User $user)
    {
        $current = \Auth::user();
        if ($current && $current->isSecretary()) {
            if ($user->role && strtolower($user->role->role_name) === 'admin') {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
                }

                return abort(403, 'Forbidden: you are not allowed to delete this user.');
            }
        }

        DB::beginTransaction();
        try {
            // تعطيل foreign key checks مؤقتاً
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // 1. احذف الـ profile picture
            if ($user->profile && $user->profile->profile_picture_url) {
                $path = public_path($user->profile->profile_picture_url);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            // 2. احذف الـ profile
            if ($user->profile) {
                $user->profile()->delete();
            }

            // 3. احذف الـ teacher أو student records
            if ($user->isTeacher()) { // Teacher
                $teacher = Teacher::where('user_id', $user->id)->first();
                if ($teacher) {
                    $teacher->delete();
                }
            } elseif ($user->isStudent()) { // Student
                $student = Student::where('user_id', $user->id)->first();
                if ($student) {
                    $student->delete();
                }
            }

            // 4. احذف أي علاقات تانيه
            $tables = ['notifications', 'activity_log', 'sessions', 'user_permissions'];
            foreach ($tables as $table) {
                try {
                    DB::table($table)->where('user_id', $user->id)->delete();
                } catch (\Exception $e) {
                    // تجاهل الجداول غير الموجودة
                }
            }

            // 5. احذف اليوزر نفسه
            $user->delete();

            // إعادة تفعيل foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::commit();

            return redirect()->route('users.index')->with('success', 'تم حذف المستخدم بنجاح!');

            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            DB::rollback();

            // التأكد من إعادة تفعيل foreign key checks في حالة الخطأ
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $errorMessage = 'Failed to delete user: '.$e->getMessage();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ]);
            }

            return redirect()->back()
                ->with('error', 'Failed to delete user: '.$e->getMessage());
        }
    }

    /**
     * Show the form for creating a new student user (public registration)
     */
    public function createStudentForm(Request $request)
    {
        // الحصول على بيانات الحجز إذا كانت موجودة (للتوافق مع النظام القديم)
        $bookingData = null;
        if ($request->has('booking_id')) {
            $booking = \App\Models\Booking::find($request->booking_id);
            if ($booking) {
                $bookingData = [
                    'name' => $booking->name,
                    'email' => $booking->email,
                    'phone' => $booking->phone,
                    'age' => $booking->age,
                    'booking_id' => $booking->id,
                    'message' => $booking->message,
                ];
            }
        }

        // الحصول على الكورسات لعرضها في القائمة
        $courses = \App\Models\Course::all();

        return view('users.register-student', [
            'courses' => $courses,
            'bookingData' => $bookingData,
        ]);
    }
    /**
     * Get user details for modal via AJAX
     */

    /**
     * Get user statistics for dashboard
     */
    /**
     * Get user details for modal via AJAX
     */
    public function getUserDetails($id)
    {
        try {
            $user = User::with(['role', 'profile', 'adminType', 'student', 'teacher'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب بيانات المستخدم',
            ], 500);
        }
    }

    /**
     * Fetch users with filters for AJAX table
     */
    public function fetchData(Request $request)
    {
        $query = User::with(['role', 'profile', 'adminType']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($q2) use ($search) {
                        $q2->where('nickname', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'id_desc':
                    $query->orderBy('id', 'desc');
                    break;
                case 'id_asc':
                    $query->orderBy('id', 'asc');
                    break;
                case 'name_asc':
                    $query->orderBy('username', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('username', 'desc');
                    break;
                default:
                    $query->orderBy('id', 'desc');
            }
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = $request->filled('per_page') ? $request->per_page : 15;
        $users = $query->paginate($perPage);

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function getStats()
    {
        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', 1)->count(),
            'admins' => User::where('role_id', Role::ADMIN_ID)->count(),
            'students' => User::where('role_id', Role::STUDENT_ID)->count(),
            'teachers' => User::where('role_id', Role::TEACHER_ID)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Store a newly created student user from public registration
     */
    public function storeStudent(Request $request)
    {
        Log::info('Student registration request received', $request->all());

        // Validation rules for student registration
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'age' => 'required|integer|min:1|max:120',
            'password' => 'required|string|min:8|confirmed',
            'course_id' => 'nullable|exists:courses,course_id',
            'subcourse_id' => 'nullable|exists:subcourses,subcourse_id',
            'message' => 'nullable|string',
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
        ];

        $request->validate($rules);

        DB::beginTransaction();
        try {
            // 1. إنشاء اسم المستخدم تلقائياً من الاسم والبريد
            $username = $this->generateUsername($request->name, $request->email);

            // 2. إنشاء المستخدم (Student role = 3)
            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'pass' => Hash::make($request->password),
                'role_id' => Role::STUDENT_ID, // Student role
                'is_active' => 0, // غير نشط حتى يتم التفعيل
            ]);

            // 3. إنشاء Profile
            Profile::create([
                'user_id' => $user->id,
                'nickname' => $request->name,
                'phone_number' => $request->phone,
                'date_of_birth' => now()->subYears($request->age)->format('Y-m-d'), // تحويل العمر لتاريخ ميلاد تقريبي
            ]);

            // 4. إنشاء Student record
            $student = Student::create([
                'user_id' => $user->id,
                'student_name' => $request->name,
                'enrollment_date' => now(),
            ]);

            // 5. تسجيل معلومات إضافية (معطل حالياً لعدم وجود موديل UserNote)
            /*
            if ($request->filled('message')) {
                UserNote::create([
                    'user_id' => $user->id,
                    'note' => 'ملاحظة من نموذج التسجيل: '.$request->message,
                    'created_by' => 1,
                ]);
            }
            */

            /* 
            // 6. إذا كان هناك تقييم امتحان تحديد مستوى (للبامين فقط) - تم التعطيل لعدم وجود الموديل
            if (auth()->check() && auth()->user()->isAdmin() && $request->filled('placement_exam_grade')) {
                PlacementExam::create([
                    'student_id' => $student->student_id,
                    'grade' => $request->placement_exam_grade,
                    'exam_date' => now(),
                    'admin_id' => \Auth::id(),
                ]);
            }
            */

            // 7. إذا كان هناك اختيار لمجموعة انتظار (للبامين فقط)
            if (\Auth::check() && \Auth::user()->isAdmin()) {
                if ($request->filled('waiting_group_name') && $request->filled('course_id')) {
                    $groupName = $request->waiting_group_name ?: $request->new_group_name;

                    if ($groupName) {
                        \App\Models\WaitingStudent::create([
                            'student_id' => $student->student_id,
                            'course_id' => $request->course_id,
                            'subcourse_id' => $request->subcourse_id,
                            'group_name' => $groupName,
                            'status' => 'waiting',
                            'added_by' => \Auth::id(),
                        ]);
                    }
                }
            }

            // 8. إذا كان هناك booking_id (لتحويل الحجوزات القديمة)
            if ($request->filled('booking_id')) {
                $booking = \App\Models\Booking::find($request->booking_id);
                if ($booking) {
                    $booking->update([
                        'status' => 'converted_to_user',
                        'converted_user_id' => $user->id,
                        'converted_at' => now(),
                    ]);
                }
            }

            DB::commit();

            // إرسال إيميل التفعيل (إذا كان النظام يدعمه)
            // $this->sendActivationEmail($user);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تسجيلك بنجاح! سيتم تفعيل حسابك من قبل الإدارة قريباً.',
                    'user_id' => $user->id,
                ]);
            }

            return redirect()->route('welcome')->with('success', 'تم تسجيلك بنجاح! سيتم تفعيل حسابك من قبل الإدارة قريباً.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Student registration failed: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء التسجيل: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء التسجيل: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Generate unique username from name and email
     */
    /**
     * عرض نموذج التسجيل للطلاب (للزوار)
     */
    /**
     * عرض نموذج التسجيل للطلاب (للزوار)
     */
    /**
     * عرض نموذج التسجيل للطلاب (للزوار)
     */
    public function showRegistrationForm(Request $request)
    {
        // الحصول على بيانات الحجز إذا كانت موجودة
        $bookingData = null;
        if ($request->has('booking_id')) {
            $booking = \App\Models\Booking::find($request->booking_id);
            if ($booking) {
                $bookingData = [
                    'name' => $booking->name,
                    'email' => $booking->email,
                    'phone' => $booking->phone,
                    'age' => $booking->age,
                    'booking_id' => $booking->id,
                    'message' => $booking->message,
                ];
            }
        }

        // جلب جميع الكورسات النشطة فقط
        $courses = \App\Models\Course::all();

        return view('auth.register-student', [
            'courses' => $courses,
            'bookingData' => $bookingData
        ]);
    }

    /**
     * تسجيل الطالب (للزوار)
     */
    /**
     * تسجيل الطالب (للزوار)
     */
    /**
     * تسجيل الطالب (للزوار)
     */
    /**
     * تسجيل الطالب (للزوار)
     */
    /**
     * تسجيل الطالب (للزوار)
     */
    public function registerStudent(Request $request)
    {
        Log::info('Student registration from public form', $request->all());

        // قواعد التحقق
        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'date_of_birth' => 'required|date|before:-10 years',
            'gender' => 'required|in:male,female',
            'education_level' => 'nullable|string|max:100',
            'parent_phone' => 'nullable|string|max:20',
            'interests' => 'nullable|string',
            'how_know_us' => 'nullable|string|max:255',
            'terms' => 'required|accepted',
            'course_id' => 'required|exists:courses,course_id',
        ];

        $messages = [
            'date_of_birth.before' => 'يجب أن يكون عمرك 10 سنوات على الأقل',
            'terms.accepted' => 'يجب الموافقة على الشروط والأحكام',
            'course_id.required' => 'يرجى اختيار الكورس المطلوب',
            'course_id.exists' => 'الكورس المختار غير موجود',
        ];

        $request->validate($rules, $messages);

        DB::beginTransaction();
        try {
            // 1. إنشاء اسم مستخدم فريد
            $username = $this->generateStudentUsername($request->full_name, $request->email);

            // 2. إنشاء المستخدم (طالب - غير نشط)
            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'pass' => Hash::make($request->password),
                'role_id' => Role::STUDENT_ID, // Student role
                'is_active' => 0, // غير نشط - يحتاج تفعيل من الإدارة
            ]);

            // 3. إنشاء الملف الشخصي
            $profileData = [
                'user_id' => $user->id,
                'nickname' => $request->full_name,
                'phone_number' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
            ];

            if ($request->filled('gender')) {
                $profileData['gender'] = $request->gender;
            }
            if ($request->filled('education_level')) {
                $profileData['education_level'] = $request->education_level;
            }
            if ($request->filled('parent_phone')) {
                $profileData['parent_phone'] = $request->parent_phone;
            }
            if ($request->filled('interests')) {
                $profileData['interests'] = $request->interests;
            }

            $profile = Profile::create($profileData);

            // 4. الحصول على بيانات الكورس المختار
            $course = \App\Models\Course::find($request->course_id);
            $courseName = $course ? $course->course_name : 'غير محدد';

            // 5. بناء بيانات الطالب لعرضها في صفحة النجاح
            $studentDataForSession = [
                'student_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'education_level' => $request->education_level ?? 'غير محدد',
                'parent_phone' => $request->parent_phone ?? 'غير محدد',
                'interests' => $request->interests ?? 'غير محدد',
                'how_know_us' => $request->how_know_us ?? 'غير محدد',
                'username' => $username,
                'course_name' => $courseName,
                'registration_date' => now()->format('Y/m-d h:i A'),
            ];

            // 6. **تخزين البيانات في notes كـ JSON**
            $notes = json_encode($studentDataForSession, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            // 7. إنشاء سجل الطالب مع تخزين notes
            $student = Student::create([
                'user_id' => $user->id,
                'student_name' => $request->full_name,
                'preferred_course_id' => $request->course_id,
                'enrollment_date' => now(),
                'status' => 'pending',
                'notes' => $notes, // تخزين جميع البيانات هنا
            ]);

            // 8. حفظ الكورس المفضل في student_meta
            \App\Models\StudentMeta::setValue($student->student_id, 'preferred_course_id', $request->course_id);
            \App\Models\StudentMeta::setValue($student->student_id, 'preferred_course_name', $courseName);

            // 9. حفظ نسخة من البيانات في student_meta أيضاً
            \App\Models\StudentMeta::create([
                'student_id' => $student->student_id,
                'meta_key' => 'registration_data',
                'meta_value' => json_encode($studentDataForSession, JSON_UNESCAPED_UNICODE),
            ]);

            // 10. البحث عن مجموعة انتظار مناسبة
            $waitingGroup = \App\Models\WaitingGroup::where('course_id', $request->course_id)
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->first();

            // 11. تسجيل الطالب في مجموعة الانتظار
            if ($waitingGroup) {
                \App\Models\WaitingStudent::create([
                    'waiting_group_id' => $waitingGroup->id,
                    'student_id' => $student->student_id,
                    'user_id' => $user->id,
                    'status' => 'waiting',
                    'notes' => 'تسجيل ذاتي من الموقع - الكورس المطلوب: '.$courseName,
                    'joined_at' => now(),
                    'added_by' => null,
                ]);
            } else {
                $newWaitingGroup = \App\Models\WaitingGroup::create([
                    'group_name' => 'مجموعة انتظار '.$courseName.' - '.now()->format('Y-m-d'),
                    'course_id' => $request->course_id,
                    'max_students' => 20,
                    'status' => 'active',
                    'created_by' => null,
                ]);

                \App\Models\WaitingStudent::create([
                    'waiting_group_id' => $newWaitingGroup->id,
                    'student_id' => $student->student_id,
                    'user_id' => $user->id,
                    'status' => 'waiting',
                    'notes' => 'تسجيل ذاتي من الموقع - الكورس المطلوب: '.$courseName.' (مجموعة جديدة)',
                    'joined_at' => now(),
                    'added_by' => null,
                ]);
            }

            // 12. إرسال إشعار للإدارة
            $this->notifyAdminsAboutNewRegistration($user, $student);

            DB::commit();

            // 13. تخزين البيانات في session
            session([
                'registration_success' => true,
                'student_id' => $student->student_id,
                'student_data' => $studentDataForSession, // إضافة student_id هنا
            ]);

            // تأكد من إضافة student_id للبيانات
            $studentDataForSession['student_id'] = $student->student_id;
            session()->put('student_data', $studentDataForSession);

            Log::info('✅ Student registration completed successfully', [
                'user_id' => $user->id,
                'student_id' => $student->student_id,
                'student_data' => $studentDataForSession,
            ]);

            // حساب غير مفعل — سجل تنبيه للمطور/الادمن مع رابط الدعم لتفعيل الحساب
            if ($user->is_active == 0) {
                Log::info('User account created but not active; instruct to contact admin to activate account', [
                    'user_id' => $user->id,
                    'help_url' => 'https://www.ict-academmy.com/help',
                ]);
            }

            // 14. التوجيه إلى صفحة النجاح مع student_id
            return redirect()->route('registration.success');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('❌ Student registration failed: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء التسجيل: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * عرض الطلاب الجدد (الغير مفعلين)
     */
    /**
     * عرض الطلاب الجدد (الغير مفعلين)
     */
    /**
     * عرض الطلاب الجدد (الغير مفعلين)
     */
    // في UserController.php
    public function updatePreferredCourse(Request $request, $userId)
    {
        $user = User::where('id', '=', $userId)->with('student')->firstOrFail(['*']);

        if (! $user->student) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على بيانات الطالب',
            ]);
        }

        $request->validate([
            'course_id' => 'required|exists:courses,course_id',
        ]);

        \DB::beginTransaction();
        try {
            // تحديث العمود المباشر
            $user->student->update([
                'preferred_course_id' => $request->course_id,
            ]);

            // تحديث الـ meta
            \App\Models\StudentMeta::setValue(
                $user->student->student_id,
                'preferred_course_id',
                $request->course_id
            );

            // سجل النشاط
            $course = \App\Models\Course::find($request->course_id);
            Log::info('Course preference updated', [
                'user_id' => $userId,
                'student_id' => $user->student->student_id,
                'course_id' => $request->course_id,
                'course_name' => $course->course_name,
                'updated_by' => \Auth::id(),
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الكورس المفضل للطالب بنجاح',
                'course_name' => $course->course_name,
            ]);

        } catch (\Exception $e) {
            \DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الكورس: '.$e->getMessage(),
            ], 500);
        }
    }

    public function pendingStudents(Request $request)
    {
        $query = User::where('role_id', Role::STUDENT_ID)
            ->where('is_active', 0)
            ->with([
                'profile',
                'student',
                'student.preferredCourse',
            ])
            ->latest();

        // فلترة حسب تاريخ التسجيل
        if ($request->filled('date_from') && ! empty($request->date_from)) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to') && ! empty($request->date_to)) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // فلترة حسب الكورس
        if ($request->filled('course_id') && ! empty($request->course_id)) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('preferred_course_id', $request->course_id);
            });
        }

        // بحث
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('nickname', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('student_name', 'like', "%{$search}%");
                    });
            });
        }

        $pendingStudents = $query->paginate(20);

        // جلب الكورسات لعرضها في الفلترة
        $courses = \App\Models\Course::orderBy('course_name')->get();

        return view('admin.users.pending-students', [
            'pendingStudents' => $pendingStudents,
            'courses' => $courses,
            'filters' => $request->only(['search', 'course_id', 'date_from', 'date_to'])
        ]);
    }

    /**
     * تفعيل طالب
     */
    /**
     * تفعيل طالب
     */
    /**
     * تفعيل طالب
     */
    public function activateStudent(Request $request, User $user)
    {
        // التحقق من أن اليوزر طالب وغير مفعل
        if (! $user->isStudent() || $user->is_active == 1) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تفعيل هذا المستخدم',
                ], 400);
            }

            return redirect()->back()->with('error', 'لا يمكن تفعيل هذا المستخدم');
        }

        \DB::beginTransaction();
        try {
            // 1. تفعيل اليوزر
            /** @var \App\Models\User $user */
            /** @var \App\Models\User $user */
            $user->update([
                'is_active' => 1,
            ]);

            // 2. تحديث حالة الطالب
            if ($user->student) {
                $student = $user->student;
                $student->update([
                    'status' => 'active',
                ]);

                // 3. جلب بيانات الكورس المفضل
                $preferredCourseId = \App\Models\StudentMeta::where('student_id', $student->student_id)
                    ->where('meta_key', 'preferred_course_id')
                    ->first();

                $courseName = 'غير محدد';
                $courseId = null;

                if ($preferredCourseId) {
                    $courseId = $preferredCourseId->meta_value;
                    $course = \App\Models\Course::find($courseId);
                    $courseName = $course ? $course->course_name : 'غير محدد';

                    // 4. البحث عن الطالب في مجموعات الانتظار
                    $waitingStudent = \App\Models\WaitingStudent::where('student_id', $student->student_id)
                        ->where('status', 'waiting')
                        ->first();

                    if ($waitingStudent) {
                        // 5. تحديث حالة الطالب في مجموعة الانتظار
                        $waitingStudent->update([
                            'status' => 'approved',
                            'notes' => $waitingStudent->notes."\nتم التفعيل في: ".now()->format('Y-m-d H:i:s').
                                      ' بواسطة: '.(\Auth::user()->profile->nickname ?? \Auth::user()->username),
                        ]);

                        Log::info('Student waiting status updated to approved', [
                            'student_id' => $student->student_id,
                            'waiting_student_id' => $waitingStudent->id,
                            'course_id' => $courseId,
                        ]);
                    } else {
                        // 6. إذا لم يكن في مجموعة انتظار، نضيفه
                        $waitingGroup = \App\Models\WaitingGroup::where('course_id', $courseId)
                            ->where('status', 'active')
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($waitingGroup) {
                            \App\Models\WaitingStudent::create([
                                'waiting_group_id' => $waitingGroup->id,
                                'student_id' => $student->student_id,
                                'user_id' => $user->id,
                                'status' => 'approved',
                                'notes' => 'تم التفعيل من قبل الإدارة في: '.now()->format('Y-m-d H:i:s').
                                          ' - الكورس المطلوب: '.$courseName,
                                'joined_at' => now(),
                                'added_by' => \Auth::id(),
                            ]);

                            Log::info('Student added to waiting group after activation', [
                                'student_id' => $student->student_id,
                                'waiting_group_id' => $waitingGroup->id,
                                'course_id' => $courseId,
                            ]);
                        }
                    }

                    // 7. تحديث ملاحظات الطالب
                    $student->update([
                        'notes' => ($student->notes ? $student->notes."\n" : '').
                                  'تم التفعيل في: '.now()->format('Y-m-d H:i:s').
                                  ' - الكورس المختار: '.$courseName,
                    ]);
                } else {
                    // 8. إذا لم يكن هناك كورس محدد
                    $student->update([
                        'notes' => ($student->notes ? $student->notes."\n" : '').
                                  'تم التفعيل في: '.now()->format('Y-m-d H:i:s').
                                  ' (لم يحدد كورس)',
                    ]);

                    Log::info('Student activated without preferred course', [
                        'student_id' => $student->student_id,
                    ]);
                }
            }

            // 9. تسجيل النشاط
            Log::info('Student activated', [
                'user_id' => $user->id,
                'student_name' => $user->profile->nickname ?? $user->username,
                'activated_by' => \Auth::id(),
                'activated_at' => now(),
                'course_id' => $courseId ?? null,
                'course_name' => $courseName,
            ]);

            // 10. إرسال إيميل للطالب (إذا أردت)
            // $this->sendActivationEmail($user);

            \DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تفعيل الطالب بنجاح',
                    'user_id' => $user->id,
                    'student_name' => $user->profile->nickname ?? $user->username,
                    'course_name' => $courseName,
                ]);
            }

            return redirect()->route('pending.students')
                ->with('success', 'تم تفعيل الطالب '.
                      ($user->profile->nickname ?? $user->username).
                      ' بنجاح '.
                      ($courseName != 'غير محدد' ? 'وتم وضعه في قائمة الانتظار للكورس: '.$courseName : ''));

        } catch (\Exception $e) {
            \DB::rollback();
            Log::error('Failed to activate student: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء التفعيل: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء التفعيل: '.$e->getMessage());
        }
    }

    /**
     * رفض طلب التسجيل
     */
    public function rejectStudent(Request $request, User $user)
    {
        // التحقق من أن اليوزر طالب وغير مفعل
        if (! $user->isStudent() || $user->is_active == 1) {
            return redirect()->back()->with('error', 'لا يمكن رفض هذا المستخدم');
        }

        \DB::beginTransaction();
        try {
            $reason = $request->reason ?? 'تم رفض الطلب من قبل الإدارة';

            // تحديث حالة الطالب
            if ($user->student) {
                $user->student->update([
                    'status' => 'rejected',
                    'notes' => $reason.' - '.now()->format('Y-m-d H:i'),
                ]);
            }

            // تسجيل سبب الرفض في الـ meta
            \App\Models\StudentMeta::create([
                'student_id' => $user->student->student_id,
                'meta_key' => 'rejection_data',
                'meta_value' => [
                    'reason' => $reason,
                    'rejected_by' => \Auth::id(),
                    'rejected_at' => now(),
                ],
            ]);

            // حذف اليوزر أو تعطيله (حسب سياسة النظام)
            // $user->delete(); // أو
            // $user->update(['is_active' => 0]); // إذا كنت تريد تعطيله فقط

            Log::info('Student registration rejected', [
                'user_id' => $user->id,
                'rejected_by' => \Auth::id(),
                'reason' => $reason,
            ]);

            // إرسال إيميل للطالب (إذا أردت)
            // $this->sendRejectionEmail($user, $reason);

            \DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم رفض طلب التسجيل بنجاح',
                    'user_id' => $user->id,
                ]);
            }

            return redirect()->route('pending.students')
                ->with('success', 'تم رفض طلب التسجيل للطالب '.$user->profile->nickname);

        } catch (\Exception $e) {
            \DB::rollback();
            Log::error('Failed to reject student: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء الرفض: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء الرفض: '.$e->getMessage());
        }
    }

    /**
     * إرسال إيميل التفعيل للطالب
     */
    private function sendActivationEmail(User $user)
    {
        try {
            // Mail::to($user->email)->send(new StudentActivated($user));
            Log::info('Activation email would be sent to: '.$user->email);
        } catch (\Exception $e) {
            Log::error('Failed to send activation email: '.$e->getMessage());
        }
    }

    /**
     * إرسال إيميل الرفض للطالب
     */
    private function sendRejectionEmail(User $user, $reason)
    {
        try {
            // Mail::to($user->email)->send(new StudentRejected($user, $reason));
            Log::info('Rejection email would be sent to: '.$user->email.' - Reason: '.$reason);
        } catch (\Exception $e) {
            Log::error('Failed to send rejection email: '.$e->getMessage());
        }
    }

    /**
     * إضافة طالب لمجموعة انتظار
     */
    /**
     * إضافة طالب لمجموعة انتظار
     */
    public function addStudentToWaitingGroup(Request $request, $userId)
    {
        Log::info('addStudentToWaitingGroup called', [
            'user_id' => $userId,
            'request_data' => $request->all(),
        ]);

        $user = User::where('id', '=', $userId)->firstOrFail(['*']);

        // التحقق من أن المستخدم طالب
        if (! $user->isStudent()) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم ليس طالباً',
            ], 400);
        }

        $request->validate([
            'waiting_group_id' => 'required|exists:waiting_groups,id',
            'placement_exam_grade' => 'nullable|numeric|min:0|max:100',
            'assigned_level' => 'nullable|in:مبتدئ,متوسط,متقدم',
            'notes' => 'nullable|string',
        ]);

        // التحقق من وجود سجل طالب أو إنشاؤه
        $studentRecord = null;
        if (! $user->student) {
            Log::info('Creating new student record for user', ['user_id' => $user->id]);
            $studentRecord = Student::create([
                'user_id' => $user->id,
                'student_name' => $user->profile->nickname ?? $user->username,
                'enrollment_date' => now(),
                'status' => 'pending',
            ]);
            $studentId = $studentRecord->student_id;
        } else {
            $studentId = $user->student->student_id;
            $studentRecord = $user->student;
            Log::info('Using existing student record', ['student_id' => $studentId]);
        }

        Log::info('Student ID to use', ['student_id' => $studentId]);

        $waitingGroup = \App\Models\WaitingGroup::findOrFail($request->waiting_group_id);

        // التحقق من أن الطالب ليس مضافاً مسبقاً
        $existing = \App\Models\WaitingStudent::where('waiting_group_id', $request->waiting_group_id)
            ->where('student_id', $studentId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطالب مضاف بالفعل لهذه المجموعة',
            ], 400);
        }

        DB::beginTransaction();
        try {
            Log::info('Creating waiting student record', [
                'waiting_group_id' => $request->waiting_group_id,
                'student_id' => $studentId,
                'user_id' => $user->id,
            ]);

            $waitingStudent = \App\Models\WaitingStudent::create([
                'waiting_group_id' => $request->waiting_group_id,
                'student_id' => $studentId,
                'user_id' => $user->id,
                'placement_exam_grade' => $request->placement_exam_grade,
                'assigned_level' => $request->assigned_level,
                'notes' => $request->notes,
                'status' => 'waiting',
                'joined_at' => now(),
                'added_by' => auth()->id(),
            ]);

            Log::info('Waiting student created successfully', ['id' => $waitingStudent->id]);

            // تحديث حالة الطالب إذا كان غير مفعل
            if ($user->is_active == 0) {
                /** @var \App\Models\User $user */
            /** @var \App\Models\User $user */
            $user->update(['is_active' => 1]);
                if ($studentRecord) {
                    $studentRecord->update(['status' => 'active']);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الطالب إلى مجموعة الانتظار: '.$waitingGroup->group_name,
                'data' => [
                    'waiting_student_id' => $waitingStudent->id,
                    'student_id' => $studentId,
                    'student_name' => $studentRecord->student_name ?? 'غير معروف',
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error adding student to waiting group: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء اسم مستخدم للطالب
     */
    private function generateStudentUsername($fullName, $email)
    {
        $nameParts = explode(' ', $fullName);
        $firstName = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nameParts[0]));
        $emailPart = explode('@', $email)[0];
        $randomNumber = rand(100, 999);

        // محاولة 1: الاسم + أرقام
        $username = $firstName.$randomNumber;

        // التأكد من التفرد
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $firstName.$randomNumber.$counter;
            $counter++;
            if ($counter > 100) {
                // إذا فشل كل شيء، استخدم جزء من البريد
                $username = substr($emailPart, 0, 15).'_'.rand(1000, 9999);
                break;
            }
        }

        return $username;
    }

    /**
     * إرسال إشعار للإدارة عن تسجيل جديد
     */
    /**
     * إرسال إشعار للإدارة عن تسجيل جديد
     */
    private function notifyAdminsAboutNewRegistration($user, $student)
    {
        try {
            // البحث عن جميع الأدمنز
            $admins = User::where('role_id', Role::ADMIN_ID)
                ->where('is_active', 1)
                ->get();

            // علّق هذا الجزء لأنه مفيش Notification Model
            /*
            foreach ($admins as $admin) {
                \App\Models\Notification::create([
                    'user_id' => $admin->id,
                    'title' => 'تسجيل طالب جديد',
                    'message' => 'طالب جديد: ' . $user->profile->nickname . ' ينتظر التفعيل',
                    'type' => 'new_registration',
                    'data' => json_encode([
                        'user_id' => $user->id,
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'email' => $user->email,
                        'phone' => $user->profile->phone_number,
                    ]),
                    'is_read' => 0,
                ]);
            }
            */

            // بدلاً من ذلك، سجّل في الـ log
            Log::info('📢 New student registration - Needs activation', [
                'student_id' => $student->student_id,
                'student_name' => $student->student_name,
                'email' => $user->email,
                'phone' => $user->profile->phone_number ?? 'N/A',
                'admins_notified' => $admins->count(),
                'admins_ids' => $admins->pluck('id')->toArray(),
            ]);

            // أو استخدم الـ Activity log إذا كان موجوداً
            if (class_exists('\App\Models\Activity')) {
                foreach ($admins as $admin) {
                    \App\Models\Activity::create([
                        'user_id' => $admin->id,
                        'subject_type' => 'App\Models\Student',
                        'subject_id' => $student->student_id,
                        'action' => 'student_registered',
                        'description' => 'تسجيل طالب جديد ينتظر التفعيل: '.$student->student_name,
                        'ip_address' => request()->ip(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification: '.$e->getMessage());
        }
    }

    private function generateUsername($name, $email)
    {
        // أخذ أول جزء من الاسم وإضافة أرقام عشوائية
        $nameParts = explode(' ', $name);
        $firstName = strtolower($nameParts[0]);
        $randomNumber = rand(100, 999);

        $username = $firstName.$randomNumber;

        // التأكد من أن اسم المستخدم فريد
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $firstName.$randomNumber.$counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Parse detailed error message to identify exactly which table is blocking deletion
     */
    // RegisterController.php
    public function registrationSuccess($student_id = null)
    {
        Log::info('Registration success page accessed', [
            'student_id' => $student_id,
            'session_has_registration' => session()->has('registration_success'),
        ]);

        // الحل الجديد: استخدام session أو student_id
        $studentData = null;

        if ($student_id) {
            Log::info('Processing registration success with student_id', ['student_id' => $student_id]);

            try {
                // جلب بيانات الطالب من قاعدة البيانات
                $student = Student::with([
                    'user',
                    'user.profile',
                    'preferredCourse',
                ])->find($student_id);

                if ($student) {
                    Log::info('Student found', [
                        'student_id' => $student->student_id,
                        'has_user' => $student->user ? 'yes' : 'no',
                        'has_profile' => $student->user && $student->user->profile ? 'yes' : 'no',
                    ]);

                    // بناء مصفوفة studentData
                    $studentData = [
                        'student_id' => $student->student_id,
                        'student_name' => $student->student_name,
                        'username' => $student->user ? $student->user->username : 'غير محدد',
                        'email' => $student->user ? $student->user->email : 'غير محدد',
                        'phone' => $student->user && $student->user->profile ?
                                    $student->user->profile->phone_number : 'غير محدد',
                        'date_of_birth' => $student->user && $student->user->profile ?
                                         $student->user->profile->date_of_birth : 'غير محدد',
                        'gender' => $student->user && $student->user->profile ?
                                   $student->user->profile->gender : 'غير محدد',
                        'education_level' => $student->user && $student->user->profile ?
                                           $student->user->profile->education_level : 'غير محدد',
                        'parent_phone' => $student->user && $student->user->profile ?
                                         $student->user->profile->parent_phone : 'غير محدد',
                        'interests' => $student->user && $student->user->profile ?
                                      $student->user->profile->interests : 'غير محدد',
                        'how_know_us' => $student->user && $student->user->profile ?
                                        $student->user->profile->how_know_us : 'غير محدد',
                        'course_id' => $student->preferred_course_id,
                        'course_name' => $student->preferredCourse ?
                                       $student->preferredCourse->course_name : 'غير محدد',
                        'registration_date' => $student->user ?
                                             $student->user->created_at->format('Y/m-d h:i A') : now()->format('Y/m-d h:i A'),
                    ];

                    Log::info('Student data prepared', [
                        'student_id' => $studentData['student_id'],
                        'student_name' => $studentData['student_name'],
                        'course_name' => $studentData['course_name'],
                    ]);
                } else {
                    Log::warning('Student not found in database', ['student_id' => $student_id]);

                    // خيار بديل: استخدام session data
                    if (session()->has('registration_data')) {
                        $studentData = session()->get('registration_data');
                        Log::info('Using session data instead');
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error fetching student data: '.$e->getMessage());

                // استخدام بيانات افتراضية في حالة الخطأ
                $studentData = [
                    'student_id' => $student_id,
                    'student_name' => 'مستخدم جديد',
                    'registration_date' => now()->format('Y/m-d h:i A'),
                    'course_name' => 'سيتم تحديده من قبل الإدارة',
                ];
            }
        } else {
            Log::warning('No student_id provided to registration success page');

            // محاولة استخدام session إذا كان هناك registration_success
            if (session()->has('registration_success')) {
                Log::info('Using registration success from session');

                $studentData = [
                    'student_id' => 'جاري المراجعة',
                    'student_name' => 'مستخدم جديد',
                    'username' => 'سيتم تعيينه',
                    'email' => 'سيتم تعيينه',
                    'registration_date' => now()->format('Y/m-d h:i A'),
                    'course_name' => 'سيتم تحديده من قبل الإدارة',
                ];
            }
        }

        // إذا لم نتمكن من الحصول على أي بيانات
        if (! $studentData) {
            Log::warning('No student data available for registration success page');

            $studentData = [
                'student_id' => 'N/A',
                'student_name' => 'مستخدم جديد',
                'registration_date' => now()->format('Y/m-d h:i A'),
            ];
        }

        Log::info('Sending student data to view', [
            'has_student_data' => $studentData ? 'yes' : 'no',
            'student_id' => $studentData['student_id'] ?? 'N/A',
        ]);

        return view('auth.registration_success', [
            'studentData' => $studentData,
        ]);
    }

    private function parseDetailedErrorMessage($errorMessage, $user)
    {
        // إذا كان الخطأ متعلق بقاعدة البيانات ووجود علاقات
        if (str_contains($errorMessage, 'foreign key constraint')) {
            // استخراج اسم الجدول من رسالة الخطأ
            preg_match('/REFERENCES `([^`]*)`/', $errorMessage, $matches);
            $tableName = isset($matches[1]) ? $matches[1] : 'unknown table';

            // جلب بعض الأمثلة من الجدول المانع للحذف
            $sampleData = $this->getSampleBlockingRecords($tableName, $user->id);

            return "Cannot delete user because they have related records in the '{$tableName}' table. ".
                'Please remove these records first. '.$sampleData;
        }

        // إذا كان الخطأ متعلق بعمود غير موجود
        if (str_contains($errorMessage, 'Column not found')) {
            preg_match("/Unknown column '([^']+)'/", $errorMessage, $matches);
            if (isset($matches[1])) {
                $columnName = $matches[1];

                return "Database configuration error: Column '{$columnName}' not found. Please contact system administrator.";
            }
        }

        // رسالة الخطأ العامة
        return 'Failed to delete user: '.$errorMessage;
    }

    /**
     * Get sample records that are blocking deletion
     */
    private function getSampleBlockingRecords($tableName, $userId)
    {
        try {
            // جلب بعض السجلات المانعة للحذف
            $records = DB::table($tableName)
                ->where('user_id', $userId)
                ->orWhere('created_by', $userId)
                ->orWhere('updated_by', $userId)
                ->limit(5)
                ->get();

            if ($records->count() > 0) {
                return 'Found '.$records->count()." related record(s) in {$tableName}.";
            }
        } catch (\Exception $e) {
            // لو مفيش عمود user_id في الجدول ده
            try {
                $records = DB::table($tableName)
                    ->where('causer_id', $userId)
                    ->limit(5)
                    ->get();

                if ($records->count() > 0) {
                    return 'Found '.$records->count()." related record(s) in {$tableName}.";
                }
            } catch (\Exception $e2) {
                // لا تفعل شيء إذا فشل الاستعلام
            }
        }

        return "Please check the {$tableName} table for related records.";
    }

    /**
     * Check all possible relationships for a user (باستثناء profile)
     */
    private function checkUserRelationshipsExcludingProfile($user)
    {
        $relatedTables = [];

        // فحص حسب نوع اليوزر (من غير profile)
        if ($user->isTeacher()) { // Teacher
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $relatedTables[] = 'teachers';

                // فحص إذا كان المدرس مربوط بمجموعات
                $groupCount = DB::table('groups')->where('teacher_id', $teacher->teacher_id)->count();
                if ($groupCount > 0) {
                    $relatedTables[] = 'groups ('.$groupCount.' groups)';
                }

                // فحص إذا كان مربوط بمقررات
                $courseCount = DB::table('courses')->where('teacher_id', $teacher->teacher_id)->count();
                if ($courseCount > 0) {
                    $relatedTables[] = 'courses ('.$courseCount.' courses)';
                }
            }
        } elseif ($user->isStudent()) { // Student
            $student = Student::where('user_id', $user->id)->first();
            if ($student) {
                $relatedTables[] = 'students';

                // فحص التسجيلات في المجموعات
                $groupEnrollmentCount = DB::table('student_group')->where('student_id', $student->student_id)->count();
                if ($groupEnrollmentCount > 0) {
                    $relatedTables[] = 'group enrollments ('.$groupEnrollmentCount.' enrollments)';
                }

                // فحص الحضور
                $attendanceCount = DB::table('attendance')->where('student_id', $student->student_id)->count();
                if ($attendanceCount > 0) {
                    $relatedTables[] = 'attendance records ('.$attendanceCount.' records)';
                }

                // فحص تسليم الواجبات
                $submissionCount = DB::table('assignment_submissions')->where('student_id', $student->student_id)->count();
                if ($submissionCount > 0) {
                    $relatedTables[] = 'assignment submissions ('.$submissionCount.' submissions)';
                }

                // فحص الدرجات
                $gradeCount = DB::table('grades')->where('student_id', $student->student_id)->count();
                if ($gradeCount > 0) {
                    $relatedTables[] = 'grades ('.$gradeCount.' records)';
                }
            }
        }

        // فحص علاقات عامة باستخدام try-catch فقط
        $tablesToCheck = [
            'notifications' => 'user_id',
            'activity_log' => 'causer_id',
            'user_permissions' => 'user_id',
        ];

        foreach ($tablesToCheck as $table => $column) {
            try {
                if (DB::table($table)->where($column, $user->id)->exists()) {
                    $relatedTables[] = $table;
                }
            } catch (\Exception $e) {
                // لو الجدول أو العمود مش موجود، كمل بدون ما نعطل النظام
                continue;
            }
        }

        return $relatedTables;
    }

    /**
     * Check all possible relationships for a user
     */
    /**
     * Check all possible relationships for a user
     */
    /**
     * Check all possible relationships for a user
     */
    private function checkUserRelationships($user)
    {
        $relatedTables = [];

        // فحص الجداول الأساسية
        if ($user->profile) {
            $relatedTables[] = 'profiles';
        }

        // فحص حسب نوع اليوزر
        if ($user->isTeacher()) { // Teacher
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $relatedTables[] = 'teachers';

                // فحص إذا كان المدرس مربوط بمجموعات
                $groupCount = DB::table('groups')->where('teacher_id', $teacher->teacher_id)->count();
                if ($groupCount > 0) {
                    $relatedTables[] = 'groups ('.$groupCount.' groups)';
                }

                // فحص إذا كان مربوط بمقررات
                $courseCount = DB::table('courses')->where('teacher_id', $teacher->teacher_id)->count();
                if ($courseCount > 0) {
                    $relatedTables[] = 'courses ('.$courseCount.' courses)';
                }
            }
        } elseif ($user->isStudent()) { // Student
            $student = Student::where('user_id', $user->id)->first();
            if ($student) {
                $relatedTables[] = 'students';

                // فحص التسجيلات في المجموعات
                $groupEnrollmentCount = DB::table('student_group')->where('student_id', $student->student_id)->count();
                if ($groupEnrollmentCount > 0) {
                    $relatedTables[] = 'group enrollments ('.$groupEnrollmentCount.' enrollments)';
                }

                // فحص الحضور
                $attendanceCount = DB::table('attendance')->where('student_id', $student->student_id)->count();
                if ($attendanceCount > 0) {
                    $relatedTables[] = 'attendance records ('.$attendanceCount.' records)';
                }

                // فحص تسليم الواجبات
                $submissionCount = DB::table('assignment_submissions')->where('student_id', $student->student_id)->count();
                if ($submissionCount > 0) {
                    $relatedTables[] = 'assignment submissions ('.$submissionCount.' submissions)';
                }

                // فحص الدرجات
                $gradeCount = DB::table('grades')->where('student_id', $student->student_id)->count();
                if ($gradeCount > 0) {
                    $relatedTables[] = 'grades ('.$gradeCount.' records)';
                }
            }
        }

        // فحص علاقات عامة باستخدام try-catch فقط
        $tablesToCheck = [
            'notifications' => 'user_id',
            'activity_log' => 'causer_id',
            'user_permissions' => 'user_id',
        ];

        foreach ($tablesToCheck as $table => $column) {
            try {
                if (DB::table($table)->where($column, $user->id)->exists()) {
                    $relatedTables[] = $table;
                }
            } catch (\Exception $e) {
                // لو الجدول أو العمود مش موجود، كمل بدون ما نعطل النظام
                continue;
            }
        }

        return $relatedTables;
    }

    /**
     * Parse error message to provide specific details about related records
     */
    private function parseErrorMessage($errorMessage, $user, $relatedRecords = [])
    {
        // إذا كان في علاقات مكتشفة
        if (! empty($relatedRecords)) {
            return 'Cannot delete user because they have related records in: '.implode(', ', $relatedRecords).'. Please remove these records first.';
        }

        // إذا كان الخطأ متعلق بقاعدة البيانات ووجود علاقات
        if (str_contains($errorMessage, 'foreign key constraint')) {
            // استخراج اسم الجدول من رسالة الخطأ
            preg_match('/CONSTRAINT.*FOREIGN KEY.*REFERENCES `([^`]*)`/', $errorMessage, $matches);
            $tableName = isset($matches[1]) ? $matches[1] : 'unknown table';

            return "Cannot delete user because they have related records in the '{$tableName}' table. Please remove these records first.";
        }

        // إذا كان الخطأ متعلق بعمود غير موجود
        if (str_contains($errorMessage, 'Column not found')) {
            preg_match("/Unknown column '([^']+)'/", $errorMessage, $matches);
            if (isset($matches[1])) {
                $columnName = $matches[1];

                return "Database configuration error: Column '{$columnName}' not found. Please contact system administrator.";
            }
        }

        // رسالة الخطأ العامة
        return 'Failed to delete user: '.$errorMessage;
    }

    /**
     * Parse error message to provide specific details about related records
     */

    /**
     * Get role-specific relationship details
     */
    private function getRoleSpecificRelationships($user)
    {
        $relationships = [];

        if ($user->isTeacher()) { // Teacher
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                // Check groups
                $groupCount = DB::table('groups')->where('teacher_id', $teacher->teacher_id)->count();
                if ($groupCount > 0) {
                    $relationships[] = "{$groupCount} group(s)";
                }
            }
        } elseif ($user->isStudent()) { // Student
            $student = Student::where('user_id', $user->id)->first();
            if ($student) {
                // Check group enrollments
                $groupCount = DB::table('student_group')->where('student_id', $student->student_id)->count();
                if ($groupCount > 0) {
                    $relationships[] = "{$groupCount} group enrollment(s)";
                }

                // Check attendance records
                $attendanceCount = DB::table('attendance')->where('student_id', $student->student_id)->count();
                if ($attendanceCount > 0) {
                    $relationships[] = "{$attendanceCount} attendance record(s)";
                }

                // Check assignment submissions
                $submissionCount = DB::table('assignment_submissions')->where('student_id', $student->student_id)->count();
                if ($submissionCount > 0) {
                    $relationships[] = "{$submissionCount} assignment submission(s)";
                }
            }
        }

        return implode(', ', $relationships);
    }

    public function fetchUsers(Request $request)
    {
        $query = User::with(['role', 'profile', 'adminType']);

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('role', function ($roleQuery) use ($search) {
                        $roleQuery->where('role_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('profile', function ($profileQuery) use ($search) {
                        $profileQuery->where('nickname', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('role_id') && ! empty($request->role_id)) {
            $query->where('role_id', $request->role_id);
        }

        $users = $query->paginate(15);

        // Convert stored profile_picture_url to absolute secure URLs
        $users->getCollection()->transform(function ($user) {
            if ($user->profile && $user->profile->profile_picture_url) {
                $pp = $user->profile->profile_picture_url;
                $user->profile->profile_picture_url = preg_match('/^https?:\/\//', $pp) ? $pp : secure_asset($pp);
            }

            return $user;
        });

        return response()->json([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get user details for AJAX requests.
     */
    public function getUser(User $user)
    {
        $user->load(['role', 'profile', 'adminType']);
        $roles = \App\Models\Role::all();
        $adminTypes = \App\Models\AdminType::all();

        return response()->json([
            'success' => true,
            'user' => $user,
            'roles' => $roles,
            'adminTypes' => $adminTypes
        ]);
    }

    /**
     * Check if username exists via AJAX
     */
    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'exclude_id' => 'nullable|integer', // لاستثناء المستخدم الحالي عند التعديل
        ]);

        $username = $request->username;
        $excludeId = $request->exclude_id;

        $query = User::where('username', $username);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'username' => $username,
            'suggestions' => $exists ? $this->generateUsernameSuggestions($username) : [],
        ]);
    }

    /**
     * Generate suggested usernames
     */
    private function generateUsernameSuggestions($baseUsername)
    {
        $suggestions = [];

        // 1. إضافة أرقام
        for ($i = 1; $i <= 5; $i++) {
            $suggestion = $baseUsername.rand(100, 999);
            if (! User::where('username', $suggestion)->exists()) {
                $suggestions[] = $suggestion;
            }

            if (count($suggestions) >= 3) {
                break;
            }
        }

        // 2. إضافة أحرف إذا لم نجد مقترحات كافية
        if (count($suggestions) < 3) {
            $letters = ['a', 'b', 'c', 'x', 'y', 'z'];
            foreach ($letters as $letter) {
                $suggestion = $baseUsername.'_'.$letter;
                if (! User::where('username', $suggestion)->exists()) {
                    $suggestions[] = $suggestion;
                }

                if (count($suggestions) >= 3) {
                    break;
                }
            }
        }

        // 3. إذا لم نجد أي مقترحات، نستخدم timestamp
        if (empty($suggestions)) {
            $suggestions[] = $baseUsername.'_'.time();
        }

        return array_slice($suggestions, 0, 3); // إرجاع 3 مقترحات فقط
    }

    /**
     * Get username suggestions via AJAX
     */
    public function suggestUsername($baseUsername)
    {
        $suggestions = $this->generateUsernameSuggestions($baseUsername);

        return response()->json([
            'success' => true,
            'base_username' => $baseUsername,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Check if email exists via AJAX
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'exclude_id' => 'nullable|integer',
        ]);

        $email = $request->email;
        $excludeId = $request->exclude_id;

        $query = User::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
            'email' => $email,
            'suggestion' => $exists ? $this->generateEmailSuggestion($email) : null,
        ]);
    }

    /**
     * Generate email suggestion
     */
    private function generateEmailSuggestion($email)
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        $username = $parts[0];
        $domain = $parts[1];

        // محاولات مختلفة
        $attempts = [
            $username.rand(100, 999).'@'.$domain,
            $username.'_'.rand(1000, 9999).'@'.$domain,
            $username.'.'.time().'@'.$domain,
        ];

        foreach ($attempts as $attempt) {
            if (! User::where('email', $attempt)->exists()) {
                return $attempt;
            }
        }

        return null;
    }

    /**
     * رفض وحذف الطالب من النظام
     */
    /**
     * رفض وحذف الطالب من النظام
     */
    /**
     * رفض وحذف الطالب من النظام
     */
    /**
     * رفض وحذف الطالب من النظام (مصحح)
     */
    public function rejectAndDeleteStudent(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $user = User::with(['profile', 'student'])->findOrFail($id);

            // التحقق أن المستخدم طالب وغير مفعل
            if (! $user->isStudent() || $user->is_active != 0) {
                return redirect()->route('pending.students')
                    ->with('error', 'لا يمكن رفض هذا المستخدم');
            }

            $reason = $request->input('reason', 'تم رفض طلب التسجيل');

            // 1. حذف بيانات الملف الشخصي
            if ($user->profile) {
                $user->profile->delete();
            }

            // 2. حذف علاقات الطالب (Student)
            if ($user->student) {
                // حذف بيانات student_meta المرتبطة
                if (class_exists('\App\Models\StudentMeta')) {
                    \App\Models\StudentMeta::where('student_id', $user->student->student_id)->delete();
                }

                // تصحيح: حذف الحجوزات المرتبطة بالطالب
                $userEmail = $user->email;
                $userPhone = $user->profile ? $user->profile->phone_number : null;

                // الطريقة الصحيحة لحذف الحجوزات
                $bookingQuery = \App\Models\Booking::query();

                if ($userEmail) {
                    $bookingQuery->orWhere('email', $userEmail);
                }

                if ($userPhone) {
                    $bookingQuery->orWhere('phone', $userPhone);
                }

                // تنفيذ الحذف
                $bookingQuery->delete();

                // حذف من مجموعات الانتظار
                if (class_exists('\App\Models\WaitingStudent')) {
                    \App\Models\WaitingStudent::where('student_id', $user->student->student_id)->delete();
                }

                // حذف الطالب
                $user->student->delete();
            } else {
                // إذا لم يكن هناك سجل طالب، احذف البيانات المرتبطة بالبريد والهاتف
                $userEmail = $user->email;
                $userPhone = $user->profile ? $user->profile->phone_number : null;

                $bookingQuery = \App\Models\Booking::query();

                if ($userEmail) {
                    $bookingQuery->orWhere('email', $userEmail);
                }

                if ($userPhone) {
                    $bookingQuery->orWhere('phone', $userPhone);
                }

                $bookingQuery->delete();
            }

            // 3. حذف أي علاقات أخرى
            try {
                // حذف الإشعارات
                if (Schema::hasTable('notifications')) {
                    DB::table('notifications')->where('user_id', $id)->delete();
                }

                // حذف سجلات النشاط
                if (Schema::hasTable('activity_log')) {
                    DB::table('activity_log')->where('causer_id', $id)->delete();
                }

                // حذف الجلسات
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $id)->delete();
                }
            } catch (\Exception $e) {
                Log::warning('خطأ في حذف بعض العلاقات: '.$e->getMessage());
            }

            // 4. حذف المستخدم نهائياً
            $user->delete();

            // 5. تسجيل العملية في الـ log
            Log::info('Student registration rejected and deleted', [
                'user_id' => $id,
                'email' => $user->email,
                'name' => $user->profile->nickname ?? $user->username,
                'reason' => $reason,
                'deleted_by' => auth()->id(),
                'deleted_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('pending.students')
                ->with('success', 'تم رفض وحذف الطالب من النظام بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في رفض وحذف الطالب: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return redirect()->route('pending.students')
                ->with('error', 'حدث خطأ أثناء عملية الرفض والحذف: '.$e->getMessage());
        }
    }

    /**
     * Sync the user's Spatie role based on legacy role_id, admin_type_id, and any custom Spatie roles.
     * This keeps the new Spatie system in sync with the existing role_id column.
     */
    private function syncSpatieRole(User $user, $roleId, $adminTypeId = null, $customSpatieRoles = null): void
    {
        try {
            $baseRoleName = match ((int) $roleId) {
                1 => ($adminTypeId ? \App\Models\AdminType::find($adminTypeId)?->name === 'full' : false)
                    ? 'super-admin'
                    : 'admin',
                2 => 'teacher',
                3 => 'student',
                default => 'admin',
            };

            $rolesToSync = [$baseRoleName];

            if (!empty($customSpatieRoles) && is_array($customSpatieRoles)) {
                $rolesToSync = array_merge($rolesToSync, $customSpatieRoles);
            }

            // Remove duplicates just in case
            $rolesToSync = array_unique($rolesToSync);

            if (!empty($rolesToSync)) {
                $user->syncRoles($rolesToSync);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync Spatie role for user '.$user->id.': '.$e->getMessage());
        }
    }
}
