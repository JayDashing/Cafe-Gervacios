<div class="flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden bg-panel-canvas">
    <div
        class="flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden tc-scrollbar px-4 py-4 sm:px-6 sm:py-5">
        <div class="mx-auto w-full max-w-md">
            {{-- Compact local header bar (replaces duplicate app header title) --}}
            <div
                class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-slate-100 pb-3 sm:mb-4 sm:pb-3">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex shrink-0 items-center gap-1.5 text-[12px] font-semibold text-slate-600 transition-colors hover:text-panel-primary">
                    <i class="fa-solid fa-arrow-left text-[10px]" aria-hidden="true"></i>
                    Dashboard
                </a>
                <span class="select-none text-slate-300" aria-hidden="true">/</span>
                <h2 class="text-[15px] font-semibold leading-tight text-slate-900">Register walk-in</h2>
            </div>

            <div class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[12px] font-semibold text-slate-800">Add Walk-in</p>
                    <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Fill the required fields, then click Add to queue. The success toast shows the queue number.</p>
                </div>

                <form wire:submit.prevent="register" class="space-y-3">
                    <div>
                        <label for="walkin-name" class="block text-[12px] font-semibold text-slate-700">Name Required</label>
                        <input type="text" wire:model.blur="customer_name"
                            id="walkin-name" aria-required="true" aria-describedby="walkin-name-help walkin-name-error"
                            class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_name') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                        <p id="walkin-name-help" class="mt-1 text-[11px] text-slate-500">Enter the guest's full name.</p>
                        @error('customer_name')
                            <p id="walkin-name-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="walkin-phone" class="block text-[12px] font-semibold text-slate-700">Phone Optional</label>
                        <input type="tel" wire:model.blur="customer_phone"
                            id="walkin-phone" aria-describedby="walkin-phone-help walkin-phone-error"
                            class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_phone') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                        <p id="walkin-phone-help" class="mt-1 text-[11px] text-slate-500">Leave blank if the guest does not
                            want SMS updates.</p>
                        @error('customer_phone')
                            <p id="walkin-phone-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="walkin-party-size" class="block text-[12px] font-semibold text-slate-700">Party Size Required</label>
                        <input type="number" wire:model.blur="party_size" min="1" max="20"
                            id="walkin-party-size" aria-required="true"
                            aria-describedby="walkin-party-size-help walkin-party-size-error"
                            class="mt-1 w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('party_size') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                        <p id="walkin-party-size-help" class="mt-1 text-[11px] text-slate-500">Enter 1 to 20 guests.</p>
                        @error('party_size')
                            <p id="walkin-party-size-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-slate-700">Priority Type</label>
                        <select wire:model="priority_type"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/25">
                            <option value="none">Regular</option>
                            <option value="pwd">PWD</option>
                            <option value="pregnant">Pregnant</option>
                            <option value="senior">Senior</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500">Priority score and PWD accessible-table rule are applied automatically.</p>
                    </div>

                    <button type="submit"
                        class="mt-1 flex min-h-[46px] w-full items-center justify-center gap-2 rounded-lg bg-panel-primary py-2.5 text-sm font-semibold text-panel-on-bright shadow-sm transition-colors hover:bg-panel-primary-hover">
                        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                        Add to queue
                    </button>
                </form>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-[12px] font-bold uppercase tracking-[0.12em] text-slate-500">Priority Queue</p>
                        <h3 class="mt-0.5 text-sm font-bold text-slate-900">Priority guests appear above regular guests</h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                        {{ $priorityQueue->count() + $regularQueue->count() }} waiting
                    </span>
                </div>

                @if ($priorityQueue->isNotEmpty())
                    <div class="mb-4">
                        <h4 class="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-900">
                            <i class="fa-solid fa-star-of-life text-[10px]" aria-hidden="true"></i>
                            Priority guests
                        </h4>
                        <div class="space-y-2">
                            @foreach ($priorityQueue as $entry)
                                @php($waitLabel = $entry->waitEstimateLabel())
                                <div class="rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-panel-primary px-1.5 py-0.5 font-mono text-xs font-bold text-white">#{{ $entry->queue_display_number }}</span>
                                        <x-status-badge status="priority" size="xs" />
                                        <span class="text-sm font-semibold text-slate-900">{{ $entry->customer_name }}</span>
                                        <span class="text-xs font-medium text-slate-600">{{ $entry->party_size }}p</span>
                                        <span class="text-xs font-semibold text-slate-600">ETA: {{ $waitLabel }}</span>
                                    </div>
                                    <x-priority-summary :entry="$entry" class="mt-2" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($regularQueue->isNotEmpty())
                    <div>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-600">Regular guests</h4>
                        <div class="space-y-2">
                            @foreach ($regularQueue as $entry)
                                @php($waitLabel = $entry->waitEstimateLabel())
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-800 ring-1 ring-slate-200">#{{ $entry->queue_display_number }}</span>
                                        <span class="text-sm font-semibold text-slate-900">{{ $entry->customer_name }}</span>
                                        <span class="text-xs font-medium text-slate-600">{{ $entry->party_size }}p</span>
                                        <span class="text-xs font-semibold text-slate-600">ETA: {{ $waitLabel }}</span>
                                    </div>
                                    <x-priority-summary :entry="$entry" class="mt-2" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($priorityQueue->isEmpty() && $regularQueue->isEmpty())
                    <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">
                        No waiting guests yet.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
