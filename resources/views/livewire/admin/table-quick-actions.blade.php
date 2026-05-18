<div>
    @if ($table)
        @php
            $primaryButton = 'inline-flex min-h-[46px] w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60';
            $secondaryButton = 'inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60';
        @endphp

        <div wire:key="table-toolbar-{{ $table->id }}-v{{ $tablesSyncVersion }}" wire:poll.5s="pollTableModal"
            class="fixed z-[60] w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/15 ring-1 ring-slate-900/5"
            style="left: {{ $popoverLeft }}px; top: {{ $popoverTop }}px; transform: translate(-50%, calc(-100% - 12px));">
            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-lg font-black leading-tight text-slate-950">{{ $table->label }}</span>
                        <x-status-badge :status="$table->status" size="md" />
                    </div>
                    <div class="mt-2 grid gap-1 text-xs text-slate-600">
                        <span><span class="font-semibold text-slate-500">Seats:</span> {{ $partyDisplay ?? '-' }}</span>
                        @if ($table->status === 'reserved')
                            <span><span class="font-semibold text-slate-500">Arriving:</span> {{ $arrivalDisplay ?? '-' }}</span>
                        @elseif ($table->status === 'occupied' && $seatedDisplay)
                            <span><span class="font-semibold text-slate-500">Seated:</span> {{ $seatedDisplay }}</span>
                        @endif
                    </div>
                </div>
                <button type="button" wire:click="clearSelection"
                    class="inline-flex min-h-[36px] min-w-[36px] shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-700 transition hover:bg-slate-100"
                    aria-label="Close table actions">
                    <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                </button>
            </div>

            @can('update', $table)
                <div class="grid gap-2">
                    @if ($table->status === 'available')
                        <button type="button" wire:click="seatWalkIn({{ $table->id }})"
                            wire:loading.attr="disabled"
                            wire:target="seatWalkIn"
                            class="{{ $primaryButton }} bg-rose-600 text-white hover:bg-rose-700">
                            <i class="fa-solid fa-chair" aria-hidden="true"></i>
                            Seat Walk-in
                        </button>
                    @elseif ($table->status === 'reserved')
                        @if ($table->booking_id)
                            <button type="button" wire:click="checkIn({{ $table->id }})"
                                wire:loading.attr="disabled"
                                wire:target="checkIn"
                                class="{{ $primaryButton }} bg-rose-600 text-white hover:bg-rose-700">
                                <i class="fa-solid fa-chair" aria-hidden="true"></i>
                                Check In
                            </button>
                        @else
                            <button type="button" wire:click="seatWalkIn({{ $table->id }})"
                                wire:loading.attr="disabled"
                                wire:target="seatWalkIn"
                                class="{{ $primaryButton }} bg-rose-600 text-white hover:bg-rose-700">
                                <i class="fa-solid fa-chair" aria-hidden="true"></i>
                                Seat Walk-in
                            </button>
                        @endif
                        <button type="button" wire:click="releaseTable({{ $table->id }})"
                            wire:loading.attr="disabled"
                            wire:target="releaseTable"
                            class="{{ $secondaryButton }} border-slate-200 bg-white text-slate-800 hover:bg-slate-50">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            Release Table
                        </button>
                    @elseif ($table->status === 'occupied')
                        <div class="w-full min-w-0" x-data="{ confirmCheckout: false }">
                            <button type="button" x-show="!confirmCheckout" @click="confirmCheckout = true"
                                class="{{ $primaryButton }} bg-blue-600 text-white hover:bg-blue-700">
                                <i class="fa-solid fa-broom" aria-hidden="true"></i>
                                Check Out
                            </button>
                            <div x-show="confirmCheckout" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-medium leading-snug text-slate-700">
                                    Move this table to cleaning?
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button type="button" wire:click="sendToCleaning({{ $table->id }})"
                                        @click="confirmCheckout = false"
                                        wire:loading.attr="disabled"
                                        wire:target="sendToCleaning"
                                        class="{{ $secondaryButton }} border-blue-200 bg-blue-50 text-blue-950 hover:bg-blue-100">
                                        Yes
                                    </button>
                                    <button type="button" @click="confirmCheckout = false"
                                        class="{{ $secondaryButton }} border-slate-200 bg-white text-slate-800 hover:bg-slate-50">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" wire:click="markFree({{ $table->id }})"
                            wire:loading.attr="disabled"
                            wire:target="markFree"
                            class="{{ $secondaryButton }} border-slate-200 bg-white text-slate-800 hover:bg-slate-50">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            Mark Free
                        </button>
                    @elseif ($table->status === 'cleaning')
                        <button type="button" wire:click="markFree({{ $table->id }})"
                            wire:loading.attr="disabled"
                            wire:target="markFree"
                            class="{{ $primaryButton }} bg-emerald-600 text-white hover:bg-emerald-700">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            Mark Free
                        </button>
                    @endif
                </div>
            @else
                <p class="text-xs text-amber-900">You cannot change this table.</p>
            @endcan
        </div>
    @endif
</div>
