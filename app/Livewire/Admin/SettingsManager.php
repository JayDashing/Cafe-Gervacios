<?php

namespace App\Livewire\Admin;

use App\Jobs\SendSmsJob;
use App\Models\AdminLog;
use App\Models\AutomationLog;
use App\Models\BlockedIp;
use App\Models\Setting;
use App\Services\AutomationEngine;
use App\Services\AutomationSettings;
use App\Services\FacebookPostService;
use App\Services\NotificationService;
use App\Support\IpBlocklist;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SettingsManager extends Component
{
    /** @var array<string, array{property: string, max: int}> */
    private const SECRET_SETTING_FIELDS = [
        'paymongo_public_key' => ['property' => 'paymongoPublicKey', 'max' => 500],
        'paymongo_secret_key' => ['property' => 'paymongoSecretKey', 'max' => 500],
        'paymongo_webhook_secret' => ['property' => 'paymongoWebhookSecret', 'max' => 500],
        'philsms_api_key' => ['property' => 'philSmsApiKey', 'max' => 255],
        'fb_access_token' => ['property' => 'fbAccessToken', 'max' => 1000],
    ];

    // Facebook
    public string $fbPageId = '';

    protected string $fbAccessToken = '';
    public string $syncMessage = '';
    public string $syncStatus = '';

    // PayMongo
    protected string $paymongoPublicKey = '';

    protected string $paymongoSecretKey = '';

    protected string $paymongoWebhookSecret = '';
    public int $depositPerGuest = 500;

    public int $reservationFee = 150;

    public string $paymongoMessage = '';
    public string $paymongoStatus = '';

    // PhilSMS (Philippines)
    protected string $philSmsApiKey = '';

    public string $philSmsSenderId = '';

    public bool $smsEnabled = true;

    public string $philSmsMessage = '';

    public string $philSmsStatus = '';

    public string $philSmsConnectionSummary = '';

    public string $philSmsTestMessage = '';

    public string $philSmsTestStatus = '';

    // Unified touchpoints & automation
    public bool $automationMasterEnabled = true;

    /** When on, PWD waitlist entries require a ♿ table; priority queue order is unchanged. */
    public bool $queuePwdRequiresAccessibleTable = false;

    public int $automationQueueHoldMinutes = 1;

    public int $automationNoShowMinutes = 30;

    public int $tableCleaningMinutes = 10;

    public bool $peakHoursLearnFromQueue = true;

    public string $peakHoursStart = '17:00';

    public string $peakHoursEnd = '22:00';

    public string $adminAlertPhone = '';

    public string $blockedIpsText = '';

    /** Snapshot of blocklist when the page loaded (normalized) — used to detect edits. */
    protected string $blockedIpsSnapshot = '';

    /** Required when saving a changed IP blocklist. */
    public string $settingsPasswordConfirm = '';

    public string $unifiedMessage = '';

    public string $unifiedStatus = '';

    public string $automationProofMessage = '';

    public string $automationProofStatus = '';

    public string $automationProofTask = '';

    /** @var array<int, array{task: string, message: string, status: string, time: string}> */
    public array $automationProofRows = [];

    public bool $automationProofVisible = false;

    /** Which settings modal is open (unified + integration keys). */
    public ?string $settingsModal = null;

    public function mount(): void
    {
        $this->automationMasterEnabled = Setting::get('automation_master_enabled', '1') === '1';
        $this->queuePwdRequiresAccessibleTable = Setting::get('queue_pwd_requires_accessible_table', '0') === '1';
        $this->automationQueueHoldMinutes = (int) Setting::get('automation_queue_hold_minutes', config('automation.queue_hold_minutes', 1));
        $this->automationNoShowMinutes = (int) Setting::get('automation_no_show_minutes', config('automation.no_show_minutes_after_booking', 30));
        $this->tableCleaningMinutes = (int) Setting::get('table_cleaning_minutes', (string) config('automation.table_cleaning_minutes', 10));
        $learnDefault = config('automation.peak_hours_learn_from_queue', true) ? '1' : '0';
        $this->peakHoursLearnFromQueue = Setting::get('peak_hours_learn_from_queue', $learnDefault) === '1';
        $this->peakHoursStart = (string) Setting::get('peak_hours_start', config('automation.peak_hours_start', '17:00'));
        $this->peakHoursEnd = (string) Setting::get('peak_hours_end', config('automation.peak_hours_end', '22:00'));
        $this->adminAlertPhone = (string) Setting::get('admin_alert_phone', '');
        $this->blockedIpsText = BlockedIp::orderBy('ip_address')->pluck('ip_address')->implode("\n");
        $this->blockedIpsSnapshot = $this->normalizeBlockedIpsText($this->blockedIpsText);

        $this->fbPageId = Setting::get('fb_page_id', '');

        $this->depositPerGuest = (int) Setting::get('deposit_per_guest', 500);

        $this->reservationFee = (int) Setting::get('reservation_fee', 150);

        $this->philSmsSenderId = (string) Setting::get('philsms_sender_id', config('services.philsms.sender_id', 'CafeGervacios'));
        $this->smsEnabled = Setting::get('sms_enabled', '1') === '1';
        $this->automationProofVisible = app()->environment('local') && request()->boolean('proof');
        if ($this->automationProofVisible) {
            $this->refreshAutomationProofRows();
        }

        $this->openModalFromQueryIfPresent();
        if ($this->settingsModal === null && $this->shouldAutoOpenQrModal()) {
            $this->openSettingsModal('qr');
        }
    }

    /**
     * Protected credentials are not in the Livewire snapshot; reload from settings on each request.
     */
    public function hydrate(): void
    {
        $this->loadSecretCredentialsFromSettings();
    }

    private function loadSecretCredentialsFromSettings(): void
    {
        $this->fbAccessToken = (string) Setting::get('fb_access_token', '');
        $this->paymongoPublicKey = Setting::get('paymongo_public_key', config('services.paymongo.public_key', ''));
        $this->paymongoSecretKey = Setting::get('paymongo_secret_key', config('services.paymongo.secret_key', ''));
        $this->paymongoWebhookSecret = Setting::get('paymongo_webhook_secret', config('services.paymongo.webhook_secret', ''));
        $this->philSmsApiKey = (string) Setting::get('philsms_api_key', config('services.philsms.api_key', ''));
    }

    /** After QR upload/crop validation errors, reopen the QR modal. */
    protected function shouldAutoOpenQrModal(): bool
    {
        if (session('success') === 'Image uploaded. Now crop the QR code area.') {
            return true;
        }

        $bag = session('errors');
        if ($bag === null || ! is_object($bag) || ! method_exists($bag, 'has')) {
            return false;
        }

        foreach ([
            'qr_image',
            'qr_account_name',
            'qr_account_number',
            'qr_payment_label',
            'crop_x',
            'crop_y',
            'crop_width',
            'crop_height',
        ] as $field) {
            if ($bag->has($field)) {
                return true;
            }
        }

        return false;
    }

    /** Open the matching modal when visiting e.g. /admin/settings?modal=paymongo */
    protected function openModalFromQueryIfPresent(): void
    {
        $modal = request()->query('modal');
        if (! is_string($modal) || $modal === '') {
            return;
        }

        $allowed = ['devices', 'timing', 'peak', 'alerts', 'paymongo', 'philsms', 'facebook', 'qr'];
        if (! in_array($modal, $allowed, true)) {
            return;
        }

        $this->openSettingsModal($modal);
    }

    public function openSettingsModal(string $section): void
    {
        $this->settingsModal = $section;
    }

    public function closeSettingsModal(): void
    {
        $this->settingsModal = null;
        $this->settingsPasswordConfirm = '';
        $this->restoreSettingsModalFocus();
    }

    /**
     * Persist secret/credential fields without exposing them as public Livewire properties.
     * Empty input is ignored so opening a modal with blank password fields does not wipe stored keys.
     */
    public function updateSecret(string $field, string $value): void
    {
        if (! isset(self::SECRET_SETTING_FIELDS[$field])) {
            return;
        }

        $meta = self::SECRET_SETTING_FIELDS[$field];
        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (strlen($value) > $meta['max']) {
            return;
        }

        $this->{$meta['property']} = $value;
        Setting::set($field, $value);
    }

    private function restoreSettingsModalFocus(): void
    {
        $this->js(<<<'JS'
            setTimeout(() => {
                const t = window.__settingsModalPreviousFocus;
                if (t && document.body.contains(t) && typeof t.focus === 'function') {
                    try { t.focus(); } catch (e) {}
                }
                window.__settingsModalPreviousFocus = null;
            }, 10);
        JS);
    }

    #[Computed]
    public function blockedIpsDirty(): bool
    {
        return $this->normalizeBlockedIpsText($this->blockedIpsText) !== $this->blockedIpsSnapshot;
    }

    #[Computed]
    public function paymongoSecretKeyConfigured(): bool
    {
        return $this->paymongoSecretKey !== '';
    }

    #[Computed]
    public function philSmsApiKeyConfigured(): bool
    {
        return $this->philSmsApiKey !== '';
    }

    private function normalizeBlockedIpsText(string $text): string
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($l) => trim($l))
            ->filter(fn ($l) => $l !== '')
            ->sort()
            ->values()
            ->implode("\n");
    }

    public function saveUnifiedFromModal(): void
    {
        $this->saveUnified();
        if ($this->getErrorBag()->has('settingsPasswordConfirm')) {
            return;
        }
        $this->settingsModal = null;
        $this->restoreSettingsModalFocus();
    }

    public function savePaymongoFromModal(): void
    {
        $this->savePaymongo();
        $this->settingsModal = null;
        $this->restoreSettingsModalFocus();
    }

    public function savePhilSmsFromModal(): void
    {
        $this->savePhilSms();
        $this->settingsModal = null;
        $this->restoreSettingsModalFocus();
    }

    public function saveFacebookFromModal(): void
    {
        $this->saveCredentials();
        $this->settingsModal = null;
        $this->restoreSettingsModalFocus();
    }

    public function saveUnified(): void
    {
        $rules = [
            'automationQueueHoldMinutes' => 'required|integer|min:1|max:120',
            'automationNoShowMinutes' => 'required|integer|min:5|max:240',
            'tableCleaningMinutes' => 'required|integer|min:0|max:240',
            'peakHoursStart' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'peakHoursEnd' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'adminAlertPhone' => 'nullable|string|max:20',
            'blockedIpsText' => 'nullable|string|max:5000',
        ];

        if ($this->blockedIpsDirty) {
            $rules['settingsPasswordConfirm'] = 'required|string';
        }

        $this->validate($rules);

        if ($this->blockedIpsDirty) {
            $user = auth()->user();
            if (!$user || !Hash::check($this->settingsPasswordConfirm, $user->password)) {
                $this->addError('settingsPasswordConfirm', 'Enter your current password to change the blocked IP list.');
                return;
            }
        }

        $this->settingsPasswordConfirm = '';

        Setting::set('automation_master_enabled', $this->automationMasterEnabled ? '1' : '0');
        Setting::set('automation_queue_hold_minutes', (string) $this->automationQueueHoldMinutes);
        Setting::set('automation_no_show_minutes', (string) $this->automationNoShowMinutes);
        Setting::set('table_cleaning_minutes', (string) $this->tableCleaningMinutes);
        Setting::set('queue_pwd_requires_accessible_table', $this->queuePwdRequiresAccessibleTable ? '1' : '0');
        Setting::set('peak_hours_learn_from_queue', $this->peakHoursLearnFromQueue ? '1' : '0');
        Setting::set('peak_hours_start', $this->peakHoursStart);
        Setting::set('peak_hours_end', $this->peakHoursEnd);
        Setting::set('admin_alert_phone', $this->adminAlertPhone);

        AutomationSettings::forgetDynamicPeakQueueHoursCache();

        $parsed = collect(preg_split('/\r\n|\r|\n/', $this->blockedIpsText))
            ->map(fn ($l) => trim($l))
            ->filter(fn ($l) => $l !== '' && filter_var($l, FILTER_VALIDATE_IP));

        $hadLoopbackInInput = $parsed->contains(fn ($ip) => IpBlocklist::isExemptFromBlocking($ip));
        $ips = IpBlocklist::filterStorable($parsed);

        BlockedIp::query()->delete();
        foreach ($ips as $ip) {
            BlockedIp::create(['ip_address' => $ip, 'reason' => 'admin']);
        }

        $this->blockedIpsText = collect($ips)->implode("\n");
        $this->blockedIpsSnapshot = $this->normalizeBlockedIpsText($this->blockedIpsText);

        AdminLog::record('update_settings', null, null, 'Updated unified / automation settings');
        $this->unifiedMessage = 'Unified settings saved.';
        if ($hadLoopbackInInput) {
            $this->unifiedMessage .= ' Localhost (127.0.0.1 / ::1) is never blocked.';
        }
        $this->unifiedStatus = 'success';
    }

    public function runAutomationProof(string $task): void
    {
        $labels = [
            'queue_holds' => 'Expire notified hold',
            'wait_estimates' => 'Refresh wait estimates',
            'no_shows' => 'Auto no-show',
            'late_checkin' => 'Late check-in alert',
            'reminders' => 'Reservation reminders',
            'reservation_table_release' => 'Release cancelled table',
            'queue_holds_master_off' => 'Hold expiry with master off',
            'skip_general_when_off' => 'Master-off skip check',
            'failure_alert' => 'Failure alert check',
        ];

        if (! array_key_exists($task, $labels)) {
            return;
        }

        $this->automationProofTask = $labels[$task];
        $this->automationProofStatus = 'success';

        try {
            if ($task === 'queue_holds_master_off') {
                $this->runQueueHoldMasterOffProof();
            } elseif ($task === 'skip_general_when_off') {
                $this->runMasterOffProof();
            } elseif ($task === 'failure_alert') {
                AutomationLog::record('automation_failure_alert', 'Failure recorded; admin alert prepared', [
                    'source' => 'automation_page_proof',
                    'failure_observed' => true,
                ]);
            } else {
                AutomationEngine::run($task);
            }

            $this->automationProofMessage = $labels[$task].' finished.';
        } catch (\Throwable $e) {
            $this->automationProofStatus = 'error';
            $this->automationProofMessage = $e->getMessage();
            AutomationLog::record($task, $e->getMessage(), ['source' => 'automation_page_proof'], false);
        }

        $this->refreshAutomationProofRows();
    }

    private function runMasterOffProof(): void
    {
        $original = Setting::get('automation_master_enabled', '1') === '1';

        Setting::set('automation_master_enabled', '0');
        $this->automationMasterEnabled = false;
        AutomationEngine::run('wait_estimates');
        AutomationLog::record('automation_master_off', 'General automation skipped while master is off', [
            'source' => 'automation_page_proof',
        ]);

        Setting::set('automation_master_enabled', $original ? '1' : '0');
        $this->automationMasterEnabled = $original;
    }

    private function runQueueHoldMasterOffProof(): void
    {
        $original = Setting::get('automation_master_enabled', '1') === '1';

        Setting::set('automation_master_enabled', '0');
        $this->automationMasterEnabled = false;
        AutomationEngine::run('queue_holds');
        AutomationLog::record('queue_holds_master_off', 'Queue hold expiry checked while master is off', [
            'source' => 'automation_page_proof',
        ]);

        Setting::set('automation_master_enabled', $original ? '1' : '0');
        $this->automationMasterEnabled = $original;
    }

    private function refreshAutomationProofRows(): void
    {
        $this->automationProofRows = AutomationLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (AutomationLog $log) => [
                'task' => str_replace('_', ' ', (string) $log->task),
                'message' => (string) ($log->message ?: 'Completed'),
                'status' => $log->success ? 'Passed' : 'Needs check',
                'time' => optional($log->created_at)->format('M j, g:i A') ?? '',
            ])
            ->all();
    }

    public function saveCredentials(): void
    {
        Validator::make(
            [
                'fbPageId' => $this->fbPageId,
                'fbAccessToken' => $this->fbAccessToken,
            ],
            [
                'fbPageId' => 'nullable|string|max:255',
                'fbAccessToken' => 'nullable|string|max:1000',
            ]
        )->validate();

        Setting::set('fb_page_id', $this->fbPageId);
        Setting::set('fb_access_token', $this->fbAccessToken);

        AdminLog::record('update_settings', null, null, 'Updated Facebook credentials');
        $this->syncMessage = 'Credentials saved.';
        $this->syncStatus = 'success';
    }

    public function syncNow(): void
    {
        $this->syncMessage = '';
        $this->syncStatus = '';

        $service = app(FacebookPostService::class);

        if (!$service->isConfigured()) {
            $this->syncMessage = 'Facebook API not configured. Please enter Page ID and Access Token first.';
            $this->syncStatus = 'error';
            return;
        }

        $count = $service->sync();

        if ($count === -1) {
            $this->syncMessage = 'Sync failed. Check that your Page ID and Access Token are correct.';
            $this->syncStatus = 'error';
        } else {
            $this->syncMessage = "{$count} post(s) synced successfully.";
            $this->syncStatus = 'success';
        }
    }

    public function savePaymongo(): void
    {
        Validator::make(
            [
                'paymongoPublicKey' => $this->paymongoPublicKey,
                'paymongoSecretKey' => $this->paymongoSecretKey,
                'paymongoWebhookSecret' => $this->paymongoWebhookSecret,
                'depositPerGuest' => $this->depositPerGuest,
                'reservationFee' => $this->reservationFee,
            ],
            [
                'paymongoPublicKey' => 'nullable|string|max:500',
                'paymongoSecretKey' => 'nullable|string|max:500',
                'paymongoWebhookSecret' => 'nullable|string|max:500',
                'depositPerGuest' => 'required|integer|min:0|max:100000',
                'reservationFee' => 'required|integer|min:0|max:100000',
            ]
        )->validate();

        Setting::set('paymongo_public_key', $this->paymongoPublicKey);
        Setting::set('paymongo_secret_key', $this->paymongoSecretKey);
        Setting::set('paymongo_webhook_secret', $this->paymongoWebhookSecret);
        Setting::set('deposit_per_guest', (string) $this->depositPerGuest);
        Setting::set('reservation_fee', (string) $this->reservationFee);

        AdminLog::record('update_settings', null, null, 'Updated PayMongo settings');
        $this->paymongoMessage = 'PayMongo settings saved.';
        $this->paymongoStatus = 'success';
    }

    public function savePhilSms(): void
    {
        Validator::make(
            [
                'philSmsApiKey' => $this->philSmsApiKey,
                'philSmsSenderId' => $this->philSmsSenderId,
            ],
            [
                'philSmsApiKey' => 'nullable|string|max:255',
                'philSmsSenderId' => 'nullable|string|max:11',
            ]
        )->validate();

        Setting::set('philsms_api_key', $this->philSmsApiKey);
        Setting::set('philsms_sender_id', $this->philSmsSenderId !== '' ? $this->philSmsSenderId : 'CafeGervacios');
        Setting::set('sms_enabled', $this->smsEnabled ? '1' : '0');

        AdminLog::record('update_settings', null, null, 'Updated PhilSMS settings');
        $this->philSmsMessage = 'PhilSMS settings saved.';
        $this->philSmsStatus = 'success';
    }

    public function checkPhilSmsConnection(): void
    {
        $this->philSmsConnectionSummary = '';
        $data = app(NotificationService::class)->checkSmsProviderConnection();

        if ($data === null) {
            $this->philSmsConnectionSummary = 'Could not verify PhilSMS. Save a valid API key first.';

            return;
        }

        $status = $data['status'] ?? '';
        $this->philSmsConnectionSummary = 'PhilSMS API reachable. Status: '.($status !== '' ? $status : 'success').'.';
    }

    public function sendTestSms(): void
    {
        $this->philSmsTestMessage = '';
        $this->philSmsTestStatus = '';

        $this->validate([
            'adminAlertPhone' => ['required', 'string', 'max:20'],
        ]);

        try {
            Bus::dispatchSync(new SendSmsJob(
                $this->adminAlertPhone,
                'admin_sms_test',
                ['venue' => config('app.name', 'Cafe Gervacios')],
                true
            ));
        } catch (\Throwable $e) {
            $this->philSmsTestMessage = $e->getMessage();
            $this->philSmsTestStatus = 'error';

            return;
        }

        $this->philSmsTestMessage = 'Test SMS sent via PhilSMS. Check the phone number in "Admin alert phone".';
        $this->philSmsTestStatus = 'success';
    }

    public function render()
    {
        return view('livewire.admin.settings-manager');
    }
}
