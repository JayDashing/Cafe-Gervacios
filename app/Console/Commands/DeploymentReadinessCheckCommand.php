<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class DeploymentReadinessCheckCommand extends Command
{
    protected $signature = 'deployment:check
        {--target=local : local, staging, or production}
        {--json : Output machine-readable JSON}';

    protected $description = 'Check deployment readiness for local, staging, or later production rollout.';

    /** @var list<array{name: string, status: string, message: string, fix: string}> */
    private array $results = [];

    public function handle(): int
    {
        $target = strtolower((string) $this->option('target'));
        if (! in_array($target, ['local', 'staging', 'production'], true)) {
            $this->error('Invalid target. Use local, staging, or production.');

            return self::FAILURE;
        }

        $this->results = [];

        $this->checkEnvironment($target);
        $this->checkPayMongo($target);
        $this->checkDatabaseAndMigrations();
        $this->checkStorage();
        $this->checkQueueAndScheduler($target);
        $this->checkViteBuild($target);
        $this->checkUrlAndSession($target);

        return $this->renderResults();
    }

    private function checkEnvironment(string $target): void
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $timezone = (string) config('app.timezone');
        $appKey = (string) config('app.key');

        if ($target === 'production') {
            $this->add('Environment', $env === 'production', 'APP_ENV is production.', 'Set APP_ENV=production only after staging passes.');
            $this->add('Debug', ! $debug, 'APP_DEBUG is false.', 'Set APP_DEBUG=false for production only.');
        } else {
            $this->add('Environment', in_array($env, ['local', 'staging', 'testing'], true), 'APP_ENV is local/staging/testing.', 'Use APP_ENV=local or APP_ENV=staging for this phase.');
            $this->add('Debug', $debug, 'APP_DEBUG is enabled for testing.', 'Use APP_DEBUG=true for local/staging testing only.');
        }

        $this->add('Timezone', $timezone === 'Asia/Manila', 'APP_TIMEZONE is Asia/Manila.', 'Set APP_TIMEZONE=Asia/Manila.');
        $this->add('App key', $appKey !== '', 'APP_KEY is configured.', 'Run php artisan key:generate.');
    }

    private function checkPayMongo(string $target): void
    {
        $mode = strtolower((string) config('services.paymongo.mode', 'test'));
        $allowLive = (bool) config('services.paymongo.allow_live', false);
        $publicKey = $this->effectiveSecret('paymongo_public_key', 'services.paymongo.public_key');
        $secretKey = $this->effectiveSecret('paymongo_secret_key', 'services.paymongo.secret_key');
        $webhookSecret = $this->effectiveSecret('paymongo_webhook_secret', 'services.paymongo.webhook_secret');
        $rawEnv = $this->readDotEnvValues(base_path('.env'));

        if ($target === 'production') {
            $this->addWarning('PayMongo mode', $mode === 'live' && $allowLive, 'Production later may use live mode after staging sign-off.', 'Do not set PAYMONGO_MODE=live or PAYMONGO_ALLOW_LIVE=true during staging.');
        } else {
            $this->add('PayMongo mode', $mode === 'test' && ! $allowLive, 'PayMongo is locked to test mode.', 'Set PAYMONGO_MODE=test and PAYMONGO_ALLOW_LIVE=false.');
            $this->add('PayMongo public key', Str::startsWith($publicKey, 'pk_test_'), 'Effective PayMongo public key is test mode.', 'Use a pk_test_ key in env or admin settings.');
            $this->add('PayMongo secret key', Str::startsWith($secretKey, 'sk_test_'), 'Effective PayMongo secret key is test mode.', 'Use an sk_test_ key in env or admin settings.');
        }

        $this->add('PayMongo webhook secret', $webhookSecret !== '' && ! Str::contains($webhookSecret, 'REPLACE_ME'), 'Webhook secret is configured.', 'Set PAYMONGO_WEBHOOK_SECRET from the PayMongo test webhook.');

        foreach (['PAYMONGO_PUBLIC_KEY', 'PAYMONGO_SECRET_KEY'] as $key) {
            $value = strtolower($rawEnv[$key] ?? '');
            $this->add(
                "Raw {$key}",
                ! Str::contains($value, '_live_'),
                "{$key} in .env is not a live key.",
                "Replace {$key} in .env with a test key or leave it blank and use test admin settings."
            );
        }
    }

    private function checkDatabaseAndMigrations(): void
    {
        try {
            DB::connection()->getPdo();
            $this->add('Database connection', true, 'Database connection works.', 'Check DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD.');

            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(File::files(database_path('migrations')))
                ->map(fn ($file) => Str::before($file->getFilename(), '.php'))
                ->values()
                ->all();

            $pending = array_values(array_diff($files, $ran));
            $this->add(
                'Migrations',
                count($pending) === 0,
                count($pending) === 0 ? 'No pending migrations.' : 'Pending migrations: '.implode(', ', $pending),
                'Run php artisan migrate before staging deploy.'
            );
        } catch (Throwable $e) {
            $this->add('Database connection', false, 'Database check failed.', 'Check database credentials and run php artisan migrate:status.');
        }
    }

    private function checkStorage(): void
    {
        foreach ([
            storage_path(),
            storage_path('framework/cache/data'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
            public_path('images'),
        ] as $path) {
            $writable = $this->canWrite($path);

            $this->add(
                'Writable '.Str::after($path, base_path(DIRECTORY_SEPARATOR)),
                $writable,
                $writable ? "{$path} is writable." : "{$path} is not writable.",
                "Grant write permission to {$path}."
            );
        }

        $this->add('Storage link', File::exists(public_path('storage')), 'public/storage exists.', 'Run php artisan storage:link.');
    }

    private function checkQueueAndScheduler(string $target): void
    {
        $queue = (string) config('queue.default');

        if ($target === 'local') {
            $this->addWarning('Queue connection', $queue !== 'sync', 'Local can use sync, but database queue is closer to staging.', 'Use QUEUE_CONNECTION=database when testing workers.');
        } else {
            $this->add('Queue connection', $queue !== 'sync', 'Queue uses an async backend.', 'Set QUEUE_CONNECTION=database and run a queue worker.');
        }

        $this->addWarning('Queue worker', true, 'Worker command must be running for SMS and automation jobs.', 'Run php artisan queue:work --sleep=3 --tries=3 --timeout=90.');
        $this->addWarning('Scheduler', true, 'Scheduler must run every minute for automation.', 'Configure * * * * * php artisan schedule:run.');
    }

    private function checkViteBuild(string $target): void
    {
        $manifest = public_path('build/manifest.json');
        $manifestExists = File::exists($manifest);
        $this->add('Vite manifest', $manifestExists, 'public/build/manifest.json exists.', 'Run npm ci && npm run build.');

        if (! $manifestExists) {
            return;
        }

        $latestSourceTime = $this->latestFrontendSourceTime();
        $manifestTime = File::lastModified($manifest);
        $fresh = $latestSourceTime === null || $manifestTime >= $latestSourceTime;

        if ($fresh) {
            $this->add('Vite freshness', true, 'Vite build is fresh enough.', 'Run npm run build before staging deploy.');
        } elseif ($target === 'local') {
            $this->addWarning('Vite freshness', false, 'Vite source files are newer than public/build.', 'Run npm run build before staging deploy.');
        } else {
            $this->add('Vite freshness', false, 'Vite source files are newer than public/build.', 'Run npm run build before staging deploy.');
        }
    }

    private function checkUrlAndSession(string $target): void
    {
        $url = rtrim((string) config('app.url'), '/');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $isHttps = Str::startsWith($url, 'https://');
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true);

        if ($target === 'local') {
            $this->add('APP_URL', $url !== '', 'APP_URL is set for local testing.', 'Set APP_URL=http://127.0.0.1:8000 or your ngrok HTTPS URL.');
            $this->addWarning('HTTPS tunnel', $isHttps, 'Use HTTPS when testing PayMongo webhooks.', 'Run ngrok http 8000 and set APP_URL to the ngrok URL while testing webhooks.');
        } else {
            $this->add('APP_URL', $isHttps && ! $isLocalHost, 'APP_URL is an HTTPS staging/production URL.', 'Set APP_URL=https://your-staging-domain.example.');
        }

        $secureCookie = (bool) config('session.secure');
        $this->add('Session secure cookie', $target === 'local' || ! $isHttps || $secureCookie, 'SESSION_SECURE_COOKIE matches the URL scheme.', 'Set SESSION_SECURE_COOKIE=true for HTTPS staging.');

        $sessionDomain = config('session.domain');
        $domainOk = $sessionDomain === null || $sessionDomain === '' || Str::contains($host, ltrim((string) $sessionDomain, '.'));
        $this->add('Session domain', $domainOk, 'SESSION_DOMAIN matches APP_URL or is null.', 'Set SESSION_DOMAIN=null unless you need a shared parent domain.');
    }

    private function effectiveSecret(string $settingKey, string $configKey): string
    {
        try {
            $value = Setting::query()->where('key', $settingKey)->value('value');
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        } catch (Throwable) {
            //
        }

        return trim((string) config($configKey, ''));
    }

    /**
     * @return array<string, string>
     */
    private function readDotEnvValues(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $values = [];
        foreach (File::lines($path) as $line) {
            $line = trim((string) $line);
            if ($line === '' || Str::startsWith($line, '#') || ! Str::contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $values;
    }

    private function canWrite(string $path): bool
    {
        if (! File::isDirectory($path)) {
            return false;
        }

        $probe = $path.DIRECTORY_SEPARATOR.'.deployment-write-test-'.Str::random(12);

        try {
            File::put($probe, 'ok');

            return File::exists($probe);
        } catch (Throwable) {
            return false;
        } finally {
            if (File::exists($probe)) {
                File::delete($probe);
            }
        }
    }

    private function latestFrontendSourceTime(): ?int
    {
        $paths = [
            resource_path('js'),
            resource_path('css'),
            resource_path('views'),
            base_path('vite.config.js'),
            base_path('package-lock.json'),
        ];

        $latest = null;

        foreach ($paths as $path) {
            if (File::isFile($path)) {
                $latest = max($latest ?? 0, File::lastModified($path));
                continue;
            }

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $latest = max($latest ?? 0, $file->getMTime());
            }
        }

        return $latest;
    }

    private function add(string $name, bool $passed, string $message, string $fix): void
    {
        $this->results[] = [
            'name' => $name,
            'status' => $passed ? 'ok' : 'error',
            'message' => $message,
            'fix' => $passed ? '' : $fix,
        ];
    }

    private function addWarning(string $name, bool $passed, string $message, string $fix): void
    {
        $this->results[] = [
            'name' => $name,
            'status' => $passed ? 'ok' : 'warning',
            'message' => $message,
            'fix' => $passed ? '' : $fix,
        ];
    }

    private function renderResults(): int
    {
        $failed = collect($this->results)->contains(fn ($row) => $row['status'] === 'error');

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $failed,
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Status', 'Check', 'Message', 'Fix'], array_map(
                fn ($row) => [$row['status'], $row['name'], $row['message'], $row['fix']],
                $this->results
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
