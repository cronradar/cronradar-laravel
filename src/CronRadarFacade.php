<?php

namespace CronRadar\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void ping(string $monitorKey, ?string $schedule = null, ?int $gracePeriod = null)
 * @method static void syncMonitor(string $monitorKey, string $schedule, ?string $source = null, ?string $name = null, int $gracePeriod = 60)
 *
 * @see \CronRadar\CronRadar
 */
class CronRadarFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cronradar';
    }
}
