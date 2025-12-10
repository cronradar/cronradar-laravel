# CronRadar Laravel Package

Monitor Laravel scheduled tasks automatically. Monitors all tasks by default - use `->skipMonitor()` to opt-out.

## Installation

```bash
composer require cronradar/laravel
```

## Quick Start

All tasks are monitored automatically:

```php
// In routes/console.php or bootstrap/app.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('emails:send')->hourly();
// Monitored automatically

Schedule::command('reports:generate')->daily();
// Monitored automatically

Schedule::command('internal:cleanup')
    ->daily()
    ->skipMonitor();  // Opt-out
```

## Configuration

Add to `.env`:

```bash
CRONRADAR_API_KEY=ck_app_xxxxx_yyyyy
```

## Custom Monitor Keys

```php
$schedule->command('reports:generate')
    ->daily()
    ->monitor('custom-report-key');  // Optional custom key
```

If no key provided, auto-detects from command name.

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x

## Links

- [Documentation](https://docs.cronradar.com)
- [Packagist](https://packagist.org/packages/cronradar/laravel)

**See also:** [CronRadar PHP SDK](https://packagist.org/packages/cronradar/php)

## License

© 2025 [CronRadar](https://cronradar.com) - See [LICENSE](./LICENSE)
