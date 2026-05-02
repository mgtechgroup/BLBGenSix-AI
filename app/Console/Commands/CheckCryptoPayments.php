<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CryptoPayment;
use App\Services\BlockchainPaymentService;

class CheckCryptoPayments extends Command
{
    protected $signature = 'crypto:check-payments';
    protected $description = 'Check pending crypto payments for blockchain confirmations';

    public function handle(BlockchainPaymentService $paymentService): int
    {
        $this->info('⛓️ Checking pending crypto payments...');

        $pending = CryptoPayment::where('status', 'awaiting_payment')
            ->orWhere('status', 'payment_detected')
            ->get();

        foreach ($pending as $payment) {
            if ($payment->isExpired()) {
                $payment->update(['status' => 'expired']);
                $this->warn("⏰ Payment {$payment->payment_id} expired");
                continue;
            }

            $status = $paymentService->verifyAndProcessPayment($payment->payment_id, []);
            
            if ($status['status'] === 'confirmed') {
                $this->info("✅ Payment {$payment->payment_id} confirmed ({$status['tx_hash']})");
            } elseif ($status['status'] === 'pending_confirmations') {
                $this->info("⏳ Payment {$payment->payment_id}: {$status['confirmations']}/{$status['required']} confirmations");
            }
        }

        return Command::SUCCESS;
    }
}
