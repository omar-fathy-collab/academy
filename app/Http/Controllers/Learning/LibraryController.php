<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Video;
use App\Models\Book;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\LibraryPaymentRequest;

class LibraryController extends Controller
{
    protected $notificationService;

    public function __construct(\App\Services\NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $user->load(['student.groups', 'teacher.groups']);
        $student = $user->student;
        $teacher = $user->teacher;

        // Helper to apply visibility logic for listing
        $applyVisibility = function($query, $user, $student, $teacher) {
            if ($user->isAdmin()) {
                return $query; // Admins see all
            }

            return $query->where(function($q) use ($user, $student, $teacher) {
                // 1. Academy-Wide (Public) content is always visible
                $q->where('visibility', '=', 'public');
                
                // 2. Items with a price > 0 should be visible to students for purchase
                $q->orWhere('price', '>', 0);
                
                // 3. Group-specific content for teachers of those groups
                if ($teacher) {
                    $q->orWhereHas('groups', function($sq) use ($teacher) {
                        $sq->where('groups.teacher_id', '=', $teacher->teacher_id);
                    });
                }
                
                // 4. Group-specific content for students in those groups
                if ($student) {
                    $studentGroupIds = $student->groups->pluck('group_id')->toArray();
                    $q->orWhereHas('groups', function($sq) use ($studentGroupIds) {
                        $sq->whereIn('groups.group_id', $studentGroupIds);
                    });
                }
            })->where('visibility', '!=', 'private'); // Still enforce private means private (Admins only)
        };

        $videoQuery = Video::with('groups');
        $bookQuery = Book::with('groups');

        $videos = $applyVisibility($videoQuery, $user, $student, $teacher)->latest()->get();
        $books = $applyVisibility($bookQuery, $user, $student, $teacher)->latest()->get();

        // Check for access/purchase status
        $mapAccess = function($items, $type) use ($user, $student) {
            return $items->map(function($item) use ($user, $student, $type) {
                $hasAccess = false;
                $paymentStatus = null;
                
                if ($user->isAdmin() || $user->isTeacher()) {
                    $hasAccess = true;
                } elseif ($item->price == 0) {

                    $hasAccess = true;
                } elseif ($student) {
                    // 1. Check if student is in a group that has access to this item
                    $studentGroupIds = $student->groups->pluck('group_id')->toArray();
                    $hasGroupAccess = $item->groups()->whereIn('groups.group_id', $studentGroupIds)->exists();
                    
                    if ($hasGroupAccess) {
                        $hasAccess = true;
                    } else {
                        // 2. Check manual payment requests
                        $paymentRequest = LibraryPaymentRequest::where('user_id', $user->id)
                            ->where('item_id', $item->id)
                            ->where('item_type', $type)
                            ->first();
                            
                        if ($paymentRequest) {
                            $paymentStatus = $paymentRequest->status;
                            if ($paymentStatus === 'approved') {
                                $hasAccess = true;
                            }
                        }

                        // 3. Check standalone purchase (Legacy table)
                        if (!$hasAccess) {
                            $hasAccess = DB::table('student_library_access')
                                ->where('student_id', $student->student_id)
                                ->where($type . '_id', $item->id)
                                ->exists();
                        }
                            
                        // 4. Check session booking (legacy)
                        if (!$hasAccess && $item->session_id) {
                            $hasAccess = DB::table('bookings')
                                ->where('student_id', $student->student_id)
                                ->where('session_id', $item->session_id)
                                ->where('payment_status', 'completed')
                                ->exists();
                        }
                    }
                }
                
                $item->has_access = $hasAccess;
                $item->payment_status = $paymentStatus;
                return $item;
            });
        };

        $videos = $mapAccess($videos, 'video');
        $books = $mapAccess($books, 'book');

        // Get all groups for the selection modal
        $allGroups = ($user->isAdmin() || $user->hasRole('teacher')) ? Group::select('group_id', 'group_name')->get() : collect([]);

        $isAdmin = $user->isAdmin() || $user->hasRole('super-admin') || $user->hasRole('student-manager') || $user->hasRole('academic-manager');

        return view('admin.library.index', [
            'videos' => $videos,
            'books' => $books,
            'all_groups' => $allGroups,
            'is_admin' => $isAdmin
        ]);
    }

