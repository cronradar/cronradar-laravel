<?php

namespace CronRadar\Laravel\Commands;

use Illuminate\Console\Command;
use CronRadar\CronRadar;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cronradar:test {monitor-key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test ping to CronRadar';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $monitorKey = $this->argument('monitor-key');

        $this->info("Sending test monitor to monitor: {$monitorKey}");

        try {
            CronRadar::monitor($monitorKey);
            $this->info('✓ Monitor sent successfully!');
            $this->line('Check your CronRadar dashboard to verify the monitor was received.');
        } catch (\Throwable $e) {
            $this->error('✗ Failed to send monitor: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
