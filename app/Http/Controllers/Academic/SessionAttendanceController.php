<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;

use App\Models\AttendanceToken;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SessionAttendanceController extends Controller
{
    /**
     * Get the first 3 octets of an IP address to represent the local subnet.
     * Works for IPv4. For IPv6 or localhost, it falls back to the full IP.
     */
    private function getSubnet($ip)
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'localhost';
        }
        
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        }
        
        return $ip; // Fallback for IPv6 or unusual formats
    }

    /**
     * Open the attendance window for a session, binding it to the teacher's current WiFi subnet.
     */
    /**
     * Open the attendance window for a session, binding it to the teacher's current WiFi subnet or GPS location.
     */
    public function openAttendanceWindow(Request $request, $id)
    {
        $session = Session::where('session_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if (!Auth::user()->hasRole(['teacher', 'admin', 'super-admin'])) {
            return response()->json(['error' => 'Unauthorized. Only authorized staff can open attendance.'], 403);
        }

        $type = $request->input('type', 'wifi'); // 'wifi' or 'qr'
        $teacherIp = $request->ip();
        $interval = $request->input('refresh_interval', 30);
        $lat = $request->input('lat');
        $lng = $request->input('lng');

        $data = [
            'opened_at' => now(),
            'closed_at' => null,
            'refresh_interval' => $interval,
        ];

        if ($type === 'wifi') {
            $data['is_wifi_open'] = true;
            $data['teacher_subnet'] = $teacherIp;
        } else {
            $data['is_qr_open'] = true;
            $data['qr_token'] = Str::random(32);
            $data['qr_expires_at'] = now()->addSeconds($interval + 10);
        }

        if ($lat && $lng) {
            $data['lat'] = $lat;
            $data['lng'] = $lng;
        }

        $token = AttendanceToken::updateOrCreate(
            ['session_id' => $session->session_id ?? $session->id],
            $data
        );

        return response()->json([
            'success' => true,
            'is_wifi_open' => (bool)$token->is_wifi_open,
            'is_qr_open' => (bool)$token->is_qr_open,
            'qr_token' => $token->qr_token,
            'refresh_interval' => $token->refresh_interval,
            'message' => ucfirst($type) . ' attendance opened successfully.',
        ]);
    }

    /**
     * Close the attendance window for a session.
     */
    public function closeAttendanceWindow(Request $request, $id)
    {
        $session = Session::where('session_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if (!Auth::user()->hasRole(['teacher', 'admin', 'super-admin'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $type = $request->input('type', 'all'); // 'wifi', 'qr', or 'all'

        $token = AttendanceToken::where('session_id', $session->session_id ?? $session->id)->first();
        
        if ($token) {
            if ($type === 'wifi' || $type === 'all') $token->is_wifi_open = false;
            if ($type === 'qr' || $type === 'all') $token->is_qr_open = false;
            
            if (!$token->is_wifi_open && !$token->is_qr_open) {
                $token->closed_at = now();
            }
            $token->save();
        }

        return response()->json([
            'success' => true,
            'is_wifi_open' => $token ? (bool)$token->is_wifi_open : false,
            'is_qr_open' => $token ? (bool)$token->is_qr_open : false,
            'message' => 'Attendance window updated/closed.',
        ]);
    }

    /**
     * Get the current status of the attendance window (used by teacher UI to poll).
     */
    public function getAttendanceWindowStatus($id)
    {
        $session = Session::where('session_id', $id)->orWhere('uuid', $id)->firstOrFail();

        if (!Auth::user()->hasRole(['teacher', 'admin', 'super-admin'])) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $token = AttendanceToken::where('session_id', $session->session_id ?? $session->id)->first();
        
        $attendances = \App\Models\Attendance::with('student:student_id,student_name')
            ->where('session_id', $session->session_id ?? $session->id)
            ->where('status', 'present')
            ->whereNotNull('ip_address')
            ->orderBy('recorded_at', 'desc')
            ->get()
            ->map(fn($a) => [
                'student_name' => $a->student?->student_name ?? 'N/A',
                'ip_address' => $a->ip_address,
                'recorded_at' => $a->recorded_at ? $a->recorded_at->format('h:i:s A') : '--:--:--',
                'ago' => $a->recorded_at ? $a->recorded_at->diffForHumans() : 'N/A'
            ]);

        return response()->json([
            'is_wifi_open' => $token ? (bool)$token->is_wifi_open : false,
            'is_qr_open' => $token ? (bool)$token->is_qr_open : false,
            'qr_token' => $token ? $token->qr_token : null,
            'refresh_interval' => $token ? $token->refresh_interval : 30,
            'expires_in' => $token && $token->qr_expires_at ? max(0, now()->diffInSeconds($token->qr_expires_at, false)) : 0,
            'present_count' => $attendances->count(),
            'checkins' => $attendances,
            'has_location' => $token && $token->lat && $token->lng
        ]);
    }


    /**
     * Student checks in. Validates if student's IP is on the same subnet as the open window.
     */
    public function checkInViaWifi(Request $request, $session_id)
    {
        $session = Session::where('session_id', $session_id)
            ->orWhere('uuid', $session_id)
            ->firstOrFail();

        $user = Auth::user();

        // 1. Ensure caller is a Student
        if (! $user->isStudent() || ! $user->student) {
            return response()->json(['error' => 'Only enrolled students can check in.'], 403);
        }

        // 1.1 Secure Check: Is this session today?
        $now = now('Africa/Cairo');
        $sessionDate = \Carbon\Carbon::parse($session->session_date);
        
        if (!$sessionDate->isSameDay($now)) {
            Log::info('Check-in failed: Not today. Session date: ' . $session->session_date . ', Now (Cairo): ' . $now->toDateString());
            return response()->json(['error' => 'يمكنك تسجيل الحضور فقط في نفس يوم الحصة.'], 400);
        }

        // 1.2 Secure Check: Has the session window definitively closed?
        // We use the same end time logic as the dashboard (start_time + 4 hours buffer for safety)
        $sessionStart = \Carbon\Carbon::parse($session->session_date, 'Africa/Cairo')->setTimeFromTimeString($session->start_time);
        $sessionEnd = (clone $sessionStart)->addHours(4); // 4 hours window

        if ($now->gt($sessionEnd)) {
             Log::info('Check-in failed: Window closed. Session end: ' . $sessionEnd->toDateTimeString() . ', Now (Cairo): ' . $now->toDateTimeString());
             return response()->json(['error' => 'عذراً، لقد انتهى الوقت المسموح به لتسجيل الحضور لهذه الحصة.'], 400);
        }

        // 2. Check if attendance window is open
        $windowInfo = AttendanceToken::where('session_id', $session->session_id ?? $session->id)->first();

        if (!$windowInfo || (!$windowInfo->is_wifi_open && !$windowInfo->is_qr_open)) {
            Log::info('Check-in failed: Window not open. Session ID: ' . ($session->session_id ?? $session->id));
            return response()->json(['error' => 'نافذة تسجيل الحضور مغلقة حالياً.'], 400);
        }

        // 3. Proximity Check (WiFi or GPS)
        $method = $request->input('method', 'wifi'); // 'wifi' or 'qr'
        $locationCheckPassed = false;
        $reason = "";

        if ($method === 'wifi') {
            if (!$windowInfo->is_wifi_open) {
                return response()->json(['error' => 'تسجيل الحضور عبر الـ WiFi غير متاح حالياً.'], 400);
            }
            $studentIp = $request->ip();
            if ($studentIp === $windowInfo->teacher_subnet || $studentIp === '127.0.0.1') {
                $locationCheckPassed = true;
            } else {
                $reason = "Network Mismatch: IP $studentIp does not match teacher's Network.";
            }
        } else {
            // QR Method requires either Token match OR GPS match
            if (!$windowInfo->is_qr_open) {
                return response()->json(['error' => 'تسجيل الحضور عبر الـ QR غير متاح حالياً.'], 400);
            }

            $inputToken = $request->input('token');
            if ($inputToken && $inputToken === $windowInfo->qr_token) {
                $locationCheckPassed = true;
            } else {
                // If token is missing or invalid, fallback to GPS if available
                $studentLat = $request->input('lat');
                $studentLng = $request->input('lng');

                if ($windowInfo->lat && $windowInfo->lng && $studentLat && $studentLng) {
                    $distance = $this->calculateDistance($windowInfo->lat, $windowInfo->lng, $studentLat, $studentLng);
                    if ($distance <= $windowInfo->radius_meters) {
                        $locationCheckPassed = true;
                    } else {
                        $reason = "GPS Mismatch: Distance {$distance}m exceeds radius {$windowInfo->radius_meters}m.";
                    }
                } else {
                    $reason = "Invalid QR Token and no GPS coordinates provided for fallback.";
                }
            }
        }

        if (!$locationCheckPassed && $session->requires_proximity) {
            Log::warning("Proximity Check Failed ($method) for student {$user->id}: $reason");
            return response()->json([
                'error' => 'يجب أن تكون متواجداً في مقر الأكاديمية لتسجيل الحضور. (تحقق من الموقع أو الشبكة)'
            ], 403);
        }

        $studentId = $user->student->student_id ?? $user->student->id;

        // 4. Group Enrollment Validation
        $isEnrolled = \App\Models\StudentGroup::where('student_id', $studentId)
            ->where('group_id', $session->group_id)
            ->exists();

        if (!$isEnrolled) {
            // Backup check via group_users if student_groups relation is structured differently
            $isEnrolledBackup = \App\Models\GroupUser::where('user_id', $user->id)
                ->where('group_id', $session->group_id)
                ->exists();
                
            if (!$isEnrolledBackup) {
                return response()->json(['error' => 'You are not enrolled in the group assigned to this session.'], 403);
            }
        }

        $sessionId = $session->session_id ?? $session->id;
        $clientIp = $request->ip();

        // 5. IP Abuse Prevention
        $duplicateAttendance = \App\Models\Attendance::where('session_id', $sessionId)
            ->where('ip_address', $clientIp)
            ->where('student_id', '!=', $studentId)
            ->first();

        if ($duplicateAttendance && $clientIp !== '127.0.0.1') {
            Log::warning("WiFi Check-in Blocked (Duplicate IP): IP $clientIp already used by someone else for session {$sessionId}.");
            return response()->json([
                'error' => 'عذراً، لقد تم تسجيل الحضور مسبقاً من هذا الجهاز الثابت لطالب آخر. يرجى استخدام جهازك الخاص.'
            ], 403);
        }

        // 6. Duplicate Check
        $existingAttendance = \App\Models\Attendance::where('session_id', $sessionId)
            ->where('student_id', $studentId)
            ->first();

        if ($existingAttendance) {
            if ($existingAttendance->status === 'present') {
                return response()->json(['message' => 'لقد تم تسجيل حضورك مسبقاً.']);
            }
            
            $existingAttendance->update([
                'status' => 'present',
                'ip_address' => $clientIp,
                'recorded_by' => $user->id,
                'recorded_at' => now()
            ]);
            return response()->json(['message' => 'تم تحديث حالة حضورك إلى "حاضر".']);
        }

        // 6. Record Initial Attendance
        \App\Models\Attendance::create([
            'session_id' => $sessionId,
            'student_id' => $studentId,
            'status' => 'present',
            'ip_address' => $clientIp,
            'notes' => 'Logged via WiFi Local Network Subnet match',
            'recorded_by' => $user->id,
            'recorded_at' => now(),
        ]);

        return response()->json(['message' => 'تم تسجيل الحضور بنجاح!']);
    }

    /**
     * Calculate distance between two points in meters (Haversine formula).
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function markRemoteAttendance(Request $request, Session $session)
    {
        // Reuse the logic from checkInViaWifi but we can bypass subnet if preferred
        // For now, let's keep it consistent: checkInViaWifi already handles both
        return $this->checkInViaWifi($request, $session);
    }

    /**
     * Show the quick check-in landing page for students.
     */
    public function showCheckInPage($id)
    {
        $session = Session::with('group')
            ->where('session_id', $id)
            ->orWhere('uuid', $id)
            ->firstOrFail();

        $user = Auth::user();

        // Basic authorization: must belong to the group
        if ($user->isStudent() && $user->student) {
            $isEnrolled = \App\Models\StudentGroup::where('student_id', $user->student->student_id)
                ->where('group_id', $session->group_id)
                ->exists();

            if (!$isEnrolled) {
                abort(403, 'You are not enrolled in the group for this session.');
            }
        }

        return view('sessions.checkin', compact('session'));
    }
}
