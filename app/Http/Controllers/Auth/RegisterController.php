<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
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

        $courses = \App\Models\Course::all();

        return view('auth.register_student', [
            'courses' => $courses,
            'bookingData' => $bookingData
        ]);
    }

    /**
     * تسجيل الطالب (للزوار)
     */
    public function register(\App\Http\Requests\Auth\RegisterStudentRequest $request)
    {
        Log::info('Student registration from public form', $request->all());

        DB::beginTransaction();
        try {
            // 1. إنشاء اسم مستخدم فريد
            $username = $this->generateStudentUsername($request->full_name, $request->email);

            // 2. إنشاء المستخدم (طالب - غير نشط)
            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'pass' => Hash::make($request->password),
                'role_id' => 3, // Student role
                'is_active' => 0, // غير نشط - يحتاج تفعيل من الإدارة
            ]);

            // 3. إنشاء الملف الشخصي
            $profileData = [
                'user_id' => $user->id,
                'nickname' => $request->full_name,
                'phone_number' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'education_level' => $request->education_level,
                'parent_phone' => $request->parent_phone,
                'interests' => $request->interests,
            ];

            Profile::create($profileData);

            // 4. إنشاء سجل الطالب
            $student = Student::create([
                'user_id' => $user->id,
                'student_name' => $request->full_name,
                'enrollment_date' => now(),
                'status' => 'pending', // في انتظار التفعيل
            ]);

            // 5. إذا كان هناك كورس محدد
            if ($request->filled('course_id')) {
                // حفظ في ملاحظات الطالب
                $student->update([
                    'notes' => 'مهتم بالكورس: '.$request->course_id.
                             (($request->filled('interests')) ? ' | الاهتمامات: '.$request->interests : ''),
                ]);

                // حفظ بيانات الكورس في student_meta
                \App\Models\StudentMeta::create([
                    'student_id' => $student->student_id,
                    'meta_key' => 'preferred_course',
                    'meta_value' => $request->course_id,
                ]);
            }

            // 6. حفظ البيانات الإضافية في student_meta
            $additionalData = [
                'how_know_us' => $request->how_know_us,
                'registration_source' => 'public_form',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'registration_date' => now()->format('Y-m-d H:i:s'),
                'education_level' => $request->education_level,
                'interests' => $request->interests,
            ];

            \App\Models\StudentMeta::create([
                'student_id' => $student->student_id,
                'meta_key' => 'registration_data',
                'meta_value' => json_encode($additionalData),
            ]);

            // 7. إذا كان هناك حجز قديم
            if ($request->filled('booking_id')) {
                $booking = \App\Models\Booking::find($request->booking_id);
                if ($booking) {
                    $booking->update([
                        'status' => 'converted_to_user',
                        'converted_user_id' => $user->id,
                        'converted_at' => now(),
                    ]);

                    // حفظ بيانات الحجز
                    \App\Models\StudentMeta::create([
                        'student_id' => $student->student_id,
                        'meta_key' => 'booking_data',
                        'meta_value' => json_encode([
                            'booking_id' => $booking->id,
                            'original_booking_date' => $booking->created_at,
                            'original_message' => $booking->message,
                        ]),
                    ]);
                }
            }

            // 8. إرسال إشعار للإدارة
            $this->notifyAdminsAboutNewRegistration($user, $student);

            DB::commit();

            Log::info('✅ Student registration completed successfully', [
                'user_id' => $user->id,
                'student_id' => $student->student_id,
                'username' => $user->username,
                'email' => $user->email,
                'is_active' => $user->is_active,
            ]);

            return redirect()->route('registration.success')->with([
                'success' => '🎉 تم التسجيل بنجاح!',
                'message' => 'سيتم مراجعة طلبك وتفعيل حسابك من قبل الإدارة خلال 24 ساعة.',
                'user_id' => $user->id,
                'student_id' => $student->student_id,
            ]);

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
     * صفحة نجاح التسجيل
     */
    public function registrationSuccess()
    {
        return view('auth.registration_success');
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

        $username = $firstName.$randomNumber;

        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $firstName.$randomNumber.$counter;
            $counter++;
            if ($counter > 100) {
                $username = substr($emailPart, 0, 15).'_'.rand(1000, 9999);
                break;
            }
        }

        return $username;
    }

    /**
     * إرسال إشعار للإدارة عن تسجيل جديد
     */
    private function notifyAdminsAboutNewRegistration($user, $student)
    {
        try {
            // البحث عن جميع الأدمنز
            $admins = User::where('role_id', 1)
                ->where('is_active', 1)
                ->get();

            // تسجيل في الـ log
            Log::info('📢 New student registration - Needs activation', [
                'student_id' => $student->student_id,
                'student_name' => $student->student_name,
                'email' => $user->email,
                'phone' => $user->profile->phone_number ?? 'N/A',
                'admins_notified' => $admins->count(),
                'admins_ids' => $admins->pluck('id')->toArray(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification: '.$e->getMessage());
        }
    }
}
