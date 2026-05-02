<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\AnalyticsController;

Route::get('/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
Route::get('/generations', [AnalyticsController::class, 'generations'])->name('analytics.generations');
Route::get('/revenue', [AnalyticsController::class, 'revenue'])->name('analytics.revenue');
Route::get('/engagement', [AnalyticsController::class, 'engagement'])->name('analytics.engagement');
Route::get('/platforms', [AnalyticsController::class, 'platformPerformance'])->name('analytics.platforms');
Route::get('/content', [AnalyticsController::class, 'contentPerformance'])->name('analytics.content');
Route::get('/audience', [AnalyticsController::class, 'audience'])->name('analytics.audience');
Route::get('/trends', [AnalyticsController::class, 'trends'])->name('analytics.trends');
Route::get('/export', [AnalyticsController::class, 'export'])->name('analytics.export');
Route::get('/realtime', [AnalyticsController::class, 'realtime'])->name('analytics.realtime');
