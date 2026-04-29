<?php

namespace CronRadar\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void monitor(string $monitorKey, ?string $schedule = null)
 * @method static void startJob(string $monitorKey, ?string $schedule = null)
 * @method static void completeJob(string $monitorKey)
 * @method static void failJob(string $monitorKey, ?string $message = null)
 * @method static callable wrap(string $monitorKey, callable $func, ?string $schedule = null)
 * @method static void syncMonitor(string $monitorKey, string $schedule, ?string $source = null, ?string $name = null)
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
