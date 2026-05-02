<?php

use Illuminate\Support\Facades\Route;
use Modules\SaaS\Http\Controllers\BillingController;

Route::get('/plans', [BillingController::class, 'plans']);
Route::post('/subscribe', [BillingController::class, 'subscribe']);
Route::post('/cancel', [BillingController::class, 'cancel']);
Route::post('/resume', [BillingController::class, 'resume']);
Route::post('/upgrade', [BillingController::class, 'upgrade']);
Route::post('/downgrade', [BillingController::class, 'downgrade']);
Route::get('/invoices', [BillingController::class, 'invoices']);
Route::get('/invoices/{id}/download', [BillingController::class, 'downloadInvoice']);
Route::get('/usage', [BillingController::class, 'usage']);
Route::get('/limits', [BillingController::class, 'limits']);
Route::post('/payment-method', [BillingController::class, 'updatePaymentMethod']);
Route::get('/receipts', [BillingController::class, 'receipts']);
