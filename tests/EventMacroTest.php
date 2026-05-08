<?php

namespace CronRadar\Laravel\Tests;

use CronRadar\Laravel\CronRadarServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Tests for Event macros
 */
class EventMacroTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CronRadarServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        putenv('CRONRADAR_API_KEY=test-api-key-123');
    }

    protected function tearDown(): void
    {
        putenv('CRONRADAR_API_KEY');
        parent::tearDown();
    }

    /**
     * Test skipMonitor macro sets property
     */
    public function testSkipMonitorMacroSetsProperty(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $event->skipMonitor();

        $this->assertTrue($event->shouldSkipMonitor());
    }

    /**
     * Test shouldSkipMonitor returns false by default
     */
    public function testShouldSkipMonitorReturnsFalseByDefault(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $this->assertFalse($event->shouldSkipMonitor());
    }

    /**
     * Test isMonitored returns false by default
     */
    public function testIsMonitoredReturnsFalseByDefault(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $this->assertFalse($event->isMonitored());
    }

    /**
     * Test normalizeKey converts to lowercase
     */
    public function testNormalizeKeyConvertsToLowercase(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $result = $event->normalizeKey('MyTask');

        $this->assertEquals('mytask', $result);
    }

    /**
     * Test normalizeKey replaces special chars with hyphens
     */
    public function testNormalizeKeyReplacesSpecialChars(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $result = $event->normalizeKey('my_task@name');

        $this->assertEquals('my-task-name', $result);
    }

    /**
     * Test normalizeKey removes leading/trailing hyphens
     */
    public function testNormalizeKeyRemovesLeadingTrailingHyphens(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $result = $event->normalizeKey('--my-task--');

        $this->assertEquals('my-task', $result);
    }

    /**
     * Test normalizeKey truncates at MAX_MONITOR_KEY_LENGTH (200).
     */
    public function testNormalizeKeyTruncatesLongKeys(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $max = \CronRadar\Constants::MAX_MONITOR_KEY_LENGTH;
        $longKey = str_repeat('a', $max + 50);
        $result = $event->normalizeKey($longKey);

        $this->assertLessThanOrEqual($max, strlen($result));
    }

    /**
     * Test extractCommandName extracts from artisan command
     */
    public function testExtractCommandNameFromArtisanCommand(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $result = $event->extractCommandName('php artisan queue:work --daemon');

        $this->assertEquals('queue-work', $result);
    }

    /**
     * Test extractCommandName handles quoted paths
     */
    public function testExtractCommandNameHandlesQuotedPaths(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $result = $event->extractCommandName('"C:\\php\\php.exe" "artisan" reports:generate');

        $this->assertEquals('reports-generate', $result);
    }

    /**
     * Test extractCommandName converts colons to hyphens
     */
    public function testExtractCommandNameConvertsColonsToHyphens(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $result = $event->extractCommandName('php artisan cache:clear');

        $this->assertEquals('cache-clear', $result);
    }

    /**
     * Test monitor macro stores custom key
     */
    public function testMonitorMacroStoresCustomKey(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $event->monitor('my-custom-key');

        $this->assertTrue(property_exists($event, '_cronRadarCustomKey'));
        $this->assertEquals('my-custom-key', $event->_cronRadarCustomKey);
    }

    /**
     * Test detectMonitorKey uses custom key when set
     */
    public function testDetectMonitorKeyUsesCustomKey(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->command('test:command')->daily();

        $event->_cronRadarCustomKey = 'my-custom-key';

        $key = $event->detectMonitorKey();

        $this->assertEquals('my-custom-key', $key);
    }

    /**
     * Test detectMonitorKey extracts from description
     */
    public function testDetectMonitorKeyExtractsFromDescription(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $event = $schedule->call(function () {})->daily()->description('backup database');

        $key = $event->detectMonitorKey();

        $this->assertEquals('backup-database', $key);
    }
}
