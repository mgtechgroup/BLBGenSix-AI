<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\IncomeStream;
use Modules\IncomeAutomation\Services\IncomeAutomationService;

class AutoPostContent extends Command
{
    protected $signature = 'income:auto-post';
    protected $description = 'Auto-post scheduled content to connected platforms';

    public function handle(IncomeAutomationService $incomeService): int
    {
        $this->info('📤 Checking for scheduled posts...');

        $platforms = config('app.income.platforms', []);
        $enabledPlatforms = array_filter($platforms, fn($p) => $p['enabled'] ?? false);

        if (empty($enabledPlatforms)) {
            $this->warn('⚠️  No income platforms enabled. Skipping auto-post.');
            return Command::SUCCESS;
        }

        foreach ($enabledPlatforms as $platformName => $config) {
            $streams = IncomeStream::where('platform', $platformName)
                ->where('is_connected', true)
                ->where('auto_post_enabled', true)
                ->get();

            foreach ($streams as $stream) {
                $this->info("Posting to {$platformName} ({$stream->id})...");
                try {
                    $result = $incomeService->autoPost($stream->user_id, $platformName, [], 1);
                    $this->info("✅ Posted {$result['posts_created']} content(s) to {$platformName}");
                } catch (\Exception $e) {
                    $this->error("❌ Failed to post to {$platformName}: {$e->getMessage()}");
                }
            }
        }

        return Command::SUCCESS;
    }
}
