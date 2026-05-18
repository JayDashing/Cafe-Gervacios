<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendSmsJob
 *
 * Queued job for SMS delivery via PhilSMS (Philippines).
 * Never call NotificationService::sendSms() directly from a request cycle.
 * Always dispatch this job to keep SMS latency off the request cycle.
 *
 * Phone numbers are never logged in full — only first 5 digits are shown.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     *
     * @param string $phone The recipient phone number.
     * @param string $template The SMS template name.
     * @param array $vars Variables to interpolate into the template.
     */
    public function __construct(
        private string $phone,
        private string $template,
        private array $vars,
        private bool $throwOnFailure = false
    ) {
    }

    /**
     * Execute the job.
     *
     * @param NotificationService $service
     */
    public function handle(NotificationService $service): void
    {
        try {
            $service->sendSms($this->phone, $this->template, $this->vars);
        } catch (\Throwable $e) {
            Log::error('SMS send failed', [
                'phone_prefix' => substr($this->phone, 0, 5).'***',
                'template' => $this->template,
                'error' => $e->getMessage(),
            ]);

            if ($this->throwOnFailure) {
                throw $e;
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $e
     */
    public function failed(\Throwable $e): void
    {
        // Never log full phone number — RA 10173
        Log::error('SMS job failed permanently', [
            'phone_prefix' => substr($this->phone, 0, 5) . '***',
            'template' => $this->template,
            'error' => $e->getMessage(),
        ]);
    }
}
