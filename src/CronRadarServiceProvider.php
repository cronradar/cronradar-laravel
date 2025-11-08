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
        try {
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
        } catch (\Throwable $e) {
            // Never break the application
            Log::error('CronRadar: ServiceProvider boot failed - ' . $e->getMessage());
        }
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

        Event::macro('extractKeyFromClosureCode', function () {
            /** @var \Illuminate\Console\Scheduling\Event $this */
            // Simplified: Just use file + line number for closures
            // Parsing source code is overly complex and fragile
            return null;
        });

        Event::macro('detectMonitorKey', function () {
            /** @var \Illuminate\Console\Scheduling\Event $this */

            try {
                // Priority 0: Check if custom key was set via ->monitor('custom-key')
                if (property_exists($this, '_cronRadarCustomKey') && !empty($this->_cronRadarCustomKey)) {
                    return $this->_cronRadarCustomKey;
                }

                // Priority 1: Extract from command name
                if (!empty($this->command)) {
                    return $this->normalizeKey($this->extractCommandName($this->command));
                }

                // Priority 2: Use description if set
                if (!empty($this->description)) {
                    return $this->normalizeKey($this->description);
                }

                // Priority 3: For closures, extract key from the closure code itself
                if ($this instanceof \Illuminate\Console\Scheduling\CallbackEvent) {
                    $extractedKey = $this->extractKeyFromClosureCode();
                    if ($extractedKey) {
                        return $extractedKey;
                    }
                }

                // Priority 4: Fallback to line number for closures (unique identifier)
                if ($this instanceof \Illuminate\Console\Scheduling\CallbackEvent) {
                    try {
                        $reflection = new \ReflectionFunction($this->callback);
                        $line = $reflection->getStartLine();
                        $file = basename($reflection->getFileName(), '.php');
                        return $this->normalizeKey($file . '-line-' . $line);
                    } catch (\Throwable $e) {
                        // Continue to next fallback
                    }
                }

                // Final fallback
                return 'task-' . substr(md5(uniqid()), 0, 8);
            } catch (\Throwable $e) {
                Log::error('CronRadar: Error detecting monitor key: ' . $e->getMessage());
                return 'task-error-' . substr(md5(uniqid()), 0, 8);
            }
        });

        Event::macro('extractCommandName', function (string $command) {
            /** @var \Illuminate\Console\Scheduling\Event $this */

            // Extract command name from full artisan command string
            // Examples:
            //   "C:\path\php.exe" "artisan" queue:work --stop-when-empty → queue-work
            //   '/usr/bin/php' 'artisan' reports:generate --weekly → reports-generate
            //   php artisan cache:clear → cache-clear

            // Pattern 1: Match "artisan" followed by command name (with or without params)
            if (preg_match('/artisan["\']?\s+([a-z0-9:_-]+)/i', $command, $matches)) {
                $commandName = $matches[1];

                // Convert colons to hyphens for better readability
                // queue:work → queue-work
                $commandName = str_replace(':', '-', $commandName);

                return $commandName;
            }

            // Pattern 2: If it's a simple command without path
            if (!str_contains($command, ' ') && !str_contains($command, '/') && !str_contains($command, '\\')) {
                return str_replace(':', '-', $command);
            }

            // Pattern 3: Extract just the executable name as fallback
            $parts = preg_split('/[\s\/\\\\]+/', $command);
            foreach ($parts as $part) {
                $cleaned = trim($part, '"\'');
                if (!empty($cleaned) && !str_contains($cleaned, '.exe') && !str_contains($cleaned, 'php') && $cleaned !== 'artisan') {
                    return str_replace(':', '-', $cleaned);
                }
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
     * Register ->monitor() macro on Event (deprecated, but kept for backward compatibility)
     * In MonitorAll mode, this just stores a custom key but doesn't affect whether task is monitored
     */
    protected function registerMonitorMacro(): void
    {
        Event::macro('monitor', function (?string $customKey = null) {
            /** @var \Illuminate\Console\Scheduling\Event $this */

            // Store custom key if provided
            if ($customKey !== null) {
                $this->_cronRadarCustomKey = $customKey;
            }

            // Add before callback for lifecycle tracking (job start)
            $this->before(function () use ($customKey) {
                try {
                    // Use custom key if provided, otherwise auto-detect
                    $monitorKey = $customKey ?? $this->detectMonitorKey();

                    // Signal job start for lifecycle tracking
                    \CronRadar\CronRadar::startJob($monitorKey);
                    Log::info("CronRadar: Started job '{$monitorKey}'");
                } catch (\Throwable $e) {
                    Log::error('CronRadar: StartJob failed: ' . $e->getMessage());
                }
            });

            // Add after callback for lifecycle tracking (job complete or fail)
            return $this->after(function () use ($customKey) {
                try {
                    // Use custom key if provided, otherwise auto-detect
                    $monitorKey = $customKey ?? $this->detectMonitorKey();

                    // Report success or failure
                    if ($this->exitCode !== 0) {
                        // Signal job failure for immediate alert
                        \CronRadar\CronRadar::failJob($monitorKey, "Exit code: {$this->exitCode}");
                        Log::warning("CronRadar: Failed job '{$monitorKey}' with exit code {$this->exitCode}");
                    } else {
                        // Signal job completion for lifecycle tracking
                        \CronRadar\CronRadar::completeJob($monitorKey);
                        Log::info("CronRadar: Completed job '{$monitorKey}'");
                    }
                } catch (\Throwable $e) {
                    Log::error('CronRadar: Lifecycle tracking failed: ' . $e->getMessage());
                }
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
     * Register MonitorAll hook using lazy monitoring approach
     *
     * TIMING ISSUE FIX: Console routes (routes/console.php) are loaded AFTER service providers boot,
     * so we can't apply monitoring at boot time. Instead, we use a lazy approach that applies
     * monitoring when schedule commands actually run (schedule:run, schedule:list, etc).
     *
     * MonitorAll mode is ALWAYS enabled. Use ->skipMonitor() to opt-out.
     */
    protected function registerMonitorAllHook(): void
    {
        // Register a macro that applies monitoring lazily when first needed
        Schedule::macro('applyMonitoringIfNeeded', function () {
            /** @var \Illuminate\Console\Scheduling\Schedule $this */

            // Check if we've already applied monitoring (run only once)
            if (property_exists($this, '_cronRadarMonitoringApplied') && $this->_cronRadarMonitoringApplied) {
                return;
            }

            try {
                // Get all scheduled events
                $events = $this->events();

                foreach ($events as $event) {
                    // Skip if event has explicit ->skipMonitor()
                    if ($event->shouldSkipMonitor()) {
                        continue;
                    }

                    // MonitorAll mode: Monitor all events unless explicitly skipped
                    try {
                        // Apply monitoring to this event
                        $event->monitor();

                        // Sync to CronRadar
                        $monitorKey = $event->detectMonitorKey();
                        $scheduleExpression = $event->expression ?? null;
                        \CronRadar\CronRadar::syncMonitor($monitorKey, $scheduleExpression);
                        Log::info("CronRadar: Synced monitor '{$monitorKey}' ({$scheduleExpression})");
                    } catch (\Throwable $e) {
                        Log::error("CronRadar: Sync failed - " . $e->getMessage());
                    }
                }

                // Mark as applied so we don't run this again
                $this->_cronRadarMonitoringApplied = true;
            } catch (\Throwable $e) {
                Log::error('CronRadar: Lazy monitoring failed - ' . $e->getMessage());
            }
        });

        // Hook into schedule commands to apply monitoring before they execute
        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                try {
                    // Listen for CommandStarting event to catch schedule commands
                    $this->app['events']->listen('Illuminate\Console\Events\CommandStarting', function ($event) {
                        // Check if it's a schedule-related command
                        if (in_array($event->command, ['schedule:run', 'schedule:list', 'schedule:test', 'schedule:work'])) {
                            try {
                                /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
                                $schedule = $this->app->make(Schedule::class);
                                $schedule->applyMonitoringIfNeeded();
                            } catch (\Throwable $e) {
                                Log::error('CronRadar: Failed to apply monitoring on command start - ' . $e->getMessage());
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    // Event system might not be available, that's ok
                    Log::error('CronRadar: Failed to register CommandStarting listener - ' . $e->getMessage());
                }
            }
        });
    }
}
