<?php

namespace CronRadar\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use CronRadar\CronRadar;

class SyncCommand extends Command
{
    protected $signature = 'cronradar:sync';
    protected $description = 'Manually sync monitored tasks (auto-syncs on boot by default)';

    public function handle(): int
    {
        $schedule = $this->laravel->make(Schedule::class);
        $events = $schedule->events();

        // Check if MonitorAll is enabled
        $monitorAll = (property_exists($schedule, '_cronRadarMonitorAll') && $schedule->_cronRadarMonitorAll)
                   || config('cronradar.monitor_all', false);

        $monitoredCount = 0;
        $skippedCount = 0;

        $this->info('Syncing scheduled tasks with CronRadar...');
        $this->newLine();

        foreach ($events as $event) {
            // Skip if explicitly marked to skip
            if ($event->shouldSkipMonitor()) {
                $skippedCount++;
                continue;
            }

            // Include if: explicitly monitored OR MonitorAll is enabled
            $shouldSync = $event->isMonitored() || $monitorAll;

            if (!$shouldSync) {
                continue;
            }

            $monitorKey = $event->detectMonitorKey();
            $scheduleExpression = $event->expression;

            try {
                CronRadar::sync($monitorKey, $scheduleExpression);
                $this->line("  ✓ Synced: {$monitorKey} ({$scheduleExpression})");
                $monitoredCount++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$monitorKey} - {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Synced {$monitoredCount} monitors");

        if ($skippedCount > 0) {
            $this->line("Skipped {$skippedCount} tasks with ->skipMonitor()");
        }

        return 0;
    }
}
