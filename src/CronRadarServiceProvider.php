<?php

namespace CronRadar\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use CronRadar\Laravel\Commands\ListCommand;
use CronRadar\Laravel\Commands\TestCommand;
use CronRadar\Laravel\Commands\SyncCommand;

class CronRadarServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cronradar.php', 'cronradar'
        );

        // Register the CronRadar class as a singleton
        $this->app->singleton('cronradar', function () {
            return new \CronRadar\CronRadar();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/cronradar.php' => config_path('cronradar.php'),
        ], 'cronradar-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListCommand::class,
                TestCommand::class,
                SyncCommand::class,
            ]);
        }

        // Register event helper macros
        $this->registerEventHelpers();

        // Register ->monitor() macro
        $this->registerMonitorMacro();

        // Register Schedule::monitorAll() macro
        $this->registerScheduleMacro();

        // Register MonitorAll hook if enabled
        $this->registerMonitorAllHook();
    }

    /**
     * Register helper macros for Event class
     */
    protected function registerEventHelpers(): void
    {
        Event::macro('skipMonitor', function () {
            /** @var \Illuminate\Console\Scheduling\Event $this */
            $this->_skipCronRadarMonitor = true;
            return $this;
        });

        Event::macro('shouldSkipMonitor', function () {
            /** @var \Illuminate\Console\Scheduling\Event $this */
            return property_exists($this, '_skipCronRadarMonitor') && $this->_skipCronRadarMonitor === true;
        });

        Event::macro('isMonitored', function () {
            /** @var \Illuminate\Console\Scheduling\Event $this */
            return property_exists($this, '_cronRadarMonitored') && $this->_cronRadarMonitored === true;
        });

        Event::macro('detectMonitorKey', function () {
            /** @var \Illuminate\Console\Scheduling\Event $this */

            // Priority 1: Extract from command name
            if (!empty($this->command)) {
                return $this->normalizeKey($this->extractCommandName($this->command));
            }

            // Priority 2: Use description if set
            if (!empty($this->description)) {
                return $this->normalizeKey($this->description);
            }

            // Priority 3: Generate from callback hash (for closures)
            if ($this->callback) {
                $hash = substr(md5(serialize($this->callback)), 0, 8);
                Log::warning('CronRadar: Closure detected without description. Consider adding ->description("task-name") before ->monitor()');
                return 'scheduled-task-' . $hash;
            }

            // Fallback: Unique ID
            return 'scheduled-task-' . uniqid();
        });

        Event::macro('extractCommandName', function (string $command) {
            /** @var \Illuminate\Console\Scheduling\Event $this */

            // Extract command name from full artisan command string
            // Example: "'/usr/bin/php' 'artisan' reports:generate" → "reports:generate"
            if (preg_match('/artisan[\s]+([^\s]+)/', $command, $matches)) {
                return $matches[1];
            }

            // If it's just the command name (no path)
            if (!str_contains($command, ' ')) {
                return $command;
            }

            return basename($command);
        });

        Event::macro('normalizeKey', function (string $key) {
            /** @var \Illuminate\Console\Scheduling\Event $this */

            // Lowercase
            $key = strtolower($key);

            // Replace special chars with hyphens
            $key = preg_replace('/[^a-z0-9]+/', '-', $key);

            // Remove leading/trailing hyphens
            $key = trim($key, '-');

            // Truncate if too long
            if (strlen($key) > 64) {
                $key = substr($key, 0, 64);
            }

            return $key;
        });
    }

    /**
     * Register ->monitor() macro on Event
     */
    protected function registerMonitorMacro(): void
    {
        Event::macro('monitor', function (?string $customKey = null) {
            /** @var \Illuminate\Console\Scheduling\Event $this */
            // Mark as monitored
            $this->_cronRadarMonitored = true;

            return $this->after(function () use ($customKey) {
                // Only monitor successful executions
                if ($this->exitCode !== 0) {
                    return;
                }

                // Auto-detect monitor key if not provided
                $monitorKey = $customKey ?? $this->detectMonitorKey();

                // Extract schedule from event
                $schedule = $this->expression ?? null;

                // Monitor execution
                \CronRadar\CronRadar::monitor($monitorKey, $schedule);
            });
        });
    }

    /**
     * Register Schedule::monitorAll() macro
     */
    protected function registerScheduleMacro(): void
    {
        Schedule::macro('monitorAll', function () {
            /** @var \Illuminate\Console\Scheduling\Schedule $this */
            $this->_cronRadarMonitorAll = true;
            return $this;
        });
    }

    /**
     * Register MonitorAll hook if enabled in config or macro
     */
    protected function registerMonitorAllHook(): void
    {
        // Hook into the scheduler after all events are defined
        $this->app->booted(function () {
            /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            // Check if MonitorAll is enabled via macro OR config (macro takes priority)
            $monitorAll = (property_exists($schedule, '_cronRadarMonitorAll') && $schedule->_cronRadarMonitorAll)
                       || config('cronradar.monitor_all', false);

            // Get all scheduled events
            $events = $schedule->events();

            foreach ($events as $event) {
                // Skip if event has explicit ->skipMonitor()
                if ($event->shouldSkipMonitor()) {
                    continue;
                }

                // Determine if this event should be monitored
                $shouldMonitor = false;

                if ($monitorAll && !$event->isMonitored()) {
                    // MonitorAll mode: auto-add monitoring to unmarked events
                    $event->monitor();
                    $shouldMonitor = true;
                } elseif ($event->isMonitored()) {
                    // Selective mode: event explicitly marked with ->monitor()
                    $shouldMonitor = true;
                }

                // Sync all monitored events (both Selective and MonitorAll)
                if ($shouldMonitor) {
                    try {
                        $monitorKey = $event->detectMonitorKey();
                        $scheduleExpression = $event->expression ?? null;
                        \CronRadar\CronRadar::sync($monitorKey, $scheduleExpression);
                    } catch (\Throwable $e) {
                        Log::warning("CronRadar sync failed for {$monitorKey}: {$e->getMessage()}");
                    }
                }
            }
        });
    }
}
