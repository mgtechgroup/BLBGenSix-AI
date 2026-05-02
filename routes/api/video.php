<?php

use Illuminate\Support\Facades\Route;
use Modules\VideoGeneration\Http\Controllers\VideoGenerationController;

Route::post('/generate', [VideoGenerationController::class, 'generate'])->name('video.generate');
Route::post('/generate/storyboard', [VideoGenerationController::class, 'fromStoryboard'])->name('video.storyboard');
Route::post('/generate/script', [VideoGenerationController::class, 'fromScript'])->name('video.script');
Route::post('/edit', [VideoGenerationController::class, 'edit'])->name('video.edit');
Route::post('/extend', [VideoGenerationController::class, 'extend'])->name('video.extend');
Route::post('/upscale', [VideoGenerationController::class, 'upscale'])->name('video.upscale');
Route::get('/formats', [VideoGenerationController::class, 'formats'])->name('video.formats');
Route::get('/history', [VideoGenerationController::class, 'history'])->name('video.history');
Route::get('/{id}', [VideoGenerationController::class, 'show'])->name('video.show');
