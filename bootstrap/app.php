<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin'          => \App\Http\Middleware\AdminRole::class,
            'admin.only'     => \App\Http\Middleware\EnsureAdminOnly::class,
            'admin.notfound' => \App\Http\Middleware\AdminNotFound::class,
            'staff'          => \App\Http\Middleware\EnsureStaffRole::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
            'require.2fa'    => \App\Http\Middleware\RequireTwoFactor::class,
            'admin.timeout'  => \App\Http\Middleware\AdminSessionTimeout::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\BlockBlacklistedIp::class,
        ]);

        $middleware->append(\App\Http\Middleware\NormalizeRequestPath::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $csrfExcept = [
            'webhook/paymongo',
        ];

        if (env('APP_ENV') === 'local') {
            // Development-only convenience: stale browser tabs should not block local admin login with a 419.
            $csrfExcept[] = 'admin/login';
            $csrfExcept[] = 'admin/dev-login';
        }

        $middleware->validateCsrfTokens(except: $csrfExcept);
    })
    ->booted(function () {
        RateLimiter::for('reservation', function (Request $request) {
            return Limit::perHour(5)->by($request->ip())->response(function () {
                return response()->json(['message' => 'Too many reservation attempts. Please try again later.'], 429);
            });
        });

        RateLimiter::for('admin-login', function (Request $request) {
            if (app()->environment(['local', 'testing'])) {
                // Development/testing only: keep credential checks intact while avoiding lockout friction during repeated manual login testing.
                return Limit::perMinute(200)->by($request->ip())->response(function () {
                    return back()->withErrors(['email' => 'Too many login attempts. Please retry in a few seconds.']);
                });
            }

            return Limit::perMinutes(15, 5)->by($request->ip())->response(function () {
                return back()->withErrors(['email' => 'Too many login attempts. Account locked for 15 minutes.']);
            });
        });

        RateLimiter::for('global-post', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
