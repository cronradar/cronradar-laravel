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

        $this->info("Sending test ping to monitor: {$monitorKey}");

        try {
            CronRadar::ping($monitorKey);
            $this->info('✓ Ping sent successfully!');
            $this->line('Check your CronRadar dashboard to verify the ping was received.');
        } catch (\Throwable $e) {
            $this->error('✗ Failed to send ping: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
