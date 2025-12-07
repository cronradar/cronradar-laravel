# CronRadar Laravel Package

Monitor Laravel scheduled tasks automatically.

## Installation

```bash
composer require cronradar/laravel
```

## Quick Start

```php
// In App\Console\Kernel
protected function schedule(Schedule $schedule)
{
    $schedule->command('emails:send')
        ->hourly()
        ->monitor();

    $schedule->command('reports:generate')
        ->daily()
        ->monitor();
}
```

## Configuration

Add to `.env`:

```bash
CRONRADAR_API_KEY=ck_app_xxxxx_yyyyy
```

Get your API key from [cronradar.com/dashboard](https://cronradar.com/dashboard)

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
