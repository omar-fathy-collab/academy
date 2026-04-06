<?php

namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    /**
     * Show the registration success page with student data.
     */
    public function success(Request $request)
    {
        // Try to get data from multiple sources
        $studentData = null;

        // 1. Fetch from session
        $studentData = session()->get('student_data');

        // 2. If not in session, check flash data or default values
        if (! $studentData) {
            $studentData = [
                'student_id' => session('student_id') ?? session()->get('student_id') ?? 'N/A',
                'student_name' => 'مستخدم جديد',
                'email' => session('email') ?? 'غير محدد',
                'phone' => 'غير محدد',
                'date_of_birth' => 'غير محدد',
                'gender' => 'غير محدد',
                'education_level' => 'غير محدد',
                'parent_phone' => 'غير محدد',
                'interests' => 'غير محدد',
                'how_know_us' => 'غير محدد',
                'username' => 'سيتم تعيينه',
                'course_name' => 'سيتم تحديده من قبل الإدارة',
                'registration_date' => now()->format('Y/m-d h:i A'),
            ];

            // 3. If a specific student_id is present, try to fetch from database
            $studentId = $request->get('student_id') ?? session('student_id');
            if ($studentId && $studentId !== 'N/A') {
                try {
                    $student = Student::with(['user', 'user.profile', 'preferredCourse'])->find($studentId);

                    if ($student) {
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
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching student data: '.$e->getMessage());
                }
            }
        }

        // 4. Ensure student_id exists
        if (! isset($studentData['student_id'])) {
            $studentData['student_id'] = 'N/A';
        }

        // 5. Log for debugging
        Log::info('Registration success page data:', [
            'has_student_data' => ! empty($studentData),
            'student_id' => $studentData['student_id'] ?? 'N/A',
            'student_name' => $studentData['student_name'] ?? 'N/A',
            'all_keys' => array_keys($studentData ?? []),
        ]);

        return view('auth.registration-success', [
            'studentData' => $studentData
        ]);
    }
}
