<section wire:key="table-management-panel-{{ $refreshVersion }}" class="space-y-3">
    @php
        $filterLabels = [
            'all' => 'All',
            'available' => 'Available',
            'reserved' => 'Reserved',
            'occupied' => 'Occupied',
            'cleaning' => 'Cleaning',
        ];

        $statusCards = [
            [
                'label' => 'Available',
                'count' => $allTables->where('status', 'available')->count(),
                'icon' => 'fa-circle-check',
                'class' => 'text-emerald-700',
            ],
            [
                'label' => 'Reserved',
                'count' => $allTables->where('status', 'reserved')->count(),
                'icon' => 'fa-bookmark',
                'class' => 'text-amber-700',
            ],
            [
                'label' => 'Occupied',
                'count' => $allTables->where('status', 'occupied')->count(),
                'icon' => 'fa-chair',
                'class' => 'text-rose-700',
            ],
            [
                'label' => 'Cleaning',
                'count' => $allTables->where('status', 'cleaning')->count(),
                'icon' => 'fa-broom',
                'class' => 'text-cyan-700',
            ],
        ];
    @endphp

    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($statusCards as $card)
            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-0.5 text-xl font-semibold tabular-nums text-slate-950">{{ $card['count'] }}</p>
                    </div>
                    <i class="fa-solid {{ $card['icon'] }} {{ $card['class'] }}" aria-hidden="true"></i>
                </div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter tables by status">
                @foreach ($filterLabels as $value => $label)
                    @php
                        $active = $statusFilter === $value;
                        $count = $value === 'all'
                            ? $allTables->count()
                            : $allTables->where('status', $value)->count();
                    @endphp
                    <button type="button" wire:click="setStatusFilter('{{ $value }}')"
                        class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold transition {{ $active ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-white' }}">
                        {{ $label }}
                        <span class="rounded-full {{ $active ? 'bg-white/15 text-white' : 'bg-white text-slate-500' }} px-1.5 py-0.5 text-[10px] font-bold tabular-nums">
                            {{ $count }}
                        </span>
                    </button>
                @endforeach
            </div>

            <label class="relative block w-full lg:max-w-sm">
                <span class="sr-only">Search table name, booking reference, or guest name</span>
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                <input type="search" wire:model.live.debounce.250ms="search"
                    placeholder="Search table, booking, or guest"
                    class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-sm text-slate-800 shadow-sm transition focus:bg-white" />
            </label>
        </div>
    </div>

    @if ($tables->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-600">
            No tables match the current filters.
        </div>
    @else
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($tables as $table)
                @php
                    $booking = $table->booking;
                    $status = (string) $table->status;
                @endphp

                <article x-data="{ open: false, detailsOpen: false }"
                    class="relative rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-slate-300">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-slate-950">{{ $table->label }}</h3>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">{{ (int) $table->capacity }} capacity</p>
                        </div>
                        <x-status-badge :status="$table->status" size="xs" />
                    </div>

                    <div class="mt-3 min-h-[2.25rem] text-xs">
                        @if ($booking)
                            <p class="truncate font-semibold text-slate-900">{{ $booking->booking_ref }}</p>
                            <p class="truncate text-slate-500">{{ $booking->customer_name ?: 'Guest' }}</p>
                        @else
                            <p class="font-medium text-slate-500">No assigned booking</p>
                        @endif
                    </div>

                    @if (auth()->user()?->can('update', $table))
                        <div class="mt-3 flex items-center gap-2">
                            @if ($status === 'available')
                                <button type="button" wire:click="markOccupied({{ $table->id }})" wire:loading.attr="disabled"
                                    class="tc-admin-btn-primary inline-flex min-h-9 flex-1 items-center justify-center px-3 py-2 text-xs disabled:opacity-60">
                                    Seat Walk-in
                                </button>
                            @elseif ($status === 'reserved')
                                <button type="button" wire:click="markOccupied({{ $table->id }})" wire:loading.attr="disabled"
                                    class="tc-admin-btn-primary inline-flex min-h-9 flex-1 items-center justify-center px-3 py-2 text-xs disabled:opacity-60">
                                    Check In
                                </button>
                            @elseif ($status === 'occupied')
                                <button type="button" wire:click="markCleaning({{ $table->id }})" wire:loading.attr="disabled"
                                    class="tc-admin-btn-primary inline-flex min-h-9 flex-1 items-center justify-center px-3 py-2 text-xs disabled:opacity-60">
                                    Mark Cleaning
                                </button>
                            @elseif ($status === 'cleaning')
                                <button type="button" wire:click="markFree({{ $table->id }})" wire:loading.attr="disabled"
                                    class="tc-admin-btn-primary inline-flex min-h-9 flex-1 items-center justify-center px-3 py-2 text-xs disabled:opacity-60">
                                    Mark Free
                                </button>
                            @endif

                            <div class="relative">
                                <button type="button" x-on:click="open = !open"
                                    class="tc-admin-btn-secondary inline-flex min-h-9 items-center justify-center gap-1.5 px-2.5 py-2 text-xs"
                                    aria-haspopup="menu" x-bind:aria-expanded="open.toString()">
                                    More
                                    <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                                </button>

                                <div x-cloak x-show="open" x-transition x-on:click.outside="open = false"
                                    class="absolute right-0 z-30 mt-2 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg"
                                    role="menu">
                                    @if ($status === 'available')
                                        <button type="button" wire:click="markOccupied({{ $table->id }})" x-on:click="open = false"
                                            class="block w-full px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Seat Walk-in
                                        </button>
                                    @elseif ($status === 'reserved')
                                        <a href="{{ route('admin.bookings') }}"
                                            class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            View Booking
                                        </a>
                                        <button type="button" wire:click="releaseTable({{ $table->id }})" x-on:click="open = false"
                                            class="block w-full px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Release Table
                                        </button>
                                    @elseif ($status === 'occupied')
                                        <a href="{{ route('admin.bookings') }}"
                                            class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            View Booking
                                        </a>
                                    @endif
                                    <button type="button" x-on:click="detailsOpen = !detailsOpen; open = false"
                                        class="block w-full px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                            View-only for your role.
                        </p>
                    @endif

                    <div x-cloak x-show="detailsOpen" x-transition
                        class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <p class="font-semibold text-slate-500">Table ID</p>
                                <p class="font-mono text-slate-900">#{{ $table->id }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-slate-500">Seat markers</p>
                                <p class="text-slate-900">{{ (int) $table->seats_count }}</p>
                            </div>
                        </div>
                        @if ($booking?->booked_at)
                            <p class="mt-2 text-slate-500">Booking time: {{ $booking->booked_at->timezone(config('app.timezone'))->format('M d, g:i A') }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
