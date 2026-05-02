<?php

use Illuminate\Support\Facades\Route;
use Modules\TextGeneration\Http\Controllers\TextGenerationController;

Route::post('/generate', [TextGenerationController::class, 'generate']);
Route::post('/novel', [TextGenerationController::class, 'generateNovel']);
Route::post('/novel/outline', [TextGenerationController::class, 'novelOutline']);
Route::post('/storyboard', [TextGenerationController::class, 'generateStoryboard']);
Route::post('/script', [TextGenerationController::class, 'generateScript']);
Route::post('/character', [TextGenerationController::class, 'characterSheet']);
Route::post('/worldbuild', [TextGenerationController::class, 'worldBuilding']);
Route::post('/continue', [TextGenerationController::class, 'continue']);
Route::get('/genres', [TextGenerationController::class, 'genres']);
Route::get('/templates', [TextGenerationController::class, 'templates']);
Route::get('/history', [TextGenerationController::class, 'history']);
Route::get('/{id}', [TextGenerationController::class, 'show']);
Route::put('/{id}', [TextGenerationController::class, 'update']);
Route::delete('/{id}', [TextGenerationController::class, 'destroy']);
