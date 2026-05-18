<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — {{ config('app.venue_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <x-tailwind-cdn />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 font-sans antialiased" style="font-family: Inter, system-ui, sans-serif">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div
            class="flex w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200/80">
            {{-- Form --}}
            <div class="w-full p-8 md:p-10 lg:w-1/2">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">
                        Sign in
                    </h1>
                </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    @php
                        $redirectValue = old('redirect', $redirect ?? null);
                    @endphp
                    @if (! empty($redirectValue))
                        <input type="hidden" name="redirect" value="{{ $redirectValue }}">
                    @endif

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username"
                            required autofocus
                            class="w-full rounded-lg border border-slate-300 px-4 py-3 text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-[#0f172a] focus:outline-none focus:ring-2 focus:ring-[#0f172a]/20">
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" autocomplete="current-password"
                                required
                                class="w-full rounded-lg border border-slate-300 px-4 py-3 pr-12 text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-[#0f172a] focus:outline-none focus:ring-2 focus:ring-[#0f172a]/20">
                            <button type="button" id="login-toggle-password" aria-label="Show password"
                                class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                                <svg data-icon="eye" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg data-icon="eye-off" class="hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" name="remember" value="1"
                                class="h-4 w-4 rounded border-slate-300 text-[#0f172a] focus:ring-[#0f172a]/30"
                                {{ old('remember') ? 'checked' : '' }}>
                            <span class="text-sm text-slate-700">Remember me</span>
                        </label>
                        <a href="{{ route('admin.password.forgot') }}" class="text-sm text-slate-500 transition hover:text-slate-700">Forgot password?</a>
                    </div>

                    <button type="submit"
                        class="w-full rounded-lg bg-[#0f172a] py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1e293b] focus:outline-none focus:ring-2 focus:ring-[#0f172a] focus:ring-offset-2">
                        Login to dashboard
                    </button>
                </form>

                @if (app()->environment('local'))
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-xs font-semibold text-emerald-950">Development-only login</p>
                        <p class="mt-1 text-xs text-emerald-800">Use this shortcut when testing locally.</p>
                        <form method="POST" action="{{ route('login.dev') }}" class="mt-3">
                            @csrf
                            @if (! empty($redirectValue))
                                <input type="hidden" name="redirect" value="{{ $redirectValue }}">
                            @endif
                            <button type="submit"
                                class="w-full rounded-lg bg-emerald-700 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2">
                                Login as local admin
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Brand / logo --}}
            <div
                class="relative hidden flex-col items-center justify-center bg-[#0f172a] p-10 text-center lg:flex lg:w-1/2">
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(255,255,255,0.06),transparent_55%)]">
                </div>
                <div class="relative flex max-w-sm flex-col items-center">
                    <img src="{{ asset('images/gervacios-login-logo.png') }}"
                        alt="Gervacio's Cafe — Coffee &amp; Bakery" width="300" height="360"
                        class="h-auto w-full max-w-[300px] object-contain drop-shadow-md">
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('login-toggle-password');
            var input = document.getElementById('password');
            if (!btn || !input) return;
            var eye = btn.querySelector('[data-icon="eye"]');
            var eyeOff = btn.querySelector('[data-icon="eye-off"]');
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                if (eye && eyeOff) {
                    eye.classList.toggle('hidden', show);
                    eyeOff.classList.toggle('hidden', !show);
                }
            });
        })();
    </script>
    <x-flash-toasts />
</body>

</html>
