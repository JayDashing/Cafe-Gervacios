@php
    $canEditPlanner = auth()->user()?->isAdmin() ?? false;
    $plannerTables = $plannerTables ?? [];
    $plannerCanvas = $plannerCanvas ?? ['width' => 1200, 'height' => 760];
@endphp

<section
    class="fp-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    data-floor-planner
    data-can-edit="{{ $canEditPlanner ? '1' : '0' }}"
    data-api-index="{{ route('admin.api.seats.planner') }}"
    data-api-store="{{ route('admin.api.seats.planner.store') }}"
    data-api-save="{{ route('admin.api.seats.planner.save') }}"
    data-api-status="{{ route('admin.api.tables.operations.status') }}"
    data-api-merge-groups="{{ route('admin.api.seats.planner.merge-groups') }}"
    data-api-delete="{{ route('admin.api.seats.planner.delete') }}"
    data-bookings-url="{{ route('admin.bookings') }}"
    data-canvas-width="{{ $plannerCanvas['width'] ?? 1200 }}"
    data-canvas-height="{{ $plannerCanvas['height'] ?? 760 }}">
    <script type="application/json" data-floor-planner-json>@json($plannerTables)</script>
    <script type="application/json" data-floor-planner-merge-json>@json($mergeGroups ?? [])</script>

    <input type="checkbox" class="sr-only" data-floor-planner-snap checked>

    <div class="bg-slate-100 p-2 sm:p-3">
        @if ($canEditPlanner)
            <div class="fp-edit-toolbar mb-2 hidden flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm"
                data-floor-planner-edit-toolbar>
                <button type="button"
                    class="tc-admin-btn-primary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-floor-planner-action="add-selected">
                    <i class="fa-solid fa-plus text-[11px]" aria-hidden="true"></i>
                    Add Table
                </button>
                <span class="inline-flex min-h-9 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                    Move
                </span>
                <button type="button"
                    class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-floor-planner-action="merge-selected">
                    <i class="fa-solid fa-object-group text-[11px]" aria-hidden="true"></i>
                    Merge
                </button>
                <button type="button"
                    class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-floor-planner-action="unmerge-selected">
                    <i class="fa-solid fa-object-ungroup text-[11px]" aria-hidden="true"></i>
                    Unmerge
                </button>
                <button type="button"
                    class="tc-admin-btn-primary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs"
                    data-floor-planner-action="save">
                    <i class="fa-solid fa-floppy-disk text-[11px]" aria-hidden="true"></i>
                    Save
                </button>
            </div>
        @endif

        <div class="fp-stage">
            <div class="fp-map-panel">
                <div class="fp-canvas-scroll tc-scrollbar h-[650px] overflow-auto rounded-xl border border-slate-200 bg-white"
                data-floor-planner-scroll>
                <div class="fp-canvas-scale" data-floor-planner-scale>
                    <div class="fp-canvas"
                        data-floor-planner-canvas
                        style="width: {{ (int) ($plannerCanvas['width'] ?? 1200) }}px; height: {{ (int) ($plannerCanvas['height'] ?? 760) }}px;">
                        <div class="fp-zone fp-zone--waiting" data-zone-key="waiting" data-zone-name="Waiting Area">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="waiting">
                                    <span>Waiting Area</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-zone fp-zone--counter" data-zone-key="counter" data-zone-name="Counter">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="counter">
                                    <span>Counter</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-zone fp-zone--dining-a" data-zone-key="dining-a" data-zone-name="Main Dining">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="dining-a">
                                    <span>Main Dining</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-zone fp-zone--dining-b" data-zone-key="dining-b" data-zone-name="Main Dining">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="dining-b">
                                    <span>Main Dining</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-zone fp-zone--window" data-zone-key="window" data-zone-name="Window Booths">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="window">
                                    <span>Window Booths</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-zone fp-zone--group" data-zone-key="group" data-zone-name="Group Tables">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="group">
                                    <span>Group Tables</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-zone fp-zone--kitchen" data-zone-key="kitchen" data-zone-name="Staff / Kitchen Area">
                            <div class="fp-zone-head">
                                <button type="button" class="fp-zone-select" data-zone-select="kitchen">
                                    <span>Staff / Kitchen Area</span>
                                </button>
                            </div>
                        </div>

                        <div class="fp-floor-element fp-floor-element--entrance" data-floor-element-zone="waiting">Entrance</div>
                        <div class="fp-floor-element fp-floor-element--windows" data-floor-element-zone="window">Windows</div>
                        <div class="fp-floor-element fp-floor-element--bench" data-floor-element-zone="waiting">Waiting Bench</div>
                        <div class="fp-floor-element fp-floor-element--counter" data-floor-element-zone="counter">Cafe Counter</div>
                        <div class="fp-floor-element fp-floor-element--kitchen" data-floor-element-zone="kitchen">Kitchen</div>

                        <div class="fp-empty-state" data-floor-planner-empty>
                            <span>No tables</span>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <aside class="fp-side-panel">
                <section class="fp-drawer" data-floor-planner-panel hidden></section>
            </aside>
        </div>
    </div>

    <div class="fp-modal" data-floor-planner-modal hidden>
        <div class="fp-modal-card" role="dialog" aria-modal="true" aria-labelledby="fp-add-table-title">
            <form data-floor-planner-add-form>
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div>
                        <h3 id="fp-add-table-title" class="text-base font-semibold text-slate-950">Add Table</h3>
                        <p class="mt-0.5 text-xs text-slate-500" data-add-table-zone-label>Select an area first.</p>
                    </div>
                    <button type="button" class="fp-modal-close" data-modal-close aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="grid gap-3 p-4">
                    <div>
                        <label class="fp-field-label" for="fp-new-table-name">Table name</label>
                        <input id="fp-new-table-name" class="fp-input" type="text" data-add-field="label" placeholder="Example: T20">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="fp-field-label" for="fp-new-table-zone">Area / Zone</label>
                            <select id="fp-new-table-zone" class="fp-input" data-add-field="zone">
                                <option value="window">Window Booths</option>
                                <option value="dining-a">Main Dining</option>
                                <option value="counter">Counter</option>
                                <option value="group">Group Tables</option>
                                <option value="waiting">Waiting Area</option>
                            </select>
                        </div>
                        <div>
                            <label class="fp-field-label" for="fp-new-table-status">Status</label>
                            <select id="fp-new-table-status" class="fp-input" data-add-field="status">
                                <option value="available">Free</option>
                                <option value="occupied">Occupied</option>
                                <option value="cleaning">Cleaning</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="fp-field-label" for="fp-new-table-capacity">Capacity</label>
                            <input id="fp-new-table-capacity" class="fp-input" type="number" min="1" max="99" value="4" data-add-field="capacity" required>
                        </div>
                        <div>
                            <label class="fp-field-label" for="fp-new-table-shape">Shape</label>
                            <select id="fp-new-table-shape" class="fp-input" data-add-field="shape">
                                <option value="square">Square</option>
                                <option value="rectangle">Rectangle</option>
                                <option value="round">Round</option>
                                <option value="booth">Booth Table</option>
                                <option value="counter">Counter / Bar</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse items-stretch justify-center gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center">
                    <button type="button" class="tc-admin-btn-secondary min-h-10 px-4 py-2 text-sm" data-modal-close>Cancel</button>
                    <button type="submit" class="tc-admin-btn-primary min-h-10 px-4 py-2 text-sm">Add Table</button>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
    .fp-shell {
        --fp-free: #4f8a68;
        --fp-reserved: #3f7fb8;
        --fp-occupied: #c46937;
        --fp-cleaning: #7a818a;
        --fp-floor: #fbf7ef;
        --fp-grid: rgba(148, 163, 184, 0.18);
        --fp-zone-border: #d8d2c7;
    }

    .fp-stage {
        position: relative;
        display: grid;
        gap: 0.75rem;
    }

    @media (min-width: 1280px) {
        .fp-shell.has-panel .fp-stage {
            grid-template-columns: minmax(0, 1fr) 340px;
            align-items: start;
        }
    }

    .fp-map-panel {
        border-radius: 1rem;
        border: 1px solid #d8dee8;
        background: #fff;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
        min-width: 0;
    }

    .fp-side-panel {
        display: none;
        background: transparent;
        border: 0;
        box-shadow: none;
    }

    .fp-shell.has-panel .fp-side-panel {
        display: block;
    }

    .fp-canvas-scale {
        min-width: max-content;
        min-height: max-content;
        transform-origin: top left;
    }

    .fp-canvas {
        position: relative;
        overflow: hidden;
        border: 8px solid #e2e8f0;
        border-radius: 1.25rem;
        background:
            linear-gradient(var(--fp-grid) 1px, transparent 1px),
            linear-gradient(90deg, var(--fp-grid) 1px, transparent 1px),
            var(--fp-floor);
        background-size: 40px 40px, 40px 40px, auto;
        box-shadow: inset 0 0 0 1px #cbd5e1;
    }

    .fp-zone {
        position: absolute;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        border: 1px solid var(--fp-zone-border);
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.76);
        padding: 0.65rem;
        color: #334155;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease, background 0.15s ease;
    }

    .fp-zone.is-selected {
        border-color: #0f172a;
        box-shadow: inset 0 0 0 2px rgba(15, 23, 42, 0.16), 0 14px 30px rgb(15 23 42 / 0.12);
    }

    .fp-zone.is-drag-boundary {
        border-color: #2563eb;
        box-shadow: inset 0 0 0 3px rgba(37, 99, 235, 0.22);
    }

    .fp-zone.is-out-of-focus,
    .fp-table.is-out-of-focus,
    .fp-floor-element.is-out-of-focus {
        display: none;
    }

    .fp-zone-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .fp-zone-select {
        display: grid;
        gap: 0.25rem;
        text-align: left;
    }

    .fp-zone-select span {
        color: #1e293b;
        font-size: 0.76rem;
        font-weight: 900;
        letter-spacing: 0.02em;
        line-height: 1.1;
        text-transform: uppercase;
    }

    .fp-zone-select strong {
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .fp-zone small {
        margin-top: 0.35rem;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .fp-zone-add {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        background: #fff;
        padding: 0.25rem 0.5rem;
        color: #0f172a;
        font-size: 0.65rem;
        font-weight: 900;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.08);
        transition: background 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
    }

    .fp-zone-add:hover:not(:disabled) {
        border-color: #64748b;
        background: #f8fafc;
    }

    .fp-zone-note {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        background: rgba(255, 255, 255, 0.72);
        padding: 0.25rem 0.5rem;
        color: #64748b;
        font-size: 0.65rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .fp-zone-add:disabled,
    .fp-shell:not(.is-editing) .fp-zone-add {
        cursor: not-allowed;
        opacity: 0.45;
    }

    .fp-zone--waiting {
        left: 28px;
        top: 520px;
        width: 246px;
        height: 198px;
        background: rgba(254, 249, 235, 0.82);
    }

    .fp-zone--counter {
        left: 28px;
        top: 38px;
        width: 252px;
        height: 178px;
        background: rgba(241, 236, 226, 0.9);
    }

    .fp-zone--dining-a {
        left: 312px;
        top: 58px;
        width: 352px;
        height: 286px;
        background: rgba(248, 244, 237, 0.86);
    }

    .fp-zone--dining-b {
        left: 312px;
        top: 376px;
        width: 352px;
        height: 316px;
        background: rgba(248, 244, 237, 0.86);
    }

    .fp-zone--window {
        left: 700px;
        top: 58px;
        width: 462px;
        height: 190px;
        background: rgba(231, 239, 242, 0.9);
    }

    .fp-zone--group {
        left: 700px;
        top: 286px;
        width: 306px;
        height: 406px;
        background: rgba(244, 237, 226, 0.9);
    }

    .fp-zone--kitchen {
        left: 1038px;
        top: 286px;
        width: 124px;
        height: 406px;
        background: rgba(229, 231, 235, 0.8);
    }

    .fp-floor-element {
        position: absolute;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px dashed #94a3b8;
        background: rgba(255, 255, 255, 0.76);
        color: #475569;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        pointer-events: none;
    }

    .fp-floor-element--entrance {
        left: 38px;
        top: 726px;
        width: 152px;
        height: 28px;
    }

    .fp-floor-element--windows {
        left: 736px;
        top: 28px;
        width: 360px;
        height: 24px;
        border-radius: 0.5rem;
        border-style: solid;
        background: #e0f2fe;
        color: #0369a1;
    }

    .fp-floor-element--bench {
        left: 56px;
        top: 636px;
        width: 188px;
        height: 34px;
        border-style: solid;
        background: #fff;
    }

    .fp-floor-element--counter {
        left: 54px;
        top: 118px;
        width: 200px;
        height: 44px;
        border-style: solid;
        background: #fff7ed;
        color: #9a3412;
    }

    .fp-floor-element--kitchen {
        left: 1056px;
        top: 470px;
        width: 88px;
        height: 44px;
        border-style: solid;
        background: #fff;
        color: #991b1b;
    }

    .fp-empty-state {
        position: absolute;
        left: 430px;
        top: 356px;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px dashed #cbd5e1;
        background: rgba(255, 255, 255, 0.85);
        padding: 0.5rem 0.9rem;
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 800;
        pointer-events: none;
    }

    .fp-table {
        position: absolute;
        z-index: 10;
        cursor: pointer;
        touch-action: none;
        user-select: none;
        transform-origin: center;
    }

    .fp-shell.is-editing .fp-table {
        cursor: grab;
    }

    .fp-shell.is-editing .fp-table:active {
        cursor: grabbing;
    }

    .fp-table.is-selected {
        z-index: 20;
    }

    .fp-table.is-merge-selected .fp-table-piece {
        outline: 3px solid rgba(14, 165, 233, 0.35);
        outline-offset: 5px;
    }

    .fp-merge-toggle {
        position: absolute;
        right: -4px;
        top: -4px;
        z-index: 4;
        display: none;
        height: 24px;
        width: 24px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid #bae6fd;
        background: #fff;
        color: #0369a1;
        font-size: 0.72rem;
        box-shadow: 0 6px 14px rgb(15 23 42 / 0.16);
    }

    .fp-shell.is-editing .fp-merge-toggle {
        display: inline-flex;
    }

    .fp-merge-toggle.is-active {
        border-color: #0284c7;
        background: #e0f2fe;
    }

    .fp-merge-group {
        position: absolute;
        z-index: 8;
        border-radius: 1rem;
        border: 2px dashed rgba(15, 23, 42, 0.35);
        background: rgba(255, 255, 255, 0.28);
        pointer-events: auto;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .fp-merge-group.is-selected {
        border-color: #0f172a;
        box-shadow: 0 12px 26px rgb(15 23 42 / 0.16);
    }

    .fp-merge-label {
        position: absolute;
        left: 0.7rem;
        top: -0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.25rem 0.55rem;
        color: #0f172a;
        font-size: 0.68rem;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        box-shadow: 0 6px 14px rgb(15 23 42 / 0.12);
    }

    .fp-table-piece {
        position: absolute;
        inset: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgb(15 23 42 / 0.75);
        background: #fff;
        color: #0f172a;
        box-shadow: 0 8px 18px rgb(15 23 42 / 0.14);
        transition: box-shadow 0.15s ease, outline-color 0.15s ease, transform 0.15s ease;
    }

    .fp-chair {
        position: absolute;
        display: block;
        height: 14px;
        width: 20px;
        border-radius: 999px;
        border: 1px solid currentColor;
        background: #fff;
        opacity: 0.72;
        pointer-events: none;
    }

    .fp-chair--1 {
        left: 16%;
        top: -12px;
    }

    .fp-chair--2 {
        right: 16%;
        top: -12px;
    }

    .fp-chair--3 {
        right: -14px;
        top: 38%;
        height: 20px;
        width: 14px;
    }

    .fp-chair--4 {
        left: -14px;
        top: 38%;
        height: 20px;
        width: 14px;
    }

    .fp-chair--5 {
        left: 18%;
        bottom: -12px;
    }

    .fp-chair--6 {
        right: 18%;
        bottom: -12px;
    }

    .fp-table.is-selected .fp-table-piece {
        outline: 3px solid rgba(15, 23, 42, 0.2);
        outline-offset: 3px;
        box-shadow: 0 14px 30px rgb(15 23 42 / 0.2);
        transform: translateY(-1px);
    }

    .fp-table--round .fp-table-piece,
    .fp-table--counter .fp-table-piece {
        border-radius: 999px;
    }

    .fp-table--square .fp-table-piece,
    .fp-table--rectangle .fp-table-piece,
    .fp-table--booth .fp-table-piece {
        border-radius: 12px;
    }

    .fp-table--booth .fp-table-piece {
        border-radius: 18px 18px 10px 10px;
        background: linear-gradient(180deg, #eef6fb 0%, #ffffff 58%);
    }

    .fp-table--counter .fp-table-piece {
        inset: 8px 16px 8px;
        border-radius: 999px 999px 12px 12px;
    }

    .fp-table--available .fp-table-piece {
        border-color: var(--fp-free);
    }

    .fp-table--reserved .fp-table-piece {
        border-color: var(--fp-reserved);
        background: #eff6ff;
    }

    .fp-table--occupied .fp-table-piece {
        border-color: var(--fp-occupied);
        background: #fff7ed;
    }

    .fp-table--cleaning .fp-table-piece {
        border-color: var(--fp-cleaning);
        background: #f1f5f9;
    }

    .fp-table-label {
        display: flex;
        max-width: 100%;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.35rem;
        text-align: center;
        line-height: 1.08;
    }

    .fp-table-label strong {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.84rem;
        font-weight: 900;
    }

    .fp-table-label small,
    .fp-table-label em,
    .fp-table-label b {
        margin-top: 0.22rem;
        color: #475569;
        font-size: 0.66rem;
        font-style: normal;
        font-weight: 800;
        text-transform: uppercase;
    }

    .fp-table-label b {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #0f172a;
        font-size: 0.58rem;
        text-transform: none;
    }

    .fp-drawer {
        position: relative;
        z-index: 2;
        min-height: 0;
        border-radius: 1rem;
        border: 1px solid #d8dee8;
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.08);
    }

    .fp-field-label {
        display: block;
        margin-bottom: 0.35rem;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .fp-input {
        min-height: 40px;
        width: 100%;
        border-radius: 0.6rem;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.5rem 0.65rem;
        font-size: 0.875rem;
        color: #0f172a;
    }

    .fp-detail-card {
        border-radius: 0.8rem;
        border: 1px solid #d8dee8;
        background: #f8fafc;
        padding: 0.75rem;
    }

    .fp-status-action {
        min-height: 36px;
        border-radius: 0.65rem;
        border: 1px solid #d8dee8;
        background: #fff;
        padding: 0.5rem 0.65rem;
        color: #334155;
        font-size: 0.76rem;
        font-weight: 800;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    .fp-status-action:hover:not(:disabled) {
        border-color: #94a3b8;
        background: #f8fafc;
    }

    .fp-status-action:disabled {
        cursor: not-allowed;
        opacity: 0.55;
    }

    .fp-modal {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgb(15 23 42 / 0.45);
        padding: 1rem;
    }

    .fp-modal[hidden],
    .fp-drawer[hidden] {
        display: none;
    }

    .fp-modal-card {
        width: min(100%, 420px);
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #d8dee8;
        background: #fff;
        box-shadow: 0 24px 60px rgb(15 23 42 / 0.26);
    }

    .fp-modal-close {
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

    @media (max-width: 767px) {
        .fp-canvas-scroll {
            height: 540px;
        }

        .fp-canvas {
            width: 960px !important;
            height: 680px !important;
        }

        .fp-drawer {
            right: 0.5rem;
            bottom: 0.5rem;
            left: 0.5rem;
        }
    }
</style>
