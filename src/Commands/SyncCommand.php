<?php

namespace CronRadar\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use CronRadar\CronRadar;

class SyncCommand extends Command
{
    protected $signature = 'cronradar:sync';
    protected $description = 'Reconcile scheduled tasks with CronRadar (run on deploy for immediate coverage; schedule:run also reconciles every minute)';

    public function handle(): int
    {
        $schedule = $this->laravel->make(Schedule::class);
        $events = $schedule->events();

        $monitors = [];
        $skippedCount = 0;

        $this->info('Reconciling scheduled tasks with CronRadar...');
        $this->newLine();

        foreach ($events as $event) {
            // Skip if explicitly marked to skip
            if ($event->shouldSkipMonitor()) {
                $skippedCount++;
                continue;
            }

            $monitorKey = $event->detectMonitorKey();
            $scheduleExpression = $event->expression;

            if (empty($monitorKey) || empty($scheduleExpression)) {
                continue;
            }

            $monitors[] = [
                'key' => $monitorKey,
                'schedule' => $scheduleExpression,
            ];
            $this->line("  • {$monitorKey} ({$scheduleExpression})");
        }

        // Reconcile the complete set in a single request
        CronRadar::syncMonitors($monitors, 'laravel');

        $this->newLine();
        $this->info("Reconciled " . count($monitors) . " monitors");

        if ($skippedCount > 0) {
            $this->line("Skipped {$skippedCount} tasks with ->skipMonitor()");
        }

        return 0;
    }
}
