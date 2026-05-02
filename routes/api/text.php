<?php

use Illuminate\Support\Facades\Route;
use Modules\TextGeneration\Http\Controllers\TextGenerationController;

Route::post('/generate', [TextGenerationController::class, 'generate'])->name('text.generate');
Route::post('/novel', [TextGenerationController::class, 'generateNovel'])->name('text.novel');
Route::post('/novel/outline', [TextGenerationController::class, 'novelOutline'])->name('text.novel.outline');
Route::post('/storyboard', [TextGenerationController::class, 'generateStoryboard'])->name('text.storyboard');
Route::post('/script', [TextGenerationController::class, 'generateScript'])->name('text.script');
Route::post('/character', [TextGenerationController::class, 'characterSheet'])->name('text.character');
Route::post('/worldbuild', [TextGenerationController::class, 'worldBuilding'])->name('text.worldbuild');
Route::post('/continue', [TextGenerationController::class, 'continue'])->name('text.continue');
Route::get('/genres', [TextGenerationController::class, 'genres'])->name('text.genres');
Route::get('/templates', [TextGenerationController::class, 'templates'])->name('text.templates');
Route::get('/history', [TextGenerationController::class, 'history'])->name('text.history');
Route::get('/{id}', [TextGenerationController::class, 'show'])->name('text.show');
Route::put('/{id}', [TextGenerationController::class, 'update'])->name('text.update');
Route::delete('/{id}', [TextGenerationController::class, 'destroy'])->name('text.destroy');
