<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CryptoWallet;
use App\Models\CryptoPayment;
use App\Services\BlockchainPaymentService;
use App\Services\ColdWalletVerificationService;
use Illuminate\Http\Request;

class CryptoPaymentController extends Controller
{
    protected BlockchainPaymentService $paymentService;
    protected ColdWalletVerificationService $coldWalletVerifier;

    public function __construct(BlockchainPaymentService $paymentService, ColdWalletVerificationService $coldWalletVerifier)
    {
        $this->paymentService = $paymentService;
        $this->coldWalletVerifier = $coldWalletVerifier;
    }

    public function networks()
    {
        return response()->json([
            'networks' => $this->paymentService->blockchain->getSupportedNetworks(),
            'tokens' => $this->paymentService->blockchain->getSupportedTokens(),
            'allowed_wallet_types' => [
                CryptoWallet::TYPE_LEDGER,
                CryptoWallet::TYPE_TREZOR,
                CryptoWallet::TYPE_BITBOX,
                CryptoWallet::TYPE_SAFE3,
                CryptoWallet::TYPE_KEYSTONE,
                CryptoWallet::TYPE_NGRAVE,
                CryptoWallet::TYPE_DEVICE_BOUND,
                CryptoWallet::TYPE_HARDWARE_EMBEDDED,
            ],
            'policy' => 'Only cold/hardware wallets or device-bound passkey wallets are permitted. Hot wallets are strictly prohibited.',
        ]);
    }

    public function registerWallet(Request $request)
    {
        $validated = $request->validate([
            'wallet_type' => 'required|string|in:ledger,trezor,bitbox02,safe3,keystone,ngrave_zero,device_bound_passkey,hardware_embedded',
            'model' => 'required|string',
            'network' => 'required|string|in:ethereum,polygon,binance_smart_chain,bitcoin,solana,arbitrum,base',
            'address' => 'required|string',
            'public_key' => 'required|string',
            'attestation' => 'nullable|array',
            'challenge_signature' => 'nullable|string',
            'qr_payload' => 'nullable|string',
            'qr_signature' => 'nullable|string',
            'webauthn_credential' => 'nullable|array',
            'tee_attestation' => 'nullable|array',
            'passkey_credential_id' => 'nullable|string',
        ]);

        $result = $this->paymentService->registerColdWallet(auth()->user(), $validated);

        return response()->json($result, 201);
    }

    public function wallets()
    {
        return response()->json([
            'wallets' => $this->paymentService->getUserWallets(auth()->user()),
        ]);
    }

    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'network' => 'required|string|in:ethereum,polygon,binance_smart_chain,bitcoin,solana,arbitrum,base',
            'token' => 'required|string',
            'amount_usd' => 'required|numeric|min:0.01',
            'order_type' => 'required|string|in:subscription,generation_credit,tip,ppv,bundle,ad_space,affiliate_payout',
            'order_id' => 'required|string',
            'expiry_minutes' => 'integer|min:5|max:1440|default:30',
        ]);

        $invoice = $this->paymentService->createPaymentInvoice(auth()->user(), $validated);

        return response()->json($invoice, 201);
    }

    public function checkPayment($paymentId)
    {
        $senderData = [
            'wallet_type' => request()->header('X-Wallet-Type'),
        ];

        $result = $this->paymentService->verifyAndProcessPayment($paymentId, $senderData);

        return response()->json($result);
    }

    public function paymentHistory()
    {
        $payments = CryptoPayment::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    public function initiateWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'wallet_id' => 'required|uuid',
            'network' => 'required|string',
            'token' => 'required|string',
            'amount' => 'required|numeric|min:0.00000001',
            'destination_address' => 'required|string',
            'hardware_signature' => 'required|string',
        ]);

        $result = $this->paymentService->processWithdrawal(auth()->user(), $validated);

        return response()->json($result);
    }

    public function exchangeRate(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $rate = $this->paymentService->blockchain->getExchangeRate($validated['token']);
        $converted = $this->paymentService->blockchain->convertToFiat(1, $validated['token']);

        return response()->json($converted);
    }

    public function getDepositAddress(Request $request)
    {
        $validated = $request->validate([
            'network' => 'required|string',
            'token' => 'required|string',
        ]);

        return response()->json([
            'address' => config("services.crypto.deposit_{$validated['network']}_{$validated['token']}", 'N/A'),
            'network' => $validated['network'],
            'token' => $validated['token'],
            'warning' => 'Only send the specified token on the specified network. Sending other tokens may result in permanent loss.',
        ]);
    }
}
