<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Display the user's profile.
     */
    public function index()
    {
        $user = Auth::user();
        $user->load(['profile', 'role']);

        return view('profile.index', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Validation
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'nickname' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Check current password before updating password
            if ($request->filled('password')) {
                if (! Hash::check($request->current_password, $user->pass)) {
                    return back()->withErrors(['current_password' => 'Current password is incorrect']);
                }

                $user->update([
                    'pass' => Hash::make($request->password),
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'event' => 'password_change',
                    'description' => 'User changed their own password via profile update',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            // Update user basic fields
            $user->update([
                'username' => $request->username,
                'email' => $request->email,
            ]);

            // ============================
            //   PROFILE PICTURE HANDLING
            // ============================

            $profilePictureUrl = $user->profile->profile_picture_url ?? null;

            if ($request->hasFile('profile_picture')) {
                $uploadPath = public_path('profile_pictures');

                if (! file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                if ($profilePictureUrl) {
                    $oldPathFragment = parse_url($profilePictureUrl, PHP_URL_PATH);
                    if ($oldPathFragment) {
                        $oldImage = public_path(ltrim($oldPathFragment, '/'));
                        if (file_exists($oldImage) && is_file($oldImage)) {
                            @unlink($oldImage);
                        }
                    }
                }

                $file = $request->file('profile_picture');
                $filename = time().'_'.$user->id.'.'.$file->getClientOriginalExtension();
                $file->move($uploadPath, $filename);
                $profilePictureUrl = '/profile_pictures/'.$filename;
            }

            // ============================
            //   UPDATE PROFILE TABLE
            // ============================

            // Fallback for nickname if still null but we want to be safe
            $nickname = $request->nickname ?: ($user->username ?: $user->name);

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nickname' => $nickname,
                    'phone_number' => $request->phone_number,
                    'address' => $request->address,
                    'date_of_birth' => $request->date_of_birth,
                    'profile_picture_url' => $profilePictureUrl,
                ]
            );

            // Update session for navbar image
            session(['profile_picture' => $profilePictureUrl]);

            DB::commit();

            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'profile_update',
                'description' => 'User updated their profile information',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('success', 'Profile updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update error: '.$e->getMessage());

            return back()->with('error', 'An error occurred while updating your profile: '.$e->getMessage())->withInput();
        }
    }
}
