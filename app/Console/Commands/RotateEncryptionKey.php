<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EncryptionService;

class RotateEncryptionKey extends Command
{
    protected $signature = 'encryption:rotate-key';
    protected $description = 'Rotate the master encryption key and re-encrypt all sensitive data';

    public function handle(EncryptionService $encryption): int
    {
        $this->info('🔐 Rotating master encryption key...');

        try {
            $encryption->rotateKey();
            $this->info('✅ Master encryption key rotated successfully.');
            $this->warn('⚠️  All previously encrypted data using old keys is still accessible via rotation history.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Key rotation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
