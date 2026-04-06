<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;

use App\Models\Session;
use App\Models\SessionMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SessionMaterialsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! in_array($user->role_id, [1, 2])) {
            abort(403);
        }

        $search = $request->get('search', '');
        $teacherId = $user->teacher->teacher_id ?? null;
        $isAdmin = $user->role_id == 1;

        $query = SessionMaterial::with(['session.group.course', 'uploader'])
            ->when(! $isAdmin, function ($q) use ($user, $teacherId) {
                // Show materials uploaded by them OR in groups they teach
                $q->where(function ($sub) use ($user, $teacherId) {
                    $sub->where('uploaded_by', $user->id)
                        ->orWhereHas('session.group', function ($g) use ($teacherId) {
                            $g->where('teacher_id', $teacherId);
                        });
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%");
            });

        $materials = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('materials.index', [
            'materials' => $materials,
            'filters' => ['search' => $search],
        ]);
    }

    // Store uploaded material

    public function store(Request $request, $sessionId)
    {
        $session = Session::when(is_numeric($sessionId), fn($q) => $q->where('session_id', $sessionId), fn($q) => $q->where('uuid', $sessionId))->firstOrFail();

        // Only admins or the group teacher may upload
        if (! in_array(auth()->user()->role_id, [1, 2])) {
            abort(403);
        }

        if (auth()->user()->role_id == 2 && (! auth()->user()->teacher || $session->group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403);
        }

        $request->validate([
            'material' => 'required|file|max:20480', // 20MB in KB
        ]);

        $file = $request->file('material');
        $originalName = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize();

        // Store file in storage/app/public/session_materials
        $path = $file->store('session_materials', 'public');

        $material = SessionMaterial::create([
            'session_id' => $session->session_id,
            'uploaded_by' => auth()->id(),
            'original_name' => $originalName,
            'file_path' => $path,
            'mime_type' => $mime,
            'size' => $size,
        ]);

        return redirect()->route('sessions.show', $session->session_id)->with('success', 'Material uploaded successfully');
    }

    // Download material
    public function download($id)
    {
        $material = SessionMaterial::findOrFail($id);

        if (! auth()->check()) {
            abort(403);
        }

        // If student, ensure they belong to the group
        $user = auth()->user();
        if ($user->isStudent()) {
            $student = auth()->user()->student;
            if (! $student) {
                abort(403);
            }
            // qualify the column name to avoid ambiguity between groups and pivot table
            $isMember = $student->groups()->where('groups.group_id', $material->session->group_id)->exists();
            if (! $isMember) {
                abort(403);
            }
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($material->file_path)) {
            abort(404, 'File not found');
        }

        $fullPath = $disk->path($material->file_path);
        if (! file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        return response()->download($fullPath, $material->original_name);
    }

    // Preview material inline (for images/pdf) with auth checks
    public function preview($id)
    {
        $material = SessionMaterial::findOrFail($id);

        if (! auth()->check()) {
            abort(403);
        }

        // If student, ensure they belong to the group
        $user = auth()->user();
        if ($user->isStudent()) {
            $student = auth()->user()->student;
            if (! $student) {
                abort(403);
            }
            $isMember = $student->groups()->where('groups.group_id', $material->session->group_id)->exists();
            if (! $isMember) {
                abort(403);
            }
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($material->file_path)) {
            abort(404, 'File not found');
        }

        $fullPath = $disk->path($material->file_path);
        if (! file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        // Serve file inline when possible
        return response()->file($fullPath, [
            'Content-Type' => $material->mime_type ?? 'application/octet-stream',
        ]);
    }

    // Delete material
    public function destroy($id)
    {
        $material = SessionMaterial::findOrFail($id);
        $session = $material->session;

        // Only admins or the group teacher who uploaded may delete
        if (! in_array(auth()->user()->role_id, [1, 2])) {
            abort(403);
        }

        if (auth()->user()->role_id == 2 && (! auth()->user()->teacher || $session->group->teacher_id != auth()->user()->teacher->teacher_id)) {
            abort(403);
        }

        // Delete file from storage
        Storage::disk('public')->delete($material->file_path);
        $material->delete();

        return redirect()->route('sessions.show', $session->session_id)->with('success', 'Material deleted');
    }
}
