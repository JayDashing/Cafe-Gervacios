@props([
    'entry',
    'availableTables',
    'selectedTableId',
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
    class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <span
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-900 font-mono text-sm font-bold text-white">
                #{{ $entry->queue_display_number ?? $entry->id }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="truncate text-sm font-semibold text-slate-950">{{ $entry->customer_name }}</h3>
                    <span class="text-xs font-semibold text-slate-500">{{ (int) $entry->party_size }} guests</span>
                    <span class="text-xs font-semibold text-slate-500">ETA: {{ $waitLabel }}</span>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <x-status-badge :status="$priorityStatus" :label="$priorityLabel" size="xs" />
                    <x-status-badge :status="$entry->status" size="xs" />
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-2 lg:min-w-[18rem]">
            @if ($mode === 'waiting')
                <button type="button"
                    wire:click="sendSmsManually({{ $entry->id }})"
                    wire:loading.attr="disabled"
                    wire:target="sendSmsManually"
                    class="tc-admin-btn-primary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-xs disabled:opacity-60">
                    <i class="fa-solid fa-paper-plane text-[10px]" aria-hidden="true"></i>
                    Notify Guest
                </button>
            @elseif ($mode === 'notified')
                <button type="button"
                    x-on:click="detailsOpen = true"
                    class="tc-admin-btn-primary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-xs">
                    <i class="fa-solid fa-chair text-[10px]" aria-hidden="true"></i>
                    Seat Guest
                </button>
            @else
                <button type="button"
                    x-on:click="detailsOpen = true"
                    class="tc-admin-btn-primary inline-flex min-h-10 items-center justify-center gap-2 px-3 py-2 text-xs">
                    <i class="fa-solid fa-circle-info text-[10px]" aria-hidden="true"></i>
                    View Details
                </button>
            @endif

            <div class="relative">
                <button type="button"
                    x-on:click.stop="detailsOpen = true"
                    class="tc-admin-btn-secondary inline-flex min-h-10 items-center justify-center gap-1.5 px-3 py-2 text-xs"
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
        class="fixed inset-0 z-[130] flex justify-end bg-slate-950/45"
        role="dialog"
        aria-modal="true"
        x-on:keydown.escape.window="detailsOpen = false">
        <button type="button" class="absolute inset-0 cursor-default" x-on:click="detailsOpen = false" aria-label="Close details"></button>
        <section
            class="relative z-10 flex h-full w-full max-w-md flex-col overflow-hidden border-l border-slate-200 bg-white shadow-2xl"
            x-on:click.stop>
            <header class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 px-4 py-4">
                <div class="min-w-0">
                    <p class="font-mono text-xs font-bold text-slate-500">Queue #{{ $entry->queue_display_number ?? $entry->id }}</p>
                    <h2 class="mt-1 truncate text-lg font-semibold text-slate-950">{{ $entry->customer_name }}</h2>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <x-status-badge :status="$priorityStatus" :label="$priorityLabel" size="xs" />
                        <x-status-badge :status="$entry->status" size="xs" />
                    </div>
                </div>
                <button type="button"
                    x-on:click="detailsOpen = false"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50"
                    aria-label="Close details">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </header>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 tc-scrollbar">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <x-priority-summary :entry="$entry" />
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ filled($entry->customer_phone) ? $entry->customer_phone : 'Not provided' }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Party size</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ (int) $entry->party_size }} guests</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">ETA</dt>
                        <dd class="mt-1 font-semibold text-slate-900">
                            {{ $waitLabel }}
                        </dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Priority score</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ (int) ($entry->priority_score ?? 0) }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Accessible rule</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ $entry->needs_accessible ? 'Accessible table required' : 'Standard table allowed' }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Source</dt>
                        <dd class="mt-1 font-semibold uppercase text-slate-900">{{ $entry->source ?? 'web' }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Created</dt>
                        <dd class="mt-1 font-semibold text-slate-900">{{ $createdAt?->format('M j, g:i A') ?? 'Unknown' }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Confirmation code</dt>
                        <dd class="mt-1 font-mono font-bold uppercase text-slate-900">{{ $entry->hold_confirmation_code ?: 'None' }}</dd>
                    </div>
                </dl>

                @if ($entry->hold_expires_at)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold">Hold expiration</span>
                            <span data-hold-expires="{{ $holdIso }}" class="rounded-full bg-white px-2 py-1 text-xs font-bold text-amber-900 ring-1 ring-amber-200">
                                <span class="tc-hold-remaining">--</span>
                            </span>
                        </div>
                        <p class="mt-1 text-xs font-medium">{{ $entry->hold_expires_at->format('M j, g:i A') }}</p>
                    </div>
                @endif

                @if (in_array($mode, ['waiting', 'notified'], true))
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <h3 class="text-sm font-semibold text-slate-950">Seat guest</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Choose a suitable table. Notified guests require the confirmation code.</p>
                        <div class="mt-3">
                            @include('livewire.admin.partials.waitlist-seat-controls', [
                                'entry' => $entry,
                                'availableTables' => $availableTables,
                                'selectedTableId' => $selectedTableId,
                                'showHoldActions' => $mode === 'notified',
                            ])
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <h3 class="text-sm font-semibold text-slate-950">Queue actions</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Administrative queue controls remain available from this details panel.</p>
                        <div class="mt-3">
                            @if ($isAdmin)
                                <button type="button"
                                    wire:click="cancelEntry({{ $entry->id }})"
                                    wire:confirm="{{ $mode === 'notified' ? 'Remove this guest from the queue and release the held table?' : 'Remove this guest from the queue?' }}"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                    Cancel Entry
                                </button>
                            @else
                                <button type="button"
                                    disabled
                                    title="Admin only: remove/cancel waitlist entries"
                                    class="inline-flex min-h-10 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-400">
                                    Cancel Entry
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
</article>
