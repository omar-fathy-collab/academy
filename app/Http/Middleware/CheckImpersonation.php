<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckImpersonation
{
    public function handle(Request $request, Closure $next)
    {
        if (Session::has('impersonator_id')) {
            // إضافة علامة للـ Blade
            view()->share('isImpersonating', true);
            view()->share('impersonatorId', Session::get('impersonator_id'));
        } else {
            view()->share('isImpersonating', false);
        }

        return $next($request);
    }
}
