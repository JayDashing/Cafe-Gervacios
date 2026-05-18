<div class="space-y-6">
    @if($step === 1)
        <h1 class="font-forum text-3xl text-cream text-center">Join walk-in queue</h1>
        <form wire:submit.prevent="sendOtp" class="space-y-4">
            <input type="text" wire:model="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div>
                <label class="block text-xs uppercase tracking-wider text-cream/60 mb-2">Name</label>
                <input type="text" wire:model.blur="customer_name" class="w-full min-h-12 rounded-xl bg-muted-bg border border-border-subtle px-4 text-cream placeholder:text-cream/30">
                @error('customer_name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-cream/60 mb-2">Mobile</label>
                <input type="tel" wire:model.blur="customer_phone" class="w-full min-h-12 rounded-xl bg-muted-bg border border-border-subtle px-4 text-cream placeholder:text-cream/30" placeholder="09XXXXXXXXX">
                @error('customer_phone') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-cream/60 mb-2">Party size</label>
                <input type="number" wire:model.blur="party_size" min="1" max="20" class="w-full min-h-12 rounded-xl bg-muted-bg border border-border-subtle px-4 text-cream">
                @error('party_size') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-cream/60 mb-2">Priority</label>
                <select wire:model="priority_type" class="w-full min-h-12 rounded-xl bg-muted-bg border border-border-subtle px-4 text-cream">
                    <option value="none">Regular</option>
                    <option value="pwd">PWD</option>
                    <option value="pregnant">Pregnant</option>
                    <option value="senior">Senior citizen</option>
                </select>
            </div>
            @if($errorMessage)
                <p class="text-amber-400 text-sm">{{ $errorMessage }}</p>
            @endif
            <button type="submit" class="w-full min-h-14 rounded-xl bg-cream text-dark font-medium uppercase tracking-wider text-sm">
                @if(\App\Models\Setting::get('mobile_queue_require_otp', '1') === '1')
                    Send verification code
                @else
                    Join queue
                @endif
            </button>
        </form>
    @endif

    @if($step === 2)
        <h1 class="font-forum text-3xl text-cream text-center">Enter code</h1>
        <p class="text-cream/70 text-sm text-center">We sent a 6-digit code to your phone.</p>
        <form wire:submit.prevent="verifyAndJoin" class="space-y-4">
            <input type="text" wire:model="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="text" wire:model="otp" inputmode="numeric" maxlength="6" class="w-full min-h-14 text-center text-3xl tracking-[0.5em] rounded-xl bg-muted-bg border border-border-subtle px-4 text-cream" placeholder="••••••">
            @error('otp') <p class="text-red-400 text-sm">{{ $message }}</p> @enderror
            @if($errorMessage)
                <p class="text-amber-400 text-sm">{{ $errorMessage }}</p>
            @endif
            <button type="submit" class="w-full min-h-14 rounded-xl bg-cream text-dark font-medium uppercase tracking-wider text-sm">Join queue</button>
            <button type="button" wire:click="goBack" class="w-full min-h-12 rounded-xl border border-border-subtle text-cream text-sm uppercase tracking-wider">Back</button>
        </form>
    @endif

    @if($step === 3)
        <h1 class="font-forum text-3xl text-cream text-center mb-6">You’re in line</h1>
        @php
            $queueEntry = \App\Models\QueueEntry::query()
                ->where('queue_display_number', $queueNumber)
                ->latest('joined_at')
                ->first();
            $waitEst = $queueEntry?->waitEstimateMinutes()
                ?? app(\App\Services\QueueService::class)->estimateWait((int) $party_size, (string) ($priority_type ?? 'none'));
        @endphp
        <x-queue-status :queueNumber="$queueNumber" :position="$position" :wait="$waitEst" />
        <a href="{{ url('/mobile') }}" wire:navigate class="mt-8 block text-center text-sm text-cream/70 underline min-h-12 leading-[48px]">Back to menu</a>
    @endif
</div>
