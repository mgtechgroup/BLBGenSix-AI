<?php

use Illuminate\Support\Facades\Route;
use Modules\BodyMapping\Http\Controllers\BodyMappingController;

Route::post('/generate', [BodyMappingController::class, 'generate'])->name('body.generate');
Route::post('/from-image', [BodyMappingController::class, 'fromImage'])->name('body.fromImage');
Route::post('/face-reconstruction', [BodyMappingController::class, 'faceReconstruction'])->name('body.face');
Route::post('/pose', [BodyMappingController::class, 'setPose'])->name('body.pose');
Route::post('/animate', [BodyMappingController::class, 'animate'])->name('body.animate');
Route::post('/texture', [BodyMappingController::class, 'applyTexture'])->name('body.texture');
Route::get('/presets', [BodyMappingController::class, 'presets'])->name('body.presets');
Route::get('/formats', [BodyMappingController::class, 'formats'])->name('body.formats');
Route::get('/{id}', [BodyMappingController::class, 'show'])->name('body.show');
Route::get('/{id}/preview', [BodyMappingController::class, 'preview'])->name('body.preview');
Route::delete('/{id}', [BodyMappingController::class, 'destroy'])->name('body.destroy');
