# Café Gervacios Kiosk — Infrastructure & deployment setup

This document describes **required infrastructure** and **admin configuration** for full system functionality. Use it for capstone defense documentation and production handover.

Replace `/path/to/artisan` with the absolute path to this project’s `artisan` file, and `yourdomain.com` with your public hostname.

---

## Local development login

When `APP_ENV=local`, database seeding ensures this development admin account exists:

```text
Email: admin@kiosk.test
Password: admin123
```

These are development-only credentials. They are created by `LocalDevelopmentSeeder` and are not seeded in production.

To recreate or reset the local admin without rebuilding the database:

```bash
php artisan dev:reset-admin
```

Expected output:

```text
---------------------------------
Local admin ready
Email: admin@kiosk.test
Password: admin123
---------------------------------
```

To recreate it through normal local seeding:

```bash
php artisan db:seed
```

The local login throttle is relaxed for development testing only. Production keeps the normal admin login lockout protection.

---

## Required for SMS to work

Outbound SMS (queue notifications, booking confirmations, OTP, etc.) is sent **asynchronously** via Laravel’s queue.

1. **Run a queue worker** (must stay running in production — use Supervisor, systemd, or your host’s process manager):

   ```bash
   php artisan queue:work
   ```

   Use `queue:work` (not one-off `queue:listen` in production unless you know why). Configure retries and timeouts to match your hosting policy.

2. **Configure PhilSMS** in **Admin -> Settings -> Text messages**:
   - **API key** (`philsms_api_key` or `PHILSMS_API_KEY`)
   - **Sender ID** (`philsms_sender_id` or `PHILSMS_SENDER_ID`)
   - Ensure **SMS enabled** is on unless you intentionally disable all SMS.

Without a worker, `SendSmsJob` and similar jobs will sit in the queue and messages will not be delivered.

---

## Required for automation to work

The app relies on **Laravel’s scheduler** for table releases, Facebook sync, data retention (RA 10173), automation engine ticks (queue holds, wait estimates, no-shows, reminders, etc.), and scheduled reports.

Add a **cron** entry on the server (runs every minute; Laravel dispatches due tasks):

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

On Windows Server or shared hosts without cron, use an equivalent **scheduled task** or the host’s “Laravel scheduler” feature to invoke `php artisan schedule:run` every minute.

**Note:** Automation also expects the **queue worker** (above) for any queued work triggered by scheduled jobs, and a correct **APP_URL** / environment for outbound links in messages.

---

## Required for PayMongo to work

1. **Keys** (set in **Admin → Settings → PayMongo**, or via `.env` as fallback):
   - Public key  
   - Secret key  
   - Webhook signing secret  

2. **Webhook endpoint** — PayMongo must be able to reach your app over HTTPS:

   - **URL:** `POST https://yourdomain.com/webhook/paymongo`  
   - Route name in code: `webhook.paymongo`  
   - The server must use the **same webhook secret** configured in PayMongo’s dashboard.

3. **Public accessibility:** Staging on `localhost` will not receive webhooks unless you use a tunnel (ngrok, etc.). Production must use a valid TLS certificate and a stable public URL.

---

## Required for Facebook blog to work

1. **Admin → Settings → Facebook**
   - **Facebook Page ID** (`fb_page_id`)
   - **Page access token** (`fb_access_token`) with permissions to read page posts.

2. **Scheduler + sync:** Facebook posts are synced on a schedule (`blog:sync-facebook`). Ensure **cron + `schedule:run`** (see above) is configured. You can also run `php artisan blog:sync-facebook` manually for testing.

---

## Required for floor map to work

1. **Admin → Edit Layout** (floor plan): upload a **floor plan image**.
2. **Place tables** on the plan and set **capacity** (and other table metadata as required by your workflow).

Without a floor plan and positioned tables, the seating map and table-based flows will not reflect the real venue.

---

## First-time setup checklist (production)

Configure or verify the following before go-live. Many items live under **Admin → Settings** (unified settings UI with modals).

### Core & URLs

- [ ] **`.env`:** `APP_URL` set to the public HTTPS URL (webhooks, emails, links).
- [ ] **`.env`:** `APP_KEY` generated (`php artisan key:generate`).
- [ ] **Database** migrated and seeded as needed (`php artisan migrate`).
- [ ] **Queue:** `QUEUE_CONNECTION` appropriate for production (`database` or `redis`); worker running continuously.

### Payments (PayMongo)

- [ ] Public key, secret key, webhook secret in Settings or `.env`.
- [ ] PayMongo dashboard: webhook URL `https://yourdomain.com/webhook/paymongo` with matching secret.
- [ ] **Deposit / fees:** `deposit_per_guest`, `reservation_fee` (Settings) match business rules.

### SMS (PhilSMS)

