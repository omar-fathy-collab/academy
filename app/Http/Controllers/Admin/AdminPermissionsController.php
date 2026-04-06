<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\User;
use Illuminate\Http\Request;

class AdminPermissionsController extends Controller
{
    public function index()
    {
        $users = User::with('role')->paginate(20);

        return view('admin.permissions.index', ['users' => $users]);
    }

    public function edit(User $user)
    {
        $permissions = AdminPermission::all();
        $user->load('permissions');

        return view('admin.permissions.edit', compact('user', 'permissions'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user); // keep existing policies if any

        // Sync requested permissions for the user (admin types are handled via admin_types flags elsewhere)
        $user->permissions()->sync($request->input('permissions', []));

        \App\Models\AccessLog::create([
            'user_id' => auth()->id(),
            'route' => request()->path(),
            'method' => request()->method(),
            'payload' => json_encode(['user' => $user->id, 'permissions' => $request->input('permissions', [])]),
            'message' => 'Updated admin permissions via UI',
        ]);

        return redirect()->route('admin.permissions.edit', $user->id)->with('success', 'Permissions updated');
    }
}
