@php
    $restaurantTables = collect($plannerTables ?? []);
@endphp

<section
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    data-restaurant-preview
    data-model-url="{{ asset('models/cafe-gervacios-top-view/Cafe_Gervacios_Refined_TopView_Operational.glb') }}"
    data-api-tables="{{ route('admin.api.tables.operations') }}"
    data-api-status="{{ route('admin.api.tables.operations.status') }}"
    data-bookings-url="{{ route('admin.bookings') }}"
    data-preview-readonly="1"
    data-can-debug="{{ auth()->user()?->isAdmin() ? '1' : '0' }}">
    <script type="application/json" data-restaurant-preview-tables>@json($restaurantTables->values())</script>

    <div class="border-b border-slate-200 bg-white px-4 py-3">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-950">3D Preview</h2>
                <p class="mt-0.5 text-sm text-slate-600">Visual reference only. Use Floor Plan or Table Status for daily operations.</p>
            </div>
            <div class="flex w-full flex-col gap-2 xl:max-w-4xl">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-end">
                    <label class="relative block w-full lg:max-w-xs">
                        <span class="sr-only">Search table name or booking reference</span>
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                        <input type="search"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-sm text-slate-800 shadow-sm transition focus:bg-white"
                            placeholder="Search table or booking"
                            data-preview-search>
                    </label>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rp-top-btn" data-preview-action="reset">
                            <i class="fa-solid fa-location-crosshairs text-[11px]" aria-hidden="true"></i>
                            Reset View
                        </button>
                        <a href="{{ route('admin.tables', ['tab' => 'status']) }}" class="tc-admin-btn-primary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs">
                            <i class="fa-solid fa-table-cells text-[11px]" aria-hidden="true"></i>
                            Open Table List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="relative min-h-[560px] bg-slate-950" data-preview-stage>
        <div class="absolute inset-0" data-preview-canvas></div>

        <div class="pointer-events-none absolute left-4 top-4 z-10 max-w-xs rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-xs font-medium leading-relaxed text-white/90 shadow-lg backdrop-blur">
            Visual reference only. The editable 2D Floor Plan is the operational seating map.
        </div>

        <div class="pointer-events-none absolute right-4 top-4 z-10 hidden rounded-full border border-white/10 bg-white/90 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-slate-900 shadow-lg"
            data-preview-zone-chip></div>

        <div class="pointer-events-none absolute z-30 hidden rounded-lg border border-white/15 bg-slate-950/88 px-3 py-2 text-xs font-semibold leading-tight text-white shadow-xl backdrop-blur"
            data-preview-tooltip></div>

        <aside class="rp-side-panel hidden" data-preview-panel></aside>

        <div class="rp-mapping-summary hidden" data-preview-mapping-summary hidden></div>

        @if (auth()->user()?->isAdmin())
            <div class="rp-debug-panel hidden" data-preview-debug-panel hidden></div>
        @endif

        <div class="absolute inset-x-4 bottom-4 z-20 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800"
            data-preview-error></div>

        <div class="absolute inset-0 z-20 flex items-center justify-center bg-slate-950/70 text-sm font-semibold text-white"
            data-preview-loading>
            Loading restaurant model...
        </div>
    </div>
</section>

