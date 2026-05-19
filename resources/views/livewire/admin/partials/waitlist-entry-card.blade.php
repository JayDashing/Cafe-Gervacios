@props([
    'entry',
    'availableTables',
    'selectedTableId',
    'highlightedQueueEntryId' => null,
    'mode' => 'waiting',
])

@php
    $isAdmin = auth()->user()?->isAdmin();
    $priorityStatus = $entry->isPriority() ? $entry->priority_type : 'standard';
    $priorityLabel = match ((string) $priorityStatus) {
        'pwd' => 'PWD',
        'senior' => 'Senior',
        'pregnant' => 'Pregnant',
        default => 'Regular',
    };
    $createdAt = $entry->joined_at ?? $entry->created_at;
    $holdIso = $entry->hold_expires_at?->toIso8601String();
    $waitLabel = $entry->waitEstimateLabel();
@endphp

<article
    wire:key="waitlist-entry-card-{{ $entry->id }}-{{ $entry->status }}"
    x-data="{ detailsOpen: false }"
    x-on:keydown.escape.window="detailsOpen = false"
    @if (in_array($mode, ['waiting', 'notified'], true))
        x-on:click="$event.target.closest('button, a, input, select, textarea') || $wire.highlightCompatibleTablesForEntry({{ $entry->id }})"
    @endif
    class="rounded-xl border bg-white p-3 shadow-sm transition hover:border-slate-300 {{ (int) ($highlightedQueueEntryId ?? 0) === (int) $entry->id ? 'border-sky-300 bg-sky-50/70 ring-2 ring-sky-200' : 'border-slate-200' }} {{ in_array($mode, ['waiting', 'notified'], true) ? 'cursor-pointer' : '' }}">
    <div class="flex flex-col gap-2.5 2xl:flex-row 2xl:items-center 2xl:justify-between">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <span
                class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-900 font-mono text-base font-bold text-white">
                #{{ $entry->queue_display_number ?? $entry->id }}
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-base font-bold leading-tight text-slate-950">{{ $entry->customer_name }}</h3>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ (int) $entry->party_size }} guests</span>
                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 ring-1 ring-slate-200">ETA: {{ $waitLabel }}</span>
                    <x-status-badge :status="$priorityStatus" :label="$priorityLabel" size="xs" />
                </div>
            </div>
        </div>

        <div class="grid shrink-0 grid-cols-[minmax(0,1fr)_auto] gap-2 2xl:min-w-[14rem]">
            @if ($mode === 'waiting')
                <button type="button"
                    wire:click="sendSmsManually({{ $entry->id }})"
                    wire:loading.attr="disabled"
                    wire:target="sendSmsManually"
                    class="tc-admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold disabled:opacity-60">
                    <i class="fa-solid fa-paper-plane text-[10px]" aria-hidden="true"></i>
                    Notify
                </button>
            @elseif ($mode === 'notified')
                <button type="button"
                    x-on:click="detailsOpen = true"
                    class="tc-admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold">
                    <i class="fa-solid fa-chair text-[10px]" aria-hidden="true"></i>
                    Seat
                </button>
            @else
                <button type="button"
                    x-on:click="detailsOpen = true"
                    class="tc-admin-btn-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold">
                    <i class="fa-solid fa-circle-info text-[10px]" aria-hidden="true"></i>
                    Details
                </button>
            @endif

            <div class="relative">
                <button type="button"
                    x-on:click.stop="detailsOpen = true"
                    class="tc-admin-btn-secondary inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-bold"
                    aria-haspopup="dialog"
                    aria-controls="waitlist-entry-details-{{ $entry->id }}"
                    x-bind:aria-expanded="detailsOpen.toString()">
                    More
                    <i class="fa-solid fa-circle-info text-[10px]" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    <div
        x-cloak
        x-show="detailsOpen"
        id="waitlist-entry-details-{{ $entry->id }}"
        class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-950/30 p-4 md:pl-64"
        role="dialog"
        aria-modal="true"
        x-on:keydown.escape.window="detailsOpen = false">
        <button type="button" class="absolute inset-0 cursor-default" x-on:click="detailsOpen = false" aria-label="Close details"></button>
        <section
            x-transition
            class="relative z-10 flex w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            x-on:click.stop>
            <header class="flex items-start justify-between gap-5 border-b border-slate-200 px-7 py-6">
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-500">Queue #{{ $entry->queue_display_number ?? $entry->id }}</p>
                    <h2 class="mt-1 truncate text-2xl font-semibold text-slate-950">{{ $entry->customer_name }}</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="inline-flex min-h-7 items-center rounded-full bg-slate-100 px-3 text-xs font-bold uppercase tracking-wide text-slate-600">
                            {{ $priorityLabel }}
                        </span>
                        <span class="inline-flex min-h-7 items-center rounded-full bg-slate-100 px-3 text-xs font-bold uppercase tracking-wide text-slate-600">
                            {{ ucfirst((string) $entry->status) }}
                        </span>
                    </div>
                </div>
                <button type="button"
                    x-on:click="detailsOpen = false"
                    class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50"
                    aria-label="Close details">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="max-h-[calc(100vh-10rem)] overflow-y-auto px-7 py-6 tc-scrollbar">
                <div class="grid gap-0 overflow-hidden rounded-xl border border-slate-200 bg-white text-sm sm:grid-cols-2">
                    <div class="flex min-h-12 items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 sm:border-r">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Party</span>
                        <span class="font-semibold text-slate-950">{{ (int) $entry->party_size }} guests</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 border-b border-slate-100 px-4 py-3">
                        <span class="font-bold uppercase tracking-wide text-slate-500">ETA</span>
                        <span class="font-semibold text-slate-950">{{ $waitLabel }}</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 sm:border-r">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Phone</span>
                        <span class="truncate text-right font-semibold text-slate-950">{{ filled($entry->customer_phone) ? $entry->customer_phone : 'Not provided' }}</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 border-b border-slate-100 px-4 py-3">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Email</span>
                        <span class="truncate text-right font-semibold text-slate-950">{{ filled($entry->customer_email) ? $entry->customer_email : 'Not provided' }}</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 px-4 py-3 sm:border-r">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Source</span>
                        <span class="font-semibold uppercase text-slate-950">{{ $entry->source ?? 'web' }}</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 px-4 py-3">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Created</span>
                        <span class="font-semibold text-slate-950">{{ $createdAt?->format('M j, g:i A') ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 border-t border-slate-100 px-4 py-3 sm:border-r">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Priority Score</span>
                        <span class="font-semibold text-slate-950">{{ (int) $entry->priority_score }}</span>
                    </div>
                    <div class="flex min-h-12 items-center justify-between gap-4 border-t border-slate-100 px-4 py-3">
                        <span class="font-bold uppercase tracking-wide text-slate-500">Seating Rule</span>
                        <span class="truncate text-right font-semibold text-slate-950">
                            {{ $entry->needs_accessible ? 'Accessible table required' : 'Standard table allowed' }}
                        </span>
                    </div>
                    @if ($entry->hold_expires_at)
                        <div class="flex min-h-12 items-center justify-between gap-4 border-t border-slate-100 px-4 py-3 sm:col-span-2">
                            <span class="font-bold uppercase tracking-wide text-slate-500">Hold</span>
                            <span class="flex items-center gap-2 font-semibold text-slate-950">
                                {{ $entry->hold_expires_at->format('g:i A') }}
                                <span data-hold-expires="{{ $holdIso }}" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                                    <span class="tc-hold-remaining">--</span>
                                </span>
                            </span>
                        </div>
                    @endif
                </div>

                @if (in_array($mode, ['waiting', 'notified'], true))
                    <div class="mt-6 border-t border-slate-100 pt-6">
                        @include('livewire.admin.partials.waitlist-seat-controls', [
                            'entry' => $entry,
                            'availableTables' => $availableTables,
                            'selectedTableId' => $selectedTableId,
                            'showHoldActions' => $mode === 'notified',
                        ])

                    </div>
                @endif
            </div>

            @if ($mode === 'notified' || $isAdmin)
                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-7 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($mode === 'notified' && $entry->hold_expires_at)
                            <button type="button"
                                wire:click="extendHold({{ $entry->id }})"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-wait disabled:opacity-60">
                                Extend Hold +5m
                            </button>
                        @endif
                    </div>

                    @if ($isAdmin)
                        <button type="button"
                            wire:click="cancelEntry({{ $entry->id }})"
                            wire:loading.attr="disabled"
                            wire:confirm="{{ $mode === 'notified' ? 'Remove this guest from the queue and release the held table?' : 'Remove this guest from the queue?' }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-wait disabled:opacity-60">
                            Cancel Entry
                        </button>
                    @endif
                </footer>
            @endif
        </section>
    </div>
</article>
