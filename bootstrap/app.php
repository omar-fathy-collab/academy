<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/student/video-progress/*/heartbeat',
        ]);
        
        // Role-based access control
        $middleware->alias([
            'role'       => \App\Http\Middleware\EnsureRole::class,
            'guest'      => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'can'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\DecodeUuidRouteParameters::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':120,1',
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\DecodeUuidRouteParameters::class,
        ]);

        $middleware->throttleApi(); // Enable standard API throttling

        // Force SSL in production
        if (env('APP_ENV') === 'production') {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, \Throwable $e, $request) {
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return $response;
                }

                // Handle Unauthenticated redirects for web
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json([
                            'message' => 'Unauthenticated.',
                            'status' => 401
                        ], 410);
                    }
                    return redirect()->guest(route('login'));
                }

                $status = 500;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                }
                
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => $e->getMessage() ?: 'حدث خطأ غير متوقع في النظام.',
                        'exception' => app()->environment('local') ? get_class($e) : null,
                        'status' => $status
                    ], $status);
                }

                return $response;
        });
    })->create();
