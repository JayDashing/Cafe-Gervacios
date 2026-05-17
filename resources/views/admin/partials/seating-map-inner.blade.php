    @php
        $dashboardEmbed = $dashboardEmbed ?? false;
        $waitlistTablePick = $waitlistTablePick ?? $dashboardEmbed;
        $fullEditor = $fullEditor ?? false;
        $showToolbar = $showToolbar ?? true;
        $enableGrouping = $enableGrouping ?? true;
        $normalizeTableStatus = fn($status) => match ($status) {
            'reserved' => 'reserved',
            'occupied' => 'occupied',
            'cleaning' => 'cleaning',
            default => 'free',
        };
        $statusClass = fn($status) => match ($status) {
            'available' => 'available',
            'free' => 'available',
            'reserved' => 'reserved',
            'occupied' => 'occupied',
            'cleaning' => 'cleaning',
            default => 'available',
        };
        $statusLabel = fn(string $s) => match ($s) {
            'occupied' => 'Occupied',
            'reserved' => 'Reserved',
            'cleaning' => 'Cleaning',
            default => 'Free',
        };
        $tableBadgeLabel = function ($table) use ($dashboardEmbed): string {
            $seatText = (int) $table->capacity === 1 ? '1 seat' : (int) $table->capacity . ' seats';

            return $dashboardEmbed ? $table->label . ' (' . $seatText . ')' : $seatText;
        };
        $floorplanRelative = \App\Models\Setting::get('floorplan_image', 'images/floorplan.png');
        $floorplanPath = public_path($floorplanRelative);
        $hasFloorplan = is_file($floorplanPath);
        $floorplanUrl = $hasFloorplan ? asset($floorplanRelative) . '?v=' . filemtime($floorplanPath) : '';
        $seatCounts = $allSeats->groupBy('table_id')->map(fn($g) => $g->count());
        $layoutEditApis = auth()->user()?->isAdmin() ?? false;
        $floorTableGuestInfo = $floorTableGuestInfo ?? collect();
    @endphp

    <div class="@if ($dashboardEmbed) w-full max-w-none seating-layout--embed @elseif ($fullEditor) seating-layout--full-editor flex min-h-0 min-w-0 w-full max-w-none flex-1 flex-col overflow-hidden bg-panel-canvas @else mx-auto max-w-5xl @endif"
        data-seating-layout
        data-api-seats="{{ route('admin.api.seats') }}" data-api-update="{{ route('admin.api.seats.update') }}"
        data-api-group="{{ $layoutEditApis && $enableGrouping ? route('admin.api.seats.group') : '' }}" data-api-place="{{ $layoutEditApis ? route('admin.api.seats.place') : '' }}"
        data-api-unmerge="{{ $layoutEditApis && $enableGrouping ? route('admin.api.seats.unmerge') : '' }}"
        data-api-delete="{{ $layoutEditApis ? route('admin.api.seats.delete') : '' }}"
        data-waitlist-table-pick="{{ $waitlistTablePick ? 'true' : 'false' }}">
        @if ($fullEditor)
            <div class="sle-full-editor-frame flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <div class="sle-legend-strip">
                    <div class="sle-legend-strip__inner">
                        <div class="sle-legend-strip__main">
                            <h3 class="sle-title">Seating layout</h3>
                            <div class="sle-legend-inline sle-legend-inline--above-map" role="group" aria-label="Seat status legend">
                                <span class="text-[11px] font-semibold text-slate-500">Status</span>
                                <span class="seating-legend-pill seating-legend-pill--free">Free</span>
                                <span class="seating-legend-pill seating-legend-pill--reserved">Reserved</span>
                                <span class="seating-legend-pill seating-legend-pill--occupied">Occupied</span>
                            </div>
                        </div>
                        <div class="sle-toolbar-actions sle-toolbar-actions--legend-top flex shrink-0 items-center gap-2">
                            @include('admin.partials.seat-focus-mode-button')
                            <details class="sle-info-popover">
                                <summary
                                    class="inline-flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors hover:bg-slate-50 hover:border-slate-300 hover:text-slate-600 [&::-webkit-details-marker]:hidden"
                                    aria-label="How to use"><i class="fa-solid fa-circle-info text-[13px]" aria-hidden="true"></i></summary>
                                <div class="sle-info-popover-panel">
                                    <p class="m-0 mb-2"><strong>Blueprint editor</strong> — Upload a floor plan, then use <strong>Add
                                            marker</strong> to place table markers. Daily merge/unmerge is handled from the
                                        Floor Map page.</p>
                                    <p class="m-0 mb-2 text-[11px] leading-snug text-slate-500">Cards show live table status · dots
                                        are seat anchors on the image.</p>
                                    <p class="m-0 mb-2"><strong>Touch:</strong> long-press a marker for selection mode.</p>
                                    <p class="m-0 text-[11px] text-slate-500">Use <strong>Floor Map</strong> for waitlist pick and
                                        quick status changes.</p>
                                </div>
                            </details>
                            <a href="{{ route('admin.tables') }}"
                                class="inline-flex items-center gap-1.5 rounded-full bg-panel-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-panel-primary-hover">
                                Floor Map
                                <i class="fa-solid fa-arrow-right text-[10px] opacity-90" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="sle-editor-split flex min-h-0 flex-1 flex-col overflow-hidden md:flex-row">
                    <div class="sle-map-scroll">
        @endif

        @if (!$fullEditor && $showToolbar)
            @include('admin.partials.seating-map-toolbar', [
                'compact' => false,
                'showQuickHelp' => true,
                'enableGrouping' => $enableGrouping,
            ])
        @endif

        @if ($enableGrouping)
            <p id="seating-selection-mode-hint"
                class="mb-2 hidden rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-[12px] font-medium leading-snug text-sky-950">
                Selection on — tap markers; one tap toggles a whole dashed group. <kbd class="rounded bg-white px-1">G</kbd> =
                merge when 2+ selected.
            </p>
        @endif

        <p id="seating-placement-hint"
            class="mb-2 hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[12px] font-medium text-emerald-950">
            Tap the map to place — <kbd class="rounded bg-white px-1">Esc</kbd> or Done to finish.
        </p>

        <div id="seating-layout-error"
            class="mb-2 hidden rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-[12px] font-semibold leading-snug text-rose-950"
            role="alert">
            <div class="flex items-start gap-2">
                <i class="fa-solid fa-triangle-exclamation mt-0.5 text-rose-600" aria-hidden="true"></i>
                <div class="min-w-0 flex-1">
                    <div class="uppercase tracking-wide text-rose-700">Action blocked</div>
                    <div id="seating-layout-error-message" class="mt-0.5 font-medium text-rose-950"></div>
                </div>
                <button type="button" id="seating-layout-error-dismiss"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-rose-700 transition hover:bg-rose-100"
                    aria-label="Dismiss floor map error">
                    <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div
            class="seating-canvas-wrap {{ $dashboardEmbed || $fullEditor ? 'seating-canvas-wrap--embed' : '' }} {{ !$hasFloorplan || $tableGroups->isEmpty() ? 'min-h-[200px]' : '' }} {{ !$hasFloorplan ? 'flex flex-col items-center justify-center gap-2' : '' }}">
            @unless ($hasFloorplan)
                <p class="max-w-md px-4 text-center text-sm text-slate-500">No blueprint file yet. Choose a PNG or JPG
                    above — it becomes the background for the seating map (like uploading in
                    <code class="rounded bg-slate-100 px-1 text-xs">seat_label_merge_tool.html</code>).
                </p>
            @else
                <div class="seating-map-stack flex min-w-0 flex-col">
                    @if ($allSeats->isNotEmpty() && !($dashboardEmbed ?? false) && !($fullEditor ?? false))
                        <div
                            class="seating-status-legend seating-status-legend--in-card flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-0 border-b border-slate-200/80 bg-white/98 px-3 py-2.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[11px] font-semibold text-slate-500">Status</span>
                                <span class="seating-legend-pill seating-legend-pill--free">Free</span>
                                <span class="seating-legend-pill seating-legend-pill--reserved">Reserved</span>
                                <span class="seating-legend-pill seating-legend-pill--occupied">Occupied</span>
                            </div>
                            <span class="max-w-full text-[11px] leading-snug text-slate-500 sm:text-right">Cards = live table
                                status · dots = seat anchors</span>
                        </div>
                    @endif
                    <div class="seating-map-stack__stage-row">
                        <div id="seating-map-stage" class="seating-map-stage" style="position: relative; display: inline-block; overflow: visible;">
                            <img src="{{ $floorplanUrl }}" alt="" class="seating-floorplan-img select-none" draggable="false" style="display: block; width: 100%; height: auto;" />

                            <div id="seating-marquee-rect" class="seating-marquee-rect" aria-hidden="true"></div>

                            @foreach ($tableGroups as $group)
                                @php
                                    $t = $group->table;
                                    $merged = str_contains($t->label, ' + ');
                                    $seatN = $group->seats->count();
                                    $b = $group->bounds;
                                    $agg = $normalizeTableStatus($t->status ?? 'available');
                                    $tg = $floorTableGuestInfo->get($t->id, [
                                        'guest' => '—',
                                        'party' => (string) max(1, (int) $t->capacity),
                                    ]);
                                    $cardTooltip = $t->label . ', ' . $statusLabel($agg) . ', ' . $tg['guest'] . ', ' . $tg['party'];
                                @endphp
                                @if ($seatN > 1)
                                    <div class="seating-group-shell seating-group-shell--{{ $agg }}" data-seating-group data-table-id="{{ $t->id }}"
                                        style="left: {{ $b->left }}%; top: {{ $b->top }}%; width: {{ $b->w }}%; height: {{ $b->h }}%;"
                                        title="{{ $cardTooltip }} (merged group)">
                                        <div class="seating-group-shell__fill" aria-hidden="true"></div>
                                        <div class="seating-group-shell__label">
                                            <div class="seating-badge-card seating-badge-stack seating-badge--{{ $agg }}">
                                                <div class="seating-badge-inner">
                                                    <span class="seating-badge-label">{{ $tableBadgeLabel($t) }}</span>
                                                    <x-status-badge :status="$agg" size="xs" class="seating-status-chip" />
                                                    <span class="seating-badge-guest">{{ $tg['guest'] }}</span>
                                                    <span class="seating-badge-party"><span class="seating-badge-party-label">Party</span>
                                                        <span class="seating-badge-party-value">{{ $tg['party'] }}</span></span>
                                                </div>
                                            </div>
                                            <span class="seating-merge-tag">Merged group</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="seating-tbl-label seating-tbl-label--{{ $agg }}" data-seating-group data-table-id="{{ $t->id }}"
                                        style="left: {{ $group->anchor_x }}%; top: {{ $group->anchor_y }}%;"
                                        title="{{ $cardTooltip }}">
                                        <div class="seating-badge-card seating-badge-stack seating-badge--{{ $agg }}">
                                            <div class="seating-badge-inner">
                                                <span class="seating-badge-label">{{ $tableBadgeLabel($t) }}</span>
                                                <x-status-badge :status="$agg" size="xs" class="seating-status-chip" />
                                                <span class="seating-badge-guest">{{ $tg['guest'] }}</span>
                                                <span class="seating-badge-party"><span class="seating-badge-party-label">Party</span>
                                                    <span class="seating-badge-party-value">{{ $tg['party'] }}</span></span>
                                            </div>
                                        </div>
                                        @if ($merged)
                                            <div class="seating-merge-tag">Merged</div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach

                            @foreach ($allSeats as $seat)
                                @php
                                    $visualStatus = ($dashboardEmbed ?? false)
                                        ? $normalizeTableStatus($seat->table->status ?? 'available')
                                        : $seat->status;
                                @endphp
                                <button type="button"
                                    class="seating-seat-dot {{ $statusClass($visualStatus) }} {{ ($seatCounts[$seat->table_id] ?? 1) > 1 ? 'seating-seat-dot--grouped' : '' }}"
                                    style="left: {{ $seat->pos_x }}%; top: {{ $seat->pos_y }}%;" data-seat-id="{{ $seat->id }}"
                                    data-seat-index="{{ $seat->seat_index }}" data-status="{{ $seat->status }}"
                                    data-table-status="{{ $seat->table->status ?? $seat->status }}"
                                    data-label="{{ $seat->table->label }}"
                                    data-table-id="{{ $seat->table_id }}" data-table-label="{{ $seat->table->label }}"
                                    data-table-seat-count="{{ $seatCounts[$seat->table_id] ?? 1 }}"
                                    data-capacity="{{ (int) $seat->table->capacity }}"
                                    data-furniture-type="{{ $seat->table->furniture_type ?? 'standard' }}"
                                    title="{{ $seat->table->label }} seat {{ $seat->seat_index }}: {{ $statusLabel($visualStatus) }}"></button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endunless
        </div>

        @if ($enableGrouping)
            <div id="seating-selection-bar"
                class="seating-selection-bar--peak hidden mt-2 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800">
                <span id="seating-selection-count" class="min-w-0 flex-1 font-semibold leading-tight sm:flex-none"></span>
                <button type="button" id="seating-group-open"
                    class="min-h-[44px] rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                    disabled>
                    Group as table
                </button>
                <button type="button" id="seating-clear-selection"
                    class="min-h-[44px] rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-100">
                    Clear
                </button>
            </div>
        @endif

        @if ($hasFloorplan && $allSeats->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Floor plan is ready. No table markers yet — use <strong>Add marker</strong> on the
                map, or
                <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">php artisan db:seed --class=SeatSeeder</code> if you want
                a schematic grid.
            </p>
        @endif

        @if ($fullEditor)
                    </div>
                    <aside id="sle-tools-panel" class="sle-tools-panel" data-collapsed="false" aria-label="Layout tools">
                        <div class="sle-tools-panel__chrome">
                            <button type="button" id="sle-tools-panel-toggle" class="sle-tools-panel__toggle"
                                aria-expanded="true" aria-controls="sle-tools-panel-body" title="Collapse tools">
                                <i class="fa-solid fa-angles-right sle-tools-panel__toggle-icon text-[11px]" aria-hidden="true"></i>
                                <span class="sr-only">Toggle layout tools</span>
                            </button>
                        </div>
                        <div id="sle-tools-panel-body" class="sle-tools-panel__body">
                            @include('admin.partials.seating-map-toolbar', [
                                'compact' => true,
                                'showQuickHelp' => false,
                                'embed' => true,
                                'enableGrouping' => $enableGrouping,
                            ])
                        </div>
                        <div id="sle-tools-panel-rail" class="sle-tools-panel__rail" aria-hidden="true">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-slate-500 [writing-mode:vertical-rl] rotate-180">Tools</span>
                            <span
                                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-panel-primary text-white shadow-sm"
                                title="Layout tools">
                                <i class="fa-solid fa-wrench text-[10px]" aria-hidden="true"></i>
                            </span>
                        </div>
                    </aside>
                </div>
            </div>
        @endif
    </div>

    @if ($enableGrouping)
        <div id="group-table-modal"
            class="fixed inset-0 z-[998] hidden items-center justify-center bg-black/45 p-4 backdrop-blur-[1px]">
            <div class="tc-ios-card relative w-full max-w-[320px] rounded-[14px] p-6 text-slate-900 shadow-xl" role="dialog"
                aria-modal="true" aria-labelledby="group-table-title">
                <button type="button" id="group-table-modal-close"
                    class="absolute right-3 top-2.5 border-0 bg-transparent text-lg leading-none text-slate-400 hover:text-slate-700"
                    aria-label="Close">&times;</button>
                <h3 id="group-table-title" class="mb-1 text-[15px] font-medium">Merge into one table</h3>
                <p class="mb-3 text-xs text-slate-500">Markers stay on the map. Capacity is summed across merged tables (not
                    below dot count).</p>
                <label class="mb-1 block text-xs font-medium text-slate-600" for="group-table-label-input">Table label
                    (optional)</label>
                <input id="group-table-label-input" type="text" maxlength="50" placeholder="e.g. T16 (auto if empty)"
                    class="mb-4 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-200/60" />
                <div class="flex gap-2">
                    <button type="button" id="group-table-submit"
                        class="flex-1 rounded-lg bg-panel-primary px-3 py-2.5 text-[13px] font-semibold text-white hover:opacity-90">
                        Merge tables
                    </button>
                    <button type="button" id="group-table-cancel"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('admin.partials.seat-modal')

    <script>
        (function () {
            var k = 'tc_seat_editor_quick_help_v1';
            var el = document.getElementById('seating-quick-help');
            if (!el) return;
            try {
                if (localStorage.getItem(k) === '1') {
                    el.remove();
                    return;
                }
            } catch (e) {}
            document.getElementById('seating-quick-help-dismiss')?.addEventListener('click', function () {
                try {
                    localStorage.setItem(k, '1');
                } catch (e2) {}
                el.remove();
            });
        })();
    </script>
    <script>
        (function () {
            var KEY = 'tc_sle_tools_collapsed';
            var panel = document.getElementById('sle-tools-panel');
            var btn = document.getElementById('sle-tools-panel-toggle');
            var icon = btn && btn.querySelector('.sle-tools-panel__toggle-icon');
            if (!panel || !btn) return;

            function applyCollapsed(collapsed) {
                panel.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
                btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                btn.setAttribute('title', collapsed ? 'Expand tools' : 'Collapse tools');
                if (icon) {
                    icon.classList.toggle('fa-angles-right', !collapsed);
                    icon.classList.toggle('fa-angles-left', collapsed);
                }
                try {
                    localStorage.setItem(KEY, collapsed ? '1' : '0');
                } catch (e) {}
                requestAnimationFrame(function () {
                    window.dispatchEvent(new Event('resize'));
                });
            }

            btn.addEventListener('click', function () {
                applyCollapsed(panel.getAttribute('data-collapsed') !== 'true');
            });

            try {
                if (localStorage.getItem(KEY) === '1') {
                    applyCollapsed(true);
                }
            } catch (e2) {}
        })();
    </script>

@include('admin.partials.seating-map-styles')
