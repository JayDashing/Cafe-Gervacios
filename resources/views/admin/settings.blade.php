@extends('layouts.admin')

@php
    $isAutomationSection = request()->query('section') === 'automation';
@endphp

@section('page_title', $isAutomationSection ? 'Automation' : 'Settings')

@section('panel_heading')
    <x-admin-panel-heading :title="$isAutomationSection ? 'Automation' : 'Settings'" />
@endsection

@section('content')
    <div class="mx-auto flex min-h-0 w-full max-w-[1500px] flex-1 flex-col gap-4">
        @if (auth()->user()->isAdmin())
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Two-Factor Authentication</h2>
                        <p class="mt-1 text-xs text-slate-500">Protect admin access with Google Authenticator.</p>
                    </div>
                    @if (auth()->user()->google2fa_enabled)
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                            Enabled
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            Disabled
                        </span>
                    @endif
                </div>

                @if (! auth()->user()->google2fa_enabled)
                    <div class="mt-4">
                        <a href="{{ route('admin.2fa.setup') }}"
                            class="inline-flex items-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                            Enable 2FA
                        </a>
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.2fa.disable') }}" class="mt-4 space-y-2">
                        @csrf
                        <label for="disable_2fa_password" class="block text-xs font-medium text-slate-700">Confirm password to disable</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <input id="disable_2fa_password" type="password" name="password" required
                                class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button type="submit"
                                class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                Disable 2FA
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-500">Recovery: contact superadmin if locked out.</p>
                    </form>
                @endif
            </section>
        @endif

        @livewire('admin.settings-manager')
    </div>
@endsection