<style>
    [data-preview-stage] {
        height: min(74vh, 780px);
        min-height: 560px;
    }

    [data-preview-canvas] canvas {
        display: block;
        height: 100% !important;
        width: 100% !important;
    }

    [data-preview-loading][hidden],
    [data-preview-tooltip][hidden],
    [data-preview-panel][hidden] {
        display: none !important;
    }

    .rp-top-btn,
    .rp-preset-btn {
        display: inline-flex;
        min-height: 36px;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        border-radius: 0.65rem;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.45rem 0.7rem;
        color: #334155;
        font-size: 0.75rem;
        font-weight: 800;
        transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
    }

    .rp-preset-btn {
        min-height: 32px;
        border-radius: 999px;
        background: #f8fafc;
        padding-inline: 0.75rem;
        font-size: 0.7rem;
    }

    .rp-top-btn:hover,
    .rp-preset-btn:hover {
        border-color: #94a3b8;
        background: #fff;
        color: #0f172a;
    }

    .rp-preset-btn.is-active {
        border-color: #0f172a;
        background: #0f172a;
        color: #fff;
    }

    .rp-top-btn.is-active {
        border-color: #0f172a;
        background: #0f172a;
        color: #fff;
    }

    .rp-mapping-summary {
        position: absolute;
        left: 1rem;
        bottom: 1rem;
        z-index: 18;
        max-width: min(360px, calc(100% - 2rem));
        border-radius: 0.9rem;
        border: 1px solid rgb(255 255 255 / 0.12);
        background: rgb(15 23 42 / 0.78);
        padding: 0.75rem;
        color: rgb(255 255 255 / 0.86);
        font-size: 0.72rem;
        line-height: 1.35;
        box-shadow: 0 18px 42px rgb(15 23 42 / 0.28);
        backdrop-filter: blur(12px);
    }

    .rp-mapping-summary[hidden],
    .rp-debug-panel[hidden] {
        display: none !important;
    }

    .rp-mapping-head {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .rp-mapping-dot {
        display: inline-flex;
        height: 0.55rem;
        width: 0.55rem;
        flex: none;
        border-radius: 999px;
    }

    .rp-mapping-dot-amber {
        background: #f59e0b;
        box-shadow: 0 0 0 4px rgb(245 158 11 / 0.18);
    }

    .rp-mapping-dot-emerald {
        background: #10b981;
        box-shadow: 0 0 0 4px rgb(16 185 129 / 0.18);
    }

    .rp-mapping-warning {
        color: rgb(254 243 199 / 0.96);
    }

    .rp-debug-panel {
        position: absolute;
        left: 1rem;
        right: 1rem;
        bottom: 1rem;
        z-index: 28;
        border-radius: 1rem;
        border: 1px solid rgb(255 255 255 / 0.12);
        background: rgb(15 23 42 / 0.92);
        padding: 0.85rem;
        color: #fff;
        box-shadow: 0 24px 60px rgb(15 23 42 / 0.36);
        backdrop-filter: blur(16px);
    }

    .rp-debug-close {
        display: inline-flex;
        height: 2rem;
        width: 2rem;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid rgb(255 255 255 / 0.14);
        background: rgb(255 255 255 / 0.08);
        color: rgb(255 255 255 / 0.82);
    }

    .rp-debug-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.68rem;
    }

    .rp-debug-table th,
    .rp-debug-table td {
        border-bottom: 1px solid rgb(255 255 255 / 0.08);
        padding: 0.4rem 0.5rem;
        text-align: left;
        vertical-align: top;
    }

    .rp-debug-table th {
        color: rgb(255 255 255 / 0.64);
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .rp-side-panel {
        position: absolute;
        right: 1rem;
        top: 1rem;
        bottom: 1rem;
        z-index: 25;
        width: min(360px, calc(100% - 2rem));
        overflow-y: auto;
        border-radius: 1rem;
        border: 1px solid rgb(226 232 240 / 0.9);
        background: rgb(255 255 255 / 0.96);
        padding: 1rem;
        box-shadow: 0 24px 60px rgb(15 23 42 / 0.24);
        backdrop-filter: blur(14px);
    }

    .rp-panel-action {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        border-radius: 0.65rem;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.55rem 0.75rem;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 800;
        text-align: center;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    .rp-panel-action:hover:not(:disabled) {
        border-color: #94a3b8;
        background: #f8fafc;
    }

    .rp-panel-action:disabled {
        cursor: not-allowed;
        opacity: 0.42;
    }

    @media (max-width: 767px) {
        [data-preview-stage] {
            height: 640px;
            min-height: 640px;
        }

        .rp-side-panel {
            top: auto;
            max-height: 62%;
        }
    }
</style>
