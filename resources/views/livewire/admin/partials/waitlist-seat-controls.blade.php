@props([
    'entry',
    'availableTables',
    'selectedTableId',
    'showHoldActions' => false,
])

@php
    $fitTables = $availableTables->filter(fn ($t) => $entry->accommodates($t));
    $needsHoldCode = $entry->status === 'notified' && filled($entry->hold_confirmation_code);
@endphp

<div class="flex flex-wrap items-center gap-2">
    @if ($needsHoldCode)
        <div class="flex min-w-0 flex-col gap-0.5">
            <input type="text" maxlength="6" placeholder="6-char code" autocomplete="off"
                wire:model.live="holdCode.{{ $entry->id }}"
                class="w-[7rem] rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium uppercase tracking-wide text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-300" />
            @error('holdCode.'.$entry->id)
                <span class="max-w-[11rem] text-[10px] font-medium leading-tight text-red-600">{{ $message }}</span>
            @enderror
        </div>
        <button type="button" wire:click="confirmAndSeatFromSeatButton({{ $entry->id }})"
            class="inline-flex min-h-[44px] items-center gap-1 rounded-lg bg-slate-900 px-2.5 py-2.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-slate-800">
            <i class="fa-solid fa-chair text-[10px]" aria-hidden="true"></i>
            Seat Guest
        </button>
        <label class="sr-only" for="wl-seat-{{ $entry->id }}">Seat at table</label>
        <select id="wl-seat-{{ $entry->id }}" wire:model.live="seatTablePick.{{ $entry->id }}"
            class="max-w-[11rem] rounded-lg border border-slate-200 bg-white py-2.5 pl-2 pr-6 text-[11px] font-medium text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-300">
            <option value="">Table…</option>
            @foreach ($fitTables as $t)
                <option value="{{ $t->id }}" @selected((int) ($selectedTableId ?? 0) === (int) $t->id)>
                    {{ $t->capacity }} seats · {{ $t->label }}{{ $t->is_accessible ? ' · Accessible' : '' }}
                </option>
            @endforeach
        </select>
    @else
        <button type="button" wire:click="openSeatQuickPick({{ $entry->id }})"
            class="inline-flex min-h-[44px] items-center gap-1 rounded-lg bg-slate-900 px-2.5 py-2.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-slate-800">
            <i class="fa-solid fa-chair text-[10px]" aria-hidden="true"></i>
            Seat Guest
        </button>

        <label class="sr-only" for="wl-seat-{{ $entry->id }}">Seat at table</label>
        <select id="wl-seat-{{ $entry->id }}" wire:change="seatCustomer({{ $entry->id }}, $event.target.value)"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50"
            wire:target="seatCustomer"
            class="max-w-[11rem] rounded-lg border border-slate-200 bg-white py-2.5 pl-2 pr-6 text-[11px] font-medium text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-300">
            <option value="">Table…</option>
            @foreach ($fitTables as $t)
                <option value="{{ $t->id }}" @selected((int) ($selectedTableId ?? 0) === (int) $t->id)>
                    {{ $t->capacity }} seats · {{ $t->label }}{{ $t->is_accessible ? ' · Accessible' : '' }}
                </option>
            @endforeach
        </select>
    @endif

    @if ($showHoldActions && $entry->hold_expires_at)
        <button type="button" wire:click="extendHold({{ $entry->id }})"
            class="inline-flex min-h-[44px] items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-2.5 text-[11px] font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            Extend Hold +5m
        </button>
    @endif
</div>
