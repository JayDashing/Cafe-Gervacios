@php
    $calendarDate = $calendarDate ?? now(config('app.timezone'))->startOfDay();
    $calendarSlots = collect($calendarSlots ?? []);
    $calendarBookings = collect($calendarBookings ?? []);
    $bookingsByHour = $calendarBookings->groupBy(fn ($booking) => $booking->booked_at?->timezone(config('app.timezone'))->format('H:00') ?? 'unscheduled');
    $previousDate = $calendarDate->copy()->subDay()->toDateString();
    $nextDate = $calendarDate->copy()->addDay()->toDateString();
@endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-white px-4 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-950">Reservation Calendar</h2>
                <p class="mt-0.5 text-sm text-slate-600">View online reservations by time, table assignment, payment, and status.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.tables', ['tab' => 'calendar', 'date' => $previousDate]) }}"
                    class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs">
                    <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                    Previous
                </a>
                <form method="GET" action="{{ route('admin.tables') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="calendar">
                    <input type="date" name="date" value="{{ $calendarDate->toDateString() }}"
                        class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800">
                    <button type="submit" class="tc-admin-btn-primary min-h-9 px-3 py-2 text-xs">Go</button>
                </form>
                <a href="{{ route('admin.tables', ['tab' => 'calendar', 'date' => $nextDate]) }}"
                    class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-2 px-3 py-2 text-xs">
                    Next
                    <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-3 bg-slate-100 p-3 xl:grid-cols-[1fr_340px]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="grid grid-cols-[92px_1fr] border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                <span>Time</span>
                <span>Reservations</span>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($calendarSlots as $slot)
                    @php
                        $slotBookings = $bookingsByHour->get($slot, collect());
                    @endphp
                    <div class="grid min-h-[76px] grid-cols-[92px_1fr]">
                        <div class="border-r border-slate-100 bg-slate-50/60 px-3 py-3 text-xs font-semibold text-slate-500">
                            {{ \Carbon\Carbon::createFromFormat('H:i', $slot, config('app.timezone'))->format('g:i A') }}
                        </div>
                        <div class="grid gap-2 px-3 py-2 md:grid-cols-2 2xl:grid-cols-3">
                            @forelse ($slotBookings as $booking)
                                <article class="rounded-lg border border-blue-100 bg-blue-50/70 px-3 py-2 shadow-sm">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">{{ $booking->customer_name }}</p>
                                            <p class="text-xs text-slate-600">{{ $booking->booking_ref }} · {{ $booking->party_size }} guests</p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-white px-2 py-1 text-[11px] font-bold text-slate-700">
                                            {{ $booking->table?->label ?? 'No table' }}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <x-status-badge :status="$booking->status" size="xs" />
                                        <x-status-badge :status="$booking->payment_status ?: 'unpaid'" size="xs" />
                                    </div>
                                </article>
                            @empty
                                <div class="flex items-center rounded-lg border border-dashed border-slate-200 px-3 py-2 text-xs font-semibold text-slate-400">
                                    No booking in this slot
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="grid gap-3">
            <section class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-950">Day Summary</h3>
                    <span class="text-xs font-semibold text-slate-500">{{ $calendarDate->format('M d, Y') }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Bookings</p>
                        <p class="mt-1 text-xl font-semibold text-slate-950">{{ $calendarBookings->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Assigned</p>
                        <p class="mt-1 text-xl font-semibold text-slate-950">{{ $calendarBookings->whereNotNull('table_id')->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Paid</p>
                        <p class="mt-1 text-xl font-semibold text-slate-950">{{ $calendarBookings->where('payment_status', 'paid')->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Pending</p>
                        <p class="mt-1 text-xl font-semibold text-slate-950">{{ $calendarBookings->whereIn('payment_status', ['pending', 'pending_verification'])->count() }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-3">
                <h3 class="text-sm font-semibold text-slate-950">Unassigned Reservations</h3>
                <div class="mt-3 grid gap-2">
                    @forelse ($calendarBookings->whereNull('table_id')->take(8) as $booking)
                        <article class="rounded-lg border border-amber-100 bg-amber-50/70 px-3 py-2">
                            <p class="truncate text-sm font-semibold text-slate-950">{{ $booking->customer_name }}</p>
                            <p class="text-xs text-slate-600">
                                {{ $booking->booked_at?->timezone(config('app.timezone'))->format('g:i A') ?? '—' }}
                                · {{ $booking->party_size }} guests · {{ $booking->booking_ref }}
                            </p>
                        </article>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs font-semibold text-slate-500">
                            All reservations have table assignments.
                        </p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</section>
