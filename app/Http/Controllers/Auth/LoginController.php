<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\User;
use App\Notifications\SuspiciousLoginNotification;
use App\Support\AdminRedirect;
use App\Support\LocalDevelopmentAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLogin(Request $request)
    {
        $redirect = AdminRedirect::safePath($request->query('redirect'));

        return view('auth.login', [
            'redirect' => $redirect,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $user = User::where('email', $request->email)->first();

            if ($user !== null) {
                AdminLog::create([
                    'user_id' => $user->id,
                    'action' => 'failed_login',
                    'details' => "Failed login attempt for {$request->email} from {$request->ip()}",
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);

                $recentFailures = AdminLog::where('action', 'failed_login')
                    ->where('details', 'like', "%{$request->email}%")
                    ->where('created_at', '>=', now()->subMinutes(15))
                    ->count();

                if ($recentFailures >= 3) {
                    User::where('role', 'superadmin')
                        ->get()
                        ->each(fn (User $superadmin) => $superadmin->notify(
                            new SuspiciousLoginNotification($request->email, $recentFailures, $request->ip())
                        ));
                }
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! auth()->user()->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'This account has been deactivated. Contact your administrator.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_remembered_login', $request->boolean('remember'));

        AdminLog::record('login', null, null, 'Admin login successful');

        $safe = AdminRedirect::safePath($request->input('redirect'));

        if ($safe !== null) {
            return redirect($safe);
        }

        return redirect()->route('admin.dashboard');
    }

    public function devLogin(Request $request): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        // Development-only credentials.
        $user = LocalDevelopmentAdmin::ensure();

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('admin_remembered_login', true);

        AdminLog::record('login', null, null, 'Local development admin login');

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        AdminLog::record('logout', null, null, 'Admin logout');

        Auth::logout();
        $request->session()->forget(['2fa_verified', '2fa_attempts', '2fa_setup_secret']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
