<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    /**
     * Handle an incoming request and ensure the user has one of the given Spatie roles.
     *
     * Usage on routes:
     *   ->middleware('role:admin|super-admin')
     *   ->middleware('role:teacher')
     *   ->middleware('role:student|admin|super-admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Super-admin always passes
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Check if user has any of the allowed roles
        foreach ($roles as $role) {
            // Support pipe-separated roles: "admin|super-admin"
            $allowedRoles = explode('|', $role);
            foreach ($allowedRoles as $r) {
                if ($user->hasRole(trim($r))) {
                    return $next($request);
                }
            }
        }

        // No matching role → 403 Unauthorized
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة.',
            ], 403);
        }

        abort(403, 'ليس لديك صلاحية للوصول لهذه الصفحة (Role Mismatch).');
    }
}
