<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page_title', 'Admin') — {{ config('app.venue_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <x-tailwind-cdn />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            /* Canonical SaaS primary (header / sidebar / CTAs / chart ink) */
            --tc-brand: #0f172a;
            --tc-brand-hover: #1e293b;
            --tc-chrome: #2d3748;
            --tc-brand-soft: rgba(15, 23, 42, 0.08);
            --tc-canvas: #e2e8f0;
            --tc-surface: #f1f5f9;
            --tc-stroke: #d8dee8;
            --tc-on-dark: #f8fafc;
            --tc-on-bright: #ffffff;
            --font-admin-ui: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial,
                sans-serif;
            --font-admin-display: var(--font-admin-ui);
        }

        /* Page titles — same stack as body/panels (see components/admin-panel-heading) */
        .admin-panel-heading h1 {
            font-family: var(--font-admin-ui);
            font-weight: 600;
            letter-spacing: -0.035em;
        }

        .admin-panel-heading-inner {
            font-family: var(--font-admin-ui);
        }

        .admin-panel-heading {
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--tc-stroke);
        }

        /* Form controls — consistent focus ring (admin / staff shell) */
        .admin-shell input:not([type='checkbox']):not([type='radio']):not([type='file']):not([type='submit']):not([type='button']):not([type='reset']),
        .admin-shell select,
        .admin-shell textarea {
            border-color: var(--tc-stroke);
        }

        .admin-shell input:not([type='checkbox']):not([type='radio']):not([type='file']):not([type='submit']):not([type='button']):not([type='reset']):focus-visible,
        .admin-shell select:focus-visible,
        .admin-shell textarea:focus-visible {
            border-color: var(--tc-brand);
            outline: none;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.12);
        }

        /* Inter + system UI (SF on macOS/iOS) — panels, tables, Livewire inherit this */
        .admin-shell {
            font-family: var(--font-admin-ui);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .admin-shell input,
        .admin-shell select,
        .admin-shell textarea,
        .admin-shell button {
            font-family: inherit;
        }

        /* Shared type roles (use with Tailwind utilities on panels) */
        .admin-type-overline {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }

        .admin-type-display {
            font-size: 1.5rem;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--tc-brand);
            font-variant-numeric: tabular-nums;
        }

        .tc-ios-card {
            border-radius: 14px;
            border: 1px solid var(--tc-stroke);
            background: var(--tc-surface);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
        }

        /*
         * Active sidebar item — overridden by admin-sidebar (dark nav).
         */
        .tc-nav-active {
            background: rgba(248, 250, 252, 0.08);
            color: var(--tc-on-dark);
            border-left: 2px solid rgba(248, 250, 252, 0.45);
            font-weight: 600;
        }

        .tc-sidebar-subnav-line {
            border-left: 1px solid rgba(248, 250, 252, 0.12);
        }

        .tc-settings-jump-active {
            background: rgba(15, 23, 42, 0.35);
            color: var(--tc-on-bright);
            font-weight: 600;
        }

        /* ── Admin top header (partials/admin-header) — unified dark chrome with sidebar -- */
        .admin-header-shell {
            font-family: var(--font-admin-ui);
        }

        .admin-header-shell .admin-header-inner {
            min-height: 3.5rem;
            box-sizing: border-box;
        }

        .admin-header-menu-btn {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            transition: background 0.15s ease, color 0.15s ease;
            background: transparent;
            border: none;
            cursor: pointer;
            flex-shrink: 0;
        }

        .admin-header-menu-btn:hover {
            background: rgba(30, 41, 59, 0.85);
            color: #f8fafc;
        }

        .admin-header-shell .admin-header-icon-btn {
            display: inline-flex;
            height: 2.5rem;
            width: 2.5rem;
            align-items: center;
            justify-content: center;
            border-radius: 0.625rem;
            color: #cbd5e1;
            background: rgba(30, 41, 59, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.25);
            transition: color 0.2s ease, background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }

        .admin-header-shell .admin-header-icon-btn:hover {
            color: #f8fafc;
            background: rgba(51, 65, 85, 0.9);
            border-color: rgba(148, 163, 184, 0.45);
        }

        .admin-header-shell .admin-header-icon-btn:active {
            transform: scale(0.96);
        }

        @media (prefers-reduced-motion: no-preference) {
            .admin-header-shell {
                animation: tcAdminHeaderIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
            }
        }

        @keyframes tcAdminHeaderIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Dashboard page — staggered card entrance */
        @media (prefers-reduced-motion: no-preference) {
            .admin-dashboard-animate .tc-dash-card {
                animation: tcDashCardIn 0.48s cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .admin-dashboard-animate .tc-dash-card:nth-child(1) {
                animation-delay: 0.02s;
            }

            .admin-dashboard-animate .tc-dash-card:nth-child(2) {
                animation-delay: 0.06s;
            }

            .admin-dashboard-animate .tc-dash-card:nth-child(3) {
                animation-delay: 0.1s;
            }

            .admin-dashboard-animate .tc-dash-card:nth-child(4) {
                animation-delay: 0.14s;
            }

            .admin-dashboard-animate .tc-dash-card--wide {
                animation-delay: 0.12s;
            }

            .admin-dashboard-animate .tc-dash-card--chart:nth-of-type(1) {
                animation-delay: 0.16s;
            }

            .admin-dashboard-animate .tc-dash-card--chart:nth-of-type(2) {
                animation-delay: 0.2s;
            }

            .admin-dashboard-animate .tc-dash-side-card {
                animation: tcDashCardIn 0.48s cubic-bezier(0.16, 1, 0.3, 1) both;
            }

            .admin-dashboard-animate aside .tc-dash-side-card:nth-child(1) {
                animation-delay: 0.1s;
            }

            .admin-dashboard-animate aside .tc-dash-side-card:nth-child(2) {
                animation-delay: 0.16s;
            }
        }

        @keyframes tcDashCardIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Side column cards — match dashboard surface, subtle lift on hover */
        .tc-dash-side-card {
            transition: box-shadow 0.26s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.22s ease,
                transform 0.26s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .tc-dash-side-card:hover {
            border-color: rgba(148, 163, 184, 0.65);
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.07);
            transform: translateY(-2px);
        }

        @media (prefers-reduced-motion: reduce) {
            .tc-dash-side-card {
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .tc-dash-side-card:hover {
                transform: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .admin-header-shell,
            .admin-dashboard-animate .tc-dash-card,
            .admin-dashboard-animate .tc-dash-side-card {
                animation: none !important;
            }
        }

        /* Primary / secondary actions — reuse across Livewire panels */
        .tc-admin-btn-primary {
            background-color: var(--tc-brand);
            color: var(--tc-on-bright);
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.15s ease;
        }

        .tc-admin-btn-primary:hover {
            background-color: var(--tc-brand-hover);
        }

        .tc-admin-btn-secondary {
            background-color: var(--tc-surface);
            color: var(--tc-brand);
            border: 1px solid var(--tc-stroke);
            border-radius: 0.5rem;
            font-weight: 500;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .tc-admin-btn-secondary:hover {
            background-color: #f8fafc;
        }

        .tc-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .tc-scrollbar::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 8px;
        }

        .tc-scrollbar::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 8px;
        }

        .tc-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .compact-table th,
        .compact-table td {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        /*
         * Full-height tool pages: main does not scroll; inner fills height; inner panes scroll.
         * Default dashboard/list pages: inner is shrink-0 height = content height so <main> scrolls
         * and bottom cards are never clipped by the viewport.
         */
        .admin-shell main:has(.staff-queue-shell),
        .admin-shell main:has(.auto-table-shell),
        .admin-shell main:has(.seating-full-editor-shell) {
            overflow: hidden;
        }

        .admin-shell main > .admin-main-inner {
            flex: 0 0 auto;
            width: 100%;
        }

        .admin-shell main:has(.staff-queue-shell) > .admin-main-inner,
        .admin-shell main:has(.auto-table-shell) > .admin-main-inner,
        .admin-shell main:has(.seating-full-editor-shell) > .admin-main-inner {
            flex: 1 1 0%;
            min-height: 0;
        }

        @media (min-width: 768px) {
            html.admin-sidebar-collapsed aside.admin-sidebar {
                width: 4.25rem;
            }

            html.admin-sidebar-collapsed .admin-sidebar-nav-text {
                display: none;
            }

            html.admin-sidebar-collapsed .admin-sidebar-settings-chevron {
                display: none;
            }

            html.admin-sidebar-collapsed #admin-settings-jump {
                display: none !important;
            }

            html.admin-sidebar-collapsed aside.admin-sidebar nav {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            html.admin-sidebar-collapsed .admin-sidebar-footer {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            html.admin-sidebar-collapsed aside.admin-sidebar nav>a,
            html.admin-sidebar-collapsed aside.admin-sidebar .settings-row>a {
                justify-content: center;
                gap: 0;
            }

            html.admin-sidebar-collapsed aside.admin-sidebar .settings-row {
                justify-content: center;
            }

            html.admin-sidebar-collapsed aside.admin-sidebar .settings-row>a {
                flex: 0 0 auto;
            }

            html.admin-sidebar-collapsed .admin-sidebar-logout-btn {
                justify-content: center;
                gap: 0;
            }
        }

        /*
         * Seat focus mode (Auto Table + Seating layout): hide nav/header and in-page chrome
         * so staff can focus on the map and waitlist. Toggled via #tc-seat-focus-toggle.
         */
        html.tc-seat-focus-mode aside.admin-sidebar {
            display: none !important;
        }

        html.tc-seat-focus-mode .admin-header-shell {
            display: none !important;
        }

        html.tc-seat-focus-mode .admin-panel-heading {
            display: none !important;
        }

        html.tc-seat-focus-mode .admin-shell-max {
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        html.tc-seat-focus-mode .admin-main-inner {
            padding: 0 !important;
        }

        html.tc-seat-focus-mode main {
            overflow: hidden;
        }

        /* Floor Map: hide title/legend in focus mode; keep quick-actions host so table popover (fixed) stays visible */
        html.tc-seat-focus-mode .dsm-toolbar-left {
            display: none !important;
        }

        html.tc-seat-focus-mode .dsm-toolbar-strip {
            justify-content: flex-end;
        }

        /* Seating layout: hide title + legend pills; keep strip for Focus / info / Auto Table */
        html.tc-seat-focus-mode .sle-legend-strip__main {
            display: none !important;
        }

        html.tc-seat-focus-mode .sle-legend-strip__inner {
            justify-content: flex-end;
        }

        html.tc-seat-focus-mode .sle-tools-panel {
            display: none !important;
        }

        /* Shell negative margins become unnecessary — full bleed in focus */
        html.tc-seat-focus-mode .auto-table-shell,
        html.tc-seat-focus-mode .seating-full-editor-shell {
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* Header/sidebar hidden in focus — show compact log out */
        html.tc-seat-focus-mode form.tc-seat-focus-logout {
            display: inline-flex !important;
        }
    </style>
    <script>
        (function () {
            try {
                if (localStorage.getItem('admin_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('admin-sidebar-collapsed');
                }
            } catch (e) { }
        })();
    </script>
    <script>
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) window.location.reload();
        });
    </script>
</head>

<body
    class="admin-shell flex min-h-[100dvh] flex-col bg-panel-canvas text-slate-800 antialiased tc-scrollbar md:h-screen md:max-h-screen md:overflow-hidden">
    {{-- Full-width header; below it, constrained shell for sidebar + main --}}
    <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col">
        @include('layouts.partials.admin-header')

        <div class="flex min-h-0 flex-1 flex-col justify-center">
            {{-- Full width below header so map editors (seating, Auto Table) use horizontal space --}}
            <div class="admin-shell-max flex w-full max-w-none flex-1 min-h-0 flex-col overflow-hidden">
                <div
                    class="admin-shell-body flex min-h-0 flex-1 flex-col overflow-hidden md:flex-row">
                    @include('layouts.partials.admin-sidebar')

                    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-panel-canvas">
                        <main
                            class="flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto overflow-x-hidden overscroll-y-contain scroll-pb-10 tc-scrollbar bg-panel-canvas [overflow-anchor:auto] [scrollbar-gutter:stable]">
                            <div class="admin-main-inner flex w-full min-w-0 max-w-full flex-col px-4 pt-4 pb-10 md:px-5 md:pt-4 md:pb-12">
                                @hasSection('panel_heading')
                                    <div class="admin-panel-heading">
                                        @yield('panel_heading')
                                    </div>
                                @endif
                                @yield('content')
                            </div>
                        </main>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-flash-toasts />

    <form method="POST" action="{{ route('logout') }}"
        class="tc-seat-focus-logout fixed bottom-4 right-4 z-[95] hidden items-center">
        @csrf
        <button type="submit"
            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-lg transition hover:bg-slate-50"
            title="Log out">
            <i class="fa-solid fa-right-from-bracket text-[13px] text-panel-primary" aria-hidden="true"></i>
            Log out
        </button>
    </form>

    <div
        x-data="{
            warned: false,
            lastActivityMs: Date.now(),
            warningAfterMs: 25 * 60 * 1000,
            pingUrl: '{{ route('admin.ping') }}',
            init() {
                const markActive = () => {
                    const shouldPing = this.warned;
                    this.lastActivityMs = Date.now();
                    if (this.warned) this.warned = false;
                    if (shouldPing) this.ping();
                };

                ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach((evt) => {
                    window.addEventListener(evt, markActive, { passive: true });
                });

                setInterval(() => {
                    if (!this.warned && Date.now() - this.lastActivityMs >= this.warningAfterMs) {
                        this.warned = true;
                    }
                }, 1000);
            },
            async ping() {
                try {
                    await fetch(this.pingUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });
                } catch (e) {}
            }
        }"
        x-init="init()"
        x-show="warned"
        x-transition.opacity
        class="fixed bottom-5 right-5 z-[120] w-full max-w-sm rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-lg"
        style="display:none;"
        role="status"
        aria-live="polite">
        <p class="text-sm font-semibold text-amber-900">Your session will expire in 5 minutes.</p>
        <p class="mt-1 text-xs text-amber-800">Click anywhere or press Stay logged in to continue.</p>
        <button type="button"
            class="mt-3 inline-flex items-center rounded-md bg-amber-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-800"
            x-on:click="lastActivityMs = Date.now(); warned = false; ping();">
            Stay logged in
        </button>
    </div>

    @stack('scripts')
    @livewireScripts
</body>

</html>
