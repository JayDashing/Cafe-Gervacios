<div data-waitlist-root data-queue-total="{{ $summary['waiting'] ?? 0 }}"
    class="w-full space-y-4 px-3 py-3 sm:px-4 sm:py-4" wire:poll.12s>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @php
        $summaryCards = [
            ['label' => 'Waiting Guests', 'value' => $summary['waiting'] ?? 0, 'icon' => 'fa-users', 'tone' => 'text-slate-700'],
            ['label' => 'Notified Guests', 'value' => $summary['notified'] ?? 0, 'icon' => 'fa-bell', 'tone' => 'text-amber-700'],
            ['label' => 'Seated Today', 'value' => $summary['seated_today'] ?? 0, 'icon' => 'fa-chair', 'tone' => 'text-emerald-700'],
            ['label' => 'Cancelled Today', 'value' => $summary['cancelled_today'] ?? 0, 'icon' => 'fa-ban', 'tone' => 'text-rose-700'],
        ];
        $tabs = [
            'waiting' => ['label' => 'Waiting', 'count' => $summary['waiting'] ?? 0],
            'notified' => ['label' => 'Notified', 'count' => $summary['notified'] ?? 0],
            'seated' => ['label' => 'Seated', 'count' => ($seatedGuests ?? collect())->count()],
            'cancelled' => ['label' => 'Cancelled', 'count' => ($cancelledGuests ?? collect())->count()],
        ];
        $tone = $systemStatus['tone'] ?? 'ok';
    @endphp

    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            @if (auth()->user()->isAdmin())
                <button type="button" wire:click="toggleAutoSms"
                    class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold shadow-sm ring-1 transition {{ $autoSmsOn ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-amber-50 text-amber-950 ring-amber-200' }}">
                    <span class="h-2 w-2 rounded-full {{ $autoSmsOn ? 'bg-emerald-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                    Auto-SMS {{ $autoSmsOn ? 'Active' : 'Paused' }}
                </button>
                <button type="button" wire:click="openBusyHoursModal"
                    class="tc-admin-btn-secondary inline-flex min-h-8 items-center gap-1.5 px-3 py-1.5 text-xs">
                    <i class="fa-regular fa-clock text-xs" aria-hidden="true"></i>
                    Busy Hours
                </button>
            @endif
            <button type="button" wire:click="togglePeakOverride"
                class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold shadow-sm ring-1 transition {{ $peakOverrideOn ? 'bg-sky-50 text-sky-950 ring-sky-200' : 'border border-slate-200 bg-white text-slate-700 ring-transparent' }}">
                Override {{ $peakOverrideOn ? 'On' : 'Off' }}
            </button>
        </div>
        <button type="button" wire:click="openWalkInModal"
            class="tc-admin-btn-primary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs">
            <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>
            Add Walk-in
        </button>
    </div>

    @if ($tone !== 'ok')
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-950 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="font-semibold">No suitable free table available.</p>
                <details class="relative">
                    <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-amber-900">View details</summary>
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
                                class="mt-3 inline-flex min-h-8 items-center justify-center rounded-lg bg-panel-primary px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60">
                                Resume SMS
                            </button>
                        @endif
                    </div>
                </details>
            </div>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summaryCards as $card)
            <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $card['value'] }}</p>
                    </div>
                    <i class="fa-solid {{ $card['icon'] }} {{ $card['tone'] }}" aria-hidden="true"></i>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_170px_170px] lg:items-end">
            <label class="block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Search guest or phone</span>
                <span class="relative block">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                    <input type="search" wire:model.live.debounce.250ms="search"
                        placeholder="Search name, phone, queue number"
                        class="min-h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-sm text-slate-800 shadow-sm transition focus:bg-white">
                </span>
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Priority type</span>
                <select wire:model.live="priorityFilter"
                    class="min-h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 shadow-sm transition focus:bg-white">
                    <option value="all">All priorities</option>
                    <option value="priority">Priority only</option>
                    <option value="pwd">PWD</option>
                    <option value="senior">Senior</option>
                    <option value="pregnant">Pregnant</option>
                    <option value="standard">Regular</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Party size</span>
                <select wire:model.live="partySizeFilter"
                    class="min-h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 shadow-sm transition focus:bg-white">
                    <option value="all">Any size</option>
                    <option value="1-2">1-2 guests</option>
                    <option value="3-4">3-4 guests</option>
                    <option value="5-plus">5+ guests</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Status</span>
                <select wire:model.live="activeTab"
                    class="min-h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 shadow-sm transition focus:bg-white">
                    <option value="waiting">Waiting</option>
                    <option value="notified">Notified</option>
                    <option value="seated">Seated</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
        <div class="grid gap-1 sm:grid-cols-4">
            @foreach ($tabs as $value => $tab)
                <button type="button" wire:click="setActiveTab('{{ $value }}')"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition {{ $activeTab === $value ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ $tab['label'] }}
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums {{ $activeTab === $value ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600' }}">
                        {{ $tab['count'] }}
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    @error('seatCustomer')
        <p class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
            <i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ $message }}
        </p>
    @enderror

    <section class="rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm">
        @if ($activeTab === 'waiting')
            @php
                $priorityWaitingGuests = $waitingGuests->filter(fn ($entry) => $entry->isPriority())->values();
                $regularWaitingGuests = $waitingGuests->filter(fn ($entry) => ! $entry->isPriority())->values();
            @endphp
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950">Waiting Guests</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Priority guests stay above regular guests.</p>
                </div>
            </div>
            <div class="space-y-2">
                @if ($priorityWaitingGuests->isNotEmpty())
                    <h3 class="px-1 pb-1 text-xs font-bold uppercase tracking-wide text-slate-500">Waiting Guests - Priority Queue</h3>
                    @foreach ($priorityWaitingGuests as $entry)
                        @include('livewire.admin.partials.waitlist-entry-card', [
                            'entry' => $entry,
                            'availableTables' => $availableTables,
                            'selectedTableId' => $selectedTableId,
                            'highlightedQueueEntryId' => $highlightedQueueEntryId,
                            'mode' => 'waiting',
                        ])
                    @endforeach
                @endif

                @if ($regularWaitingGuests->isNotEmpty())
                    <h3 class="px-1 pb-1 pt-2 text-xs font-bold uppercase tracking-wide text-slate-500">Waiting Guests - Regular</h3>
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
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-8 text-center text-sm text-slate-500">
                        No waiting guests match the current filters.
                    </p>
                @endif
            </div>
        @elseif ($activeTab === 'notified')
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-950">Notified Guests</h2>
                <p class="mt-0.5 text-xs text-slate-500">Guests with active table holds and confirmation codes.</p>
            </div>
            <div class="space-y-2">
                @forelse ($filteredNotifiedGuests as $entry)
                    @include('livewire.admin.partials.waitlist-entry-card', [
                        'entry' => $entry,
                        'availableTables' => $availableTables,
                        'selectedTableId' => $selectedTableId,
                        'highlightedQueueEntryId' => $highlightedQueueEntryId,
                        'mode' => 'notified',
                    ])
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-8 text-center text-sm text-slate-500">
                        No notified guests match the current filters.
                    </p>
                @endforelse
            </div>
        @elseif ($activeTab === 'seated')
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-950">Seated Guests</h2>
                <p class="mt-0.5 text-xs text-slate-500">Recently seated walk-in guests.</p>
            </div>
            <div class="space-y-2">
                @forelse ($filteredSeatedGuests as $entry)
                    @include('livewire.admin.partials.waitlist-entry-card', [
                        'entry' => $entry,
                        'availableTables' => $availableTables,
                        'selectedTableId' => $selectedTableId,
                        'highlightedQueueEntryId' => $highlightedQueueEntryId,
                        'mode' => 'seated',
                    ])
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-8 text-center text-sm text-slate-500">
                        No seated guests match the current filters.
                    </p>
                @endforelse
            </div>
        @else
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-950">Cancelled Guests</h2>
                <p class="mt-0.5 text-xs text-slate-500">Recently cancelled waitlist entries.</p>
            </div>
            <div class="space-y-2">
                @forelse ($filteredCancelledGuests as $entry)
                    @include('livewire.admin.partials.waitlist-entry-card', [
                        'entry' => $entry,
                        'availableTables' => $availableTables,
                        'selectedTableId' => $selectedTableId,
                        'highlightedQueueEntryId' => $highlightedQueueEntryId,
                        'mode' => 'cancelled',
                    ])
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-8 text-center text-sm text-slate-500">
                        No cancelled guests match the current filters.
                    </p>
                @endforelse
            </div>
        @endif
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-950">Reservations Awaiting Check-in</h2>
                <p class="mt-0.5 text-xs text-slate-500">Manual no-show action stays available for reservation follow-up.</p>
            </div>
        </div>
        @error('markBookingNoShow')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ $message }}</p>
        @enderror
        <div class="mt-3 space-y-2">
            @forelse ($noShowBookings as $booking)
                <div class="flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50/70 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 text-sm text-slate-900">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-semibold">{{ $booking->customer_name }}</span>
                            <span class="font-mono text-xs text-slate-600">{{ $booking->booking_ref }}</span>
                            <x-status-badge :status="$booking->status" size="xs" />
                            <span class="text-xs text-slate-600">{{ $booking->party_size }} guests</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">{{ $booking->booked_at?->format('M j, g:i A') }}</p>
                    </div>
                    @if ($booking->payment_status === 'pending' && $booking->status === 'pending')
                        <span class="text-xs font-semibold text-slate-600">Awaiting payment</span>
                    @else
                        <button type="button" wire:click="markBookingNoShow({{ $booking->id }})"
                            wire:confirm="Mark this reservation as no-show? The customer will receive the no-show SMS and any assigned table will be released."
                            class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center px-3 py-2 text-xs">
                            Mark as No-show
                        </button>
                    @endif
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-center text-sm text-slate-500">None</p>
            @endforelse
        </div>
    </section>

    @if ($showWalkInModal)
        <div class="fixed inset-0 z-[140] flex items-end justify-center bg-slate-950/55 p-3 sm:items-center sm:p-4"
            role="dialog" aria-modal="true" aria-labelledby="walkin-modal-title" wire:click.self="closeWalkInModal">
            <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-5">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Waitlist Management</p>
                        <h3 id="walkin-modal-title" class="mt-0.5 text-base font-bold text-slate-950">Register Walk-in</h3>
                    </div>
                    <button type="button" wire:click="closeWalkInModal"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Close Register Walk-in modal">
                        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4 tc-scrollbar">
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
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeBusyHoursModal"
                        class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center px-4 py-2 text-sm">Cancel</button>
                    <button type="button" wire:click="saveBusyHours"
                        class="tc-admin-btn-primary inline-flex min-h-10 items-center justify-center px-4 py-2 text-sm">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if ($seatQuickPickEntryId && $quickPickEntry)
        <div class="fixed inset-0 z-[130] flex items-end justify-center bg-black/45 p-4 sm:items-center" wire:click.self="closeSeatQuickPick">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl"
                @click.stop>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Seat {{ $quickPickEntry->customer_name }}</h3>
                        <x-priority-summary :entry="$quickPickEntry" class="mt-2" />
                        <p class="text-xs text-slate-600">{{ $quickPickEntry->party_size }} guests - best fit first</p>
                    </div>
                    <button type="button" wire:click="closeSeatQuickPick" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                        aria-label="Close">&times;</button>
                </div>
                <div class="mt-4 grid gap-2">
                    @forelse ($sortedQuickTables as $t)
                        <button type="button" wire:click="seatFromQuickPick({{ $quickPickEntry->id }}, {{ $t->id }})"
                            class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-900 transition hover:border-sky-300 hover:bg-sky-50">
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
        <div class="fixed inset-0 z-[132] flex items-end justify-center bg-black/45 p-4 sm:items-center" wire:click.self="closeFloorMapSeatModal">
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
                        class="inline-flex min-h-[36px] min-w-[36px] shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100"
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
