<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of all roles.
     */
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('roles.index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show creation form.
     */
    public function create()
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('_', $p->name, 2)[0]; // Group by prefix (view, manage, create, etc.)
        });

        return view('roles.create', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a new role.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::create([
            'name'       => $request->name,
            'guard_name' => 'web',
        ]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')
            ->with('success', 'تم إنشاء الدور "' . $role->name . '" بنجاح مع ' . count($request->permissions ?? []) . ' صلاحية.');
    }

    /**
     * Show edit form.
     */
    public function edit(Role $role)
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($p) {
            return explode('_', $p->name, 2)[0];
        });

        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('roles.edit', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    /**
     * Update role (name + permissions).
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:roles,name,' . $role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'تم تحديث الدور "' . $role->name . '" بنجاح.');
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role)
    {
        $protectedRoles = ['super-admin', 'admin', 'teacher', 'student'];

        if (in_array($role->name, $protectedRoles)) {
            return back()->with('error', 'لا يمكن حذف الأدوار الأساسية للنظام.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'تم حذف الدور بنجاح.');
    }

    /**
     * Show users with a specific role.
     */
    public function users(Role $role)
    {
        $users = $role->users()->paginate(20);

        return view('roles.users', [
            'role' => $role,
            'users' => $users,
        ]);
    }
}
