<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\VideoProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VideoProgressController extends Controller
{
    /**
     * Get current progress for the authenticated student.
     */
    public function getProgress(Request $request, $video_id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Fallback for signed requests if session expired
        if (!$user && ($request->has('uid') || $request->query('uid'))) {
            if (!$request->hasValidSignature()) {
                return response()->json(['error' => 'Invalid or expired signature'], 403);
            }
            $user = \App\Models\User::find($request->input('uid'));
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $student = $user->student;
        if (!$student) {
            // Staff can view but don't have progress
            return response()->json(['progress' => null]);
        }

        $video = \App\Models\Video::where('id', $video_id)->orWhere('uuid', $video_id)->first();
        if (!$video) return response()->json(['error' => 'Video not found'], 404);

        $progress = VideoProgress::where('student_id', $student->student_id)
            ->where('video_id', $video->id)
            ->first();

        return response()->json([
            'progress' => $progress,
            'duration' => $video->duration
        ]);
    }

    /**
     * Record a secure heartbeat from the video player.
     */
    public function heartbeat(Request $request, $video_id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Fallback for signed requests if session expired
        if (!$user && ($request->has('uid') || $request->query('uid'))) {
            if (!$request->hasValidSignature()) {
                return response()->json(['error' => 'Invalid or expired signature'], 403);
            }
            $user = \App\Models\User::find($request->input('uid'));
        }

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $video = \App\Models\Video::where('id', $video_id)->orWhere('uuid', $video_id)->first();
        if (!$video) return response()->json(['error' => 'Video not found'], 404);

        $request->validate([
            'current_position' => 'required|numeric|min:0',
            'duration' => 'sometimes|numeric|min:0',
            'is_completed' => 'sometimes|boolean'
        ]);

        $currentPosition = (int) $request->current_position;
        $isCompleted = $request->boolean('is_completed', false);
        $duration = $request->input('duration');

        // Update video duration if missing
        if ($duration > 0 && (!$video->duration || $video->duration == 0)) {
            DB::table('videos')->where('id', $video->id)->update(['duration' => $duration]);
            $video->duration = $duration;
        }

        $videoDuration = $video->duration ?: $duration ?: 0;

        Log::info("Heartbeat: User {$user->id}, Video {$video->id}, Pos {$currentPosition}");

        // Track by user_id primarily to support all roles (Admin/Staff/Student)
        $progress = VideoProgress::query()->where('video_id', $video->id)
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->student) {
                    $q->orWhere('student_id', $user->student?->student_id);
                }
            })
            ->first();

        if (!$progress) {
             $progress = new VideoProgress();
             $progress->video_id = $video->id;
             $progress->user_id = $user->id;
             $progress->student_id = $user->student?->student_id;
             $progress->watched_seconds = 0;
             $progress->watched_segments = [];
             $progress->last_position = $currentPosition;
             $progress->is_completed = false;
        }

        $now = now();
        $timeSinceLastHeartbeat = $progress->last_heartbeat_at ? $now->diffInSeconds($progress->last_heartbeat_at) : null;
        $currentIp = $request->ip();
        
        // Concurrent Device Check
        if ($timeSinceLastHeartbeat !== null && $timeSinceLastHeartbeat <= 45) {
            if ($progress->active_ip && $progress->active_ip !== $currentIp && $currentIp !== '127.0.0.1') {
                return response()->json(['error' => 'Multiple devices detected.'], 429);
            }
        }
        
        // Anti-cheat verification
        $positionDelta = $currentPosition - $progress->last_position;
        // Allow up to 2.5x speed + 2s buffer
        $maxAllowedDelta = ($timeSinceLastHeartbeat !== null) ? ($timeSinceLastHeartbeat * 2.5) + 2 : 100;
        $canCreditMatch = $timeSinceLastHeartbeat !== null && $timeSinceLastHeartbeat <= 60; // Increased window to 60s
        
        Log::info("Heartbeat Analysis: Delta {$positionDelta}, SinceLast {$timeSinceLastHeartbeat}, MaxAllowed {$maxAllowedDelta}, CanCredit " . ($canCreditMatch ? 'YES' : 'NO'));

        if ($canCreditMatch && $positionDelta > 0 && $positionDelta <= $maxAllowedDelta) {
            // Valid forward progress
            $segments = is_array($progress->watched_segments) ? $progress->watched_segments : [];
            $newSegment = [(int)$progress->last_position, (int)$currentPosition];
            
            // Merge logic
            $mergedSegments = [];
            $inserted = false;
            foreach ($segments as $segment) {
                if ($newSegment[1] < $segment[0]) {
                    if (!$inserted) { $mergedSegments[] = $newSegment; $inserted = true; }
                    $mergedSegments[] = $segment;
                } elseif ($newSegment[0] > $segment[1]) {
                    $mergedSegments[] = $segment;
                } else {
                    $newSegment[0] = min($newSegment[0], $segment[0]);
                    $newSegment[1] = max($newSegment[1], $segment[1]);
                }
            }
            if (!$inserted) $mergedSegments[] = $newSegment;
            
            $progress->watched_segments = $mergedSegments;
            
            // Recalculate total unique seconds
            $totalSeconds = 0;
            foreach ($mergedSegments as $seg) {
                $totalSeconds += ($seg[1] - $seg[0]);
            }
            $progress->watched_seconds = $totalSeconds;

            if ($videoDuration > 0) {
                $progress->watched_percentage = min(100, ($totalSeconds / $videoDuration) * 100);
                if ($progress->watched_percentage >= 98) $progress->is_completed = true;
            }
            
        }
        
        // Always update last_position to current_position to avoid permanent blocks/loops
        // even if progress wasn't credited this time.
        $progress->last_position = $currentPosition;

        $progress->active_ip = $currentIp;
        $progress->last_heartbeat_at = $now;
        if ($isCompleted) $progress->is_completed = true;

        $progress->save();

        return response()->json([
            'success' => true,
            'watched_seconds' => (int)$progress->watched_seconds,
            'last_position' => (int)$progress->last_position,
            'is_completed' => $progress->is_completed
        ]);
    }

    public function showEngagement($videoId = null)
    {
        $videoId = $videoId ?? request('id');
        if (!$videoId) abort(400, 'Video ID is required.');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isTeacher()) {
            abort(403);
        }

        $video = DB::table('videos')->where('id', '=', $videoId)->first();
        if (!$video) abort(404);

        // Check if teacher owns this video (if not admin)
        if (Auth::user()->isTeacher() && $video->session_id) {
            $session = \App\Models\Session::with('group')->find($video->session_id);
            if ($session && $session->group->teacher_id != Auth::user()->teacher->teacher_id) {
                abort(403);
            }
        }

        return view('videos.engagement_dashboard', [
            'videoId' => (int)$videoId
        ]);
    }

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) return redirect()->route('login');
        
        return view('videos.index');
    }

    public function getEngagement($videoId = null)
    {
        $videoId = $videoId ?? request('id');
        if (!$videoId) abort(400, 'Video ID is required.');

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isTeacher())) {

            abort(403);
        }

        $video = DB::table('videos')->where('id', '=', $videoId)->first();
        if (!$video) abort(404);

        // Check if teacher owns this video (if not admin)
        if (Auth::user()->isTeacher() && $video->session_id) {
            $session = \App\Models\Session::with('group')->find($video->session_id);
            if ($session && $session->group->teacher_id != Auth::user()->teacher->teacher_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $engagement = VideoProgress::query()->where('video_id', '=', $videoId)
            ->with(['student' => function($query) {
                /** @var \Illuminate\Database\Eloquent\Builder $query */
                $query->select('student_id', 'student_name', 'user_id');
            }])
            ->get();

        return response()->json([
            'video' => $video,
            'engagement' => $engagement
        ]);
    }
}
