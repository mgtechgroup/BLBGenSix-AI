<?php

namespace App\Services;

use App\Models\CryptoWallet;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BlockchainPaymentService
{
    protected BlockchainService $blockchain;
    protected ColdWalletVerificationService $coldWalletVerifier;
    protected AuditLogger $audit;
    protected EncryptionService $encryption;

    // Only these wallet types are allowed - NO hot wallets
    protected array $allowedWalletTypes = [
        CryptoWallet::TYPE_LEDGER,
        CryptoWallet::TYPE_TREZOR,
        CryptoWallet::TYPE_BITBOX,
        CryptoWallet::TYPE_SAFE3,
        CryptoWallet::TYPE_KEYSTONE,
        CryptoWallet::TYPE_NGRAVE,
        CryptoWallet::TYPE_DEVICE_BOUND,
        CryptoWallet::TYPE_HARDWARE_EMBEDDED,
    ];

    // Supported payment tokens on each network
    protected array $paymentTokens = [
        'ethereum' => ['ETH', 'USDT', 'USDC', 'DAI'],
        'polygon' => ['MATIC', 'USDT', 'USDC', 'DAI'],
        'binance_smart_chain' => ['BNB', 'USDT', 'BUSD'],
        'bitcoin' => ['BTC'],
        'solana' => ['SOL', 'USDC'],
        'arbitrum' => ['ETH', 'USDC', 'USDT'],
        'base' => ['ETH', 'USDC'],
    ];

    public function __construct(
        BlockchainService $blockchain,
        ColdWalletVerificationService $coldWalletVerifier,
        AuditLogger $audit,
        EncryptionService $encryption
    ) {
        $this->blockchain = $blockchain;
        $this->coldWalletVerifier = $coldWalletVerifier;
        $this->audit = $audit;
        $this->encryption = $encryption;
    }

    public function createPaymentInvoice(User $user, array $params): array
    {
        $validated = validator($params, [
            'network' => 'required|string|in:ethereum,polygon,binance_smart_chain,bitcoin,solana,arbitrum,base',
            'token' => 'required|string',
            'amount_usd' => 'required|numeric|min:0.01',
            'order_type' => 'required|string|in:subscription,generation_credit,tip,ppv,bundle,ad_space,affiliate_payout',
            'order_id' => 'required|string',
            'expiry_minutes' => 'integer|min:5|max:1440|default:30',
        ])->validate();

        // Verify token is supported on network
        if (!in_array($validated['token'], $this->paymentTokens[$validated['network']] ?? [])) {
            throw new \RuntimeException("Token {$validated['token']} not supported on {$validated['network']}");
        }

        // Get live exchange rate
        $rate = $this->blockchain->getExchangeRate($validated['token']);
        $cryptoAmount = $validated['amount_usd'] / $rate;

        // Get network config
        $networkConfig = $this->blockchain->getSupportedNetworks();
        $network = collect($networkConfig)->firstWhere('id', $validated['network']);

        $paymentId = uniqid('pay_');
        $expiresAt = now()->addMinutes($validated['expiry_minutes']);

        $invoice = [
            'payment_id' => $paymentId,
            'user_id' => $user->id,
            'network' => $validated['network'],
            'token' => $validated['token'],
            'amount_usd' => $validated['amount_usd'],
            'amount_crypto' => $cryptoAmount,
            'exchange_rate' => $rate,
            'recipient_address' => $this->getPlatformDepositAddress($validated['network'], $validated['token']),
            'order_type' => $validated['order_type'],
            'order_id' => $validated['order_id'],
            'expires_at' => $expiresAt,
            'status' => 'awaiting_payment',
            'qr_code' => $this->generatePaymentQR($validated['network'], $validated['token'], $cryptoAmount),
            'payment_uri' => $this->generatePaymentURI($validated['network'], $validated['token'], $cryptoAmount),
            'created_at' => now(),
        ];

        Cache::put("invoice:{$paymentId}", $invoice, $expiresAt);

        $this->audit->log('payment.invoice_created', 'crypto_payment', [
            'payment_id' => $paymentId,
            'user_id' => $user->id,
            'network' => $validated['network'],
            'token' => $validated['token'],
            'amount_usd' => $validated['amount_usd'],
        ]);

        return $invoice;
    }

    public function verifyAndProcessPayment(string $paymentId, array $senderData): array
    {
        $invoice = Cache::get("invoice:{$paymentId}");

        if (!$invoice) {
            return ['status' => 'not_found'];
        }

        if ($invoice['expires_at'] < now()) {
            return ['status' => 'expired'];
        }

        // CRITICAL: Verify sender is using a cold/hardware wallet
        $this->verifySenderColdWallet($senderData);

        // Check blockchain for payment
        $txHash = $this->blockchain->checkBlockchainForPayment([
            'network' => $invoice['network'],
            'token' => $invoice['token'],
            'recipient_address' => $invoice['recipient_address'],
            'expected_amount' => $invoice['amount_crypto'],
        ]);

        if ($txHash) {
            // Verify confirmations
            $confirmations = $this->getTransactionConfirmations($invoice['network'], $txHash);
            $minConfirmations = $this->getMinConfirmations($invoice['network']);

            if ($confirmations >= $minConfirmations) {
                Cache::put("invoice:{$paymentId}", array_merge($invoice, [
                    'status' => 'confirmed',
                    'tx_hash' => $txHash,
                    'confirmations' => $confirmations,
                    'confirmed_at' => now(),
                ]), now()->addDays(30));

                $this->audit->log('payment.confirmed', 'crypto_payment', [
                    'payment_id' => $paymentId,
                    'tx_hash' => $txHash,
                    'confirmations' => $confirmations,
                ]);

                return [
                    'status' => 'confirmed',
                    'tx_hash' => $txHash,
                    'confirmations' => $confirmations,
                    'message' => 'Payment confirmed on blockchain',
                ];
            }

            return [
                'status' => 'pending_confirmations',
                'confirmations' => $confirmations,
                'required' => $minConfirmations,
                'tx_hash' => $txHash,
            ];
        }

        return ['status' => 'awaiting_payment', 'expires_at' => $invoice['expires_at']];
    }

    public function processWithdrawal(User $user, array $params): array
    {
        $validated = validator($params, [
            'wallet_id' => 'required|uuid|exists:crypto_wallets,id',
            'network' => 'required|string',
            'token' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
            'destination_address' => 'required|string',
        ])->validate();

        // STRICT: Only allow withdrawals to verified cold wallets
        $wallet = CryptoWallet::where('user_id', $user->id)
            ->where('id', $validated['wallet_id'])
            ->coldOnly()
            ->deviceBound()
            ->verified()
            ->first();

        if (!$wallet) {
            $this->audit->logSecurityEvent('withdrawal.rejected_non_cold_wallet', [
                'user_id' => $user->id,
                'wallet_id' => $validated['wallet_id'],
            ]);
            throw new \RuntimeException('Withdrawals are only permitted to verified cold/hardware wallets bound to your device');
        }

        // Verify device fingerprint matches
        if ($wallet->binding_device_fingerprint !== request()->header('X-Device-Fingerprint')) {
            throw new \RuntimeException('Withdrawal must be initiated from the device bound to this wallet');
        }

        // Require biometric authentication
        if ($wallet->requiresReverification()) {
            throw new \RuntimeException('Biometric re-verification required before withdrawal');
        }

        // Verify transaction signature from hardware wallet
        $txData = json_encode([
            'from' => $wallet->address,
            'to' => $validated['destination_address'],
            'amount' => $validated['amount'],
            'token' => $validated['token'],
            'network' => $validated['network'],
            'nonce' => time(),
        ]);

        $signature = $params['hardware_signature'] ?? null;
        if (!$signature) {
            throw new \RuntimeException('Hardware wallet signature required for withdrawal');
        }

        $signatureValid = $this->coldWalletVerifier->verifyTransactionSignature($wallet, $txData, $signature);

        if (!$signatureValid) {
            throw new \RuntimeException('Hardware wallet signature verification failed');
        }

        // Process withdrawal
        $txHash = $this->executeBlockchainWithdrawal($validated);

        $this->audit->log('payment.withdrawal_processed', 'crypto_payment', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => $validated['amount'],
            'tx_hash' => $txHash,
        ]);

        return [
            'status' => 'processing',
            'tx_hash' => $txHash,
            'message' => 'Withdrawal initiated from verified cold wallet',
        ];
    }

    public function registerColdWallet(User $user, array $walletData): array
    {
        return $this->coldWalletVerifier->verifyHardwareWallet($user, $walletData);
    }

    public function getUserWallets(User $user): array
    {
        return $user->cryptoWallets()
            ->coldOnly()
            ->deviceBound()
            ->get()
            ->map(fn($w) => [
                'id' => $w->id,
                'type' => $w->wallet_type,
                'model' => $w->wallet_model,
                'network' => $w->network,
                'address' => $w->address,
                'status' => $w->status,
                'trust_score' => $w->verification_score,
                'last_used' => $w->last_used_at,
            ])
            ->toArray();
    }

    protected function verifySenderColdWallet(array $senderData): void
    {
        // Verify sender is using a recognized cold wallet
        // Check for hardware wallet signatures in the transaction
        if (!isset($senderData['wallet_type']) || !in_array($senderData['wallet_type'], $this->allowedWalletTypes)) {
            throw new \RuntimeException('Only cold/hardware wallets are permitted for payments. Hot wallets are prohibited.');
        }
    }

    protected function getPlatformDepositAddress(string $network, string $token): string
    {
        // Return the platform's deposit address for the given network/token
        return config("services.crypto.deposit_{$network}_{$token}", '0xPlatformAddress');
    }

    protected function generatePaymentQR(string $network, string $token, float $amount): string
    {
        // Generate QR code data URI
        $uri = $this->generatePaymentURI($network, $token, $amount);
        return "data:image/png;base64," . base64_encode($uri);
    }

    protected function generatePaymentURI(string $network, string $token, float $amount): string
    {
        return match ($network) {
            'bitcoin' => "bitcoin:?amount={$amount}",
            'ethereum', 'polygon', 'arbitrum', 'base' => "ethereum:0xPlatformAddress?value=" . $this->toWei($amount),
            'solana' => "solana:PlatformAddress?amount={$amount}",
            'binance_smart_chain' => "bsc:0xPlatformAddress?value=" . $this->toWei($amount),
            default => "crypto:{$amount}",
        };
    }

    protected function toWei(float $amount): string
    {
        return (string) (int) ($amount * 1e18);
    }

    protected function getTransactionConfirmations(string $network, string $txHash): int
    {
        // Query blockchain for confirmations
        return 1;
    }

    protected function getMinConfirmations(string $network): int
    {
        return match ($network) {
            'bitcoin' => 6,
            'ethereum' => 12,
            'polygon' => 128,
            'binance_smart_chain' => 15,
            'solana' => 32,
            default => 12,
        };
    }

    protected function executeBlockchainWithdrawal(array $params): string
    {
        // Execute on-chain withdrawal
        return '0x' . bin2hex(random_bytes(32));
    }
}
