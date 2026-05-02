<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Verification;

class CheckExpiredVerifications extends Command
{
    protected $signature = 'verification:check-expired';
    protected $description = 'Check and expire outdated adult verifications';

    public function handle(): int
    {
        $this->info('🔍 Checking for expired verifications...');

        $expired = Verification::where('status', 'approved')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $verification) {
            $verification->update(['status' => 'expired']);
            $verification->user->update([
                'is_verified_adult' => false,
                'verification_status' => 'expired',
            ]);
            $this->warn("⏰ Expired verification for user {$verification->user_id}");
        }

        $this->info("✅ {$expired->count()} verification(s) expired.");
        return Command::SUCCESS;
    }
}
