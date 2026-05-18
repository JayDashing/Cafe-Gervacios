<div class="flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden bg-panel-canvas" data-dashboard-seat-map="1"
    data-seat-click-mode="{{ $seatClickMode }}">

    @push('scripts')
        @vite(['resources/js/seating-layout.js', 'resources/js/dashboard-seat-map-waitlist.js'])
    @endpush

    @include('admin.partials.seating-map-inner-styles')

    <style>
        [data-dashboard-seat-map] {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .dsm-root {
            display: flex;
            flex-direction: column;
            min-height: 0;
            flex: 1;
            overflow: hidden;
            background: #fff;
        }

        /* One left cluster (title + legend) + right actions — no empty flex gap in the middle */
        .dsm-toolbar-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem 0.75rem;
            padding: 0.65rem 0.75rem 0.7rem;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
            flex-shrink: 0;
        }

        @media (min-width: 1024px) {
            .dsm-toolbar-strip {
                padding: 0.7rem 1rem 0.75rem;
                gap: 0.5rem 1rem;
            }
        }

        .dsm-toolbar-left {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem 0.75rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .dsm-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            line-height: 1.15;
            flex-shrink: 0;
        }

        .dsm-status-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
        }

        .dsm-status-pill {
            display: inline-flex;
            min-height: 2rem;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            border: 1px solid transparent;
            padding: 0.25rem 0.65rem;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .dsm-status-pill__count {
            display: inline-flex;
            min-height: 1.35rem;
            min-width: 1.35rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            padding: 0 0.25rem;
            font-variant-numeric: tabular-nums;
        }

        .dsm-status-pill--free {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #065f46;
        }

        .dsm-status-pill--reserved {
            border-color: #fde68a;
            background: #fffbeb;
            color: #92400e;
        }

        .dsm-status-pill--occupied {
            border-color: #fecdd3;
            background: #fff1f2;
            color: #9f1239;
        }

        .dsm-status-pill--cleaning {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1e40af;
        }

        @media (max-width: 639px) {
            .dsm-toolbar-left,
            .dsm-status-strip {
                width: 100%;
            }

            .dsm-status-pill {
                flex: 1 1 calc(50% - 0.35rem);
                justify-content: space-between;
            }
        }

        .dsm-toolbar-actions {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            gap: 0.35rem;
        }

        .dsm-mode-switch {
            display: inline-flex;
            min-height: 2rem;
            align-items: center;
            overflow: hidden;
            border: 1px solid #d8dee8;
            border-radius: 0.65rem;
            background: #f8fafc;
        }

        .dsm-mode-switch button {
            display: inline-flex;
            min-height: 2rem;
            align-items: center;
            justify-content: center;
            border: 0;
            border-right: 1px solid #d8dee8;
            background: transparent;
            padding: 0 0.65rem;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .dsm-mode-switch button:last-child {
            border-right: 0;
        }

        .dsm-mode-switch button:hover {
            background: #eef2f7;
            color: #0f172a;
        }

        .dsm-mode-switch button.is-active {
            background: #0f172a;
            color: #fff;
        }

        .dsm-info-popover {
            display: none;
            position: relative;
        }

        .dsm-info-popover-panel {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            z-index: 60;
            width: min(22rem, calc(100vw - 2rem));
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 10px 25px -5px rgb(15 23 42 / 0.12);
            font-size: 12px;
            line-height: 1.5;
            color: #64748b;
        }

        .dsm-info-popover-panel kbd {
            display: inline-block;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 1px 5px;
            font-family: ui-monospace, monospace;
            font-size: 10px;
            color: #334155;
        }

        .dsm-info-popover-panel strong {
            color: #0f172a;
        }

        .dsm-quick-actions {
            flex-shrink: 0;
            padding: 0.45rem 0.75rem 0.55rem;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        @media (min-width: 1024px) {
            .dsm-quick-actions {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        /* Tight horizontal padding so the floor plan + scrollbar sit flush; less “dead” white on the right */
        .dsm-map-scroll {
            flex: 1 1 0%;
            min-height: 0;
            min-width: 0;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 0.35rem 0.35rem 0.75rem;
            background: #fff;
        }

        @media (min-width: 1024px) {
            .dsm-map-scroll {
                padding: 0.45rem 0.5rem 1rem;
            }
        }

        .seating-seat-dot.is-waitlist-seat-at::after {
            box-shadow:
                0 0 0 3px rgba(26, 34, 50, 0.8),
                0 2px 8px rgba(26, 34, 50, 0.2),
                0 0 0 1px rgba(26, 34, 50, 0.06);
            z-index: 2;
        }

        .seating-seat-dot.is-table-ops-selected::after {
            box-shadow:
                0 0 0 3px rgba(26, 34, 50, 0.9),
                0 2px 8px rgba(26, 34, 50, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            z-index: 3;
        }

        [data-dashboard-seat-map] .seating-map-stage img.seating-floorplan-img {
            opacity: 0.42;
            filter: grayscale(1) contrast(1.08);
        }

        [data-dashboard-seat-map] .seating-seat-dot {
            opacity: 0;
            pointer-events: none;
        }

        [data-dashboard-seat-map] [data-seating-group] {
            cursor: pointer;
            pointer-events: auto;
        }

        [data-dashboard-seat-map] .seating-group-shell {
            pointer-events: auto;
        }

        [data-dashboard-seat-map] .seating-group-shell__fill {
            border-width: 2px;
            border-style: solid;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.1);
        }

        [data-dashboard-seat-map] .seating-group-shell--free .seating-group-shell__fill {
            border-color: rgba(22, 163, 74, 0.48);
            background: rgba(34, 197, 94, 0.12);
        }

        [data-dashboard-seat-map] .seating-group-shell--reserved .seating-group-shell__fill {
            border-color: rgba(217, 119, 6, 0.52);
            background: rgba(245, 158, 11, 0.14);
        }

        [data-dashboard-seat-map] .seating-group-shell--occupied .seating-group-shell__fill {
            border-color: rgba(225, 29, 72, 0.5);
            background: rgba(244, 63, 94, 0.14);
        }

        [data-dashboard-seat-map] .seating-group-shell--cleaning .seating-group-shell__fill {
            border-color: rgba(37, 99, 235, 0.52);
            background: rgba(59, 130, 246, 0.14);
        }

        [data-dashboard-seat-map] .seating-tbl-label {
            transform: translate(-50%, -50%);
            pointer-events: auto;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge-card {
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            background: rgba(255, 255, 255, 0.96);
            box-shadow:
                0 8px 20px rgba(15, 23, 42, 0.18),
                0 0 0 1px rgba(255, 255, 255, 0.74) inset;
            pointer-events: auto;
            transition: transform 150ms ease, box-shadow 150ms ease, filter 150ms ease;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge-inner {
            flex-direction: row;
            align-items: center;
            gap: 0.42rem;
            padding: 0.42rem 0.68rem;
            line-height: 1;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge-label {
            margin: 0;
            color: #0f172a;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0;
            text-shadow: none;
            white-space: nowrap;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge-status {
            display: inline-flex;
            order: -1;
            width: 0.56rem;
            height: 0.56rem;
            flex: 0 0 0.56rem;
            overflow: hidden;
            border-radius: 999px;
            color: transparent;
            font-size: 0;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--free {
            border-color: #86efac;
            background: #ecfdf5;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--free .seating-badge-status {
            background: #16a34a;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--reserved {
            border-color: #fcd34d;
            background: #fffbeb;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--reserved .seating-badge-status {
            background: #d97706;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--occupied {
            border-color: #fda4af;
            background: #fff1f2;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--occupied .seating-badge-status {
            background: #e11d48;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--cleaning {
            border-color: #93c5fd;
            background: #eff6ff;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-badge--cleaning .seating-badge-status {
            background: #2563eb;
        }

        [data-dashboard-seat-map] [data-seating-layout] .seating-merge-tag {
            display: none;
        }

        [data-dashboard-seat-map] [data-seating-group]:hover .seating-badge-card {
            transform: translateY(-2px);
            filter: saturate(1.06);
            box-shadow:
                0 14px 30px rgba(15, 23, 42, 0.24),
                0 0 0 1px rgba(255, 255, 255, 0.8) inset;
        }
    </style>

    <div class="dsm-root">

        <div class="dsm-toolbar-strip">
            <div class="dsm-toolbar-left">
                <h3 class="dsm-title">Floor Map</h3>

                <div class="dsm-status-strip" role="group" aria-label="Table status counts">
                    <span class="dsm-status-pill dsm-status-pill--free"
                        aria-label="Free tables on map: {{ $tableStatusCounts['available'] ?? 0 }}">
                        <span>Free</span>
                        <span class="dsm-status-pill__count">{{ $tableStatusCounts['available'] ?? 0 }}</span>
                    </span>
                    <span class="dsm-status-pill dsm-status-pill--reserved"
                        aria-label="Reserved tables on map: {{ $tableStatusCounts['reserved'] ?? 0 }}">
                        <span>Reserved</span>
                        <span class="dsm-status-pill__count">{{ $tableStatusCounts['reserved'] ?? 0 }}</span>
                    </span>
                    <span class="dsm-status-pill dsm-status-pill--occupied"
                        aria-label="Occupied tables on map: {{ $tableStatusCounts['occupied'] ?? 0 }}">
                        <span>Occupied</span>
                        <span class="dsm-status-pill__count">{{ $tableStatusCounts['occupied'] ?? 0 }}</span>
                    </span>
                    <span class="dsm-status-pill dsm-status-pill--cleaning"
                        aria-label="Cleaning tables on map: {{ $tableStatusCounts['cleaning'] ?? 0 }}">
                        <span>Cleaning</span>
                        <span class="dsm-status-pill__count">{{ $tableStatusCounts['cleaning'] ?? 0 }}</span>
                    </span>
                </div>
            </div>

            <div class="dsm-toolbar-actions">
                <div class="dsm-mode-switch" role="group" aria-label="Floor map click mode">
                    <button type="button" wire:click="setSeatClickMode('edit')"
                        class="{{ $seatClickMode === 'edit' ? 'is-active' : '' }}"
                        aria-pressed="{{ $seatClickMode === 'edit' ? 'true' : 'false' }}">
                        Edit
                    </button>
                    <button type="button" wire:click="setSeatClickMode('waitlist')"
                        class="{{ $seatClickMode === 'waitlist' ? 'is-active' : '' }}"
                        aria-pressed="{{ $seatClickMode === 'waitlist' ? 'true' : 'false' }}">
                        Waitlist
                    </button>
                    <button type="button" wire:click="setSeatClickMode('table')"
                        class="{{ $seatClickMode === 'table' ? 'is-active' : '' }}"
                        aria-pressed="{{ $seatClickMode === 'table' ? 'true' : 'false' }}">
                        Table
                    </button>
                </div>
                @unless (request()->routeIs('admin.tables'))
                    @include('admin.partials.seat-focus-mode-button')
                @endunless
                <details class="dsm-info-popover">
                    <summary
                        class="inline-flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors hover:bg-slate-50 hover:border-slate-300 hover:text-slate-600 [&::-webkit-details-marker]:hidden"
                        aria-label="How to use"><i class="fa-solid fa-circle-info text-[13px]" aria-hidden="true"></i></summary>
                    <div class="dsm-info-popover-panel">
                        <p class="m-0 mb-2"><strong>Floor Map</strong> — Tap a dot to select a table; use the status bar below
                            to update status or furniture.</p>
                        <p class="m-0 mb-2 text-[11px] leading-snug text-slate-500">Dots are seat anchors on your floor
                            plan.</p>
                        <p class="m-0 mb-2"><kbd>Alt</kbd>+click a seat to assign <strong>waitlist</strong> seating to that
                            table.</p>
                        @if (auth()->user()->isAdmin())
                            <p class="m-0 text-[11px] text-slate-500">Upload, add seats, and layout tools are in
                                <strong>Edit Blueprint</strong>.</p>
                        @endif
                    </div>
                </details>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.seating-layout') }}"
                        class="inline-flex items-center gap-1.5 rounded-full bg-panel-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-panel-primary-hover">
                        Edit Blueprint
                        <i class="fa-solid fa-arrow-right text-[10px] opacity-90" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="dsm-quick-actions">
            @livewire('admin.table-quick-actions')
        </div>

        <div class="dsm-map-scroll">
            @include('admin.partials.seating-map-inner', [
                'dashboardEmbed' => true,
                'waitlistTablePick' => true,
                'showToolbar' => false,
                'enableGrouping' => false,
            ])
        </div>
    </div>
</div>
