<?php

namespace CronRadar\Laravel\Tests;

use CronRadar\Laravel\CronRadarServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Tests for Schedule macros
 */
class ScheduleMacroTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [CronRadarServiceProvider::class];
    }

    /**
     * Test monitorAll macro sets property
     */
    public function testMonitorAllMacroSetsProperty(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $schedule->monitorAll();

        $this->assertTrue($schedule->_cronRadarMonitorAll);
    }

    /**
     * Test monitorAll returns schedule for chaining
     */
    public function testMonitorAllReturnsSelfForChaining(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $result = $schedule->monitorAll();

        $this->assertSame($schedule, $result);
    }

    /**
     * Test applyMonitoringIfNeeded macro exists
     */
    public function testApplyMonitoringIfNeededMacroExists(): void
    {
        $this->assertTrue(Schedule::hasMacro('applyMonitoringIfNeeded'));
    }

    /**
     * Test applyMonitoringIfNeeded only runs once
     */
    public function testApplyMonitoringIfNeededOnlyRunsOnce(): void
    {
        $schedule = $this->app->make(Schedule::class);

        // First call
        $schedule->applyMonitoringIfNeeded();
        $this->assertTrue($schedule->_cronRadarMonitoringApplied);

        // Second call should be no-op (no error)
        $schedule->applyMonitoringIfNeeded();
        $this->assertTrue($schedule->_cronRadarMonitoringApplied);
    }
}
