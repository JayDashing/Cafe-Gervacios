@php
    use App\Models\Setting;

    $queueSettingsOn =
        (int) $automationMasterEnabled +
        (int) $queuePwdRequiresAccessibleTable;
    $ipLines = collect(preg_split('/\r\n|\r|\n/', $blockedIpsText ?? ''))
        ->map(fn ($l) => trim($l))
        ->filter(fn ($l) => $l !== '')
        ->count();
    $qrHasTemp = file_exists(public_path('images/qrcode-temp.png'));
    try {
        $peakStart12 = \Illuminate\Support\Carbon::parse($peakHoursStart)->format('g:i A');
        $peakEnd12 = \Illuminate\Support\Carbon::parse($peakHoursEnd)->format('g:i A');
    } catch (\Throwable $e) {
        $peakStart12 = substr((string) $peakHoursStart, 0, 5);
        $peakEnd12 = substr((string) $peakHoursEnd, 0, 5);
    }

    $modalTitles = [
        'devices' => 'Queue',
        'timing' => 'Timing',
        'peak' => 'Texts',
        'alerts' => 'Alerts',
        'paymongo' => 'Online payments',
        'philsms' => 'Text messages',
        'facebook' => 'Facebook',
        'qr' => 'QR code',
    ];
    $allowedModals = array_keys($modalTitles);
@endphp

