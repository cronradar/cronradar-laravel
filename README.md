# CronRadar for Laravel

Auto-discovery for the Laravel scheduler. Every task added to your `app/Console/Kernel.php` (or `routes/console.php` in Laravel 11+) is monitored automatically. Opt out per task with `->skipMonitor()`.

Built on top of the [`cronradar/php`](https://packagist.org/packages/cronradar/php) base SDK.

## Install

```bash
composer require cronradar/laravel
```

PHP 8.0+. Laravel 10, 11, and 12 supported. Auto-discovery via Laravel's package manifest — no `config/app.php` registration required.

## Setup

Add the API key to your `.env`:

```dotenv
CRONRADAR_API_KEY=ck_app_xxxxxxxxxxxxxxxxxxxx
```

Get the API key from the CronRadar dashboard at [app.cronradar.com](https://app.cronradar.com) under your application's settings. API keys have the form `ck_app_<random>`.

Create a separate CronRadar application and API key for production, staging, and development. Keep production secrets in the deployment platform rather than a committed `.env` file, and never reuse a production key outside production.

(Optional) publish the config file if you want to override defaults:

```bash
php artisan vendor:publish --tag=cronradar-config
```

That writes `config/cronradar.php` where you can change the base URL, default grace period, etc.

The package's service provider auto-registers a Schedule listener — no further bootstrap is needed.

## Quickstart

Schedule tasks the way you always have. They're monitored automatically.

```php
// routes/console.php (Laravel 11+) or app/Console/Kernel.php (Laravel 10)
use Illuminate\Support\Facades\Schedule;

Schedule::command('emails:send')
    ->hourly();

Schedule::command('reports:generate')
    ->dailyAt('02:00');

Schedule::call(fn () => MyService::nightlyJob())
    ->dailyAt('03:00');
```

That's it. The package:

- Hooks every `Schedule::command(...)`, `Schedule::call(...)`, `Schedule::job(...)`, `Schedule::exec(...)`
- Pre-syncs each scheduled task to CronRadar at boot (so they appear in the dashboard immediately)
- Translates the Laravel-fluent expression (`->hourly()`, `->dailyAt('02:00')`, etc.) to a standard cron expression
- Records `start` / `complete` / `fail` for every execution via the schedule's `before` and `after` hooks

## Annotations

### Opt out per task

Chain `->skipMonitor()` on any scheduled task:

```php
Schedule::command('internal:cleanup')
    ->daily()
    ->skipMonitor();   // not monitored
```

### Override the monitor key

By default the key is derived from the command name (`emails:send` → `emails-send`) or the closure file/line for `Schedule::call(...)`. Override explicitly:

```php
Schedule::command('reports:generate')
    ->daily()
    ->monitor('critical-daily-report');
```

Grace period is configured per-monitor on CronRadar (default 60 seconds; set via the dashboard) — it is not a per-task argument on the Laravel side.

## Reference

### Schedule macros

The package adds these methods to Laravel's `Illuminate\Console\Scheduling\Event`:

| Method | Purpose |
|---|---|
| `->skipMonitor()` | Exclude this task from monitoring. Returns `$this` for chaining. |
| `->monitor(?string $customKey = null)` | Override the monitor key for this task. Returns `$this` for chaining. |
| `->shouldSkipMonitor()` | Returns true if `->skipMonitor()` was applied. |
| `->isMonitored()` | Returns true if the task will be monitored. |

### What gets monitored

Every entry in your schedule list is auto-discovered, including:

- `Schedule::command(...)` — Artisan commands
- `Schedule::call(Closure)` — inline closures
- `Schedule::job(...)` — queued jobs
- `Schedule::exec(...)` — shell commands

The monitor key is derived as follows:

| Schedule type | Default key derivation |
|---|---|
| `command('foo:bar')` | `foo-bar` |
| `job(SomeJob::class)` | `some-job` (class basename, lowercased + kebab) |
| `exec('php artisan ...')` | first non-flag token, normalized |
| `call(Closure)` | `closure-{file-basename}-{line}` |

Override any of these with `->monitor(key: '...')`.

### Service provider behaviors

The package's `ServiceProvider` does the following at boot:

1. Reads the `Illuminate\Console\Scheduling\Schedule` instance after the application has finished registering schedules.
2. For each event, computes a CronRadar key and resolves the cron expression via `$event->expression`.
3. Calls `CronRadar::syncMonitor(...)` for each non-skipped event.
4. Wraps each event's `before(...)` and `after(...)` with `startJob` / `completeJob`. Failures are detected via Laravel's `onFailure` callback, which calls `failJob`.

This means schedule changes that ship via CI deploy: the next boot syncs the new monitors, and removed tasks sit "stale" in CronRadar until you delete them.

## Configuration

Published config (`config/cronradar.php`) keys:

| Key | Default | Purpose |
|---|---|---|
| `api_key` | `env('CRONRADAR_API_KEY')` | API key. Required. |
| `base_url` | `env('CRONRADAR_BASE_URL', 'https://cron.life')` | Override the ingestion endpoint. |
| `default_grace_period` | `env('CRONRADAR_DEFAULT_GRACE_PERIOD', 60)` | Default seconds for tasks without `->monitor(gracePeriod: N)`. |
| `enabled` | `env('CRONRADAR_ENABLED', true)` | Master switch. Set to `false` in tests / local dev. |
| `timeout` | `5` | HTTP request timeout in seconds. |

All envs:

| Environment variable | Required | Default | Purpose |
|---|---|---|---|
| `CRONRADAR_API_KEY` | yes | — | API key. Format `ck_app_xxxxx`. |
| `CRONRADAR_BASE_URL` | no | `https://cron.life` | Override the ingestion endpoint. |
| `CRONRADAR_ENABLED` | no | `true` | Set to `false` to disable monitoring entirely (useful in tests). |
| `CRONRADAR_DEFAULT_GRACE_PERIOD` | no | `60` | Default grace period in seconds. |
| `CRONRADAR_LOG_ERRORS` | no | `1` | Set to `0` to suppress Laravel log warnings on network failure. |

## Error handling

The package upholds the same hard guarantees as the base SDK:

1. **Never throws to user code.** Network errors, timeouts, 4xx/5xx responses — all caught and logged via `Log::warning(...)`. A failing CronRadar API must not break a Laravel scheduled task.
2. **Never alters task execution.** The package uses Laravel's standard `before` / `after` / `onFailure` hooks; it never modifies task state, exit codes, or queueing behavior.

What this looks like at runtime:

| Situation | SDK behavior | Laravel behavior |
|---|---|---|
| CronRadar API unreachable | Logs once; returns | Task runs and is logged normally |
| Sync on boot fails for one task | Logs; continues | Other tasks sync; `php artisan schedule:run` works |
| Per-task ping fails | Logs; returns | Task state unchanged |
| User task throws | Records `failJob` with exception message | Laravel logs the failure as usual |
| Cron expression not parseable | Warns; that task is not monitored | Laravel runs it normally on its schedule |

## Troubleshooting

### Tasks aren't showing up in the CronRadar dashboard

1. **Confirm `CRONRADAR_API_KEY` is set in `.env`** and that you've cleared config cache (`php artisan config:clear`).
2. **Confirm `php artisan schedule:list`** shows your tasks. If they're not in the schedule, the package can't see them.
3. **Run `php artisan schedule:run` manually** to trigger an execution and look for any CronRadar warnings in `storage/logs/laravel.log`.
4. **Verify outbound HTTPS to `https://cron.life`** isn't blocked by your hosting provider.

### `->skipMonitor()` is undefined

Macro registration didn't run. Causes:

- Service provider registration failed (check `php artisan package:discover`).
- You're calling `->skipMonitor()` on something other than `Illuminate\Console\Scheduling\Event` (e.g., a closure result).
- The package version is mismatched against your Laravel version. Confirm Laravel 10/11/12 and `cronradar/laravel` ^1.0.

### `php artisan schedule:run` works in production but tasks never re-monitor after a deploy

The schedule is read at boot. If your deploy doesn't restart workers / FPM, the new tasks aren't synced until something boots Laravel fresh. Either:

- Add `php artisan cronradar:sync` to your deploy step (force-resyncs the schedule), or
- Use `php artisan optimize:clear` + restart your workers.

### Closure-based tasks get ugly auto-keys like `closure-app-console-kernel-42`

That's the default key derivation for closures (file basename + line). For closures you care about, set an explicit key:

```php
Schedule::call(fn () => doNightlyWork())
    ->dailyAt('03:00')
    ->monitor(key: 'nightly-work');
```

### How do I disable monitoring during tests?

Set `CRONRADAR_ENABLED=false` in `phpunit.xml`:

```xml
<env name="CRONRADAR_ENABLED" value="false"/>
```

The package becomes a no-op; your scheduled-task assertions and `Bus::fake()` setups behave exactly as without the package.

## Verification

Run these commands in the deployed application environment:

```bash
php artisan cronradar:list
php artisan cronradar:sync
php artisan cronradar:test
```

Then confirm the intended CronRadar application shows the scheduled task and a terminal execution result. Use the guided reliability drill for notification delivery; do not make a production schedule fail for testing.

## Migration

When replacing manual `CronRadar::monitor()` calls, keep each existing monitor key with `->monitorAs('existing-key')`, deploy, and confirm discovery plus one terminal run. Remove the old heartbeat only after the framework lifecycle is visible so history remains continuous and duplicate terminal events are avoided.

## Links

- **Documentation:** [docs.cronradar.com](https://docs.cronradar.com)
- **Agent-friendly index:** [docs.cronradar.com/llms.txt](https://docs.cronradar.com/llms.txt)
- **OpenAPI spec:** [docs.cronradar.com/openapi.json](https://docs.cronradar.com/openapi.json)
- **Packagist:** [packagist.org/packages/cronradar/laravel](https://packagist.org/packages/cronradar/laravel)
- **GitHub:** [github.com/cronradar/cronradar-laravel](https://github.com/cronradar/cronradar-laravel)
- **Base SDK:** [packagist.org/packages/cronradar/php](https://packagist.org/packages/cronradar/php)
- **Support:** support@cronradar.com

## License

© 2026 [CronRadar](https://cronradar.com) · Proprietary — see [LICENSE](./LICENSE).
