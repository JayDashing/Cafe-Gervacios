{{-- Admin sidebar — included from layouts.admin --}}
<aside id="admin-sidebar" class="admin-sidebar flex shrink-0 flex-col border-b border-panel-chrome bg-panel-primary z-40 min-h-0
           transition-[width] duration-300 ease-out
           md:h-full md:max-h-full md:border-b-0 md:border-r md:border-panel-chrome
           md:shadow-[2px_0_16px_rgba(0,0,0,0.25)]">

    <style>
        /* Admin sidebar — primary dark chrome, light text (matches app header) */

        /* ── Sidebar shell ── */
        .admin-sidebar {
            font-family: inherit;
            background: #0f172a;
            width: 256px;
            transition: width 0.3s ease-out;
        }

        .admin-sidebar[data-collapsed="true"] {
            width: 64px;
        }

        /* ── Section labels ── */
        .admin-sidebar-section-label {
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #64748b;
            padding: 0 12px;
            margin-bottom: 4px;
            margin-top: 12px;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.15s, height 0.2s, margin 0.2s, padding 0.2s;
        }

        .admin-sidebar-section-label:first-child {
            margin-top: 0;
        }

        .admin-sidebar[data-collapsed="true"] .admin-sidebar-section-label {
            opacity: 0;
            height: 0;
            margin: 6px 0 2px;
            padding: 0;
            pointer-events: none;
        }

        /* ── Nav items ── */
        .tc-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            color: #e2e8f0;
            border-left: 2px solid transparent;
            transition: background 0.15s, color 0.15s, border-color 0.15s, padding 0.2s, justify-content 0.2s;
            text-decoration: none;
            position: relative;
            white-space: nowrap;
            overflow: hidden;
        }

        .tc-nav-link:hover {
            background: #1e293b;
            color: #f8fafc;
        }

        .tc-nav-link:hover .tc-nav-icon {
            color: #f8fafc;
        }

        .admin-sidebar[data-collapsed="true"] .tc-nav-link {
            justify-content: center;
            min-height: 46px;
            padding: 13px 12px;
            gap: 0;
        }

        /* ── Nav icon ── */
        .tc-nav-icon {
            width: 16px;
            min-width: 16px;
            text-align: center;
            font-size: 14px;
            color: #94a3b8;
            transition: color 0.15s;
            flex-shrink: 0;
        }

        /* ── Nav text ── */
        .admin-sidebar-nav-text {
            transition: opacity 0.15s;
            overflow: hidden;
            white-space: nowrap;
        }

        .admin-sidebar[data-collapsed="true"] .admin-sidebar-nav-text {
            opacity: 0;
            width: 0;
            pointer-events: none;
        }

        /* ── Active nav item — light rail on dark chrome ── */
        .tc-nav-active {
            background: #1e293b !important;
            color: #ffffff !important;
            border-left: 2px solid rgba(248, 250, 252, 0.55) !important;
            font-weight: 600 !important;
        }

        .tc-nav-active .tc-nav-icon {
            color: #f8fafc !important;
        }

        /* ── Tooltips (collapsed only) ── */
        .admin-sidebar[data-collapsed="true"] .tc-nav-link::after,
        .admin-sidebar[data-collapsed="true"] .admin-sidebar-logout-btn::after {
            content: attr(title);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: #1e293b;
            color: #f8fafc;
            font-size: 12px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 7px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s;
            z-index: 200;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .admin-sidebar[data-collapsed="true"] .tc-nav-link:hover::after,
        .admin-sidebar[data-collapsed="true"] .admin-sidebar-logout-btn:hover::after {
            opacity: 1;
        }

        /* ── Settings row ── */
        .settings-row {
            border-radius: 10px;
            overflow: visible;
        }

        .settings-row-inner {
            display: flex;
            align-items: stretch;
            border-radius: 10px;
            overflow: hidden;
        }

        .settings-row a.settings-main-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            padding: 10px 12px;
            font-size: 14px;
            font-weight: 500;
            color: #e2e8f0;
            text-decoration: none;
            flex: 1;
            min-width: 0;
            transition: color 0.15s, padding 0.2s;
            white-space: nowrap;
            overflow: hidden;
        }

        .settings-row a.settings-main-link:hover {
            color: #f8fafc;
        }

        .settings-row.tc-nav-active a.settings-main-link {
            color: #ffffff;
            font-weight: 600;
        }

        .admin-sidebar[data-collapsed="true"] .settings-row a.settings-main-link {
            justify-content: center;
            min-height: 46px;
            padding: 13px 12px;
            gap: 0;
        }

        /* ── Settings chevron ── */
        #admin-settings-jump-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            align-self: stretch;
            padding: 0 10px;
            margin: 4px 4px 4px 0;
            border-radius: 8px;
            color: #94a3b8;
            cursor: pointer;
            background: transparent;
            border: none;
            transition: background 0.15s, color 0.15s, opacity 0.15s, width 0.2s, padding 0.2s, margin 0.2s;
            overflow: hidden;
        }

        #admin-settings-jump-toggle:hover {
            background: rgba(15, 23, 42, 0.5);
            color: #f8fafc;
        }

        .tc-nav-active #admin-settings-jump-toggle {
            color: #cbd5e1;
        }

        .admin-sidebar[data-collapsed="true"] #admin-settings-jump-toggle {
            opacity: 0;
            width: 0;
            padding: 0;
            margin: 0;
            pointer-events: none;
        }

        /* ── Sub-nav ── */
        .tc-sidebar-subnav-line {
            border-left: 2px solid rgba(248, 250, 252, 0.12);
            margin-left: 20px;
        }

        .settings-jump-link {
            display: block;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 12.5px;
            font-weight: 500;
            color: #94a3b8;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }

        .settings-jump-link:hover {
            background: rgba(30, 41, 59, 0.6);
            color: #f8fafc;
        }

        .tc-settings-jump-active {
            background: rgba(30, 41, 59, 0.85);
            color: #ffffff;
            font-weight: 600;
        }

        .admin-sidebar[data-collapsed="true"] #admin-settings-jump {
            display: none !important;
        }

        /* ── Logout ── */
        .admin-sidebar-logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            color: #cbd5e1;
            background: transparent;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
            transition: background 0.15s, color 0.15s, padding 0.2s, justify-content 0.2s;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
        }

        .admin-sidebar-logout-btn:hover {
            background: rgba(225, 29, 72, 0.12);
            color: #fda4af;
        }

        .admin-sidebar-logout-btn:hover .tc-nav-icon {
            color: #fda4af;
        }

        .admin-sidebar[data-collapsed="true"] .admin-sidebar-logout-btn {
            justify-content: center;
            min-height: 46px;
            padding: 13px 12px;
            gap: 0;
        }

        /* ── Scrollbar ── */
        #admin-sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        #admin-sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        #admin-sidebar-nav::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 99px;
        }

        #admin-sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
    </style>

    {{-- Navigation (brand + menu toggle live in layouts.partials.admin-header) --}}
    <nav id="admin-sidebar-nav" class="flex-1 space-y-1.5 overflow-y-auto px-2.5 pb-3 pt-3" aria-label="Main">

        <p class="admin-sidebar-section-label text-[11px]">Main Menu</p>

        @php
            $item = function (string|array $routePattern) {
                $patterns = is_array($routePattern) ? $routePattern : [$routePattern];
                $active = request()->routeIs(...$patterns);
                return [$active ? 'tc-nav-link tc-nav-active' : 'tc-nav-link', $active];
            };
        @endphp

        @php $isAdminUser = auth()->user()->isAdmin(); @endphp

        @if ($isAdminUser)
            @php [$c] = $item('admin.dashboard'); @endphp
            <a href="{{ route('admin.dashboard') }}" class="{{ $c }}" title="Dashboard" aria-current="{{ request()->routeIs('admin.dashboard') ? 'page' : 'false' }}">
                <i class="fa-solid fa-house tc-nav-icon"></i>
                <span class="admin-sidebar-nav-text">Dashboard</span>
            </a>
        @endif

        @php [$c] = $item('admin.waitlist'); @endphp
        <a href="{{ route('admin.waitlist') }}" class="{{ $c }}" title="Waitlist Management" aria-current="{{ request()->routeIs('admin.waitlist') ? 'page' : 'false' }}">
            <i class="fa-solid fa-users-line tc-nav-icon"></i>
            <span class="admin-sidebar-nav-text">Waitlist Management</span>
        </a>

        @php [$c] = $item(['admin.tables', 'admin.seating-layout', 'admin.bookings']); @endphp
        <a href="{{ route('admin.tables') }}" class="{{ $c }}" title="Floor Map Management" aria-current="{{ request()->routeIs('admin.tables', 'admin.seating-layout', 'admin.bookings') ? 'page' : 'false' }}">
            <i class="fa-solid fa-table-cells tc-nav-icon"></i>
            <span class="admin-sidebar-nav-text">Floor Map Management</span>
        </a>

        @php [$c] = $item('staff.queue'); @endphp
        <a href="{{ route('staff.queue') }}" class="{{ $c }}" title="Priority Queue" aria-current="{{ request()->routeIs('staff.queue') ? 'page' : 'false' }}">
            <i class="fa-solid fa-clipboard-list tc-nav-icon"></i>
            <span class="admin-sidebar-nav-text">Priority Queue</span>
        </a>

        @if ($isAdminUser)
            @php [$c] = $item('admin.seating-analytics'); @endphp
            <a href="{{ route('admin.seating-analytics') }}" class="{{ $c }}" title="Reports & Analytics" aria-current="{{ request()->routeIs('admin.seating-analytics') ? 'page' : 'false' }}">
                <i class="fa-solid fa-chart-line tc-nav-icon"></i>
                <span class="admin-sidebar-nav-text">Reports & Analytics</span>
            </a>

            @php [$c] = $item('admin.system-logs'); @endphp
            <a href="{{ route('admin.system-logs') }}" class="{{ $c }}" title="System Logs" aria-current="{{ request()->routeIs('admin.system-logs') ? 'page' : 'false' }}">
                <i class="fa-solid fa-clipboard-list tc-nav-icon"></i>
                <span class="admin-sidebar-nav-text">System Logs</span>
            </a>
        @endif

        <p class="admin-sidebar-section-label text-[11px]">System</p>

        @if ($isAdminUser)
            @php
                $settingsActive = request()->routeIs('admin.settings', 'admin.2fa.*', 'admin.password.change');
                $settingsBase = route('admin.settings');
                $settingsModalUrl = fn(string $modal) => route('admin.settings', ['modal' => $modal]);
            @endphp

            <div class="settings-row {{ $settingsActive ? 'tc-nav-active' : '' }}"
                style="border-left: 2px solid {{ $settingsActive ? 'rgba(248,250,252,0.55)' : 'transparent' }}; border-radius: 10px;">
                <div class="settings-row-inner">
                    <a href="{{ $settingsBase }}" class="settings-main-link" title="Settings" aria-current="{{ $settingsActive ? 'page' : 'false' }}">
                        <i class="fa-solid fa-gear tc-nav-icon"></i>
                        <span class="admin-sidebar-nav-text truncate">Settings</span>
                    </a>
                    <button type="button" id="admin-settings-jump-toggle" aria-expanded="true"
                        aria-controls="admin-settings-jump-list" aria-label="Toggle Settings sections">
                        <i id="admin-settings-jump-chevron" class="fa-solid fa-chevron-up text-[10px]"
                            aria-hidden="true"></i>
                    </button>
                </div>
                <div id="admin-settings-jump" class="mt-0.5 ml-1 tc-sidebar-subnav-line pl-3">
                    <ul id="admin-settings-jump-list" class="space-y-0.5 pb-1" role="list">
                        <li><a href="{{ $settingsModalUrl('devices') }}" data-modal="devices"
                                class="settings-jump-link">Automation</a></li>
                        <li><a href="{{ $settingsModalUrl('paymongo') }}" data-modal="paymongo"
                                class="settings-jump-link">Online payments</a></li>
                        <li><a href="{{ $settingsModalUrl('semaphore') }}" data-modal="semaphore"
                                class="settings-jump-link">Text messages</a></li>
                        <li><a href="{{ $settingsModalUrl('facebook') }}" data-modal="facebook"
                                class="settings-jump-link">Facebook</a></li>
                        <li><a href="{{ $settingsModalUrl('qr') }}" data-modal="qr" class="settings-jump-link">QR code</a>
                        </li>
                    </ul>
                </div>
            </div>

            <script>
                (function () {
                    /* ── Settings sub-nav expand/collapse ── */
                    var key = 'admin_settings_jump_expanded';
                    var toggle = document.getElementById('admin-settings-jump-toggle');
                    var wrap = document.getElementById('admin-settings-jump');
                    var chev = document.getElementById('admin-settings-jump-chevron');
                    if (!toggle || !wrap || !chev) return;

                    function applySubnav(expanded) {
                        wrap.classList.toggle('hidden', !expanded);
                        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                        chev.classList.remove('fa-chevron-up', 'fa-chevron-down');
                        chev.classList.add(expanded ? 'fa-chevron-up' : 'fa-chevron-down');
                        localStorage.setItem(key, expanded ? '1' : '0');
                    }

                    var stored = localStorage.getItem(key);
                    var expanded = stored === null ? true : stored === '1';
                    applySubnav(expanded);

                    toggle.addEventListener('click', function (e) {
                        e.preventDefault();
                        applySubnav(wrap.classList.contains('hidden'));
                    });

                    function syncJumpActive() {
                        var modal = '';
                        try { modal = new URLSearchParams(window.location.search).get('modal') || ''; } catch (e) { }
                        document.querySelectorAll('.settings-jump-link').forEach(function (el) {
                            el.classList.toggle('tc-settings-jump-active', el.getAttribute('data-modal') === modal);
                        });
                    }
                    syncJumpActive();
                    window.addEventListener('popstate', syncJumpActive);
                })();
            </script>
        @else
            @php [$c] = $item('staff.profile'); @endphp
            <a href="{{ route('staff.profile') }}" class="{{ $c }}" title="Settings" aria-current="{{ request()->routeIs('staff.profile') ? 'page' : 'false' }}">
                <i class="fa-solid fa-gear tc-nav-icon"></i>
                <span class="admin-sidebar-nav-text">Settings</span>
            </a>
        @endif
    </nav>

    {{-- Footer --}}
    <div class="admin-sidebar-footer mt-auto shrink-0 px-3 py-2.5"
        style="border-top: 1px solid rgba(248, 250, 252, 0.1); background: #0f172a;">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="admin-sidebar-logout-btn" title="Logout">
                <i class="fa-solid fa-right-from-bracket tc-nav-icon"></i>
                <span class="admin-sidebar-nav-text">Logout</span>
            </button>
        </form>
    </div>

    {{-- ── Sidebar collapse / expand JS ───────────────────────────────── --}}
    <script>
        (function () {
            var STORAGE_KEY = 'admin_sidebar_collapsed';
            var sidebar = document.getElementById('admin-sidebar');
            var toggleBtn = document.getElementById('admin-sidebar-toggle');
            if (!sidebar || !toggleBtn) return;

            function applyCollapsed(collapsed) {
                sidebar.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
                toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
            }

            /* Restore saved state on load */
            var saved = localStorage.getItem(STORAGE_KEY);
            applyCollapsed(saved === '1'); /* default: expanded */

            toggleBtn.addEventListener('click', function () {
                applyCollapsed(sidebar.getAttribute('data-collapsed') !== 'true');
            });
        })();
    </script>
</aside>