<div class="flex min-h-0 w-full min-w-0 flex-1 flex-col">
    @once
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    @endonce

    <div
        class="grid grid-cols-1 gap-3 xl:grid-cols-12 xl:gap-4 xl:[grid-template-rows:minmax(0,1fr)_minmax(0,1fr)] xl:min-h-0 xl:flex-1">
        <div id="settings-unified"
            class="flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm scroll-mt-24 xl:col-span-8 xl:row-start-1">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-gray-200 px-3 py-2.5 sm:px-4 sm:py-3">
                <div>
                    <h2 class="text-base font-bold text-gray-800 sm:text-lg">Automation</h2>
                    <p class="mt-0.5 text-xs text-gray-500 sm:text-sm">
                        Queue and alerts.
                        <a href="{{ route('admin.logs') }}"
                            class="font-medium text-slate-700 underline underline-offset-2 hover:text-panel-primary">Logs</a>
                    </p>
                </div>
            </div>
            <div class="flex min-h-0 flex-1 flex-col p-3 sm:p-4">
                <div class="grid min-w-0 w-full grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3 [&>button]:min-w-0">
                    <button type="button" wire:click="openSettingsModal('devices')"
                        onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                        class="group flex min-h-[5.5rem] w-full flex-col rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-2.5 text-left shadow-sm transition-colors hover:border-slate-300 hover:shadow sm:p-3">
                        <div class="flex items-start justify-between gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100"
                                aria-hidden="true">
                                <i class="fa-solid fa-computer text-[13px] !text-slate-900"></i>
                            </span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Queue</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold leading-tight text-slate-800">Queue</p>
                        <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">{{ $queueSettingsOn }} of 2 on
                        </p>
                    </button>

                    <button type="button" wire:click="openSettingsModal('timing')"
                        onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                        class="group flex min-h-[5.5rem] w-full flex-col rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-2.5 text-left shadow-sm transition-colors hover:border-slate-300 hover:shadow sm:p-3">
                        <div class="flex items-start justify-between gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100"
                                aria-hidden="true">
                                <i class="fa-solid fa-clock text-[13px] !text-slate-900"></i>
                            </span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Times</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold leading-tight text-slate-800">Timing</p>
                        <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">
                            {{ $automationQueueHoldMinutes }}·{{ $automationNoShowMinutes }}·{{ $tableCleaningMinutes }}m
                        </p>
                    </button>

                    <button type="button" wire:click="openSettingsModal('peak')"
                        onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                        class="group flex min-h-[5.5rem] w-full flex-col rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-2.5 text-left shadow-sm transition-colors hover:border-slate-300 hover:shadow sm:p-3">
                        <div class="flex items-start justify-between gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100"
                                aria-hidden="true">
                                <i class="fa-solid fa-comment-dots text-[13px] !text-slate-900"></i>
                            </span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Busy</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold leading-tight text-slate-800">Texts</p>
                        <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">
                            {{ $peakHoursLearnFromQueue ? 'Auto' : 'Set times' }}
                            {{ $peakStart12 }} to {{ $peakEnd12 }}
                        </p>
                    </button>

                    <button type="button" wire:click="openSettingsModal('alerts')"
                        onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                        class="group flex min-h-[5.5rem] w-full flex-col rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-2.5 text-left shadow-sm transition-colors hover:border-slate-300 hover:shadow sm:p-3">
                        <div class="flex items-start justify-between gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100"
                                aria-hidden="true">
                                <i class="fa-solid fa-bell text-[13px] !text-slate-900"></i>
                            </span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Alerts</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold leading-tight text-slate-800">Alerts</p>
                        <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">
                            {{ $adminAlertPhone !== '' ? 'Phone set' : 'No phone' }}
                            · {{ $ipLines }} blocked
                        </p>
                    </button>
                </div>

                <div class="mt-3 space-y-3 border-t border-slate-100 pt-3">
                    @if ($this->blockedIpsDirty)
                        <div class="w-full max-w-md space-y-1.5 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2.5">
                            <label class="block text-xs font-medium text-amber-900">Enter your password to save the block
                                list</label>
                            <input type="password" wire:model="settingsPasswordConfirm" autocomplete="current-password"
                                class="w-full rounded-lg border border-amber-300/80 bg-white px-3 py-2 text-sm text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400/35"
                                placeholder="Enter account password to save">
                            @error('settingsPasswordConfirm')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="text-[11px] text-amber-800/90">Localhost is never blocked and is not saved in this
                                list.</p>
                        </div>
                    @endif
                    @if ($unifiedMessage)
                        <div
                            class="rounded-lg px-3 py-2 text-sm {{ $unifiedStatus === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                            {{ $unifiedMessage }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div id="settings-qr"
            class="flex h-full min-h-0 min-w-0 w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm scroll-mt-24 xl:col-span-4 xl:row-start-1">
            <div class="shrink-0 border-b border-slate-200 px-3 py-2">
                <h2 class="text-sm font-semibold text-slate-800">QR code</h2>
            </div>
            <div class="flex min-h-0 flex-1 flex-col space-y-3 px-3 py-3 sm:py-4">
                @if (
                    $errors->has('qr_image') ||
                        $errors->has('qr_account_name') ||
                        $errors->has('qr_account_number') ||
                        $errors->has('qr_payment_label') ||
                        $errors->has('crop_x') ||
                        $errors->has('crop_y') ||
                        $errors->has('crop_width') ||
                        $errors->has('crop_height'))
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach (['qr_image', 'qr_account_name', 'qr_account_number', 'qr_payment_label', 'crop_x', 'crop_y', 'crop_width', 'crop_height'] as $qrField)
                                @foreach ($errors->get($qrField) as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="flex min-h-0 flex-1 flex-col gap-4 sm:flex-row sm:items-stretch">
                    <div class="flex shrink-0 justify-center sm:w-[7.5rem] sm:justify-start">
                        @if (Setting::get('qr_image_path'))
                            <img src="{{ asset(Setting::get('qr_image_path')) }}?v={{ Setting::get('qr_updated_at') }}"
                                alt=""
                                class="h-24 w-24 rounded-xl border border-slate-100 object-contain sm:h-28 sm:w-28" />
                        @else
                            <div class="h-24 w-24 rounded-xl border border-dashed border-slate-200 bg-slate-50 sm:h-28 sm:w-28">
                            </div>
                        @endif
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col justify-center gap-2">
                        <p class="text-xs leading-relaxed text-slate-500">Guests see this when paying by bank transfer.</p>
                        <button type="button" wire:click="openSettingsModal('qr')"
                            onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-panel-primary px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-panel-primary-hover sm:w-auto">
                            Upload or crop
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="settings-paymongo"
            class="settings-protected flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm scroll-mt-24 xl:col-span-4 xl:row-start-2"
            data-settings-search="paymongo payment deposit gcash maya card webhook mamo grabpay pay mongo">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-3.5 py-2.5">
                <div class="min-w-0 pt-0.5">
                    <h2 class="text-sm font-bold leading-tight text-slate-800">Online payments</h2>
                    <p class="mt-0.5 text-[11px] leading-snug text-slate-500">PayMongo · GCash, Maya, card</p>
                </div>
                <button type="button" wire:click="openSettingsModal('paymongo')"
                    onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                    class="shrink-0 rounded-lg bg-panel-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-panel-primary-hover">
                    Set up</button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col px-3.5 py-3 text-[13px] leading-snug text-slate-600">
                <p class="flex flex-1 items-start gap-2">
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $this->paymongoSecretKeyConfigured ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                    <span>
                        <span class="font-medium text-slate-700">{{ $this->paymongoSecretKeyConfigured ? 'Connected' : 'Not set up' }}</span>
                        <span class="text-slate-400"> · </span>
                        <span class="text-slate-600">Deposit per guest ₱{{ number_format($depositPerGuest) }}</span>
                        <span class="text-slate-400"> · </span>
                        <span class="text-slate-500" title="Shown on reservation form (manual / QR), not the PayMongo charge">Booking
                            fee ₱{{ number_format($reservationFee) }}</span>
                    </span>
                </p>
                @if ($paymongoMessage)
                    <div
                        class="mt-2 rounded-lg px-2.5 py-1.5 text-xs {{ $paymongoStatus === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                        {{ $paymongoMessage }}
                    </div>
                @endif
            </div>
        </div>

        <div id="settings-philsms"
            class="settings-protected flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm scroll-mt-24 xl:col-span-4 xl:row-start-2"
            data-settings-search="philsms sms text message api philippines text blast">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-3.5 py-2.5">
                <div class="min-w-0 pt-0.5">
                    <h2 class="text-sm font-bold leading-tight text-slate-800">Text messages</h2>
                    <p class="mt-0.5 text-[11px] leading-snug text-slate-500">PhilSMS</p>
                </div>
                <button type="button" wire:click="openSettingsModal('philsms')"
                    onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                    class="shrink-0 rounded-lg bg-panel-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-panel-primary-hover">
                    Set up</button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col px-3.5 py-3 text-[13px] leading-snug text-slate-600">
                <p class="flex flex-1 items-start gap-2">
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $this->philSmsApiKeyConfigured ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                    <span>
                        <span class="font-medium text-slate-700">{{ $smsEnabled ? 'Sending texts' : 'Not sending' }}</span>
                        <span class="text-slate-400"> · </span>
                        <span class="text-slate-600">{{ $philSmsSenderId }}</span>
                    </span>
                </p>
                @if ($philSmsMessage)
                    <div
                        class="mt-2 rounded-lg px-2.5 py-1.5 text-xs {{ $philSmsStatus === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                        {{ $philSmsMessage }}
                    </div>
                @endif
            </div>
        </div>

        <div id="settings-facebook"
            class="settings-protected flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm scroll-mt-24 xl:col-span-4 xl:row-start-2"
            data-settings-search="facebook meta page blog token graph sync instagram">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-3.5 py-2.5">
                <div class="min-w-0 pt-0.5">
                    <h2 class="text-sm font-bold leading-tight text-slate-800">Facebook</h2>
                    <p class="mt-0.5 text-[11px] leading-snug text-slate-500">Posts to your site</p>
                </div>
                <button type="button" wire:click="openSettingsModal('facebook')"
                    onclick="window.__settingsModalPreviousFocus = document.activeElement;"
                    class="shrink-0 rounded-lg bg-panel-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-panel-primary-hover">
                    Set up</button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col px-3.5 py-3 text-[13px] leading-snug text-slate-600">
                <p class="flex flex-1 items-start gap-2">
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $fbPageId !== '' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                    <span>
                        <span class="font-medium text-slate-700">{{ $fbPageId !== '' ? 'Connected' : 'Not set up' }}</span>
                    </span>
                </p>
                @if ($syncMessage)
                    <div
                        class="mt-2 rounded-lg px-2.5 py-1.5 text-xs {{ $syncStatus === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                        {{ $syncMessage }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($settingsModal && in_array($settingsModal, $allowedModals, true))
        <x-modal-base wire:transition.opacity.duration.200ms wire:key="settings-modal-{{ $settingsModal }}"
            title="{{ $modalTitles[$settingsModal] ?? 'Settings' }}" titleId="settings-modal-title"
            descriptionId="settings-modal-desc"
            maxWidth="{{ $settingsModal === 'qr' ? 'max-w-xl' : 'max-w-lg' }}" closeAction="closeSettingsModal"
            :showFooter="true">
            @include('admin.settings.partials._' . $settingsModal)

            <x-slot:footer>
                @include('admin.settings.partials._footer', ['settingsModal' => $settingsModal])
            </x-slot>
        </x-modal-base>
    @endif

    <script>
        (function () {
            var cropperInstance = null;

            function destroyCropper() {
                if (cropperInstance) {
                    try {
                        cropperInstance.destroy();
                    } catch (e) {}
                    cropperInstance = null;
                }
            }

            function initCropper() {
                if (typeof Cropper === 'undefined') {
                    return;
                }
                var img = document.getElementById('qr-crop-image');
                var form = document.getElementById('qr-crop-form');
                if (!img || !form || cropperInstance) {
                    return;
                }
                cropperInstance = new Cropper(img, {
                    aspectRatio: 1,
                    viewMode: 1,
                    guides: true,
                    movable: true,
                    zoomable: true,
                    zoomOnWheel: false,
                    responsive: true,
                    restore: false,
                    autoCropArea: 0.5,
                });
                if (!form.dataset.qrCropSubmitBound) {
                    form.dataset.qrCropSubmitBound = '1';
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        if (!cropperInstance) {
                            return;
                        }
                        var d = cropperInstance.getData(true);
                        document.getElementById('crop_x').value = d.x;
                        document.getElementById('crop_y').value = d.y;
                        document.getElementById('crop_width').value = d.width;
                        document.getElementById('crop_height').value = d.height;
                        HTMLFormElement.prototype.submit.call(form);
                    });
                }
            }

            function syncCropperWithDom() {
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var img = document.getElementById('qr-crop-image');
                        if (img && !cropperInstance) {
                            initCropper();
                        }
                        if (!img) {
                            destroyCropper();
                        }
                    });
                });
            }

            document.addEventListener('livewire:init', function () {
                Livewire.hook('morph.updated', function () {
                    syncCropperWithDom();
                });
            });
            document.addEventListener('DOMContentLoaded', syncCropperWithDom);
        })();
    </script>
</div>
