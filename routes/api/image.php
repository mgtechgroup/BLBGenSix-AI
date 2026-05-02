<?php

use Illuminate\Support\Facades\Route;
use Modules\ImageGeneration\Http\Controllers\ImageGenerationController;

Route::post('/generate', [ImageGenerationController::class, 'generate'])->name('image.generate');
Route::post('/generate/batch', [ImageGenerationController::class, 'batchGenerate'])->name('image.batch');
Route::post('/img2img', [ImageGenerationController::class, 'imageToImage'])->name('image.img2img');
Route::post('/upscale', [ImageGenerationController::class, 'upscale'])->name('image.upscale');
Route::post('/inpaint', [ImageGenerationController::class, 'inpaint'])->name('image.inpaint');
Route::post('/variation', [ImageGenerationController::class, 'variation'])->name('image.variation');
Route::get('/styles', [ImageGenerationController::class, 'styles'])->name('image.styles');
Route::get('/models', [ImageGenerationController::class, 'models'])->name('image.models');
Route::get('/history', [ImageGenerationController::class, 'history'])->name('image.history');
Route::get('/{id}', [ImageGenerationController::class, 'show'])->name('image.show');
Route::delete('/{id}', [ImageGenerationController::class, 'destroy'])->name('image.destroy');
