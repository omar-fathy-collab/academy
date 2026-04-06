<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        // Only enforce in production environment unless explicitly enabled
        // Also bypass if on localhost or 127.0.0.1
        $host = $request->getHost();
        $isLocalHost = in_array($host, ['127.0.0.1', 'localhost']);

        if ((config('app.env') === 'production' || env('FORCE_HTTPS', false)) && ! $request->isSecure() && ! $isLocalHost) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
