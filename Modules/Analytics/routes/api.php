<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::get('/overview', [AnalyticsController::class, 'overview']);
Route::get('/generations', [AnalyticsController::class, 'generations']);
Route::get('/revenue', [AnalyticsController::class, 'revenue']);
Route::get('/engagement', [AnalyticsController::class, 'engagement']);
Route::get('/platforms', [AnalyticsController::class, 'platformPerformance']);
Route::get('/content', [AnalyticsController::class, 'contentPerformance']);
Route::get('/audience', [AnalyticsController::class, 'audience']);
Route::get('/trends', [AnalyticsController::class, 'trends']);
Route::get('/export', [AnalyticsController::class, 'export']);
Route::get('/realtime', [AnalyticsController::class, 'realtime']);
