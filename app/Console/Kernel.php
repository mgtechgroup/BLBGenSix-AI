<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Income automation - post scheduled content
        $schedule->command('income:auto-post')
            ->hourly()
            ->when(function () {
                return config('app.income.auto_posting.enabled', false);
            });

        // Revenue sync from platforms
        $schedule->command('income:sync-revenue')
            ->everySixHours();

        // Clean up expired sessions
        $schedule->command('session:prune')
            ->daily();

        // Verification expiry check
        $schedule->command('verification:check-expired')
            ->daily();

        // Analytics aggregation
        $schedule->command('analytics:aggregate')
            ->dailyAt('00:00');

        // Clean up temp files
        $schedule->command('storage:cleanup-temp')
            ->daily();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
