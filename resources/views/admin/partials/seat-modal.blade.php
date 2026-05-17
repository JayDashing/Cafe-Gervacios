{{--
    Table marker editor modal.
    IDs/includes stay stable for seating-layout.js.
--}}
<div id="seat-modal"
    class="seat-modal--editorial fixed inset-0 z-[999] items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm sm:p-6">
    <div class="relative mx-auto flex w-full max-w-[min(100%,520px)] flex-col">
        <div
            class="seat-modal__panel w-full max-h-[calc(100dvh-3rem)] overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.18)]"
            role="dialog"
            aria-modal="true"
            aria-labelledby="seat-modal-dialog-label seat-modal-title">
            <div class="seat-modal__content flex flex-col text-slate-950">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <p id="seat-modal-dialog-label"
                            class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-500">
                            Table Marker
                        </p>
                        <h3 id="seat-modal-title"
                            class="mt-1 text-lg font-semibold leading-snug text-slate-950"></h3>
                        <p id="seat-modal-sub" class="mt-1 text-sm leading-relaxed text-slate-500"></p>
                    </div>
                    <button type="button" id="seat-modal-close" data-seat-modal-close
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-panel-primary focus-visible:ring-offset-2"
                        aria-label="Close table marker editor">
                        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </header>

                <div class="seat-modal__body shrink-0 px-5 py-5 sm:px-6">
                    @include('admin.partials.seat-modal.form')
                    @include('admin.partials.seat-modal.status')
                </div>

                <div class="seat-modal__footer shrink-0 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    @include('admin.partials.seat-modal.actions')
                </div>
            </div>
        </div>
    </div>
</div>
