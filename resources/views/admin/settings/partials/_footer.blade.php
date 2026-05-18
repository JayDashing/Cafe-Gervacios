<div class="flex w-full flex-wrap items-center justify-end gap-2 sm:gap-3">
    <button type="button" wire:click="closeSettingsModal"
        class="inline-flex min-h-11 min-w-[5.5rem] items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
        Cancel
    </button>

    @if (in_array($settingsModal, ['devices', 'timing', 'peak', 'alerts'], true))
        <button type="button" wire:click="saveUnifiedFromModal" wire:loading.attr="disabled"
            wire:target="saveUnifiedFromModal"
            class="inline-flex min-h-11 min-w-[5.5rem] items-center justify-center rounded-lg border border-panel-primary bg-panel-primary px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-panel-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
            <span wire:loading.remove wire:target="saveUnifiedFromModal">Save</span>
            <span wire:loading wire:target="saveUnifiedFromModal" class="inline-flex items-center gap-2">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                    aria-hidden="true"></span>
                Saving…
            </span>
        </button>
    @endif

    @if ($settingsModal === 'paymongo')
        <button type="button" wire:click="savePaymongoFromModal" wire:loading.attr="disabled"
            wire:target="savePaymongoFromModal"
            class="inline-flex min-h-11 min-w-[5.5rem] items-center justify-center rounded-lg border border-panel-primary bg-panel-primary px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-panel-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
            <span wire:loading.remove wire:target="savePaymongoFromModal">Save</span>
            <span wire:loading wire:target="savePaymongoFromModal" class="inline-flex items-center gap-2">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                    aria-hidden="true"></span>
                Saving…
            </span>
        </button>
    @endif

    @if ($settingsModal === 'philsms')
        <button type="button" wire:click="savePhilSmsFromModal" wire:loading.attr="disabled"
            wire:target="savePhilSmsFromModal"
            class="inline-flex min-h-11 min-w-[5.5rem] items-center justify-center rounded-lg border border-panel-primary bg-panel-primary px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-panel-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
            <span wire:loading.remove wire:target="savePhilSmsFromModal">Save</span>
            <span wire:loading wire:target="savePhilSmsFromModal" class="inline-flex items-center gap-2">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                    aria-hidden="true"></span>
                Saving…
            </span>
        </button>
    @endif

    @if ($settingsModal === 'facebook')
        <button type="button" wire:click="saveFacebookFromModal" wire:loading.attr="disabled"
            wire:target="saveFacebookFromModal"
            class="inline-flex min-h-11 min-w-[5.5rem] items-center justify-center rounded-lg border border-panel-primary bg-panel-primary px-6 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-panel-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
            <span wire:loading.remove wire:target="saveFacebookFromModal">Save</span>
            <span wire:loading wire:target="saveFacebookFromModal" class="inline-flex items-center gap-2">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                    aria-hidden="true"></span>
                Saving…
            </span>
        </button>
    @endif

    @if ($settingsModal === 'qr')
        <p class="w-full flex-1 text-[11px] leading-relaxed text-slate-600 sm:mr-auto sm:text-left">
            Upload and crop use form posts. Close when finished.
        </p>
    @endif
</div>
