<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->routeIs('login')
            || $request->routeIs('admin.login')
            || $request->routeIs('admin.2fa.verify')
            || $request->routeIs('admin.2fa.verify.submit')
        ) {
            return $next($request);
        }

        if (! Auth::check()) {
            return $next($request);
        }

        if (
            app()->environment('local')
            && ((bool) $request->session()->get('admin_remembered_login', false) || Auth::viaRemember())
        ) {
            $request->session()->put('admin_last_activity', now());

            return $next($request);
        }

        $lastActivity = $request->session()->get('admin_last_activity');

        if ($lastActivity && now()->diffInMinutes($lastActivity) > 30) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $loginRoute = Route::has('admin.login') ? 'admin.login' : 'login';

            return redirect()
                ->route($loginRoute)
                ->with('warning', 'Your session expired due to inactivity. Please log in again.');
        }

        $request->session()->put('admin_last_activity', now());

        return $next($request);
    }
}
