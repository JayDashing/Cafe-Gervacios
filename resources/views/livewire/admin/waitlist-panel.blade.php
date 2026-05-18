<div data-waitlist-root data-queue-total="{{ $summary['waiting'] ?? 0 }}"
    x-data="{ filtersOpen: false, reservationsOpen: {{ $noShowBookings->isNotEmpty() ? 'true' : 'false' }} }"
    class="flex h-full min-h-full w-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" wire:poll.12s>
    <style>
        [x-cloak] {
            display: none !important;
        }

        [data-waitlist-root] {
            --ops-space: 1rem;
            --ops-card-border: #e2e8f0;
            --ops-card-bg: #ffffff;
            --ops-radius: 0.75rem;
        }

        .wl-sms-status {
            display: inline-flex;
            min-height: 2.25rem;
            width: fit-content;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            align-self: flex-start;
            border: 1px solid;
            border-radius: 0.625rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.8125rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0.01em;
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.72);
        }

        .wl-sms-status.is-active {
            border-color: #c9f1ca;
            background: #effff0;
            color: #1b5e20;
        }

        .wl-sms-status.is-paused {
            border-color: #ffeab8;
            background: #ffffe0;
            color: #7a4a00;
        }

        .wl-sms-status-dot {
            height: 0.5rem;
            width: 0.5rem;
            flex-shrink: 0;
            border-radius: 999px;
        }

        .wl-sms-status.is-active .wl-sms-status-dot {
            background: #4caf50;
            box-shadow: 0 0 0 3px #c9f1ca;
        }

        .wl-sms-status.is-paused .wl-sms-status-dot {
            background: #ffb800;
            box-shadow: 0 0 0 3px #ffeab8;
        }

        @media (min-width: 640px) {
            .wl-sms-status {
                align-self: auto;
            }
        }
    </style>

    @php
        $visibleTabs = [
            'waiting' => ['label' => 'Waiting', 'count' => $summary['waiting'] ?? 0],
            'notified' => ['label' => 'Notified', 'count' => $summary['notified'] ?? 0],
            'seated' => ['label' => 'Seated', 'count' => ($seatedGuests ?? collect())->count()],
        ];
        $activeLabel = [
            'waiting' => 'Waiting',
            'notified' => 'Notified',
            'seated' => 'Seated',
            'cancelled' => 'History',
        ][$activeTab] ?? 'Queue';
        $tone = $systemStatus['tone'] ?? 'ok';
        $priorityWaitingGuests = $waitingGuests->filter(fn ($entry) => $entry->isPriority())->values();
        $regularWaitingGuests = $waitingGuests->filter(fn ($entry) => ! $entry->isPriority())->values();
    @endphp

    <section class="shrink-0 border-b border-slate-200 bg-white p-4" aria-label="Waitlist actions">
        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button type="button" wire:click="openWalkInModal"
                wire:loading.attr="disabled"
                wire:target="openWalkInModal"
                class="tc-admin-btn-primary inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-5 py-2 text-sm font-bold sm:w-auto sm:min-w-44">
                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                Add Walk-in
            </button>

            <div class="wl-sms-status {{ $autoSmsOn ? 'is-active' : 'is-paused' }}" role="status">
                <span class="wl-sms-status-dot" aria-hidden="true"></span>
                Auto-SMS {{ $autoSmsOn ? 'Active' : 'Paused' }}
            </div>
        </div>

        @if ($tone !== 'ok')
            <div class="mt-4 rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-950">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="font-semibold">No suitable free table available.</p>
                    <details class="relative">
                        <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-amber-900">Details</summary>
                        <div class="mt-2 max-w-xl rounded-lg border border-amber-200 bg-white p-3 text-xs leading-relaxed text-amber-950 sm:absolute sm:right-0 sm:z-30 sm:w-96 sm:shadow-lg">
                            <p class="font-semibold">{{ $systemStatus['headline'] ?? 'Table availability needs attention.' }}</p>
                            @if (count($systemStatus['hints'] ?? []) > 0)
                                <ul class="mt-2 list-disc space-y-1 pl-4">
                                    @foreach ($systemStatus['hints'] as $hint)
                                        <li>{{ $hint }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (auth()->user()->isAdmin() && ($systemStatus['resume_auto_sms_available'] ?? false))
                                <button type="button" wire:click="resumeAutoSms" wire:loading.attr="disabled"
                                    class="mt-3 inline-flex min-h-9 items-center justify-center rounded-lg bg-panel-primary px-3 py-2 text-xs font-semibold text-white disabled:opacity-60">
                                    Resume SMS
                                </button>
                            @endif
                        </div>
                    </details>
                </div>
            </div>
        @endif
    </section>

    <section class="flex min-h-0 flex-1 flex-col bg-white p-4" aria-labelledby="current-queue-title">
        <div class="flex min-h-0 w-full flex-1 flex-col gap-4">
            <div class="flex min-h-11 w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="current-queue-title" class="text-base font-bold leading-tight text-slate-950">Current Queue</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $activeLabel }} guests staff can act on now.</p>
                </div>
                <button type="button"
                    x-on:click="filtersOpen = !filtersOpen"
                    class="tc-admin-btn-secondary inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold sm:w-32"
                    x-bind:aria-expanded="filtersOpen.toString()">
                    <i class="fa-solid fa-sliders text-xs" aria-hidden="true"></i>
                    Filter
                </button>
            </div>

            <div class="grid w-full grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1">
                @foreach ($visibleTabs as $value => $tab)
                    <button type="button" wire:click="setActiveTab('{{ $value }}')"
                        class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-lg px-2 text-sm font-bold leading-none transition {{ $activeTab === $value ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white' }}">
                        {{ $tab['label'] }}
                        <span class="inline-flex min-w-5 justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums {{ $activeTab === $value ? 'bg-white/15 text-white' : 'bg-white text-slate-500' }}">
                            {{ $tab['count'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            <div x-cloak x-show="filtersOpen" x-transition
                class="w-full rounded-xl border border-slate-200 bg-white p-4">
                <div class="grid gap-4">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Search</span>
                        <span class="relative block">
                            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                            <input type="search" wire:model.live.debounce.250ms="search"
                                placeholder="Guest, phone, queue number"
                                class="min-h-11 w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-800 shadow-sm transition">
                        </span>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Priority</span>
                            <select wire:model.live="priorityFilter"
                                class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 shadow-sm transition">
                                <option value="all">All priorities</option>
                                <option value="priority">Priority only</option>
                                <option value="pwd">PWD</option>
                                <option value="senior">Senior</option>
                                <option value="pregnant">Pregnant</option>
                                <option value="standard">Regular</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Party</span>
                            <select wire:model.live="partySizeFilter"
                                class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 shadow-sm transition">
                                <option value="all">Any size</option>
                                <option value="1-2">1-2 guests</option>
                                <option value="3-4">3-4 guests</option>
                                <option value="5-plus">5+ guests</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">View</span>
                            <select wire:model.live="activeTab"
                                class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 shadow-sm transition">
                                <option value="waiting">Waiting</option>
                                <option value="notified">Notified</option>
                                <option value="seated">Seated</option>
                                <option value="cancelled">Cancelled history</option>
                            </select>
                        </label>
                    </div>

                    @if (auth()->user()->isAdmin())
                        <div class="flex flex-wrap gap-2 border-t border-slate-200 pt-3">
                            <button type="button" wire:click="openBusyHoursModal"
                                class="tc-admin-btn-secondary inline-flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold">
                                <i class="fa-regular fa-clock text-xs" aria-hidden="true"></i>
                                Busy Hours
                            </button>
                            <button type="button" wire:click="togglePeakOverride"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold ring-1 transition {{ $peakOverrideOn ? 'bg-sky-50 text-sky-950 ring-sky-200' : 'bg-white text-slate-700 ring-slate-200' }}">
                                Override {{ $peakOverrideOn ? 'On' : 'Off' }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @error('seatCustomer')
                <p class="w-full rounded-xl border border-red-200 bg-white px-4 py-3 text-xs font-semibold text-red-700">
                    <i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ $message }}
                </p>
            @enderror

            <div class="w-full flex-1 space-y-3 overflow-y-auto pr-1 tc-scrollbar">
                @if ($activeTab === 'waiting')
                    @foreach ($priorityWaitingGuests as $entry)
                        @include('livewire.admin.partials.waitlist-entry-card', [
                            'entry' => $entry,
                            'availableTables' => $availableTables,
                            'selectedTableId' => $selectedTableId,
                            'highlightedQueueEntryId' => $highlightedQueueEntryId,
                            'mode' => 'waiting',
                        ])
                    @endforeach

                    @if ($regularWaitingGuests->isNotEmpty())
                        <span class="sr-only">Waiting Guests - Regular</span>
                        @foreach ($regularWaitingGuests as $entry)
                            @include('livewire.admin.partials.waitlist-entry-card', [
                                'entry' => $entry,
                                'availableTables' => $availableTables,
                                'selectedTableId' => $selectedTableId,
                                'highlightedQueueEntryId' => $highlightedQueueEntryId,
                                'mode' => 'waiting',
                            ])
                        @endforeach
                    @endif

                    @if ($waitingGuests->isEmpty())
                        <p class="flex min-h-[72px] w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white px-4 text-center text-sm text-slate-500">
                            No waiting guests.
                        </p>
                    @endif
                @elseif ($activeTab === 'notified')
                    @forelse ($filteredNotifiedGuests as $entry)
                        @include('livewire.admin.partials.waitlist-entry-card', [
                            'entry' => $entry,
                            'availableTables' => $availableTables,
                            'selectedTableId' => $selectedTableId,
                            'highlightedQueueEntryId' => $highlightedQueueEntryId,
                            'mode' => 'notified',
                        ])
                    @empty
                        <p class="flex min-h-[72px] w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white px-4 text-center text-sm text-slate-500">
                            No notified guests.
                        </p>
                    @endforelse
                @elseif ($activeTab === 'seated')
                    @forelse ($filteredSeatedGuests as $entry)
                        @include('livewire.admin.partials.waitlist-entry-card', [
                            'entry' => $entry,
                            'availableTables' => $availableTables,
                            'selectedTableId' => $selectedTableId,
                            'highlightedQueueEntryId' => $highlightedQueueEntryId,
                            'mode' => 'seated',
                        ])
                    @empty
                        <p class="flex min-h-[72px] w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white px-4 text-center text-sm text-slate-500">
                            No seated guests.
                        </p>
                    @endforelse
                @else
                    @forelse ($filteredCancelledGuests as $entry)
                        @include('livewire.admin.partials.waitlist-entry-card', [
                            'entry' => $entry,
                            'availableTables' => $availableTables,
                            'selectedTableId' => $selectedTableId,
                            'highlightedQueueEntryId' => $highlightedQueueEntryId,
                            'mode' => 'cancelled',
                        ])
                    @empty
                        <p class="flex min-h-[72px] w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white px-4 text-center text-sm text-slate-500">
                            No cancelled history.
                        </p>
                    @endforelse
                @endif
            </div>
        </div>
    </section>

    <section class="shrink-0 border-t border-slate-200 bg-white p-4" aria-labelledby="reservations-checkin-title">
        <button type="button"
            x-on:click="reservationsOpen = !reservationsOpen"
            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl text-left"
            x-bind:aria-expanded="reservationsOpen.toString()">
            <span>
                <span id="reservations-checkin-title" class="block text-sm font-bold text-slate-950">Reservations Awaiting Check-in</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ $noShowBookings->count() }} pending</span>
            </span>
            <i class="fa-solid fa-chevron-down text-xs text-slate-500 transition" x-bind:class="reservationsOpen ? 'rotate-180' : ''" aria-hidden="true"></i>
        </button>

        @error('markBookingNoShow')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
        @enderror

        @if ($noShowBookings->isEmpty())
            <p class="mt-4 flex min-h-[72px] w-full items-center rounded-xl border border-dashed border-slate-200 bg-white px-4 text-sm text-slate-500">No reservations awaiting check-in.</p>
        @else
            <div x-cloak x-show="reservationsOpen" x-transition class="mt-4 space-y-3">
                @foreach ($noShowBookings as $booking)
                    <div class="flex w-full flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 text-sm text-slate-900">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-semibold">{{ $booking->customer_name }}</span>
                                <span class="font-mono text-xs text-slate-600">{{ $booking->booking_ref }}</span>
                                <span class="text-xs text-slate-600">{{ $booking->party_size }} guests</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">{{ $booking->booked_at?->format('M j, g:i A') }}</p>
                        </div>
                        @if ($booking->payment_status === 'pending' && $booking->status === 'pending')
                            <span class="text-xs font-semibold text-slate-600">Awaiting payment</span>
                        @else
                            <button type="button" wire:click="markBookingNoShow({{ $booking->id }})"
                                wire:loading.attr="disabled"
                                wire:confirm="Mark this reservation as no-show? The customer will receive the no-show SMS and any assigned table will be released."
                                class="tc-admin-btn-secondary inline-flex min-h-11 items-center justify-center rounded-xl px-3 py-2 text-sm font-bold disabled:cursor-wait disabled:opacity-60">
                                No-show
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($showWalkInModal)
        <div class="fixed inset-0 z-[140] flex items-center justify-center bg-slate-950/45 p-3 sm:p-4"
            role="dialog" aria-modal="true" aria-labelledby="walkin-modal-title" wire:click.self="closeWalkInModal">
            <div class="flex max-w-[960px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                style="width: min(960px, 96vw); max-height: 90vh;">
                <div class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-7">
                    <div>
                        <h3 id="walkin-modal-title" class="text-xl font-bold text-slate-950">Register Walk-in</h3>
                    </div>
                    <button type="button" wire:click="closeWalkInModal"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                        aria-label="Close Register Walk-in modal">
                        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-hidden bg-white px-5 py-4 sm:px-7 sm:py-5">
                    @livewire('staff-walk-in-queue', ['modalMode' => true], key('walk-in-modal-' . $walkInModalKey))
                </div>
            </div>
        </div>
    @endif

    @if ($showBusyHoursModal)
        <div class="fixed inset-0 z-[120] flex items-center justify-center bg-black/40 p-4" wire:click.self="closeBusyHoursModal">
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl" @click.stop>
                <h3 class="text-base font-bold text-slate-900">Busy hours (auto table-ready SMS)</h3>
                <p class="mt-1 text-xs text-slate-600">When learn from waitlist is on, peak hours are estimated from traffic; otherwise fixed window applies.</p>
                <div class="mt-4 space-y-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                        <input type="checkbox" wire:model.live="busyLearnFromQueue" class="rounded border-slate-300">
                        Learn busy hours from waitlist joins
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">Start</label>
                            <input type="time" wire:model="busyPeakStart"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600">End</label>
                            <input type="time" wire:model="busyPeakEnd"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex flex-col-reverse items-stretch justify-center gap-2 sm:flex-row sm:items-center">
                    <button type="button" wire:click="closeBusyHoursModal"
                        class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center px-4 py-2 text-sm">Cancel</button>
                    <button type="button" wire:click="saveBusyHours"
                        class="tc-admin-btn-primary inline-flex min-h-10 items-center justify-center px-4 py-2 text-sm">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if ($seatQuickPickEntryId && $quickPickEntry)
        <div class="fixed inset-0 z-[130] flex items-center justify-center bg-black/45 p-4" wire:click.self="closeSeatQuickPick">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl"
                @click.stop>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Seat {{ $quickPickEntry->customer_name }}</h3>
                        <x-priority-summary :entry="$quickPickEntry" class="mt-2" />
                        <p class="text-xs text-slate-600">{{ $quickPickEntry->party_size }} guests - best fit first</p>
                    </div>
                    <button type="button" wire:click="closeSeatQuickPick"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950"
                        aria-label="Close">
                        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="mt-4 grid gap-2">
                    @forelse ($sortedQuickTables as $t)
                        <button type="button" wire:click="seatFromQuickPick({{ $quickPickEntry->id }}, {{ $t->id }})"
                            wire:loading.attr="disabled"
                            class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-900 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-wait disabled:opacity-60">
                            <span>{{ $t->capacity }} seats ({{ $t->label }})</span>
                            <span class="text-xs font-medium text-slate-600">{{ $t->capacity }}p cap -
                                @if ($t->capacity == $quickPickEntry->party_size)
                                    <span class="text-emerald-700">exact fit</span>
                                @else
                                    +{{ $t->capacity - $quickPickEntry->party_size }} spare
                                @endif
                                @if ($t->is_accessible)
                                    <span class="ml-1 text-sky-700">Accessible</span>
                                @endif
                            </span>
                        </button>
                    @empty
                        <p class="text-sm text-amber-800">No free table fits this party. Free a larger table or adjust capacity.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @if ($floorSeatTableId && $floorSeatTable)
        <div class="fixed inset-0 z-[132] flex items-center justify-center bg-black/45 p-4" wire:click.self="closeFloorMapSeatModal">
            <div class="max-h-[85vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl"
                @click.stop>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Floor Map</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-slate-900">Seat waitlist guest at {{ $floorSeatTable->label }}</h3>
                            <x-status-badge :status="$floorSeatTable->status" size="xs" />
                        </div>
                        <p class="mt-1 text-xs text-slate-600">
                            {{ (int) $floorSeatTable->capacity }} seats{{ $floorSeatTable->is_accessible ? ' - Accessible' : '' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeFloorMapSeatModal"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950"
                        aria-label="Close waitlist seating modal">
                        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="mt-4 grid gap-2">
                    @forelse ($floorSeatCandidates as $entry)
                        <button type="button" wire:click="seatWaitingGuestAtFloorTable({{ $entry->id }})"
                            wire:loading.attr="disabled"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-900 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-wait disabled:opacity-60">
                            <span class="min-w-0">
                                <span class="block truncate">{{ $entry->customer_name }}</span>
                                <span class="mt-0.5 block text-xs font-medium text-slate-600">
                                    Queue #{{ $entry->queue_display_number ?? $entry->id }} - {{ $entry->party_size }} guests - ETA: {{ $entry->waitEstimateLabel() }}
                                </span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5">
                                @if ($entry->isPriority())
                                    <x-status-badge :status="$entry->priority_type ?: 'priority'" size="xs" />
                                @endif
                                <span class="rounded-full bg-white px-2 py-1 text-[11px] font-bold text-slate-600">Seat</span>
                            </span>
                        </button>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                            No waiting guest fits this free table.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <script>
        (function () {
            function fmt(ms) {
                if (ms <= 0) return '0:00';
                var s = Math.floor(ms / 1000);
                var m = Math.floor(s / 60);
                s = s % 60;
                return m + ':' + (s < 10 ? '0' : '') + s;
            }

            function tick() {
                document.querySelectorAll('[data-hold-expires]').forEach(function (el) {
                    var iso = el.getAttribute('data-hold-expires');
                    if (!iso) return;
                    var end = Date.parse(iso);
                    var span = el.querySelector('.tc-hold-remaining');
                    if (!span) return;
                    var left = end - Date.now();
                    span.textContent = left > 0 ? fmt(left) + ' left' : 'Expired';
                });
            }

            tick();
            setInterval(tick, 1000);
            document.addEventListener('livewire:navigated', tick);
            document.addEventListener('livewire:init', function () {
                if (typeof Livewire === 'undefined' || !Livewire.hook) return;
                Livewire.hook('morph.updated', function () {
                    tick();
                });
            });
        })();
    </script>
</div>
