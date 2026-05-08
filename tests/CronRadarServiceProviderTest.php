<?php

namespace CronRadar\Laravel\Tests;

use CronRadar\Laravel\CronRadarServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * CronRadar Laravel SDK - Service Provider Tests
 */
class CronRadarServiceProviderTest extends TestCase
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
     * Test that the service provider registers successfully
     */
    public function testServiceProviderRegisters(): void
    {
        $this->assertTrue($this->app->bound('cronradar'));
    }

    /**
     * Test that cronradar singleton is registered
     */
    public function testCronradarSingletonIsRegistered(): void
    {
        $instance1 = $this->app->make('cronradar');
        $instance2 = $this->app->make('cronradar');

        $this->assertSame($instance1, $instance2);
    }
}
