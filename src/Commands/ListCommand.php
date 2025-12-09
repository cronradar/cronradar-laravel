<?php

namespace CronRadar\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cronradar:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all scheduled tasks that can be monitored';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $schedule = $this->laravel->make(Schedule::class);
        $events = $schedule->events();

        if (empty($events)) {
            $this->warn('No scheduled tasks found.');
            return 0;
        }

        $this->info("Found " . count($events) . " scheduled tasks:\n");

        $rows = [];
        foreach ($events as $event) {
            // Use the same detectMonitorKey() logic from the macro
            $monitorKey = $event->detectMonitorKey();

            $command = $event->command ?? $event->description ?? 'Closure';
            $expression = $event->expression;

            $rows[] = [
                $monitorKey,
                $command,
                $expression,
            ];
        }

        $this->table(['Monitor Key', 'Command', 'Schedule'], $rows);

        $this->newLine();
        $this->line('Monitor keys are auto-generated from task commands/closures.');
        $this->line('Use <info>Schedule::monitorAll()</info> in routes/console.php to enable monitoring.');

        return 0;
    }
}
