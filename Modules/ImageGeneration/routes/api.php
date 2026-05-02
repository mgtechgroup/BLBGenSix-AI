<?php

use Illuminate\Support\Facades\Route;
use Modules\ImageGeneration\Http\Controllers\ImageGenerationController;

Route::post('/generate', [ImageGenerationController::class, 'generate']);
Route::post('/batch', [ImageGenerationController::class, 'batchGenerate']);
Route::post('/img2img', [ImageGenerationController::class, 'imageToImage']);
Route::post('/upscale', [ImageGenerationController::class, 'upscale']);
Route::post('/inpaint', [ImageGenerationController::class, 'inpaint']);
Route::post('/variation', [ImageGenerationController::class, 'variation']);
Route::get('/styles', [ImageGenerationController::class, 'styles']);
Route::get('/models', [ImageGenerationController::class, 'models']);
Route::get('/history', [ImageGenerationController::class, 'history']);
Route::get('/{id}', [ImageGenerationController::class, 'show']);
Route::delete('/{id}', [ImageGenerationController::class, 'destroy']);
