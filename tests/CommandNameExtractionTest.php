<?php

namespace CronRadar\Laravel\Tests;

use CronRadar\Laravel\CronRadarServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for command name extraction logic
 */
class CommandNameExtractionTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CronRadarServiceProvider::class];
    }

    private function extractCommandName(string $command): string
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();
        return $event->extractCommandName($command);
    }

    #[DataProvider('commandNameProvider')]
    public function testCommandNameExtraction(string $input, string $expected): void
    {
        $result = $this->extractCommandName($input);
        $this->assertEquals($expected, $result);
    }

    public static function commandNameProvider(): array
    {
        return [
            'simple artisan command' => [
                'php artisan queue:work',
                'queue-work'
            ],
            'artisan with options' => [
                'php artisan queue:work --daemon --tries=3',
                'queue-work'
            ],
            'quoted php path' => [
                '"C:\\php\\php.exe" "artisan" cache:clear',
                'cache-clear'
            ],
            'unix quoted path' => [
                "'/usr/bin/php' 'artisan' reports:generate",
                'reports-generate'
            ],
            'full path artisan' => [
                '/var/www/html/artisan schedule:run',
                'schedule-run'
            ],
        ];
    }
}
