<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use Closure;
use Illuminate\Http\Request;

class BlockRestrictedReports
{
    /**
     * Block access to sensitive reports for restricted roles (secretary/accountant or admin partial).
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Allow any admin (full or partial). Block non-admins.
        if (! ($user->isAdminFull() || $user->isAdminPartial())) {
            // Log the denied access attempt
            AccessLog::create([
                'user_id' => $user->id,
                'route' => $request->path(),
                'method' => $request->method(),
                'payload' => json_encode($request->except(['password', 'pass', '_token'])),
                'message' => 'Blocked restricted reports access',
            ]);

            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
