<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                
                // Dynamic redirection based on role
                switch ($user->role_id) {
                    case 1:
                    case 4:
                        return redirect()->route('dashboard');
                    case 2:
                        return redirect()->route('teacher.dashboard');
                    case 3:
                        return redirect()->route('student.dashboard');
                    default:
                        return redirect()->route('dashboard');
                }
            }
        }

        return $next($request);
    }
}
