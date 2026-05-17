# Staging Deployment Checklist

This project is still in local/staging testing. Do not use PayMongo live keys until staging has passed payment, webhook, queue, and rollback tests.

## Required Rules

- Use `PAYMONGO_MODE=test`.
- Use only `pk_test_`, `sk_test_`, and the test webhook secret from PayMongo.
- Keep `PAYMONGO_ALLOW_LIVE=false`.
- Use `APP_ENV=local` for local work or `APP_ENV=staging` for staging.
- Keep `APP_DEBUG=true` only during testing/staging.
- Use HTTPS for any PayMongo webhook callback URL.

## Local Testing Commands

```bash
composer validate --no-check-publish
composer check-platform-reqs --no-dev
npm ci
npm run build
php artisan key:generate
php artisan migrate
php artisan storage:link
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit --do-not-cache-result
php artisan deployment:check --target=local
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

For webhook testing, start an HTTPS tunnel:

```bash
ngrok http 8000
```

Then set the PayMongo test webhook URL to:

```text
https://YOUR-NGROK-DOMAIN.ngrok-free.app/webhook/paymongo
```

Set `APP_URL` to the same ngrok HTTPS origin while testing success/failure redirects.

## Staging Deployment Commands

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan deployment:check --target=staging
php artisan queue:restart
```

Worker process:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Scheduler cron:

```cron
* * * * * cd /path/to/kiosk && php artisan schedule:run >> /dev/null 2>&1
```

## Production Commands Later

Do not run this set during staging. Use it only after staging passes and live PayMongo keys are approved.

```bash
APP_ENV=production
APP_DEBUG=false
PAYMONGO_MODE=live
PAYMONGO_ALLOW_LIVE=true
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan down
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan deployment:check --target=production
php artisan up
```

## Manual Test Case: TC-STAGE-001

Goal: prove a PayMongo test payment can complete even if the webhook is delayed or retried.

Preconditions:

- `.env` or admin settings contain only PayMongo test keys.
- `PAYMONGO_MODE=test` and `PAYMONGO_ALLOW_LIVE=false`.
- `php artisan deployment:check --target=local` has no errors except acceptable local HTTPS/queue warnings.
- `ngrok http 8000` is running and PayMongo test webhook points to `/webhook/paymongo`.
- Queue worker is running.

Steps:

1. Open the local or staging reservation page.
2. Submit a reservation with PayMongo online payment.
3. Complete checkout using PayMongo test payment details.
4. Return to `/reservation/success?ref=...`.
5. Wait for either webhook confirmation or success-page polling fallback.
6. Replay the same signed webhook payload once.
7. Run `php artisan deployment:check --target=local` again.

Expected results:

- The booking ends with `payment_status=paid`.
- `paymongo_payment_id` is stored.
- The second webhook returns `already_processed`.
- Only one confirmation SMS job is queued for the booking.
- No duplicate booking or duplicate PayMongo payment ID is created.
- Deployment check reports no missing migrations, broken storage paths, or stale Vite build.
