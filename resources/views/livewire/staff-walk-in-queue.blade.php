<div class="{{ $modalMode ? 'w-full overflow-hidden' : 'flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden bg-panel-canvas' }}"
    @if ($modalMode) style="width: 100%;" @endif>
    <div
        class="{{ $modalMode ? 'w-full' : 'flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden tc-scrollbar px-4 py-4 sm:px-6 sm:py-5' }}">
        <div class="{{ $modalMode ? 'w-full' : 'mx-auto w-full max-w-5xl' }}">
            {{-- Compact local header bar (replaces duplicate app header title) --}}
            @unless ($modalMode)
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
            @endunless

            @php
                $compatibleCount = $walkInTableMarkers->where('selectable', true)->count();
                $hasSeatingOption = $compatibleCount > 0;
                $primaryDisabled = $hasSeatingOption && ! $selectedTable;
                $primaryLabel = $hasSeatingOption
                    ? ($selectedTable ? 'Seat Guest at '.$selectedTable->label : 'Seat Guest')
                    : 'Add to Waitlist';
            @endphp

            @if ($modalMode)
                @php
                    $wizardSteps = [
                        'details' => ['number' => 1, 'label' => 'Details'],
                        'selection' => ['number' => 2, 'label' => 'Table'],
                        'review' => ['number' => 3, 'label' => 'Review'],
                        'success' => ['number' => 4, 'label' => 'Done'],
                    ];
                    $currentStepNumber = $wizardSteps[$wizardStep]['number'] ?? 1;
                    $summaryPriority = match ($priority_type) {
                        'pwd' => 'PWD',
                        'pregnant' => 'Pregnant',
                        'senior' => 'Senior',
                        default => 'Regular',
                    };
                    $notificationEnabled = filled(trim($customer_phone)) || filled(trim($customer_email));
                    $reviewIsSeating = $wizardAction === 'seat';
                    $reviewBackAction = $hasSeatingOption ? 'backToTableSelection' : 'backToGuestDetails';
                    $selectionAction = $hasSeatingOption ? 'seat' : 'waitlist';
                @endphp

                <div class="walkin-workflow">
                    <nav class="walkin-stepper" aria-label="Walk-in registration progress">
                        <ol class="walkin-stepper__list">
                            @foreach ($wizardSteps as $stepKey => $step)
                                <li class="walkin-stepper__item {{ $currentStepNumber === $step['number'] ? 'is-active' : ($currentStepNumber > $step['number'] ? 'is-complete' : '') }}">
                                    @if (! $loop->first)
                                        <span class="walkin-stepper__line" aria-hidden="true"></span>
                                    @endif
                                    <span class="walkin-stepper__dot">{{ $step['number'] }}</span>
                                    <span class="walkin-stepper__label">{{ $step['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </nav>

                    @if ($wizardStep === 'details')
                        <section class="walkin-workflow-panel" aria-labelledby="walkin-step-details-title">
                            <form wire:submit.prevent="continueToTableSelection" class="walkin-panel-form">
                                <input type="hidden" wire:model="selectedTableId" name="selected_table_id">

                                <div class="walkin-step-content walkin-step-content--form">
                                    <header class="walkin-pane-header">
                                        <h3 id="walkin-step-details-title" class="walkin-panel-title">Guest Details</h3>
                                    </header>
                                    <p class="sr-only">
                                        {{ $hasSeatingOption ? 'Suitable table available.' : 'No suitable table available. Guest will join waitlist.' }}
                                        Estimated wait: {{ $estimatedWait }} min.
                                    </p>

                                    @if ($errors->has('customer_name') || $errors->has('party_size'))
                                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                                            Please complete the required fields.
                                        </div>
                                    @endif

                                    <div class="walkin-fields">
                                        <div class="walkin-field-full">
                                            <label for="walkin-name" class="block text-[12px] font-semibold text-slate-700">
                                                Guest Name <span class="text-red-600" aria-hidden="true">*</span>
                                            </label>
                                            <input type="text" wire:model.live.debounce.250ms="customer_name"
                                                id="walkin-name" aria-required="true" aria-describedby="walkin-name-help walkin-name-error"
                                                placeholder="e.g., Juan Dela Cruz"
                                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_name') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                                            <p id="walkin-name-help" class="sr-only">Enter the guest's full name.</p>
                                            @error('customer_name')
                                                <p id="walkin-name-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="walkin-phone" class="block text-[12px] font-semibold text-slate-700">Phone <span class="font-medium text-slate-400">(optional)</span></label>
                                            <input type="tel" wire:model.blur="customer_phone"
                                                id="walkin-phone" aria-describedby="walkin-phone-help walkin-phone-error"
                                                placeholder="e.g., 09171234567"
                                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_phone') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                                            <p id="walkin-phone-help" class="sr-only">Leave blank if the guest does not want SMS updates.</p>
                                            @error('customer_phone')
                                                <p id="walkin-phone-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="walkin-email" class="block text-[12px] font-semibold text-slate-700">Email <span class="font-medium text-slate-400">(optional)</span></label>
                                            <input type="email" wire:model.blur="customer_email"
                                                id="walkin-email" aria-describedby="walkin-email-help walkin-email-error"
                                                placeholder="e.g., guest@email.com"
                                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_email') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                                            <p id="walkin-email-help" class="sr-only">Used for table-ready email notification.</p>
                                            @error('customer_email')
                                                <p id="walkin-email-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="walkin-party-size" class="block text-[12px] font-semibold text-slate-700">
                                                Party Size <span class="text-red-600" aria-hidden="true">*</span>
                                            </label>
                                            <div class="walkin-party-stepper mt-1 {{ $errors->has('party_size') ? 'is-invalid' : '' }}">
                                                <button type="button" wire:click="decrementPartySize" aria-label="Decrease party size">
                                                    <i class="fa-solid fa-minus" aria-hidden="true"></i>
                                                </button>
                                                <input type="number" wire:model.live.debounce.250ms="party_size" min="1" max="20"
                                                    id="walkin-party-size" aria-required="true"
                                                    placeholder="Number of guests"
                                                    aria-describedby="walkin-party-size-help walkin-party-size-error">
                                                <button type="button" wire:click="incrementPartySize" aria-label="Increase party size">
                                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                            <p id="walkin-party-size-help" class="sr-only">1 to 20 guests.</p>
                                            @error('party_size')
                                                <p id="walkin-party-size-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="block text-[12px] font-semibold text-slate-700">Priority Type</label>
                                            <select wire:model.live="priority_type"
                                                class="mt-1 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-800 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/25">
                                                <option value="none">Regular</option>
                                                <option value="pwd">PWD</option>
                                                <option value="pregnant">Pregnant</option>
                                                <option value="senior">Senior</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="walkin-action-row">
                                    <button type="button" class="walkin-action-secondary" disabled>Back</button>
                                    <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="continueToTableSelection"
                                        class="walkin-action-primary disabled:cursor-wait disabled:opacity-60">
                                        Continue
                                        <i class="fa-solid fa-arrow-right text-sm" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </form>
                        </section>
                    @elseif ($wizardStep === 'selection')
                        <section class="walkin-workflow-panel is-map-step" aria-labelledby="walkin-step-selection-title">
                            <div class="walkin-step-content walkin-step-content--map">
                                <header class="walkin-pane-header">
                                    <h3 id="walkin-step-selection-title" class="walkin-panel-title">Table Selection</h3>
                                </header>

                                <dl class="walkin-selection-summary">
                                    <div>
                                        <dt>Selected table</dt>
                                        <dd>{{ $selectedTable?->label ?? 'None selected' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Capacity</dt>
                                        <dd>{{ $selectedTable ? (int) $selectedTable->capacity.' seats' : '--' }}</dd>
                                    </div>
                                    <div>
                                        <dt>ETA</dt>
                                        <dd>{{ $selectedTable ? 'Immediate' : $estimatedWait.' min if waitlisted' }}</dd>
                                    </div>
                                </dl>

                                @if ($hasFloorplan)
                                    <div class="walkin-modal-map">
                                        <div class="walkin-modal-map-stage">
                                            <img src="{{ $floorplanUrl }}" alt="Cafe Gervacios floor blueprint" class="walkin-modal-map-image" draggable="false">
                                            @foreach ($walkInTableMarkers as $marker)
                                                <button type="button"
                                                    wire:key="walkin-wizard-map-table-{{ $marker['id'] }}"
                                                    wire:click="selectTable({{ $marker['id'] }})"
                                                    @disabled(! $marker['selectable'])
                                                    aria-disabled="{{ $marker['selectable'] ? 'false' : 'true' }}"
                                                    aria-pressed="{{ $marker['selected'] ? 'true' : 'false' }}"
                                                    class="walkin-table-marker {{ $marker['selectable'] ? 'is-selectable' : 'is-disabled' }} {{ $marker['selected'] ? 'is-selected' : '' }} walkin-table-marker--{{ $marker['status'] }}"
                                                    style="left: {{ $marker['x'] }}%; top: {{ $marker['y'] }}%;"
                                                    title="{{ $marker['label'] }} - {{ $marker['status_label'] }} - {{ $marker['capacity'] }} seats">
                                                    <span class="walkin-table-marker__label">{{ $marker['label'] }}</span>
                                                    <span class="walkin-table-marker__meta">
                                                        <span>{{ $marker['status_label'] }}</span>
                                                        <span>{{ $marker['capacity'] }}p</span>
                                                        @if ($marker['is_accessible'])
                                                            <span>ACC</span>
                                                        @endif
                                                    </span>
                                                    @unless ($marker['selectable'])
                                                        <span class="walkin-table-marker__reason">{{ $marker['reason'] }}</span>
                                                    @endunless
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="walkin-operational-note">
                                        <strong>No blueprint uploaded.</strong>
                                        <span>Continue with waitlist registration.</span>
                                    </div>
                                @endif
                            </div>

                            <div class="walkin-action-row">
                                <button type="button" wire:click="backToGuestDetails"
                                    class="walkin-action-secondary">
                                    Back
                                </button>
                                <button type="button"
                                    wire:click="continueToReview('{{ $selectionAction }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="continueToReview"
                                    class="walkin-action-primary disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none">
                                    Continue
                                </button>
                            </div>
                        </section>
                    @elseif ($wizardStep === 'review')
                        <section class="walkin-workflow-panel" aria-labelledby="walkin-step-review-title">
                            <div class="walkin-step-content walkin-step-content--review">
                                <header class="walkin-pane-header">
                                    <h3 id="walkin-step-review-title" class="walkin-panel-title">Review & Confirm</h3>
                                </header>

                                <div class="walkin-review-edit-row">
                                    <button type="button" wire:click="backToGuestDetails">Edit Guest Details</button>
                                    @if ($hasSeatingOption)
                                        <button type="button" wire:click="backToTableSelection">Edit Table Selection</button>
                                    @endif
                                </div>

                                <dl class="walkin-review-list">
                                    <div><dt>Guest</dt><dd>{{ $customer_name }}</dd></div>
                                    <div><dt>Phone</dt><dd>{{ filled(trim($customer_phone)) ? $customer_phone : 'No phone number provided' }}</dd></div>
                                    <div><dt>Party</dt><dd>{{ (int) $party_size }} guests</dd></div>
                                    <div><dt>Priority</dt><dd>{{ $summaryPriority }}</dd></div>
                                    @if ($reviewIsSeating && $selectedTable)
                                        <div><dt>Selected Table</dt><dd>{{ $selectedTable->label }} - {{ (int) $selectedTable->capacity }} seats</dd></div>
                                        <div><dt>ETA</dt><dd>Immediate</dd></div>
                                    @else
                                        <div><dt>Selected Table</dt><dd>Waitlist</dd></div>
                                        <div><dt>ETA</dt><dd>{{ $estimatedWait }} min</dd></div>
                                    @endif
                                    <div><dt>Email</dt><dd>{{ filled(trim($customer_email)) ? $customer_email : 'No email provided' }}</dd></div>
                                    <div><dt>Notify</dt><dd>{{ $notificationEnabled ? (filled(trim($customer_email)) ? 'Email' : 'SMS') : 'Disabled' }}</dd></div>
                                </dl>
                            </div>

                            <div class="walkin-action-row">
                                <button type="button" wire:click="{{ $reviewBackAction }}"
                                    class="walkin-action-secondary">
                                    Back
                                </button>
                                <button type="button" wire:click="confirmWizardAction"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmWizardAction"
                                    class="walkin-action-primary disabled:cursor-wait disabled:opacity-60">
                                    {{ $reviewIsSeating ? 'Confirm & Seat Guest' : 'Confirm & Add to Waitlist' }}
                                </button>
                            </div>
                        </section>
                    @else
                        <section class="walkin-workflow-panel is-success" aria-labelledby="walkin-step-success-title">
                            <div class="walkin-success-row">
                                <span class="walkin-success-icon" aria-hidden="true">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                                <div class="min-w-0 text-left">
                                    <h3 id="walkin-step-success-title" class="text-lg font-black text-slate-950">{{ $successTitle ?? 'Walk-in complete.' }}</h3>
                                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $successDetail ?? 'Queue has been refreshed.' }}</p>
                                </div>
                            </div>
                            @if (! empty($successSummary))
                                <dl class="walkin-success-summary" aria-label="Walk-in result summary">
                                    @foreach ($successSummary as $item)
                                        <div>
                                            <dt>{{ $item['label'] }}</dt>
                                            <dd>{{ $item['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                            <div class="walkin-action-row is-single">
                                <button type="button" wire:click="startAnotherWalkIn"
                                    wire:loading.attr="disabled"
                                    wire:target="startAnotherWalkIn"
                                    class="walkin-action-secondary disabled:cursor-wait disabled:opacity-60">
                                    Register another
                                </button>
                                <button type="button" wire:click="finishWizard"
                                    wire:loading.attr="disabled"
                                    wire:target="finishWizard"
                                    class="walkin-action-primary disabled:cursor-wait disabled:opacity-60">
                                    Done
                                </button>
                            </div>
                        </section>
                    @endif
                </div>
            @else
            <div class="grid gap-4 lg:grid-cols-[minmax(320px,420px)_minmax(0,1fr)]">
                <div class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 rounded-xl border {{ $hasSeatingOption ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-4 py-3">
                        <p class="text-[12px] font-bold uppercase tracking-[0.1em] {{ $hasSeatingOption ? 'text-emerald-800' : 'text-amber-900' }}">
                            Step 1 - Guest details
                        </p>
                        @if ($hasSeatingOption)
                            <p class="mt-1 text-sm font-semibold {{ $selectedTable ? 'text-emerald-950' : 'text-slate-900' }}">
                                {{ $selectedTable ? 'Ready to seat at '.$selectedTable->label.'.' : 'Select an available table to seat guest.' }}
                            </p>
                        @else
                            <p class="mt-1 text-sm font-semibold text-amber-950">No suitable table available. Guest will be added to the waitlist.</p>
                            <p class="mt-1 text-xs font-bold text-amber-900">Estimated wait: {{ $estimatedWait }} min</p>
                        @endif
                    </div>

                    <form wire:submit.prevent="submitGuidedAction" class="space-y-4">
                        <input type="hidden" wire:model="selectedTableId" name="selected_table_id">

                        <div>
                            <label for="walkin-name" class="block text-[12px] font-semibold text-slate-700">
                                Guest Name <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <input type="text" wire:model.blur="customer_name"
                                id="walkin-name" aria-required="true" aria-describedby="walkin-name-help walkin-name-error"
                                placeholder="e.g., Juan Dela Cruz"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_name') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-name-help" class="mt-1 text-[11px] text-slate-500">Enter the guest's full name.</p>
                            @error('customer_name')
                                <p id="walkin-name-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="walkin-phone" class="block text-[12px] font-semibold text-slate-700">Phone <span class="font-medium text-slate-400">(optional)</span></label>
                            <input type="tel" wire:model.blur="customer_phone"
                                id="walkin-phone" aria-describedby="walkin-phone-help walkin-phone-error"
                                placeholder="e.g., 09171234567"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_phone') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-phone-help" class="mt-1 text-[11px] text-slate-500">Leave blank if the guest does not
                                want SMS updates.</p>
                            @error('customer_phone')
                                <p id="walkin-phone-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="walkin-email-page" class="block text-[12px] font-semibold text-slate-700">Email <span class="font-medium text-slate-400">(optional)</span></label>
                            <input type="email" wire:model.blur="customer_email"
                                id="walkin-email-page" aria-describedby="walkin-email-page-help walkin-email-page-error"
                                placeholder="e.g., guest@email.com"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('customer_email') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-email-page-help" class="mt-1 text-[11px] text-slate-500">Used for table-ready email notification.</p>
                            @error('customer_email')
                                <p id="walkin-email-page-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="walkin-party-size" class="block text-[12px] font-semibold text-slate-700">
                                Party Size <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <input type="number" wire:model.blur="party_size" min="1" max="20"
                                id="walkin-party-size" aria-required="true"
                                aria-describedby="walkin-party-size-help walkin-party-size-error"
                                placeholder="e.g., 4"
                                class="mt-1 min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-800 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400/25 {{ $errors->has('party_size') ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : 'border-slate-200 focus:border-slate-400' }}">
                            <p id="walkin-party-size-help" class="mt-1 text-[11px] text-slate-500">Enter 1 to 20 guests.</p>
                            @error('party_size')
                                <p id="walkin-party-size-error" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-[12px] font-semibold text-slate-700">Priority Type</label>
                            <select wire:model.live="priority_type"
                                class="mt-1 min-h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-800 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400/25">
                                <option value="none">Regular</option>
                                <option value="pwd">PWD</option>
                                <option value="pregnant">Pregnant</option>
                                <option value="senior">Senior</option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500">Priority score and PWD accessible-table rule are applied automatically.</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <span class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Selected Table</span>
                            @if ($selectedTable)
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900">Selected Table: {{ $selectedTable->label }}</span>
                                    <button type="button" wire:click="clearSelectedTable"
                                        class="inline-flex min-h-9 items-center rounded-lg px-2 text-xs font-semibold text-slate-500 underline-offset-2 hover:bg-white hover:text-slate-800 hover:underline">
                                        Clear
                                    </button>
                                </div>
                            @else
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $hasSeatingOption ? 'Tap one highlighted table marker.' : 'No table is needed for waitlist registration.' }}
                                </p>
                            @endif
                        </div>

                        <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="submitGuidedAction"
                            @disabled($primaryDisabled)
                            class="mt-1 flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-panel-primary px-4 py-3 text-base font-bold text-panel-on-bright shadow-sm transition-colors hover:bg-panel-primary-hover disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 disabled:shadow-none">
                            <i class="fa-solid {{ $hasSeatingOption ? 'fa-chair' : 'fa-plus' }} text-sm" aria-hidden="true"></i>
                            {{ $primaryLabel }}
                        </button>
                    </form>
                </div>

                <div class="rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm sm:p-4">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-[12px] font-bold uppercase tracking-[0.12em] text-slate-500">Floor Map</p>
                            <h3 class="mt-0.5 text-sm font-bold text-slate-900">Choose from table markers</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                            {{ $walkInTableMarkers->where('selectable', true)->count() }} free match
                        </span>
                    </div>

                    @if ($accessibleRequired)
                        <p class="mb-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-900">
                            PWD accessible-table rule is active.
                        </p>
                    @endif

                    @if ($hasFloorplan)
                        <div class="walkin-map-scroll tc-scrollbar overflow-auto rounded-lg border border-slate-200 bg-slate-100">
                            <div class="walkin-map-stage">
                                <img src="{{ $floorplanUrl }}" alt="Cafe Gervacios floor blueprint" class="walkin-map-image" draggable="false">
                                @foreach ($walkInTableMarkers as $marker)
                                    <button type="button"
                                        wire:key="walkin-map-table-{{ $marker['id'] }}"
                                        wire:click="selectTable({{ $marker['id'] }})"
                                        aria-disabled="{{ $marker['selectable'] ? 'false' : 'true' }}"
                                        aria-pressed="{{ $marker['selected'] ? 'true' : 'false' }}"
                                        class="walkin-table-marker {{ $marker['selectable'] ? 'is-selectable' : 'is-disabled' }} {{ $marker['selected'] ? 'is-selected' : '' }} walkin-table-marker--{{ $marker['status'] }}"
                                        style="left: {{ $marker['x'] }}%; top: {{ $marker['y'] }}%;"
                                        title="{{ $marker['label'] }} · {{ $marker['status_label'] }} · {{ $marker['capacity'] }} seats">
                                        <span class="walkin-table-marker__label">{{ $marker['label'] }}</span>
                                        <span class="walkin-table-marker__meta">
                                            <span>{{ $marker['status_label'] }}</span>
                                            <span>{{ $marker['capacity'] }}p</span>
                                            @if ($marker['is_accessible'])
                                                <span>ACC</span>
                                            @endif
                                        </span>
                                        @unless ($marker['selectable'])
                                            <span class="walkin-table-marker__reason">{{ $marker['reason'] }}</span>
                                        @endunless
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">
                            No blueprint uploaded yet. The walk-in queue can still be used without table selection.
                        </p>
                    @endif
                </div>
            </div>

            @unless ($modalMode)
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
            @endunless
            @endif
        </div>
    </div>

    <style>
        .walkin-workflow {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 0.85rem;
            height: 100%;
            min-height: 0;
            color: #0f172a;
        }

        .walkin-stepper {
            border-bottom: 1px solid #e5e7eb;
            padding: 0.1rem 0 0.85rem;
        }

        .walkin-stepper__list {
            display: flex;
            align-items: center;
            justify-content: stretch;
            width: 100%;
            gap: 0;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .walkin-stepper__item {
            position: relative;
            display: flex;
            flex: 0 0 auto;
            min-width: 0;
            align-items: center;
            gap: 6px;
            color: #94a3b8;
            font-size: 0.76rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .walkin-stepper__item:not(:first-child) {
            flex: 1 1 0;
            margin-left: 0;
        }

        .walkin-stepper__line {
            display: block;
            flex: 1 1 auto;
            min-width: 1.5rem;
            height: 1px;
            margin: 0 0.5rem;
            border-top: 0;
            background: #e5e7eb;
        }

        .walkin-stepper__dot {
            display: inline-flex;
            height: 1.65rem;
            width: 1.65rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #eef2f7;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .walkin-stepper__item.is-active {
            color: #0f172a;
        }

        .walkin-stepper__item.is-active .walkin-stepper__dot {
            background: #0f172a;
            color: #fff;
            box-shadow: 0 6px 12px rgb(15 23 42 / 0.14);
        }

        .walkin-stepper__item.is-complete {
            color: #334155;
        }

        .walkin-stepper__item.is-complete .walkin-stepper__dot {
            background: #dcfce7;
            color: #15803d;
        }

        .walkin-stepper__item.is-complete .walkin-stepper__line {
            background: #bbf7d0;
        }

        .walkin-workflow-panel {
            display: flex;
            min-height: 0;
            height: 100%;
            flex-direction: column;
            border: 1px solid #e5e7eb;
            border-radius: 0.9rem;
            background: #fff;
            padding: 1rem;
            box-shadow: 0 8px 18px rgb(15 23 42 / 0.04);
        }

        .walkin-workflow label {
            font-size: 0.75rem !important;
            font-weight: 750 !important;
            color: #334155 !important;
        }

        .walkin-workflow input,
        .walkin-workflow select {
            min-height: 3rem !important;
            border-radius: 0.75rem !important;
            font-size: 0.95rem !important;
            box-shadow: none !important;
        }

        .walkin-workflow input::placeholder {
            color: #94a3b8;
            font-weight: 600;
        }

        .walkin-workflow [id$="-help"] {
            font-size: 0.72rem !important;
            color: #64748b !important;
        }

        .walkin-workflow-panel__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 0.8rem;
            margin-bottom: 0.9rem;
        }

        .walkin-kicker {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .walkin-panel-title {
            margin-top: 0.15rem;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 850;
            line-height: 1.2;
        }

        .walkin-panel-subtitle {
            margin-top: 0.25rem;
            color: #64748b;
            font-size: 0.82rem;
        }

        .walkin-status-note {
            display: grid;
            gap: 0.15rem;
            max-width: 21rem;
            border-left: 2px solid #cbd5e1;
            background: #f8fafc;
            padding: 0.55rem 0.75rem;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 750;
            text-align: left;
        }

        .walkin-status-note.is-ready {
            border-color: #10b981;
            background: #fbfefc;
            color: #166534;
        }

        .walkin-status-note.is-waiting {
            border-color: #f59e0b;
            background: #fffdf5;
            color: #92400e;
        }

        .walkin-section {
            margin-bottom: 0.85rem;
        }

        .walkin-section__title {
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 850;
        }

        .walkin-section__hint {
            margin-top: 0.2rem;
            color: #64748b;
            font-size: 0.75rem;
        }

        .walkin-count-pill {
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 0.34rem 0.68rem;
            color: #334155;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .walkin-inline-alert {
            margin-bottom: 0.75rem;
            border-left: 2px solid #38bdf8;
            background: #f8fcff;
            padding: 0.55rem 0.75rem;
            color: #075985;
            font-size: 0.78rem;
            font-weight: 750;
        }

        .walkin-step-form {
            display: flex;
            min-height: 0;
            flex: 1;
            flex-direction: column;
            gap: 1rem;
            overflow-y: auto;
            padding-right: 0.15rem;
        }

        .walkin-action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: auto;
            border-top: 1px solid #edf2f7;
            padding-top: 1rem;
        }

        .walkin-action-row.is-single {
            justify-content: flex-end;
        }

        .walkin-action-primary,
        .walkin-action-secondary {
            display: inline-flex;
            height: 3rem;
            width: min(100%, 14rem);
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.7rem;
            padding: 0 1rem;
            font-size: 0.9rem;
            font-weight: 850;
            line-height: 1.1;
            text-align: center;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }

        .walkin-action-primary {
            background: #0f172a;
            color: #fff;
            box-shadow: 0 6px 14px rgb(15 23 42 / 0.1);
        }

        .walkin-action-primary:hover:not(:disabled) {
            background: #1e293b;
        }

        .walkin-action-secondary {
            border: 1px solid #dbe3ef;
            background: #fff;
            color: #0f172a;
        }

        .walkin-action-secondary:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .walkin-review {
            display: grid;
            min-height: 0;
            flex: 1;
            gap: 0.85rem;
            align-content: start;
            overflow-y: auto;
            padding-right: 0.15rem;
        }

        .walkin-review__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .walkin-review__header h4 {
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 850;
        }

        .walkin-review__header p {
            margin-top: 0.15rem;
            color: #64748b;
            font-size: 0.76rem;
        }

        .walkin-review__edits {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.55rem;
        }

        .walkin-review__edits button {
            color: #334155;
            font-size: 0.74rem;
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .walkin-review-edit-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .walkin-review-edit-row button {
            color: #334155;
            font-size: 0.74rem;
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .walkin-review-list {
            display: grid;
            gap: 0.15rem;
            overflow: hidden;
            color: #334155;
            font-size: 0.86rem;
        }

        .walkin-review-list div {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            min-height: 2.35rem;
            border-radius: 0.65rem;
            background: #f8fafc;
            padding: 0.52rem 0.75rem;
        }

        .walkin-review-list dt {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 750;
        }

        .walkin-review-list dd {
            color: #0f172a;
            font-weight: 800;
            text-align: right;
        }

        .walkin-workflow-panel.is-success {
            padding: 1rem;
        }

        .walkin-success-row {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            max-width: 36rem;
        }

        .walkin-success-icon {
            display: inline-flex;
            height: 2rem;
            width: 2rem;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #f0fdf4;
            color: #15803d;
            font-size: 0.85rem;
        }

        @media (max-width: 760px) {
            .walkin-stepper__label {
                display: none;
            }

            .walkin-stepper__item:not(:first-child) {
                flex: 1 1 0;
                margin-left: 0;
            }

            .walkin-stepper__line {
                width: auto;
                min-width: 1rem;
            }

            .walkin-workflow-panel__header {
                display: grid;
            }

            .walkin-status-note {
                max-width: none;
            }

            .walkin-action-row,
            .walkin-action-row.is-single {
                justify-content: stretch;
            }

            .walkin-action-primary,
            .walkin-action-secondary {
                width: 100%;
            }

            .walkin-review__header {
                display: grid;
            }

            .walkin-review__edits {
                justify-content: flex-start;
            }

            .walkin-review-list div {
                grid-template-columns: 1fr;
                gap: 0.15rem;
            }

            .walkin-review-list dd {
                text-align: left;
            }
        }

        .walkin-wizard-map-scroll {
            display: grid;
            min-height: 0;
            flex: 1;
            height: auto;
            overflow: hidden;
            place-items: center;
            padding: 0.5rem;
        }

        .walkin-wizard-map-stage {
            position: relative;
            display: inline-block;
            max-width: 100%;
            max-height: 100%;
            line-height: 0;
            vertical-align: top;
        }

        .walkin-wizard-map-image {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            pointer-events: none;
            user-select: none;
        }

        .walkin-workflow {
            height: auto;
            max-height: calc(90vh - 7rem);
            overflow: hidden;
            grid-template-rows: auto auto;
            gap: 0.75rem;
        }

        .walkin-stepper {
            display: flex;
            align-items: center;
            width: 100%;
            min-height: 2.4rem;
            overflow: hidden;
            padding: 0 0 0.65rem;
            margin: 0;
        }

        .walkin-stepper__dot {
            height: 1.28rem;
            width: 1.28rem;
            font-size: 0.64rem;
        }

        .walkin-stepper__label {
            font-size: 0.68rem;
        }

        .walkin-workflow-panel {
            display: grid;
            grid-template-rows: minmax(0, 1fr) auto;
            gap: 0;
            height: auto;
            max-height: calc(90vh - 10rem);
            overflow: hidden;
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 0;
            box-shadow: none;
        }

        .walkin-workflow-panel.is-map-step {
            max-height: calc(90vh - 10rem);
        }

        .walkin-panel-form {
            display: grid;
            grid-template-rows: minmax(0, 1fr) auto;
            min-height: 0;
            height: auto;
            overflow: hidden;
        }

        .walkin-step-content {
            display: grid;
            flex: 1 1 auto;
            min-height: 0;
            gap: 1.1rem;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.35rem 0 1rem;
        }

        .walkin-step-content--form,
        .walkin-step-content--review {
            align-content: start;
        }

        .walkin-step-content--map {
            gap: 0.9rem;
            padding: 0.35rem 0 1rem;
        }

        .walkin-two-column {
            display: grid;
            min-height: 0;
            height: 100%;
            overflow: auto;
            grid-template-columns: minmax(0, 0.38fr) minmax(0, 0.62fr);
            gap: 1rem;
            padding: 0.85rem;
        }

        .walkin-left-pane,
        .walkin-right-pane {
            display: flex;
            min-height: 0;
            height: 100%;
            flex-direction: column;
            gap: 0.7rem;
            overflow: visible;
        }

        .walkin-left-pane {
            padding-right: 0;
        }

        .walkin-right-pane {
            border-radius: 0.85rem;
            background: #f8fafc;
            padding: 0.75rem;
        }

        .walkin-pane-header {
            display: grid;
            gap: 0.15rem;
        }

        .walkin-pane-header.is-compact {
            flex: 0 0 auto;
        }

        .walkin-side-title {
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 850;
            line-height: 1.2;
        }

        .walkin-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.25rem;
        }

        .walkin-field-full {
            grid-column: 1 / -1;
        }

        .walkin-field-pair {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 0.75rem;
        }

        .walkin-party-stepper {
            display: grid;
            grid-template-columns: 3rem minmax(0, 1fr) 3rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #fff;
        }

        .walkin-party-stepper.is-invalid {
            border-color: #fca5a5;
            box-shadow: 0 0 0 2px rgb(254 202 202 / 0.75);
        }

        .walkin-party-stepper button,
        .walkin-party-stepper input {
            min-height: 3rem !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .walkin-party-stepper button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 900;
            transition: background 0.15s ease;
        }

        .walkin-party-stepper button:hover {
            background: #f8fafc;
        }

        .walkin-party-stepper input {
            width: 100%;
            border-left: 1px solid #e2e8f0 !important;
            border-right: 1px solid #e2e8f0 !important;
            text-align: center;
            font-weight: 850;
        }

        .walkin-selection-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0.85rem;
            background: #f8fafc;
        }

        .walkin-selection-summary div {
            min-width: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 0.7rem 0.9rem;
        }

        .walkin-selection-summary div + div {
            border-left: 1px solid #e5e7eb;
        }

        .walkin-selection-summary dt {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .walkin-selection-summary dd {
            margin-top: 0.2rem;
            overflow: hidden;
            color: #0f172a;
            font-size: 0.86rem;
            font-weight: 850;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .walkin-side-summary {
            display: grid;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
        }

        .walkin-side-summary div {
            display: grid;
            grid-template-columns: minmax(7rem, 0.45fr) minmax(0, 1fr);
            gap: 0.75rem;
            align-items: center;
            min-height: 2.55rem;
            padding: 0.58rem 0.75rem;
        }

        .walkin-side-summary div + div {
            border-top: 1px solid #edf2f7;
        }

        .walkin-side-summary dt {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 750;
        }

        .walkin-side-summary dd {
            color: #0f172a;
            font-size: 0.84rem;
            font-weight: 800;
            text-align: right;
        }

        .walkin-operational-note,
        .walkin-decision-panel {
            display: grid;
            gap: 0.3rem;
            border-left: 2px solid #cbd5e1;
            background: #fff;
            padding: 0.7rem 0.8rem;
            color: #334155;
            font-size: 0.8rem;
        }

        .walkin-operational-note strong,
        .walkin-decision-panel strong {
            color: #0f172a;
            font-weight: 850;
        }

        .walkin-decision-panel.is-ready {
            border-color: #10b981;
        }

        .walkin-decision-panel.is-waiting {
            border-color: #f59e0b;
        }

        .walkin-decision-panel__label {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .walkin-modal-map {
            display: grid;
            min-height: 0;
            width: 100%;
            height: auto;
            flex: 1 1 auto;
            place-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            padding: 0.75rem;
            overflow: hidden;
        }

        .walkin-modal-map.is-preview {
            max-height: none;
            opacity: 0.92;
        }

        .walkin-modal-map.is-review {
            flex: 1 1 auto;
            min-height: 0;
        }

        .walkin-modal-map-stage {
            position: relative;
            display: inline-block;
            max-width: 100%;
            line-height: 0;
        }

        .walkin-modal-map-image {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: min(30vh, 17rem);
            object-fit: contain;
            object-position: center;
            pointer-events: none;
            user-select: none;
        }

        .walkin-workflow .walkin-table-marker {
            min-width: 4.25rem;
            min-height: 2.85rem;
            padding: 0.36rem 0.44rem;
            border-radius: 0.6rem;
        }

        .walkin-workflow .walkin-table-marker.is-preview {
            min-width: 2.6rem;
            min-height: 1.85rem;
            padding: 0.25rem 0.32rem;
            pointer-events: none;
        }

        .walkin-workflow .walkin-table-marker.is-preview .walkin-table-marker__label {
            font-size: 0.66rem;
        }

        .walkin-action-row {
            position: sticky;
            bottom: 0;
            z-index: 5;
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            min-height: 4rem;
            max-height: 4.25rem;
            margin: 0;
            border-top: 1px solid #e5e7eb;
            background: #fff;
            padding: 1rem 0 0;
        }

        .walkin-action-row.is-single {
            justify-content: flex-end;
        }

        .walkin-action-primary,
        .walkin-action-secondary {
            height: 3rem;
            min-width: 10.5rem;
            width: auto;
            max-width: 14rem;
            border-radius: 0.75rem;
            font-size: 0.86rem;
        }

        .walkin-action-secondary:disabled {
            cursor: default;
            opacity: 0.45;
        }

        .walkin-workflow-panel.is-success {
            align-items: stretch;
            justify-content: stretch;
            text-align: center;
        }

        .walkin-workflow-panel.is-success .walkin-success-row {
            align-self: center;
            justify-self: center;
            padding: 1rem;
        }

        .walkin-success-summary {
            display: grid;
            gap: 0.15rem;
            align-self: start;
            margin: 0 1.25rem 1rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            color: #334155;
            font-size: 0.86rem;
        }

        .walkin-success-summary div {
            display: flex;
            min-height: 2.35rem;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.52rem 0.75rem;
        }

        .walkin-success-summary div + div {
            border-top: 1px solid #edf2f7;
        }

        .walkin-success-summary dt {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 750;
        }

        .walkin-success-summary dd {
            color: #0f172a;
            font-weight: 800;
            text-align: right;
        }

        .walkin-workflow-panel.is-success .walkin-action-row {
            width: 100%;
            justify-content: flex-end;
            margin: 0;
        }

        @media (max-height: 700px) {
            .walkin-modal-map-image {
                max-height: min(32vh, 16rem);
            }
        }

        .walkin-map-scroll {
            max-height: min(500px, 58vh);
        }

        .walkin-map-stage {
            position: relative;
            display: inline-block;
            min-width: min(760px, 100%);
            line-height: 0;
            vertical-align: top;
        }

        .walkin-map-image {
            display: block;
            width: 100%;
            height: auto;
            pointer-events: none;
            user-select: none;
        }

        .walkin-table-marker {
            position: absolute;
            z-index: 8;
            transform: translate(-50%, -50%);
            display: grid;
            min-width: 5rem;
            min-height: 3.5rem;
            gap: 0.12rem;
            border-radius: 0.7rem;
            border: 2px solid #94a3b8;
            background: rgba(255, 255, 255, 0.96);
            padding: 0.48rem 0.56rem;
            text-align: center;
            color: #0f172a;
            box-shadow: 0 8px 18px rgb(15 23 42 / 0.15);
            line-height: 1.05;
            transition: box-shadow 0.14s ease, opacity 0.14s ease, transform 0.14s ease;
        }

        .walkin-table-marker.is-selectable {
            cursor: pointer;
            border-color: #16a34a;
            outline: 4px solid rgba(22, 163, 74, 0.16);
            outline-offset: 3px;
        }

        .walkin-table-marker.is-selectable:hover {
            transform: translate(-50%, -50%) translateY(-1px);
            box-shadow: 0 12px 26px rgb(15 23 42 / 0.22);
        }

        .walkin-table-marker.is-selected {
            border-color: #0f172a;
            outline: 5px solid rgba(15, 23, 42, 0.28);
            outline-offset: 4px;
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.18), 0 14px 30px rgb(15 23 42 / 0.22);
        }

        .walkin-table-marker.is-disabled {
            cursor: not-allowed;
            opacity: 0.42;
            filter: grayscale(0.9);
            background: repeating-linear-gradient(
                135deg,
                rgba(248, 250, 252, 0.95),
                rgba(248, 250, 252, 0.95) 7px,
                rgba(226, 232, 240, 0.95) 7px,
                rgba(226, 232, 240, 0.95) 14px
            );
        }

        .walkin-table-marker--reserved {
            border-color: #d97706;
        }

        .walkin-table-marker--occupied {
            border-color: #dc2626;
        }

        .walkin-table-marker--cleaning {
            border-color: #64748b;
        }

        .walkin-table-marker__label {
            font-size: 0.86rem;
            font-weight: 900;
        }

        .walkin-table-marker__meta,
        .walkin-table-marker__reason {
            display: inline-flex;
            justify-content: center;
            gap: 0.24rem;
            font-size: 0.55rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
        }

        .walkin-table-marker__reason {
            color: #991b1b;
        }

        .walkin-workflow .walkin-table-marker {
            min-width: 3rem;
            min-height: 3rem;
            border-radius: 0.75rem;
            padding: 0.34rem 0.42rem;
        }

        .walkin-workflow .walkin-table-marker.is-preview {
            min-width: 3rem;
            min-height: 3rem;
            padding: 0.25rem 0.32rem;
            pointer-events: none;
        }

        .walkin-workflow .walkin-table-marker.is-preview .walkin-table-marker__label {
            font-size: 0.66rem;
        }

        .walkin-workflow .walkin-table-marker.is-disabled {
            opacity: 0.58;
            filter: grayscale(0.55);
        }

        @media (max-width: 900px) {
            .walkin-selection-summary {
                grid-template-columns: 1fr;
            }

            .walkin-selection-summary div + div {
                border-left: 0;
                border-top: 1px solid #e5e7eb;
            }

            .walkin-fields {
                grid-template-columns: 1fr;
            }

            .walkin-two-column {
                grid-template-columns: 1fr;
                overflow-y: auto;
            }

            .walkin-right-pane {
                min-height: 18rem;
            }

            .walkin-action-primary,
            .walkin-action-secondary {
                min-width: 0;
                width: min(100%, 12rem);
            }
        }
    </style>
</div>
