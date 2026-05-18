@php
    use App\Models\Setting;

    $canEditBlueprint = auth()->user()?->isAdmin() ?? false;
    $editMode = $canEditBlueprint && request()->boolean('edit');
    $floorplanRelative = Setting::get('floorplan_image', 'images/floorplan.png');
    $floorplanPath = public_path($floorplanRelative);
    $hasFloorplan = is_file($floorplanPath);
    $floorplanUrl = $hasFloorplan ? asset($floorplanRelative) . '?v=' . filemtime($floorplanPath) : '';
    $activeBookingsByTable = $floorMapActiveBookingsByTable ?? collect();
    $guestInfo = $floorTableGuestInfo ?? collect();
    $statusLabel = fn(string $status) => match ($status) {
        'reserved' => 'Reserved',
        'occupied' => 'Occupied',
        'cleaning' => 'Cleaning',
        default => 'Free',
    };

    $markers = ($tableGroups ?? collect())->map(function ($group) use ($activeBookingsByTable, $guestInfo, $statusLabel) {
        $table = $group->table;
        $firstSeat = $group->seats->first();
        $booking = $activeBookingsByTable->get($table->id);
        $guest = $guestInfo->get($table->id, [
            'guest' => 'No current guest',
            'party' => (string) max(1, (int) $table->capacity),
            'arrival_at' => null,
        ]);

        return [
            'id' => (int) $table->id,
            'seat_id' => (int) $firstSeat?->id,
            'label' => (string) $table->label,
            'capacity' => (int) $table->capacity,
            'status' => (string) $table->status,
            'status_label' => $statusLabel((string) $table->status),
            'x' => (float) $group->anchor_x,
            'y' => (float) $group->anchor_y,
            'seat_count' => (int) $group->seats->count(),
            'furniture_type' => (string) ($table->furniture_type ?? 'standard'),
            'merge_group' => (string) ($table->getAttribute('merge_group') ?? 'default'),
            'booking' => $booking ? [
                'id' => (int) $booking->id,
                'ref' => (string) $booking->booking_ref,
                'guest' => (string) $booking->customer_name,
                'party' => (int) $booking->party_size,
                'status' => (string) $booking->status,
                'payment_status' => (string) ($booking->payment_status ?? 'unpaid'),
                'booked_at' => optional($booking->booked_at)->timezone(config('app.timezone'))->format('M d, g:i A'),
            ] : null,
            'guest' => [
                'name' => (string) ($guest['guest'] ?? 'No current guest'),
                'party' => (string) ($guest['party'] ?? max(1, (int) $table->capacity)),
                'arrival_at' => $guest['arrival_at'] ?? null,
            ],
        ];
    })->values();

    $bookingChoices = ($todaysReservations ?? collect())
        ->merge($pendingBookings ?? collect())
        ->filter(fn ($booking) => in_array($booking->status, ['active', 'pending'], true))
        ->unique('id')
        ->values()
        ->map(fn ($booking) => [
            'id' => (int) $booking->id,
            'ref' => (string) $booking->booking_ref,
            'guest' => (string) $booking->customer_name,
            'party' => (int) $booking->party_size,
            'time' => optional($booking->booked_at)->timezone(config('app.timezone'))->format('M d, g:i A'),
        ]);
@endphp

