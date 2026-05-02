<?php

use Illuminate\Support\Facades\Route;
use Modules\SaaS\Http\Controllers\BillingController;

Route::get('/plans', [BillingController::class, 'plans'])->name('billing.plans');
Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
Route::post('/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
Route::post('/resume', [BillingController::class, 'resume'])->name('billing.resume');
Route::post('/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');
Route::post('/downgrade', [BillingController::class, 'downgrade'])->name('billing.downgrade');
Route::get('/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
Route::get('/invoices/{id}/download', [BillingController::class, 'downloadInvoice'])->name('billing.invoice.download');
Route::get('/usage', [BillingController::class, 'usage'])->name('billing.usage');
Route::get('/limits', [BillingController::class, 'limits'])->name('billing.limits');
Route::post('/payment-method', [BillingController::class, 'updatePaymentMethod'])->name('billing.payment.update');
Route::get('/receipts', [BillingController::class, 'receipts'])->name('billing.receipts');
