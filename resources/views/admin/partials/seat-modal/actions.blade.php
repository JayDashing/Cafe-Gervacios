{{-- Primary: Save + Close side by side. Destructive actions in a collapsed disclosure (edit mode only). --}}
<div class="flex flex-col gap-3">
    <div class="grid grid-cols-2 gap-3">
        <button type="button" id="seat-modal-save"
            class="min-h-[44px] w-full rounded-lg border border-panel-primary bg-panel-primary px-3 py-3 text-xs font-semibold uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-panel-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2">
            Save
        </button>
        <button type="button" id="seat-modal-done"
            data-seat-modal-close
            class="min-h-[44px] w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-xs font-semibold uppercase tracking-[0.1em] text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">
            Close
        </button>
    </div>

    <div id="seat-modal-unmerge-row" class="hidden">
        <button type="button" id="seat-modal-unmerge-table"
            class="min-h-[42px] w-full rounded-lg border border-sky-300 bg-sky-50 px-3 py-2.5 text-[11px] font-semibold uppercase leading-snug tracking-[0.08em] text-sky-900 transition hover:bg-sky-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2">
            Unmerge into separate tables
        </button>
    </div>

    <div id="seat-modal-delete-row" class="hidden">
        <details class="seat-modal__delete-disclosure rounded-lg border border-neutral-300 bg-neutral-50 open:border-rose-200 open:bg-rose-50/40">
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-600 marker:content-none hover:bg-neutral-100 open:text-rose-900 [&::-webkit-details-marker]:hidden">
                <span>Danger zone: remove from map</span>
                <svg class="seat-modal__delete-caret h-4 w-4 shrink-0 text-neutral-400" viewBox="0 0 20 20" fill="currentColor"
                    aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd" />
                </svg>
            </summary>
            <div class="border-t border-rose-200/70 px-3 pb-3 pt-2">
                <p class="mb-3 text-[11px] leading-relaxed text-rose-900/80">
                    Deletes seat markers from the floor map. Tables with bookings are protected and will show an action-blocked
                    error instead of being removed.
                </p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                    <button type="button" id="seat-modal-delete-seat"
                        class="min-h-[40px] w-full rounded-lg border border-rose-400 bg-white px-2 py-2.5 text-[11px] font-semibold uppercase leading-snug tracking-[0.04em] text-rose-900 transition hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-2">
                        Remove this seat only
                    </button>
                    <button type="button" id="seat-modal-delete-table"
                        class="min-h-[40px] w-full rounded-lg border border-rose-400 bg-white px-2 py-2.5 text-[11px] font-semibold uppercase leading-snug tracking-[0.04em] text-rose-900 transition hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-2">
                        Remove whole table
                    </button>
                </div>
            </div>
        </details>
    </div>
</div>
