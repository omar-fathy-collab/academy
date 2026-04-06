<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;

use App\Models\Session;
use App\Models\SessionMaterial;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StudentMaterialsController extends Controller
{
    // Show all materials accessible to the authenticated student
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        if (! ($user->isStudent() || $user->isAdmin())) {
            abort(403);
        }

        $student = $user->student;
        if (! $student && ! $user->isAdmin()) {
            abort(403);
        }

        // Get group ids the student belongs to
        $groupIds = $student ? $student->groups()->pluck('groups.group_id')->toArray() : [];

        // Sorting
        $sort = request()->query('sort', 'latest');
        $query = SessionMaterial::with(['session.group', 'uploader'])->whereHas('session', function ($q) use ($groupIds) {
            $q->whereIn('group_id', $groupIds);
        });

        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'name') {
            $query->orderBy('original_name', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Paginate results
        $materials = $query->paginate(15)->withQueryString();

        return view('materials.index', [
            'materials' => $materials,
            'sort' => $sort,
        ]);
    }

    // Show materials for a particular session to a student
    public function sessionMaterials($sessionId)
    {
        $user = Auth::user();
        if (! $user->isStudent()) {
            abort(403);
        }

        $student = $user->student;
        if (! $student) {
            abort(403);
        }

        $session = Session::with('group')
            ->where('session_id', $sessionId)
            ->orWhere('uuid', $sessionId)
            ->firstOrFail();

        // Check membership
        $isMember = $student->groups()->where('groups.group_id', $session->group_id)->exists();
        if (! $isMember) {
            abort(403);
        }

        $materials = $session->materials()->orderBy('created_at', 'desc')->get();
        
        // Fetch ready videos for this session
        $videos = \Illuminate\Support\Facades\DB::table('videos')
            ->where('session_id', $sessionId)
            ->where('status', 'ready')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('materials.session', [
            'materials' => $materials,
            'videos' => $videos,
            'session' => $session,
        ]);
    }
}
