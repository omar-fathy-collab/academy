<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Video;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function store(Request $request, $sessionId)
    {
        $request->validate([
            'video' => 'required_if:provider,local|mimes:mp4,mov,avi,wmv,flv,mkv|max:102400',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'required|in:local,youtube,vimeo',
            'youtube_url' => 'required_if:provider,youtube|nullable|url',
            'vimeo_url' => 'required_if:provider,vimeo|nullable|url',
            'visibility' => 'required|in:public,private,group',
            'price' => 'nullable|numeric|min:0',
        ]);

        $session = Session::where('uuid', $sessionId)->orWhere('session_id', $sessionId)->firstOrFail();

        $provider = $request->input('provider', 'local');
        $filePath = null;
        $status = 'ready';
        $streamType = 'mp4';

        if ($provider === 'youtube') {
            $filePath = $request->youtube_url;
            $streamType = 'youtube';
        } elseif ($provider === 'vimeo') {
            $filePath = $request->vimeo_url;
            $streamType = 'vimeo';
        } else {
            $file = $request->file('video');
            $fileName = time().'_'.$file->getClientOriginalName();
            $filePath = $file->storeAs('secure_videos', $fileName, 'local');
            $status = 'processing';
        }

        $video = Video::create([
            'uuid' => (string) Str::uuid(),
            'session_id' => $session->session_id,
            'group_id' => $session->group_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $filePath,
            'provider' => $provider,
            'stream_type' => $streamType,
            'status' => $status,
            'visibility' => $request->visibility,
            'price' => $request->price ?? 0,
        ]);

        // Automatically assign to the session's group if it exists
        if ($session->group_id) {
            $video->groups()->attach($session->group_id);
        }

        return back()->with('success', 'Video uploaded successfully.');
    }

    public function showDetails($id)
    {
        $video = Video::where('uuid', $id)->orWhere('id', $id)->firstOrFail();
        
        if (!$this->authorizeVideoAccess(Auth::user(), $video)) {
            abort(403, 'Unauthorized access to this video.');
        }

        return view('admin.library.view_video', [
            'video' => $video,
            'user' => Auth::user()
        ]);
    }

    public function getSignedUrl($videoId)
    {
        $user = Auth::user();
        $video = Video::where('uuid', $videoId)->orWhere('id', $videoId)->firstOrFail();


        if (!$this->authorizeVideoAccess($user, $video)) {
            abort(403, 'Unauthorized access to this video.');
        }

        if ($video->provider === 'youtube' || $video->provider === 'vimeo') {
            return response()->json([
                'url' => $video->file_path,
                'provider' => $video->provider
            ]);
        }

        $url = URL::temporarySignedRoute(
            'student.secure_video.stream',
            now()->addHours(24),
            ['video_id' => $videoId, 'uid' => $user->id]
        );

        $heartbeatUrl = URL::temporarySignedRoute(
            'student.video-progress.heartbeat',
            now()->addHours(24),
            ['material_id' => $videoId, 'uid' => $user->id]
        );

        return response()->json([
            'url' => $url,
            'heartbeat_url' => $heartbeatUrl,
            'provider' => 'local'
        ]);
    }

    protected function authorizeVideoAccess($user, $video)
    {
        if (!$user) return false;
        if ($user->isAdmin() || $user->isTeacher()) return true;


        $student = DB::table('students')->where('user_id', $user->id)->first();
        if (!$student) return false;

        // Public and free videos are accessible to all students
        if ($video->visibility === 'public' && $video->price == 0) return true;

        // Check assigned groups
        $studentGroups = DB::table('student_group')
            ->where('student_id', $student->student_id)
            ->pluck('group_id')
            ->toArray();
            
        $isMember = DB::table('video_group')
            ->where('video_id', $video->id)
            ->whereIn('group_id', $studentGroups)
            ->exists();

        // Check group visibility logic
        if ($video->visibility === 'group' && !$isMember) {
            return false;
        }

        // Check for payment if it's a paid video
        if ($video->price > 0) {
            // 1. Check if student is in a group assigned to this video (Groups get it for free)
            if ($isMember) {
                return true;
            }

            // 2. Check direct library access (Legacy or direct grant)
            $hasPaid = DB::table('student_library_access')
                ->where('student_id', $student->student_id)
                ->where('video_id', $video->id)
                ->exists();

            // 3. Check for an approved payment request (Newer system)
            if (!$hasPaid) {
                $hasPaid = DB::table('library_payment_requests')
                    ->where('user_id', $user->id)
                    ->where('item_id', $video->id)
                    ->where('item_type', 'video')
                    ->where('status', 'approved')
                    ->exists();
            }
                
            // 4. Fallback to session booking if applicable
            if (!$hasPaid && $video->session_id) {
                $hasPaid = DB::table('bookings')
                    ->where('student_id', $student->student_id)
                    ->where('session_id', $video->session_id)
                    ->where('payment_status', 'completed')
                    ->exists();
            }

            if (!$hasPaid) return false;
        }



        if ($video->visibility === 'private' && !$user->isAdmin()) {
             return false;
        }

        return true;
    }

    public function destroy($id)
    {
        $video = Video::findOrFail($id);
        
        $user = Auth::user();
        if (!$user->isAdmin()) {
            if ($video->session_id) {
                $session = DB::table('sessions')->where('session_id', $video->session_id)->first();
                if ($session) {
                    $group = DB::table('groups')->where('group_id', $session->group_id)->first();
                    if (!$group || $group->teacher_id != $user->teacher->teacher_id) {
                        abort(403, 'Unauthorized to delete this video.');
                    }
                }
            } else {
                abort(403, 'Only admins can delete library videos.');
            }
        }

        if ($video->provider === 'local' && !filter_var($video->file_path, FILTER_VALIDATE_URL)) {
            Storage::disk('local')->delete(str_replace('storage/', '', $video->file_path));
        }

        if ($video->thumbnail_url && !filter_var($video->thumbnail_url, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete(str_replace('storage/', '', $video->thumbnail_url));
        }

        $video->delete();

        return back()->with('success', 'Video deleted successfully.');
    }

    /**
     * Stream a secure video file.
     */
    public function stream(Request $request, $video_id)
    {
        $video = Video::where('uuid', $video_id)->orWhere('id', $video_id)->firstOrFail();
        
        // Handle signed request uid fallback
        $user = Auth::user();
        if (!$user && $request->has('uid')) {
            $user = \App\Models\User::find($request->uid);
        }

        if (!$user || !$this->authorizeVideoAccess($user, $video)) {
            abort(403);
        }

        $path = $video->file_path;
        
        if ($video->provider === 'local') {
            if (!Storage::disk('local')->exists($path)) {
                abort(404, 'Video file not found.');
            }

            $storagePath = Storage::disk('local')->path($path);
            
            // Use BinaryFileResponse for range support
            return response()->file($storagePath, [
                'Content-Type' => 'video/mp4',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        abort(400, 'Invalid video provider for streaming.');
    }

    /**
     * Get security key (placeholder).
     */
    public function getKey(Request $request, $video_id)
    {
        return response()->json(['message' => 'HLS keys not implemented'], 501);
    }
}