<section
    class="bfm-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $editMode ? 'is-edit-mode' : '' }}"
    data-blueprint-floor-map
    data-edit-mode="{{ $editMode ? 'true' : 'false' }}"
    data-api-status="{{ route('admin.api.tables.operations.status') }}"
    data-api-merge-groups="{{ route('admin.api.tables.operations.merge-groups') }}"
    data-api-place="{{ $canEditBlueprint ? route('admin.api.seats.place') : '' }}"
    data-api-update="{{ $canEditBlueprint ? route('admin.api.seats.update') : '' }}"
    data-api-delete="{{ $canEditBlueprint ? route('admin.api.seats.delete') : '' }}"
    data-bookings-url="{{ route('admin.bookings') }}">
    <script type="application/json" data-blueprint-tables-json>@json($markers)</script>
    <script type="application/json" data-blueprint-groups-json>@json($dailyMergeGroups ?? [])</script>
    <script type="application/json" data-blueprint-bookings-json>@json($bookingChoices)</script>

    @if ($editMode)
        <form data-blueprint-upload-form action="{{ route('admin.seating-layout.floorplan') }}" method="post" enctype="multipart/form-data" class="hidden">
            @csrf
            <input type="file" name="floorplan" accept="image/png,image/jpeg,image/webp" data-blueprint-upload-input>
        </form>

        <div class="bfm-edit-toolbar flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-3 py-3">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-blueprint-action="upload-blueprint">
                    <i class="fa-solid fa-image text-[11px]" aria-hidden="true"></i>
                    Upload Blueprint
                </button>
                <button type="button" class="tc-admin-btn-primary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-blueprint-action="open-add-marker">
                    <i class="fa-solid fa-plus text-[11px]" aria-hidden="true"></i>
                    Add Table Marker
                </button>
                <button type="button" class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-blueprint-action="save-layout">
                    <i class="fa-solid fa-floppy-disk text-[11px]" aria-hidden="true"></i>
                    Save Layout
                </button>
            </div>
            <a href="{{ route('admin.tables') }}"
                class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs">
                <i class="fa-solid fa-arrow-left text-[11px]" aria-hidden="true"></i>
                Exit Edit Mode
            </a>
        </div>
    @endif

    <div class="bfm-placement-bar mx-3 mt-3 hidden items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-950"
        data-blueprint-placement-bar>
        <span class="font-semibold">Click on the blueprint where this table should appear.</span>
        <button type="button" class="text-xs font-bold uppercase tracking-wide text-emerald-900" data-blueprint-action="cancel-placement">Cancel</button>
    </div>

    <div class="bfm-merge-bar mx-3 mt-3 hidden items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950"
        data-blueprint-merge-bar>
        <span class="font-semibold" data-blueprint-selection-summary>Select two or more tables to merge.</span>
        <div class="flex items-center gap-2">
            <button type="button" class="tc-admin-btn-primary min-h-9 px-3 py-2 text-xs" data-blueprint-action="open-merge" disabled>Confirm Merge</button>
            <button type="button" class="tc-admin-btn-secondary min-h-9 px-3 py-2 text-xs" data-blueprint-action="cancel-merge-select">Cancel</button>
        </div>
    </div>

    <div class="bfm-body bg-slate-100 p-3" data-blueprint-body>
        <div class="bfm-map-card rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
            @unless ($hasFloorplan)
                <div class="grid min-h-[420px] place-items-center rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">No blueprint uploaded</h3>
                        <p class="mt-1 max-w-md text-sm text-slate-500">
                            Add the cafe floor image in Edit Layout, then place table markers on top of it.
                        </p>
                        @if ($canEditBlueprint && ! $editMode)
                            <a href="{{ route('admin.tables', ['edit' => 1]) }}"
                                class="tc-admin-btn-primary mt-4 inline-flex min-h-10 items-center justify-center gap-2 px-4 py-2 text-sm">
                                <i class="fa-solid fa-pen-ruler text-xs" aria-hidden="true"></i>
                                Edit Layout
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <div class="bfm-map-scroll tc-scrollbar overflow-auto rounded-lg bg-slate-100">
                    <div class="bfm-stage" data-blueprint-stage>
                        <img src="{{ $floorplanUrl }}" alt="Cafe Gervacios floor blueprint" class="bfm-blueprint" data-blueprint-image draggable="false">

                        <div data-blueprint-merge-layer></div>

                        @foreach ($markers as $table)
                            <button type="button"
                                class="bfm-marker bfm-marker--{{ $table['status'] === 'available' ? 'free' : $table['status'] }}"
                                style="left: {{ $table['x'] }}%; top: {{ $table['y'] }}%;"
                                data-blueprint-marker
                                data-table-id="{{ $table['id'] }}"
                                aria-label="{{ $table['label'] }}, {{ $table['status_label'] }}, {{ $table['capacity'] }} seats">
                                <span class="bfm-marker__name">{{ $table['label'] }}</span>
                                <span class="bfm-marker__meta">
                                    <span class="bfm-marker__status">{{ $table['status_label'] }}</span>
                                    <span class="bfm-marker__capacity">{{ $table['capacity'] }}</span>
                                </span>
                            </button>
                        @endforeach

                        @if ($markers->isEmpty())
                            <div class="bfm-empty">
                                <span>No table markers yet</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endunless
        </div>

        <aside class="bfm-panel rounded-xl border border-slate-200 bg-white shadow-sm" data-blueprint-panel hidden></aside>
    </div>

    @if ($editMode)
        <div class="bfm-modal" data-blueprint-add-modal hidden>
            <div class="bfm-modal-card" role="dialog" aria-modal="true" aria-labelledby="bfm-add-title">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div>
                        <h3 id="bfm-add-title" class="text-base font-semibold text-slate-950">Add Table Marker</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Enter table details first, then click the blueprint location.</p>
                    </div>
                    <button type="button" class="bfm-close-btn" data-blueprint-action="close-add-marker" aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <form data-blueprint-add-form>
                    <div class="grid gap-3 p-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <span class="bfm-field-label mb-1">New table will be named</span>
                            <strong class="block text-xl font-black tracking-tight text-slate-950" data-blueprint-next-label>T1</strong>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="bfm-field-label" for="bfm-add-capacity">Capacity</label>
                                <input id="bfm-add-capacity" class="bfm-input" type="number" min="1" max="99" value="2" data-add-field="capacity">
                            </div>
                            <div>
                                <label class="bfm-field-label" for="bfm-add-type">Shape / type</label>
                                <select id="bfm-add-type" class="bfm-input" data-add-field="type">
                                    <option value="standard">Standard</option>
                                    <option value="booth">Booth</option>
                                    <option value="bar">Bar / counter</option>
                                    <option value="high-top">High-top</option>
                                    <option value="outdoor">Outdoor</option>
                                    <option value="bench">Bench</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:justify-end">
                        <button type="button" class="tc-admin-btn-secondary min-h-10 px-4 py-2 text-sm" data-blueprint-action="close-add-marker">Cancel</button>
                        <button type="submit" class="tc-admin-btn-primary min-h-10 px-4 py-2 text-sm">Choose Location</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="bfm-modal" data-blueprint-merge-modal hidden>
        <div class="bfm-modal-card" role="dialog" aria-modal="true" aria-labelledby="bfm-merge-title">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 id="bfm-merge-title" class="text-base font-semibold text-slate-950">Merge Tables</h3>
                    <p class="mt-0.5 text-xs text-slate-500" data-blueprint-merge-summary>Choose tables first.</p>
                </div>
                <button type="button" class="bfm-close-btn" data-blueprint-action="close-merge" aria-label="Close">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <form data-blueprint-merge-form>
                <div class="grid gap-3 p-4">
                    <div>
                        <label class="bfm-field-label" for="bfm-merge-booking">Booking or walk-in group</label>
                        <select id="bfm-merge-booking" class="bfm-input" data-merge-field="booking">
                            <option value="">Walk-in group</option>
                            @foreach ($bookingChoices as $booking)
                                <option value="{{ $booking['id'] }}">
                                    {{ $booking['ref'] }} - {{ $booking['guest'] }} - {{ $booking['party'] }} guests
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="bfm-field-label" for="bfm-merge-label">Group label</label>
                        <input id="bfm-merge-label" class="bfm-input" type="text" data-merge-field="label" placeholder="Example: T1 + T2">
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:justify-end">
                    <button type="button" class="tc-admin-btn-secondary min-h-10 px-4 py-2 text-sm" data-blueprint-action="close-merge">Cancel</button>
                    <button type="submit" class="tc-admin-btn-primary min-h-10 px-4 py-2 text-sm">Confirm Merge</button>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
    .bfm-shell {
        --bfm-free: #15803d;
        --bfm-reserved: #b7791f;
        --bfm-occupied: #c2410c;
        --bfm-cleaning: #64748b;
    }

    .bfm-body {
        display: grid;
        gap: 0.75rem;
    }

    @media (min-width: 1280px) {
        .bfm-body.has-panel {
            grid-template-columns: minmax(0, 1fr) 340px;
        }
    }

    .bfm-map-scroll {
        max-height: calc(100vh - 230px);
        min-height: 520px;
    }

    .bfm-stage {
        position: relative;
        display: inline-block;
        min-width: 840px;
        overflow: hidden;
        border-radius: 0.85rem;
        background: #f8fafc;
    }

    .bfm-stage.is-placement-mode {
        cursor: crosshair;
        outline: 3px solid rgba(16, 185, 129, 0.24);
        outline-offset: -3px;
    }

    .bfm-blueprint {
        display: block;
        width: min(1180px, max(100%, 840px));
        height: auto;
        user-select: none;
    }

    .bfm-marker {
        position: absolute;
        z-index: 10;
        display: grid;
        min-width: 58px;
        transform: translate(-50%, -50%);
        gap: 0.16rem;
        border-radius: 0.8rem;
        border: 2px solid var(--bfm-free);
        background: rgba(255, 255, 255, 0.96);
        padding: 0.36rem 0.46rem;
        text-align: center;
        color: #0f172a;
        box-shadow: 0 8px 18px rgb(15 23 42 / 0.14);
        transition: box-shadow 0.15s ease, outline-color 0.15s ease, opacity 0.15s ease, transform 0.15s ease;
        touch-action: none;
    }

    .bfm-marker:hover,
    .bfm-marker.is-selected {
        box-shadow: 0 12px 26px rgb(15 23 42 / 0.22);
        transform: translate(-50%, -50%) translateY(-1px);
    }

    .bfm-shell.is-edit-mode .bfm-marker {
        cursor: grab;
    }

    .bfm-shell.is-edit-mode .bfm-marker.is-dragging {
        cursor: grabbing;
        opacity: 0.88;
    }

    .bfm-marker.is-pick-mode {
        outline: 3px solid rgba(14, 165, 233, 0.18);
        outline-offset: 4px;
    }

    .bfm-marker.is-picked {
        outline: 3px solid rgba(14, 165, 233, 0.5);
        outline-offset: 5px;
    }

    .bfm-marker.is-merge-compatible:not(.is-picked) {
        outline: 3px solid rgba(22, 163, 74, 0.28);
        outline-offset: 4px;
    }

    .bfm-marker.is-merge-blocked:not(.is-picked) {
        cursor: not-allowed;
        opacity: 0.36;
        filter: grayscale(0.45);
    }

    .bfm-marker.is-merged:not(.is-selected) {
        opacity: 0.82;
    }

    .bfm-marker--reserved {
        border-color: var(--bfm-reserved);
    }

    .bfm-marker--occupied {
        border-color: var(--bfm-occupied);
    }

    .bfm-marker--cleaning {
        border-color: var(--bfm-cleaning);
    }

    .bfm-marker__name {
        font-size: 0.82rem;
        font-weight: 900;
        line-height: 1;
    }

    .bfm-marker__meta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        color: #64748b;
        font-size: 0.62rem;
        font-weight: 800;
        line-height: 1;
        text-transform: uppercase;
    }

    .bfm-marker__capacity {
        display: inline-flex;
        min-width: 1.25rem;
        justify-content: center;
        border-radius: 999px;
        background: #f1f5f9;
        padding: 0.12rem 0.28rem;
        color: #334155;
    }

    .bfm-group {
        position: absolute;
        z-index: 8;
        transform: translate(-50%, -50%);
        border-radius: 1rem;
        border: 2px dashed rgba(15, 23, 42, 0.42);
        background: rgba(255, 255, 255, 0.2);
        box-shadow: inset 0 0 0 999px rgba(248, 250, 252, 0.12);
    }

    .bfm-group.is-selected {
        border-color: #0f172a;
        box-shadow: 0 14px 30px rgb(15 23 42 / 0.18);
    }

    .bfm-group__label {
        position: absolute;
        left: 0.5rem;
        top: -0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.25rem 0.55rem;
        color: #0f172a;
        font-size: 0.66rem;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        box-shadow: 0 6px 14px rgb(15 23 42 / 0.12);
    }

    .bfm-panel[hidden],
    .bfm-modal[hidden] {
        display: none;
    }

    .bfm-empty {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        border-radius: 999px;
        border: 1px dashed #cbd5e1;
        background: rgba(255, 255, 255, 0.9);
        padding: 0.55rem 0.9rem;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 800;
        pointer-events: none;
    }

    .bfm-field-label {
        display: block;
        margin-bottom: 0.35rem;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .bfm-input {
        min-height: 40px;
        width: 100%;
        border-radius: 0.6rem;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.5rem 0.65rem;
        font-size: 0.875rem;
        color: #0f172a;
    }

    .bfm-action {
        min-height: 38px;
        border-radius: 0.65rem;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.5rem 0.65rem;
        color: #334155;
        font-size: 0.76rem;
        font-weight: 800;
        text-align: center;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    .bfm-action:hover:not(:disabled) {
        border-color: #94a3b8;
        background: #f8fafc;
    }

    .bfm-action:disabled,
    [data-blueprint-action="open-merge"]:disabled {
        cursor: not-allowed;
        opacity: 0.55;
    }

    .bfm-close-btn {
        display: inline-flex;
        height: 36px;
        width: 36px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid #d8dee8;
        background: #fff;
        color: #334155;
    }

    .bfm-modal {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgb(15 23 42 / 0.45);
        padding: 1rem;
    }

    .bfm-modal-card {
        width: min(100%, 430px);
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #d8dee8;
        background: #fff;
        box-shadow: 0 24px 60px rgb(15 23 42 / 0.26);
    }

    @media (max-width: 1279px) {
        .bfm-panel:not([hidden]) {
            position: sticky;
            bottom: 0.75rem;
            z-index: 30;
        }
    }
</style>
