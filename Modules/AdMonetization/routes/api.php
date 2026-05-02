<?php

use Illuminate\Support\Facades\Route;
use Modules\AdMonetization\Http\Controllers\AdMonetizationController;

Route::get('/spaces', [AdMonetizationController::class, 'availableSpaces']);
Route::post('/book', [AdMonetizationController::class, 'bookSpace']);
Route::get('/my-campaigns', [AdMonetizationController::class, 'myCampaigns']);
Route::get('/revenue', [AdMonetizationController::class, 'revenue']);
Route::post('/report-impression', [AdMonetizationController::class, 'reportImpression']);
Route::post('/report-click', [AdMonetizationController::class, 'reportClick']);
