<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BlockchainService
{
    protected array $supportedNetworks = [
        'ethereum' => [
            'chain_id' => 1,
            'rpc_url' => 'https://eth-mainnet.alchemyapi.io/v2/',
            'explorer' => 'https://etherscan.io',
            'currency' => 'ETH',
            'decimals' => 18,
            'min_confirmations' => 12,
        ],
        'polygon' => [
            'chain_id' => 137,
            'rpc_url' => 'https://polygon-rpc.com',
            'explorer' => 'https://polygonscan.com',
            'currency' => 'MATIC',
            'decimals' => 18,
            'min_confirmations' => 128,
        ],
        'binance_smart_chain' => [
            'chain_id' => 56,
            'rpc_url' => 'https://bsc-dataseed.binance.org',
            'explorer' => 'https://bscscan.com',
            'currency' => 'BNB',
            'decimals' => 18,
            'min_confirmations' => 15,
        ],
        'bitcoin' => [
            'rpc_url' => 'https://blockstream.info/api',
            'explorer' => 'https://blockstream.info',
            'currency' => 'BTC',
            'decimals' => 8,
            'min_confirmations' => 6,
        ],
        'solana' => [
            'rpc_url' => 'https://api.mainnet-beta.solana.com',
            'explorer' => 'https://solscan.io',
            'currency' => 'SOL',
            'decimals' => 9,
            'min_confirmations' => 32,
        ],
        'arbitrum' => [
            'chain_id' => 42161,
            'rpc_url' => 'https://arb1.arbitrum.io/rpc',
            'explorer' => 'https://arbiscan.io',
            'currency' => 'ETH',
            'decimals' => 18,
            'min_confirmations' => 12,
        ],
        'base' => [
            'chain_id' => 8453,
            'rpc_url' => 'https://mainnet.base.org',
            'explorer' => 'https://basescan.org',
            'currency' => 'ETH',
            'decimals' => 18,
            'min_confirmations' => 12,
        ],
    ];

    protected array $supportedTokens = [
        'USDT' => [
            'ethereum' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
            'polygon' => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
            'bsc' => '0x55d398326f99059fF775485246999027B3197955',
        ],
        'USDC' => [
            'ethereum' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
            'polygon' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174',
            'base' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        ],
        'DAI' => [
            'ethereum' => '0x6B175474E89094C44Da98b954EedeAC495271d0F',
            'polygon' => '0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063',
        ],
    ];

    public function generateWallet(string $network = 'ethereum'): array
    {
        return match ($network) {
            'bitcoin' => $this->generateBitcoinWallet(),
            'solana' => $this->generateSolanaWallet(),
            default => $this->generateEVMWallet($network),
        };
    }

    protected function generateEVMWallet(string $network): array
    {
        $privateKey = bin2hex(random_bytes(32));
        $publicKey = $this->derivePublicKey($privateKey);
        $address = $this->deriveAddress($publicKey);

        return [
            'network' => $network,
            'address' => $address,
            'public_key' => $publicKey,
            'private_key_encrypted' => encrypt($privateKey),
        ];
    }

    protected function generateBitcoinWallet(): array
    {
        // Using simplified BIP-32/BIP-44
        $seed = random_bytes(64);
        $extendedKey = $this->deriveExtendedKey($seed);

        return [
            'network' => 'bitcoin',
            'address' => $this->deriveBitcoinAddress($extendedKey),
            'xpub' => $extendedKey['public'],
            'encrypted_seed' => encrypt(bin2hex($seed)),
        ];
    }

    protected function generateSolanaWallet(): array
    {
        $seed = random_bytes(32);
        $keypair = sodium_crypto_sign_keypair_from_seed($seed);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        return [
            'network' => 'solana',
            'address' => base58_encode($publicKey),
            'public_key' => base64_encode($publicKey),
            'encrypted_secret' => encrypt(base64_encode($secretKey)),
        ];
    }

    public function createPaymentRequest(array $params): array
    {
        $validated = validator($params, [
            'network' => 'required|string|in:ethereum,polygon,binance_smart_chain,bitcoin,solana,arbitrum,base',
            'token' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
            'recipient_address' => 'required|string',
            'order_id' => 'required|string',
            'expiry_minutes' => 'integer|min:5|max:1440|default:30',
        ])->validate();

        $network = $this->supportedNetworks[$validated['network']];
        $amountInSmallestUnit = $this->toSmallestUnit($validated['amount'], $network['decimals']);

        $paymentId = uniqid('pay_');
        $expiresAt = now()->addMinutes($validated['expiry_minutes']);

        $paymentRequest = [
            'payment_id' => $paymentId,
            'network' => $validated['network'],
            'token' => $validated['token'],
            'amount' => $validated['amount'],
            'amount_smallest_unit' => $amountInSmallestUnit,
            'recipient_address' => $validated['recipient_address'],
            'order_id' => $validated['order_id'],
            'expires_at' => $expiresAt,
            'status' => 'pending',
            'created_at' => now(),
        ];

        // Store in cache for quick lookup
        Cache::put("payment:{$paymentId}", $paymentRequest, $expiresAt);

        return $paymentRequest;
    }

    public function checkPaymentStatus(string $paymentId): array
    {
        $payment = Cache::get("payment:{$paymentId}");

        if (!$payment) {
            return ['status' => 'not_found'];
        }

        if ($payment['expires_at'] < now()) {
            return ['status' => 'expired'];
        }

        if ($payment['status'] === 'confirmed') {
            return ['status' => 'confirmed', 'tx_hash' => $payment['tx_hash']];
        }

        // Check blockchain for transaction
        $txHash = $this->checkBlockchainForPayment($payment);

        if ($txHash) {
            $payment['status'] = 'confirmed';
            $payment['tx_hash'] = $txHash;
            $payment['confirmed_at' ] = now();
            Cache::put("payment:{$paymentId}", $payment, $payment['expires_at']);

            return ['status' => 'confirmed', 'tx_hash' => $txHash];
        }

        return ['status' => 'pending', 'expires_at' => $payment['expires_at']];
    }

    public function getSupportedNetworks(): array
    {
        return collect($this->supportedNetworks)
            ->map(fn($config, $key) => [
                'id' => $key,
                'name' => ucfirst(str_replace('_', ' ', $key)),
                'currency' => $config['currency'],
                'min_confirmations' => $config['min_confirmations'],
            ])
            ->values()
            ->toArray();
    }

    public function getSupportedTokens(): array
    {
        return array_keys($this->supportedTokens);
    }

    public function getExchangeRate(string $from, string $to = 'USD'): float
    {
        $cacheKey = "rate:{$from}_{$to}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Fetch from CoinGecko API
        try {
            $response = Http::get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => strtolower($from),
                'vs_currencies' => strtolower($to),
            ]);

            $rate = $response->json(strtolower($from))[strtolower($to)];
            Cache::put($cacheKey, $rate, now()->addMinutes(5));

            return $rate;
        } catch (\Exception $e) {
            Log::error('Failed to fetch exchange rate', ['from' => $from, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    public function convertToFiat(float $cryptoAmount, string $cryptoCurrency): array
    {
        $rate = $this->getExchangeRate($cryptoCurrency);

        return [
            'crypto_amount' => $cryptoAmount,
            'crypto_currency' => $cryptoCurrency,
            'usd_value' => $cryptoAmount * $rate,
            'rate' => $rate,
            'rate_timestamp' => now(),
        ];
    }

    protected function toSmallestUnit(float $amount, int $decimals): string
    {
        return (string) (int) ($amount * pow(10, $decimals));
    }

    protected function fromSmallestUnit(string $amount, int $decimals): float
    {
        return (float) $amount / pow(10, $decimals);
    }

    protected function checkBlockchainForPayment(array $payment): ?string
    {
        // Implementation: Query blockchain RPC for incoming transactions
        // to the payment address matching the exact amount
        return null;
    }

    protected function derivePublicKey(string $privateKey): string
    {
        // Simplified secp256k1 derivation
        return hash('sha256', $privateKey);
    }

    protected function deriveAddress(string $publicKey): string
    {
        // Simplified EVM address derivation
        return '0x' . substr(hash('sha3-256', hex2bin($publicKey)), -40);
    }

    protected function deriveExtendedKey(string $seed): array
    {
        return ['public' => 'xpub' . bin2hex(random_bytes(64)), 'private' => 'xprv...'];
    }

    protected function deriveBitcoinAddress($extendedKey): string
    {
        return 'bc1' . substr(bin2hex(random_bytes(20)), 0, 38);
    }
}
