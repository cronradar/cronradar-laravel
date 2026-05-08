<?php

namespace CronRadar\Laravel\Tests;

use CronRadar\Laravel\CronRadarServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for key normalization logic
 */
class KeyNormalizationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CronRadarServiceProvider::class];
    }

    private function normalizeKey(string $key): string
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();
        return $event->normalizeKey($key);
    }

    #[DataProvider('keyNormalizationProvider')]
    public function testKeyNormalization(string $input, string $expected): void
    {
        $result = $this->normalizeKey($input);
        $this->assertEquals($expected, $result);
    }

    public static function keyNormalizationProvider(): array
    {
        return [
            'simple lowercase' => ['backup', 'backup'],
            'uppercase to lowercase' => ['BACKUP', 'backup'],
            'mixed case' => ['BackupDatabase', 'backupdatabase'],
            'underscores to hyphens' => ['backup_database', 'backup-database'],
            'colons to hyphens' => ['cache:clear', 'cache-clear'],
            'special chars removed' => ['task@name!', 'task-name'],
            'multiple hyphens collapsed' => ['task---name', 'task-name'],
            'leading hyphen removed' => ['-task', 'task'],
            'trailing hyphen removed' => ['task-', 'task'],
            'spaces to hyphens' => ['my task name', 'my-task-name'],
        ];
    }
}
