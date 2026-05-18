@php
    $field =
        'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-panel-primary focus:outline-none focus:ring-2 focus:ring-panel-primary/15';
@endphp

<div class="grid grid-cols-1 gap-y-4">
    <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-900">
        <input type="checkbox" wire:model.live="smsEnabled" data-modal-initial-focus
            class="h-4 w-4 shrink-0 rounded border-slate-300 text-panel-primary focus:ring-panel-primary/30">
        <span>Send text messages to guests</span>
    </label>
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-[0.12em] text-black" for="modal-philsms-key">API key</label>
        <input id="modal-philsms-key" type="password" wire:input.debounce.500ms="updateSecret('philsms_api_key', $event.target.value)" autocomplete="off"
            class="{{ $field }} font-mono">
    </div>
    <div>
        <label class="mb-1.5 block text-xs font-bold uppercase tracking-[0.12em] text-black" for="modal-philsms-sender">Sender ID</label>
        <input id="modal-philsms-sender" type="text" wire:model="philSmsSenderId" maxlength="11"
            class="{{ $field }}">
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="checkPhilSmsConnection"
            class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-[0.08em] text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
            <span wire:loading.remove wire:target="checkPhilSmsConnection">Check</span>
            <span wire:loading wire:target="checkPhilSmsConnection">...</span>
        </button>
        <button type="button" wire:click="sendTestSms"
            class="inline-flex items-center justify-center rounded-lg border border-panel-primary bg-panel-primary px-3 py-2 text-xs font-semibold uppercase tracking-[0.08em] text-white shadow-sm transition hover:bg-panel-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2">
            <span wire:loading.remove wire:target="sendTestSms">Test SMS</span>
            <span wire:loading wire:target="sendTestSms">...</span>
        </button>
    </div>
    @if ($philSmsConnectionSummary)
        <p
            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-700">
            {{ $philSmsConnectionSummary }}</p>
    @endif
    @if ($philSmsTestMessage)
        <p
            class="text-sm font-medium {{ $philSmsTestStatus === 'success' ? 'text-emerald-800' : 'text-red-700' }}">
            {{ $philSmsTestMessage }}</p>
    @endif
</div>