- [ ] API key and sender ID (**Settings -> Text messages**).
- [ ] SMS enabled (unless SMS is deliberately off).
- [ ] **Queue worker** running (see above).

### QR / manual reservation payments

- [ ] **Settings → QR code:** upload/crop payment QR; account name, number, label.
- [ ] Guests can complete manual bank/GCash flows per your operations.

### Facebook / blog

- [ ] Page ID and access token (**Settings → Facebook**).
- [ ] **Scheduler** cron active (sync runs on schedule).

### Automation & operations

- [ ] **Automation master** and related toggles (devices, peak hours, queue hold minutes, no-show minutes, table cleaning, etc.) reviewed under Settings.
- [ ] **Admin alert phone** (if used for alerts).
- [ ] **Peak hours** (or learn-from-queue) aligned with venue policy.

### Seating

- [ ] **Floor plan** uploaded and **tables** placed with capacities (**Edit Layout**).
- [ ] **Menu** categories/items if the public menu depends on admin data.

### Email & compliance

- [ ] **Mail** (`MAIL_*` in `.env`) if the app sends mail (contact form, notifications, etc.).
- [ ] Review **OTP / mobile queue** settings if you use the mobile queue experience.

### Security

- [ ] Admin accounts and roles; **production** debug off (`APP_DEBUG=false`).
- [ ] Optional: IP blocklist / rate limits per your security policy.

---

## Quick reference commands

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:work
php artisan schedule:run   # usually only via cron every minute
```

---

*Last updated for deployment handover and capstone documentation.*

---

## Pre-deployment checklist:

- [ ] Copy `.env.example` to `.env` and fill **all** required values.
- [ ] Set `APP_ENV=production`.
- [ ] Set `APP_DEBUG=false`.
- [ ] Set `APP_URL` to your real HTTPS domain.
- [ ] Set `DB_CONNECTION=mysql` with real DB credentials.
- [ ] Set `QUEUE_CONNECTION=database` (not `sync`).
- [ ] Run `php artisan key:generate` (**first deploy only**).

## Deployment steps (in order):

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. `php artisan migrate --force`
4. `php artisan db:seed --class=UserSeeder` (**first deploy only**)
5. `php artisan storage:link`
6. `php artisan config:cache`
7. `php artisan route:cache`
8. `php artisan view:cache`
9. `php artisan queue:restart`

## Required background processes:

- Queue worker: `php artisan queue:work --tries=3`
- Scheduler cron: `* * * * * cd /path && php artisan schedule:run`

## What breaks if skipped:

- `.env` not fully configured: app boot/runtime failures, missing integrations, invalid URLs.
- `APP_ENV` not `production`: production behavior may not match expected hardening/perf defaults.
- `APP_DEBUG=true`: sensitive stack traces/details may be exposed to users.
- Wrong `APP_URL`: bad links in SMS/email/webhooks; callback URLs can fail.
- Wrong DB config / not MySQL in prod: migration/query failures or wrong data source.
- `QUEUE_CONNECTION=sync`: web requests block on jobs; SMS/automation timing degrades.
- No `key:generate` on first deploy: encrypted sessions/cookies may be invalid; app security breaks.
- Skipping `composer install --no-dev --optimize-autoloader`: missing/slow autoloading in production.
- Skipping `npm ci && npm run build`: missing/stale frontend assets; broken UI.
- Skipping `migrate --force`: schema drift; runtime SQL errors.
- Skipping first `UserSeeder`: no initial admin/staff bootstrap accounts (depending on your seed logic).
- Skipping `storage:link`: broken public file URLs (uploads/QR/media).
- Skipping `config:cache`: stale/slow config reads.
- Skipping `route:cache`: slower route resolution (and possible stale route behavior if old cache persists).
- Skipping `view:cache`: slower first-hit view rendering.
- Skipping `queue:restart`: workers may keep old code/config after deploy.
- No queue worker running: queued jobs (SMS, notifications, async tasks) do not process.
- No scheduler cron: automated tasks (reminders, automation ticks, retention/sync jobs) stop running.

## Rollback procedure:

If deployment fails during or after migration:

1. Put app in maintenance mode: `php artisan down`.
2. Identify the failing migration: `php artisan migrate:status`.
3. If safe to roll back one batch: `php artisan migrate:rollback --step=1`.
4. If rollback SQL is not safe/available, restore DB from the latest backup snapshot.
5. Re-deploy previous known-good code revision.
6. Clear and rebuild caches:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
7. Restart workers: `php artisan queue:restart`.
8. Bring app back: `php artisan up`.

Recommended: always take a DB backup immediately before `php artisan migrate --force`.

## Server Configuration

## Required server-level settings:
- Redirect all HTTP traffic to HTTPS
- Minimum TLS 1.2
- Add HSTS header:
  Strict-Transport-Security: max-age=31536000; includeSubDomains
- Point document root to /public folder only
- Disable directory listing
