<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\CryptoPaymentController;
use Modules\Payments\Http\Controllers\FiatPaymentController;

// Crypto Payments (Cold Wallet Only)
Route::prefix('crypto')->group(function () {
    Route::get('/networks', [CryptoPaymentController::class, 'networks']);
    Route::post('/wallets/register', [CryptoPaymentController::class, 'registerWallet']);
    Route::get('/wallets', [CryptoPaymentController::class, 'wallets']);
    Route::post('/invoice', [CryptoPaymentController::class, 'createInvoice']);
    Route::get('/invoice/{paymentId}/status', [CryptoPaymentController::class, 'checkPayment']);
    Route::get('/history', [CryptoPaymentController::class, 'paymentHistory']);
    Route::post('/withdraw', [CryptoPaymentController::class, 'initiateWithdrawal']);
    Route::get('/rate', [CryptoPaymentController::class, 'exchangeRate']);
    Route::get('/deposit-address', [CryptoPaymentController::class, 'getDepositAddress']);
});

// Fiat Payments
Route::prefix('fiat')->group(function () {
    Route::get('/methods', [FiatPaymentController::class, 'methods']);
    Route::post('/paypal', [FiatPaymentController::class, 'payWithPayPal']);
    Route::post('/cashapp', [FiatPaymentController::class, 'payWithCashApp']);
});
