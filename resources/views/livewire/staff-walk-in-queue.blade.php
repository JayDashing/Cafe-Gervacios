<div class="{{ $modalMode ? 'w-full' : 'flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden bg-panel-canvas' }}">
    <div
        class="{{ $modalMode ? 'w-full' : 'flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden tc-scrollbar px-4 py-4 sm:px-6 sm:py-5' }}">
        <div class="{{ $modalMode ? 'w-full' : 'mx-auto w-full max-w-5xl' }}">
            {{-- Compact local header bar (replaces duplicate app header title) --}}
            @unless ($modalMode)
            <div
                class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-slate-100 pb-3 sm:mb-4 sm:pb-3">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex shrink-0 items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition-colors hover:text-panel-primary">
                    <i class="fa-solid fa-arrow-left text-[10px]" aria-hidden="true"></i>
                    Dashboard
                </a>
                <span class="select-none text-slate-300" aria-hidden="true">/</span>
                <h2 class="text-[15px] font-semibold leading-tight text-slate-900">Register walk-in</h2>
            </div>
            @endunless

            @php
                $compatibleCount = $walkInTableMarkers->where('selectable', true)->count();
                $hasSeatingOption = $compatibleCount > 0;
                $primaryDisabled = $hasSeatingOption && ! $selectedTable;
                $primaryLabel = $hasSeatingOption
                    ? ($selectedTable ? 'Seat Guest at '.$selectedTable->label : 'Seat Guest')
                    : 'Add to Waitlist';
            @endphp

            <div class="grid gap-4 lg:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
                <div class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 rounded-xl border {{ $hasSeatingOption ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-4 py-3">
                        <p class="text-[12px] font-bold uppercase tracking-[0.1em] {{ $hasSeatingOption ? 'text-emerald-800' : 'text-amber-900' }}">
                            Step 1 - Guest details
                        </p>
                        @if ($hasSeatingOption)
                            <p class="mt-1 text-sm font-semibold {{ $selectedTable ? 'text-emerald-950' : 'text-slate-900' }}">
                                {{ $selectedTable ? 'Ready to seat at '.$selectedTable->label.'.' : 'Select an available table to seat guest.' }}
                            </p>
                        @else
                            <p class="mt-1 text-sm font-semibold text-amber-950">No suitable table available. Guest will be added to the waitlist.</p>
                            <p class="mt-1 text-xs font-bold text-amber-900">Estimated wait: {{ $estimatedWait }} min</p>
                        @endif
                    </div>

                    <form wire:submit.prevent="submitGuidedAction" class="space-y-4">
                        <input type="hidden" wire:model="selectedTableId" name="selected_table_id">

                        <div>
                            <label for="walkin-name" class="block text-[12px] font-semibold text-slate-700">Name Required</label>
                            <input type="text" wire:model.blur="customer_name"
                                id="walkin-name" aria-required="true" aria-describedby="walkin-name-help walkin-name-error"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_name') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-name-help" class="mt-1 text-[11px] text-slate-500">Enter the guest's full name.</p>
                            @error('customer_name')
                                <p id="walkin-name-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="walkin-phone" class="block text-[12px] font-semibold text-slate-700">Phone Optional</label>
                            <input type="tel" wire:model.blur="customer_phone"
                                id="walkin-phone" aria-describedby="walkin-phone-help walkin-phone-error"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_phone') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-phone-help" class="mt-1 text-[11px] text-slate-500">Leave blank if the guest does not
                                want SMS updates.</p>
                            @error('customer_phone')
                                <p id="walkin-phone-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="walkin-party-size" class="block text-[12px] font-semibold text-slate-700">Party Size Required</label>
                            <input type="number" wire:model.blur="party_size" min="1" max="20"
                                id="walkin-party-size" aria-required="true"
                                aria-describedby="walkin-party-size-help walkin-party-size-error"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('party_size') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-party-size-help" class="mt-1 text-[11px] text-slate-500">Enter 1 to 20 guests.</p>
                            @error('party_size')
                                <p id="walkin-party-size-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-slate-700">Priority Type</label>
                            <select wire:model.live="priority_type"
                                class="mt-1 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-800 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/25">
                                <option value="none">Regular</option>
                                <option value="pwd">PWD</option>
                                <option value="pregnant">Pregnant</option>
                                <option value="senior">Senior</option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">Priority score and PWD accessible-table rule are applied automatically.</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <span class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Selected Table</span>
                            @if ($selectedTable)
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900">Selected Table: {{ $selectedTable->label }}</span>
                                    <button type="button" wire:click="clearSelectedTable"
                                        class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-slate-500 underline-offset-2 hover:bg-white hover:text-slate-800 hover:underline">
                                        Clear
                                    </button>
                                </div>
                            @else
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $hasSeatingOption ? 'Tap one highlighted table marker.' : 'No table is needed for waitlist registration.' }}
                                </p>
                            @endif
                        </div>

                        <button type="submit"
                            @disabled($primaryDisabled)
                            class="mt-1 flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-panel-primary px-4 py-3 text-base font-bold text-panel-on-bright shadow-sm transition-colors hover:bg-panel-primary-hover disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none">
                            <i class="fa-solid {{ $hasSeatingOption ? 'fa-chair' : 'fa-plus' }} text-sm" aria-hidden="true"></i>
                            {{ $primaryLabel }}
                        </button>
                    </form>
                </div>

                <div class="rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm sm:p-4">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-[12px] font-bold uppercase tracking-[0.12em] text-slate-500">Floor Map</p>
                            <h3 class="mt-0.5 text-sm font-bold text-slate-900">Choose from table markers</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                            {{ $walkInTableMarkers->where('selectable', true)->count() }} free match
                        </span>
                    </div>

                    @if ($accessibleRequired)
                        <p class="mb-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-900">
                            PWD accessible-table rule is active.
                        </p>
                    @endif

                    @if ($hasFloorplan)
                        <div class="walkin-map-scroll tc-scrollbar overflow-auto rounded-lg border border-slate-200 bg-slate-100">
                            <div class="walkin-map-stage">
                                <img src="{{ $floorplanUrl }}" alt="Cafe Gervacios floor blueprint" class="walkin-map-image" draggable="false">
                                @foreach ($walkInTableMarkers as $marker)
                                    <button type="button"
                                        wire:key="walkin-map-table-{{ $marker['id'] }}"
                                        wire:click="selectTable({{ $marker['id'] }})"
                                        aria-disabled="{{ $marker['selectable'] ? 'false' : 'true' }}"
                                        aria-pressed="{{ $marker['selected'] ? 'true' : 'false' }}"
                                        class="walkin-table-marker {{ $marker['selectable'] ? 'is-selectable' : 'is-disabled' }} {{ $marker['selected'] ? 'is-selected' : '' }} walkin-table-marker--{{ $marker['status'] }}"
                                        style="left: {{ $marker['x'] }}%; top: {{ $marker['y'] }}%;"
                                        title="{{ $marker['label'] }} · {{ $marker['status_label'] }} · {{ $marker['capacity'] }} seats">
                                        <span class="walkin-table-marker__label">{{ $marker['label'] }}</span>
                                        <span class="walkin-table-marker__meta">
                                            <span>{{ $marker['status_label'] }}</span>
                                            <span>{{ $marker['capacity'] }}p</span>
                                            @if ($marker['is_accessible'])
                                                <span>ACC</span>
                                            @endif
                                        </span>
                                        @unless ($marker['selectable'])
                                            <span class="walkin-table-marker__reason">{{ $marker['reason'] }}</span>
                                        @endunless
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">
                            No blueprint uploaded yet. The walk-in queue can still be used without table selection.
                        </p>
                    @endif
                </div>
            </div>

            @unless ($modalMode)
            <div class="mt-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-[12px] font-bold uppercase tracking-[0.12em] text-slate-500">Priority Queue</p>
                        <h3 class="mt-0.5 text-sm font-bold text-slate-900">Priority guests appear above regular guests</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                        {{ $priorityQueue->count() + $regularQueue->count() }} waiting
                    </span>
                </div>

                @if ($priorityQueue->isNotEmpty())
                    <div class="mb-4">
                        <h4 class="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-900">
                            <i class="fa-solid fa-star-of-life text-[10px]" aria-hidden="true"></i>
                            Priority guests
                        </h4>
                        <div class="space-y-2">
                            @foreach ($priorityQueue as $entry)
                                @php($waitLabel = $entry->waitEstimateLabel())
                                <div class="rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-panel-primary px-1.5 py-0.5 font-mono text-xs font-bold text-white">#{{ $entry->queue_display_number }}</span>
                                        <x-status-badge status="priority" size="xs" />
                                        <span class="text-sm font-semibold text-slate-900">{{ $entry->customer_name }}</span>
                                        <span class="text-xs font-medium text-slate-600">{{ $entry->party_size }}p</span>
                                        <span class="text-xs font-semibold text-slate-600">ETA: {{ $waitLabel }}</span>
                                    </div>
                                    <x-priority-summary :entry="$entry" class="mt-2" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($regularQueue->isNotEmpty())
                    <div>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-600">Regular guests</h4>
                        <div class="space-y-2">
                            @foreach ($regularQueue as $entry)
                                @php($waitLabel = $entry->waitEstimateLabel())
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-800 ring-1 ring-slate-200">#{{ $entry->queue_display_number }}</span>
                                        <span class="text-sm font-semibold text-slate-900">{{ $entry->customer_name }}</span>
                                        <span class="text-xs font-medium text-slate-600">{{ $entry->party_size }}p</span>
                                        <span class="text-xs font-semibold text-slate-600">ETA: {{ $waitLabel }}</span>
                                    </div>
                                    <x-priority-summary :entry="$entry" class="mt-2" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($priorityQueue->isEmpty() && $regularQueue->isEmpty())
                    <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">
                        No waiting guests yet.
                    </p>
                @endif
            </div>
            @endunless
        </div>
    </div>

    <style>
        .walkin-map-scroll {
            max-height: min(500px, 58vh);
        }

        .walkin-map-stage {
            position: relative;
            display: inline-block;
            min-width: min(760px, 100%);
            line-height: 0;
            vertical-align: top;
        }

        .walkin-map-image {
            display: block;
            width: 100%;
            height: auto;
            pointer-events: none;
            user-select: none;
        }

        .walkin-table-marker {
            position: absolute;
            z-index: 8;
            transform: translate(-50%, -50%);
            display: grid;
            min-width: 5rem;
            min-height: 3.5rem;
            gap: 0.12rem;
            border-radius: 0.7rem;
            border: 2px solid #94a3b8;
            background: rgba(255, 255, 255, 0.96);
            padding: 0.48rem 0.56rem;
            text-align: center;
            color: #0f172a;
            box-shadow: 0 8px 18px rgb(15 23 42 / 0.15);
            line-height: 1.05;
            transition: box-shadow 0.14s ease, opacity 0.14s ease, transform 0.14s ease;
        }

        .walkin-table-marker.is-selectable {
            cursor: pointer;
            border-color: #16a34a;
            outline: 4px solid rgba(22, 163, 74, 0.16);
            outline-offset: 3px;
        }

        .walkin-table-marker.is-selectable:hover {
            transform: translate(-50%, -50%) translateY(-1px);
            box-shadow: 0 12px 26px rgb(15 23 42 / 0.22);
        }

        .walkin-table-marker.is-selected {
            border-color: #0f172a;
            outline: 5px solid rgba(15, 23, 42, 0.28);
            outline-offset: 4px;
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.18), 0 14px 30px rgb(15 23 42 / 0.22);
        }

        .walkin-table-marker.is-disabled {
            cursor: not-allowed;
            opacity: 0.42;
            filter: grayscale(0.9);
            background: repeating-linear-gradient(
                135deg,
                rgba(248, 250, 252, 0.95),
                rgba(248, 250, 252, 0.95) 7px,
                rgba(226, 232, 240, 0.95) 7px,
                rgba(226, 232, 240, 0.95) 14px
            );
        }

        .walkin-table-marker--reserved {
            border-color: #d97706;
        }

        .walkin-table-marker--occupied {
            border-color: #dc2626;
        }

        .walkin-table-marker--cleaning {
            border-color: #64748b;
        }

        .walkin-table-marker__label {
            font-size: 0.86rem;
            font-weight: 900;
        }

        .walkin-table-marker__meta,
        .walkin-table-marker__reason {
            display: inline-flex;
            justify-content: center;
            gap: 0.24rem;
            font-size: 0.55rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
        }

        .walkin-table-marker__reason {
            color: #991b1b;
        }
    </style>
</div>