    public function storeVideo(Request $request)
    {
        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isTeacher())) abort(403);

        $request->validate([
            'video' => 'required_if:provider,local|mimes:mp4,mov,avi,wmv,flv,mkv|max:102400',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'required|in:local,youtube,vimeo',
            'youtube_url' => 'required_if:provider,youtube|nullable|url',
            'vimeo_url' => 'required_if:provider,vimeo|nullable|url',
            'visibility' => 'required|in:public,private,group',
            'price' => 'nullable|numeric|min:0',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,group_id'
        ]);

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
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $filePath,
            'provider' => $provider,
            'stream_type' => $streamType,
            'status' => $status,
            'visibility' => $request->visibility,
            'price' => $request->price ?? 0,
            'is_library' => true,
        ]);

        if ($request->visibility === 'group' && $request->group_ids) {
            $video->groups()->sync($request->group_ids);
        }

        return back()->with('success', 'Video added to library.');
    }

    public function storeBook(Request $request)
    {
        $user = Auth::user();
        if (!$user || (!$user->isAdmin() && !$user->isTeacher())) abort(403);

        $request->validate([
            'book' => 'required|mimes:pdf|max:20480',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,private,group',
            'price' => 'nullable|numeric|min:0',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,group_id'
        ]);

        $file = $request->file('book');
        $fileName = time().'_'.$file->getClientOriginalName();
        $filePath = $file->storeAs('secure_books', $fileName, 'local');

        $book = Book::create([
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $filePath,
            'visibility' => $request->visibility,
            'price' => $request->price ?? 0,
            'is_library' => true,
            'status' => 'ready',
        ]);

        if ($request->visibility === 'group' && $request->group_ids) {
            $book->groups()->sync($request->group_ids);
        }

        return back()->with('success', 'Book uploaded to library.');
    }

    public function updateAsset(Request $request, $type, $id)
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) abort(403);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,private,group',
            'price' => 'nullable|numeric|min:0',
            'group_ids' => 'nullable|array',
            'group_ids.*' => 'exists:groups,group_id'
        ]);

        $model = $type === 'video' ? Video::findOrFail($id) : Book::findOrFail($id);
        $model->update([
            'title' => $request->title,
            'description' => $request->description,
            'visibility' => $request->visibility,
            'price' => $request->price ?? 0,
        ]);

        if ($request->visibility === 'group') {
            $model->groups()->sync($request->group_ids ?? []);
        } else {
            $model->groups()->detach();
        }

        return back()->with('success', ucfirst($type) . ' updated successfully.');
    }

    public function toggleVisibility(Request $request, $type, $id)
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) abort(403);

        $model = $type === 'video' ? Video::findOrFail($id) : Book::findOrFail($id);
        $model->visibility = $model->visibility === 'public' ? 'private' : 'public';
        $model->save();

        return back()->with('success', ucfirst($type) . ' visibility updated.');
    }

    public function deleteAsset($type, $id)
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) abort(403);

        $model = $type === 'video' ? Video::findOrFail($id) : Book::findOrFail($id);
        
        if ($type === 'book') {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($model->file_path);
        }

        $model->delete();

        return back()->with('success', ucfirst($type) . ' removed from library.');
    }

    public function addToLibrary($id)
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) abort(403);

        $video = Video::findOrFail($id);
        $video->is_library = true;
        $video->save();

        return back()->with('success', 'Video added to library.');
    }

    /**
     * Handle Manual Payment Request (Student)
     */
    public function submitPaymentRequest(Request $request)
    {
        $user = Auth::user();
        if (!$user) abort(401);

        $request->validate([
            'item_id' => 'required',
            'item_type' => 'required|in:video,book',
            'screenshot' => 'required|image|max:5120', // 5MB
            'amount' => 'required|numeric|min:0'
        ]);

        $file = $request->file('screenshot');
        $filePath = $file->store('library_payments', 'public');

        LibraryPaymentRequest::updateOrCreate(
            [
                'user_id' => $user->id,
                'item_id' => $request->item_id,
                'item_type' => $request->item_type,
            ],
            [
                'screenshot_path' => $filePath,
                'amount' => $request->amount,
                'status' => 'pending',
                'notes' => $request->notes
            ]
        );

        return back()->with('success', 'Payment request submitted. Please wait for approval.');
    }

    /**
     * Admin/Teacher: Get Pending Payments
     */
    public function pendingPayments()
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->hasRole('teacher')) abort(403);

        $requests = LibraryPaymentRequest::with(['user', 'item'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('admin.library.payments', [
            'requests' => $requests
        ]);
    }

    /**
     * Admin/Teacher: Approve/Reject Payment
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->hasRole('teacher')) abort(403);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string'
        ]);

        $paymentRequest = LibraryPaymentRequest::findOrFail($id);
        $paymentRequest->update([
            'status' => $request->status,
            'notes' => $request->notes
        ]);

        if ($request->status === 'approved') {
            try {
                // Grant permanent access to student_library_access table
                $student = \App\Models\Student::where('user_id', $paymentRequest->user_id)->first();
                if ($student) {
                    DB::table('student_library_access')->updateOrInsert([
                        'student_id' => $student->student_id,
                        $paymentRequest->item_type . '_id' => $paymentRequest->item_id
                    ], [
                        'updated_at' => now()
                    ]);
                }

                // Ensure relations are loaded for the notification
                $paymentRequest->load(['user', 'item']);
                $this->notificationService->sendLibraryApprovalNotification($paymentRequest);
            } catch (\Exception $e) {
                \Log::error('Failed to grant library access or send notification: ' . $e->getMessage());
            }
        }


        return back()->with('success', 'Payment status updated to ' . $request->status);
    }
}
