<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthCheck
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect('/')->with('error', 'Please login first');
        }

        // التحقق من أن الحساب نشط
        $user = Auth::user();
        if ($user->is_active != 1) {
            Auth::logout();

            return redirect('/')->with('error', 'Account is inactive');
        }

        return $next($request);
    }
}
