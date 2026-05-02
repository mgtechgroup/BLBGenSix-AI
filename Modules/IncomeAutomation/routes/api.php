<?php

use Illuminate\Support\Facades\Route;
use Modules\IncomeAutomation\Http\Controllers\IncomeAutomationController;

Route::get('/dashboard', [IncomeAutomationController::class, 'dashboard']);
Route::get('/revenue', [IncomeAutomationController::class, 'revenue']);
Route::post('/platforms/connect', [IncomeAutomationController::class, 'connectPlatform']);
Route::post('/platforms/disconnect', [IncomeAutomationController::class, 'disconnectPlatform']);
Route::get('/platforms', [IncomeAutomationController::class, 'platforms']);
Route::post('/schedule', [IncomeAutomationController::class, 'schedule']);
Route::get('/schedule', [IncomeAutomationController::class, 'getSchedule']);
Route::post('/autopost', [IncomeAutomationController::class, 'autoPost']);
Route::post('/autopost/toggle', [IncomeAutomationController::class, 'toggleAutoPost']);
Route::get('/analytics', [IncomeAutomationController::class, 'analytics']);
Route::get('/payouts', [IncomeAutomationController::class, 'payouts']);
Route::post('/pricing/optimize', [IncomeAutomationController::class, 'optimizePricing']);
Route::get('/streams', [IncomeAutomationController::class, 'streams']);
Route::post('/streams/create', [IncomeAutomationController::class, 'createStream']);
Route::delete('/streams/{id}', [IncomeAutomationController::class, 'deleteStream']);
