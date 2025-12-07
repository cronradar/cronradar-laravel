# CronRadar Laravel Package

Monitor Laravel scheduled tasks automatically with two monitoring modes.

## Installation

```bash
composer require cronradar/laravel
```

## Quick Start

### Selective Mode (Default)

Monitor specific tasks by adding `->monitor()`:

```php
// In routes/console.php or bootstrap/app.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('emails:send')
    ->hourly()
    ->monitor();  // Auto-detects key from command name

Schedule::command('reports:generate')
    ->daily()
    ->monitor('custom-key');  // Optional custom key
```

### MonitorAll Mode

Monitor all tasks automatically with one line:

```php
// In routes/console.php or bootstrap/app.php
use Illuminate\Support\Facades\Schedule;

Schedule::monitorAll();  // ← Enable monitoring for all tasks

Schedule::command('emails:send')->hourly();
// Monitored automatically

Schedule::command('internal:cleanup')
    ->daily()
    ->skipMonitor();  // Opt-out
```

**Alternative:** Use config instead of macro:

```php
// Publish config first: php artisan vendor:publish --tag=cronradar-config
// Then edit config/cronradar.php
'monitor_all' => true,
```

## Configuration

Add to `.env`:

```bash
CRONRADAR_API_KEY=ck_app_xxxxx_yyyyy
CRONRADAR_MONITOR_ALL=false  # Set to true for MonitorAll mode
```

Get your API key from [cronradar.com/dashboard](https://cronradar.com/dashboard)

## Monitoring Modes

| Mode | Usage | When to Use |
|------|-------|-------------|
| **Selective** (default) | Add `->monitor()` to specific tasks | Few critical tasks, explicit control needed |
| **MonitorAll** | Call `Schedule::monitorAll()`, use `->skipMonitor()` to opt-out | Many tasks, comprehensive monitoring needed |

**Three ways to enable MonitorAll:**
1. **Macro** (recommended): `Schedule::monitorAll()` - Visible in code
2. **Config**: `'monitor_all' => true` in `config/cronradar.php` - Laravel-native
3. **Env**: `CRONRADAR_MONITOR_ALL=true` in `.env` - Quick override

## Custom Monitor Keys

```php
$schedule->command('reports:generate')
    ->daily()
    ->monitor('custom-report-key');
```

If no key provided, auto-detects from command name.

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x

## Links

- 📦 [Packagist](https://packagist.org/packages/cronradar/laravel)
- 🐛 [Issues](https://github.com/cronradar/cronradar-laravel/issues)
- 📚 [Documentation](https://cronradar.com/docs)
- ✉️ support@cronradar.com

## License

Proprietary - © 2025 CronRadar. All rights reserved.
