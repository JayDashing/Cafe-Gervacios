<div wire:poll.5s
    class="flex min-h-0 flex-col rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
        <div>
            <h3
                class="text-base font-bold tracking-tight text-slate-950 [font-family:var(--font-admin-display)]">
                Live queue</h3>
            <p class="mt-0.5 text-[12px] font-medium text-slate-500">Last {{ $lookbackHours }}h · updates every 5s</p>
        </div>
    </div>

    {{-- Who’s next --}}
    <div class="border-b border-slate-100 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">Next up</p>
        @if ($nextInLine)
            <div class="mt-2 flex items-start gap-2">
                <span
                    class="mt-0.5 inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded-md bg-panel-primary px-1.5 text-[12px] font-bold text-white tabular-nums">
                    #{{ $nextInLine->queue_display_number ?? $nextInLine->id }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold leading-snug text-slate-900">{{ $nextInLine->customer_name }}</p>
                    <p class="text-[12px] text-slate-500">
                        {{ $nextInLine->party_size }} guests
                        @if ($nextInLine->isPriority())
                            <span
                                class="ml-1 inline-flex items-center rounded border border-amber-200/80 bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-900">Priority</span>
                        @endif
                    </p>
                </div>
            </div>
        @else
            <p class="mt-2 text-sm text-slate-500">No one waiting — queue is clear.</p>
        @endif
    </div>

    {{-- Activity stream --}}
    <div class="pt-3">
        <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-slate-500">Activity</p>
        @if (count($items) === 0)
            <p class="mt-3 text-sm leading-relaxed text-slate-500">No joins, seating, or payments in this window yet.</p>
        @else
            <ul class="mt-2 max-h-[min(22rem,55vh)] space-y-0 overflow-y-auto pr-1 tc-scrollbar" role="list">
                @foreach ($items as $item)
                    @php
                        $icon = match ($item['type']) {
                            'join' => 'fa-user-plus',
                            'notified' => 'fa-message',
                            'seated' => 'fa-chair',
                            'cancelled' => 'fa-arrow-right-from-bracket',
                            'paid' => 'fa-credit-card',
                            default => 'fa-circle',
                        };
                        $tone = match ($item['type']) {
                            'join' => 'text-slate-600',
                            'notified' => 'text-panel-primary',
                            'seated' => 'text-emerald-700',
                            'cancelled' => 'text-slate-500',
                            'paid' => 'text-panel-primary',
                            default => 'text-slate-500',
                        };
                    @endphp
                    <li wire:key="queue-feed-{{ $loop->index }}-{{ $item['type'] }}"
                        class="flex gap-2.5 border-b border-slate-100/90 py-2.5 last:border-b-0 {{ !empty($item['highlight']) ? 'bg-slate-50/80 -mx-1 px-1 rounded-md' : '' }}">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 {{ $tone }}"
                            aria-hidden="true">
                            <i class="fa-solid {{ $icon }} text-[12px]"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                                <span class="text-[13px] font-semibold text-slate-800">{{ $item['title'] }}</span>
                                <time class="shrink-0 text-[11px] tabular-nums text-slate-400"
                                    datetime="{{ $item['at']->toIso8601String() }}">
                                    {{ $item['at']->format('g:i A') }}
                                </time>
                            </div>
                            <p class="mt-0.5 text-[12px] leading-snug text-slate-600">{{ $item['detail'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
