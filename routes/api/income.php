<?php

use Illuminate\Support\Facades\Route;
use Modules\IncomeAutomation\Http\Controllers\IncomeAutomationController;

Route::get('/dashboard', [IncomeAutomationController::class, 'dashboard'])->name('income.dashboard');
Route::get('/revenue', [IncomeAutomationController::class, 'revenue'])->name('income.revenue');
Route::post('/platforms/connect', [IncomeAutomationController::class, 'connectPlatform'])->name('income.connect');
Route::post('/platforms/disconnect', [IncomeAutomationController::class, 'disconnectPlatform'])->name('income.disconnect');
Route::get('/platforms', [IncomeAutomationController::class, 'platforms'])->name('income.platforms');
Route::post('/schedule', [IncomeAutomationController::class, 'schedule'])->name('income.schedule');
Route::get('/schedule', [IncomeAutomationController::class, 'getSchedule'])->name('income.schedule.get');
Route::post('/autopost', [IncomeAutomationController::class, 'autoPost'])->name('income.autopost');
Route::post('/autopost/toggle', [IncomeAutomationController::class, 'toggleAutoPost'])->name('income.autopost.toggle');
Route::get('/analytics', [IncomeAutomationController::class, 'analytics'])->name('income.analytics');
Route::get('/payouts', [IncomeAutomationController::class, 'payouts'])->name('income.payouts');
Route::post('/pricing/optimize', [IncomeAutomationController::class, 'optimizePricing'])->name('income.pricing.optimize');
Route::get('/streams', [IncomeAutomationController::class, 'streams'])->name('income.streams');
Route::post('/streams/create', [IncomeAutomationController::class, 'createStream'])->name('income.streams.create');
Route::delete('/streams/{id}', [IncomeAutomationController::class, 'deleteStream'])->name('income.streams.delete');
