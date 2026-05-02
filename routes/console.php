<?php

use Illuminate\Support\Facades\Schedule;

// Income automation - auto-post scheduled content
Schedule::command('income:auto-post')
    ->hourly()
    ->when(fn() => config('app.income.auto_posting.enabled', false));

// Revenue sync from platforms
Schedule::command('income:sync-revenue')
    ->everySixHours();

// Clean expired sessions
Schedule::command('session:prune')
    ->daily();

// Verification expiry check
Schedule::command('verification:check-expired')
    ->daily();

// Analytics aggregation
Schedule::command('analytics:aggregate')
    ->dailyAt('00:00');

// Clean temp files
Schedule::command('storage:cleanup-temp')
    ->daily();

// Rotate encryption keys (monthly)
Schedule::command('encryption:rotate-key')
    ->monthlyOn(1, '03:00');

// Blockchain payment confirmation checks
Schedule::command('crypto:check-payments')
    ->everyFiveMinutes();
