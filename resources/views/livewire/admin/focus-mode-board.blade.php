<div wire:poll.8s class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-5">
    <section class="min-h-0 rounded-2xl border border-slate-700 bg-slate-900/70 p-4 lg:col-span-3">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-white">Today's Queue</h2>
            <span class="rounded-full bg-slate-700 px-3 py-1 text-sm font-semibold text-slate-100">{{ $queueEntries->count() }}</span>
        </div>

        <div class="space-y-2">
            @forelse ($queueEntries as $entry)
                <div class="flex min-h-[64px] flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-700 bg-slate-800/80 px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-panel-primary px-2.5 py-1 text-sm font-bold text-white">#{{ $entry->queue_display_number }}</span>
                            <span class="text-lg font-semibold text-white">{{ $entry->customer_name }}</span>
                            <span class="text-sm text-slate-200">{{ $entry->party_size }}p</span>
                            @if ($entry->isPriority())
                                <x-status-badge status="priority" size="sm" />
                                <x-status-badge :status="$entry->priority_type" size="sm" />
                            @else
                                <x-status-badge status="standard" size="sm" />
                            @endif
                            <x-status-badge :status="$entry->status" size="sm" />
                        </div>
                    </div>
                    <button type="button" wire:click="goToFloorMapForSeat({{ $entry->id }})"
                        class="min-h-[44px] min-w-[80px] rounded-lg bg-white px-4 py-2.5 text-sm font-bold text-slate-900">
                        Seat
                    </button>
                </div>
            @empty
                <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 text-sm text-slate-200">No active queue entries.</div>
            @endforelse
        </div>
    </section>

    <section class="min-h-0 rounded-2xl border border-slate-300 bg-white p-4 lg:col-span-2">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-slate-900">Today's Reservations</h2>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $reservations->count() }}</span>
        </div>

        <div class="space-y-2">
            @forelse ($reservations as $booking)
                <div class="flex min-h-[64px] flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-sm font-bold text-blue-900">
                                {{ $booking->booked_at?->format('g:i A') ?? '--' }}
                            </span>
                            <span class="text-lg font-semibold text-slate-900">{{ $booking->customer_name }}</span>
                            <span class="text-sm text-slate-600">{{ $booking->party_size }}p</span>
                            <x-status-badge :status="$booking->status" size="sm" />
                            <span class="rounded-full bg-slate-200 px-2.5 py-1 text-sm font-semibold text-slate-800">
                                {{ $booking->table?->label ?? 'Unassigned' }}
                            </span>
                        </div>
                    </div>
                    <button type="button" wire:click="checkIn({{ $booking->id }})"
                        class="min-h-[44px] min-w-[80px] rounded-lg bg-panel-primary px-4 py-2.5 text-sm font-bold text-white">
                        Check In
                    </button>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">No paid active reservations awaiting check-in today.</div>
            @endforelse
        </div>
    </section>
</div>
