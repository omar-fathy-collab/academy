<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

trait Impersonate
{
    /**
     * انتحال هوية مستخدم آخر
     */
    public function impersonate($userId)
    {
        $originalUserId = Auth::id();
        $originalUserName = Auth::user()->profile->nickname ?? Auth::user()->username;

        // التحقق من الصلاحيات
        if (! $this->canImpersonate()) {
            return false;
        }

        $userToImpersonate = \App\Models\User::findOrFail($userId);

        // التحقق من أن المستخدم المستهدف ليس له صلاحيات أعلى
        if (! $this->canImpersonateUser($userToImpersonate)) {
            return false;
        }

        // حفظ الهوية الأصلية في الجلسة
        Session::put('impersonator_id', $originalUserId);
        Session::put('impersonator_name', $originalUserName);
        Session::put('impersonator_role', Auth::user()->role_id);

        // تسجيل الدخول كمستخدم آخر
        Auth::login($userToImpersonate);

        // تسجيل الحدث
        Log::info('Impersonation started', [
            'impersonator_id' => $originalUserId,
            'impersonator_name' => $originalUserName,
            'impersonated_id' => $userId,
            'impersonated_name' => $userToImpersonate->username,
            'impersonated_role' => $userToImpersonate->role_id,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);

        return true;
    }

    /**
     * إنهاء الانتحال والعودة للهوية الأصلية
     */
    public function stopImpersonating()
    {
        if (! Session::has('impersonator_id')) {
            return false;
        }

        $impersonatorId = Session::get('impersonator_id');
        $impersonator = \App\Models\User::find($impersonatorId);

        if (! $impersonator) {
            Session::forget(['impersonator_id', 'impersonator_role', 'impersonator_name']);
            Auth::logout();
            return false;
        }

        // تسجيل الخروج من الهوية المنتحلة
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // تسجيل الدخول بالهوية الأصلية
        Auth::login($impersonator);

        // تسجيل الحدث
        Log::info('Impersonation stopped', [
            'impersonator_id' => $impersonatorId,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);

        return true;
    }

    /**
     * التحقق مما إذا كان المستخدم الحالي منتحلاً
     */
    public function isImpersonating()
    {
        return Session::has('impersonator_id');
    }

    /**
     * الحصول على ID المنتحل الأصلي
     */
    public function getImpersonatorId()
    {
        return Session::get('impersonator_id');
    }

    /**
     * التحقق من صلاحية الانتحال
     */
    protected function canImpersonate()
    {
        $user = Auth::user();

        if ($user->role_id == 1) { // Admin
            // Only allow specific admin types to impersonate
            $allowedTypes = ['full']; // تغيير من 'Super Admin' إلى 'full'

            if ($user->adminType && in_array($user->adminType->name, $allowedTypes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * التحقق من إمكانية انتحال مستخدم معين
     */
    protected function canImpersonateUser($targetUser)
    {
        $currentUser = Auth::user();

        // منع انتحال مستخدمين من نفس الدور أو أعلى
        if ($currentUser->role_id == 1) { // Admin
            // الأدمن لا يمكنه انتحال أدمن آخر full
            if ($targetUser->isAdminFull()) {
                return false;
            }
        }

        // منع انتحال المستخدم نفسه
        if ($currentUser->id == $targetUser->id) {
            return false;
        }

        return true;
    }
}
