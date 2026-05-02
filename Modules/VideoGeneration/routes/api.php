<?php

use Illuminate\Support\Facades\Route;
use Modules\VideoGeneration\Http\Controllers\VideoGenerationController;

Route::post('/generate', [VideoGenerationController::class, 'generate']);
Route::post('/storyboard', [VideoGenerationController::class, 'fromStoryboard']);
Route::post('/script', [VideoGenerationController::class, 'fromScript']);
Route::post('/edit', [VideoGenerationController::class, 'edit']);
Route::post('/extend', [VideoGenerationController::class, 'extend']);
Route::post('/upscale', [VideoGenerationController::class, 'upscale']);
Route::get('/formats', [VideoGenerationController::class, 'formats']);
Route::get('/history', [VideoGenerationController::class, 'history']);
Route::get('/{id}', [VideoGenerationController::class, 'show']);
