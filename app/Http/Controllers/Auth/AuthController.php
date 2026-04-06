<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Models\Profile;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'pass' => 'required',
        ]);

        $login = $request->login;
        $throttleKey = strtolower($login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            
            AuditLog::create([
                'event' => 'login_lockout',
                'description' => "Account locked out for 5 minutes due to too many failed attempts for login: {$login}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->withInput($request->only('login'));
        }

        // البحث عن المستخدم (بغض النظر عن حالة التفعيل) للتحقق من كلمة المرور أولاً
        $user = $this->findUserByLoginAnyStatus($login);

        if ($user && Hash::check($request->pass, $user->pass)) {
            // إذا كان الحساب غير مفعل، ارجع مع إشارة للعرض على الواجهة
            if ($user->is_active == 0) {
                Log::info('Login attempt for inactive account', ['user_id' => $user->id, 'login' => $login]);

                return back()->withInput($request->only('login'))
                    ->with('activation_needed', true)
                    ->with('activation_help_url', 'https://www.ict-academmy.com/help');
            }

            // Use a strict boolean for the remember flag (checkbox may return 'on')
            $remember = $request->has('remember');
            
            Auth::login($user, $remember);
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);

            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'login_success',
                'description' => "User logged in successfully: {$user->username}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // حفظ بيانات الجلسة
            session([
                'id' => $user->id,
                'username' => $user->username,
                'role_id' => $user->role_id,
                'role_name' => $user->role->role_name ?? 'Unknown',
                'profile_picture' => $user->profile ? $user->profile->profile_picture_url : null,
                'admin_type_id' => $user->adminType ? $user->adminType->id : null,
                'admin_type_name' => $user->adminType ? $user->adminType->name : null,
            ]);

            // التوجيه حسب الصلاحية — استخدم intended حتى يرجع المستخدم للرابط الذي حاول الوصول إليه
            // بناء مسار افتراضي حسب الدور لاستخدامه كـ fallback
            // افتراضي إن لم نحدد دور معروف — عُد إلى صفحة الدخول
            $fallback = route('login');
            switch ($user->role_id) {
                case 1:
                case 4: // role id 4 (accountant/secretary) يجب أن يذهب إلى لوحة الإدارة مثل الأدمن
                    $fallback = route('dashboard');
                    break;
                case 2:
                    $fallback = route('teacher.dashboard');
                    break;
                case 3:
                    $fallback = route('student.dashboard');
                    break;
            }

            return redirect()->intended($fallback);
        }

        RateLimiter::hit($throttleKey, 300); // 5 min lockout

        AuditLog::create([
            'event' => 'login_failed',
            'description' => "Failed login attempt for login: {$login}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->withErrors([
            'login' => 'Invalid credentials',
        ])->withInput($request->only('login'));
    }

    /**
     * البحث عن المستخدم باستخدام أي نوع من بيانات التسجيل
     */
    private function findUserByLogin($login)
    {
        // أولاً: البحث بالبريد الإلكتروني أو اسم المستخدم في جدول users
        $user = User::where(function ($query) use ($login) {
            $query->where('email', $login)
                ->orWhere('username', $login);
        })
            ->where('is_active', 1)
            ->first();

        if ($user) {
            return $user;
        }

        // ثانياً: إذا لم يتم العثور، البحث برقم الهاتف في جدول profile
        return $this->findUserByPhone($login);
    }

    /**
     * البحث عن المستخدم بواسطة رقم الهاتف
     */
    private function findUserByPhone($phone)
    {
        // تنظيف رقم الهاتف من المسافات والرموز
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // البحث في جدول profile عن رقم الهاتف
        $profile = Profile::where('phone_number', 'LIKE', '%'.$cleanPhone.'%')
            ->orWhereRaw("REPLACE(REPLACE(phone_number, ' ', ''), '-', '') LIKE ?", ['%'.$cleanPhone.'%'])
            ->first();

        if ($profile) {
            return User::where('id', $profile->user_id)
                ->where('is_active', 1)
                ->first();
        }

        return null;
    }

    /**
     * Find user by login regardless of is_active
     */
    private function findUserByLoginAnyStatus($login)
    {
        $user = User::where(function ($query) use ($login) {
            $query->where('email', $login)
                ->orWhere('username', $login);
        })->first();

        if ($user) {
            return $user;
        }

        // البحث في profile عن رقم الهاتف (بدون فلتر is_active)
        $cleanPhone = preg_replace('/[^0-9]/', '', $login);
        $profile = Profile::where('phone_number', 'LIKE', '%'.$cleanPhone.'%')
            ->orWhereRaw("REPLACE(REPLACE(phone_number, ' ', ''), '-', '') LIKE ?", ['%'.$cleanPhone.'%'])
            ->first();

        if ($profile) {
            return User::where('id', $profile->user_id)->first();
        }

        return null;
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'logout',
                'description' => "User logged out: {$user->username}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/loginpage');
    }
}
