<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;

class AdminVerificationMiddleware
{
    /**
     * Handle an incoming request.
     * Enforces that the admin has "confirmed" their password recently for sensitive actions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user has confirmed their password in the last 2 hours
        $lastAuth = Session::get('auth.password_confirmed_at');
        $hasPasswordConfirmRoute = \Illuminate\Support\Facades\Route::has('password.confirm');

        if ($hasPasswordConfirmRoute && (!$lastAuth || now()->timestamp - $lastAuth > 7200)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Confirmation required',
                    'message' => 'Please confirm your password to perform this sensitive action.',
                    'redirect' => route('password.confirm')
                ], 423);
            }

            return redirect()->guest(route('password.confirm'));
        }

        return $next($request);
    }
}
