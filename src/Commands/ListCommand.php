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
            $command = $event->command ?? $event->description ?? 'unknown';
            $expression = $event->expression;

            $rows[] = [
                $this->generateMonitorKey($command),
                $command,
                $expression,
            ];
        }

        $this->table(['Monitor Key', 'Command', 'Schedule'], $rows);

        $this->newLine();
        $this->line('To start monitoring, add ->pingCronRadar(\'monitor-key\') to your scheduled tasks.');
        $this->line('Or run <info>php artisan cronradar:discover</info> to auto-register all tasks.');

        return 0;
    }

    /**
     * Generate a monitor key from a command string
     */
    private function generateMonitorKey(string $command): string
    {
        // Remove quotes and artisan prefix
        $key = trim($command, "'\"");
        $key = str_replace("'artisan' ", '', $key);
        $key = str_replace('"artisan" ', '', $key);

        // Convert to kebab-case
        $key = preg_replace('/[^a-zA-Z0-9]+/', '-', $key);
        $key = trim($key, '-');
        $key = strtolower($key);

        return $key;
    }
}
