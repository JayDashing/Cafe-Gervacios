@props([
    'entry',
    'availableTables',
    'selectedTableId',
    'showHoldActions' => false,
])

@php
    $needsHoldCode = $entry->status === 'notified' && filled($entry->hold_confirmation_code);
@endphp

<div class="grid gap-2">
    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
        @if ($needsHoldCode)
            <div class="min-w-0">
                <label class="sr-only" for="wl-code-{{ $entry->id }}">Guest code</label>
                <input id="wl-code-{{ $entry->id }}" type="text" maxlength="6" placeholder="Guest code" autocomplete="off"
                    wire:model.live="holdCode.{{ $entry->id }}"
                    class="min-h-14 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-semibold uppercase tracking-wide text-slate-800 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
            </div>
        @endif

        <button type="button" wire:click="confirmAndSeatFromSeatButton({{ $entry->id }})"
            wire:loading.attr="disabled"
            class="inline-flex min-h-14 items-center justify-center gap-2 rounded-xl bg-slate-900 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60 {{ $needsHoldCode ? '' : 'sm:justify-self-start' }}">
            <i class="fa-solid fa-chair text-[10px]" aria-hidden="true"></i>
            Seat Guest
        </button>
    </div>

    @error('holdCode.'.$entry->id)
        <span class="text-xs font-semibold leading-tight text-red-600">{{ $message }}</span>
    @enderror

    @error('seatCustomer')
        <span class="text-xs font-semibold leading-tight text-red-600">{{ $message }}</span>
    @enderror
</div>
