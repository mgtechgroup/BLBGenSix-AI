<?php

use Illuminate\Support\Facades\Route;
use Modules\MultiRevenue\Http\Controllers\MultiRevenueController;

Route::get('/streams', [MultiRevenueController::class, 'revenueStreams']);
Route::post('/tip', [MultiRevenueController::class, 'sendTip']);
Route::post('/ppv', [MultiRevenueController::class, 'createPPVContent']);
Route::post('/bundle', [MultiRevenueController::class, 'createBundle']);
Route::get('/affiliate', [MultiRevenueController::class, 'affiliateLink']);
Route::post('/nft', [MultiRevenueController::class, 'createNFT']);
