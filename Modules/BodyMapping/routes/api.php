<?php

use Illuminate\Support\Facades\Route;
use Modules\BodyMapping\Http\Controllers\BodyMappingController;

Route::post('/generate', [BodyMappingController::class, 'generate']);
Route::post('/from-image', [BodyMappingController::class, 'fromImage']);
Route::post('/face-reconstruction', [BodyMappingController::class, 'faceReconstruction']);
Route::post('/pose', [BodyMappingController::class, 'setPose']);
Route::post('/animate', [BodyMappingController::class, 'animate']);
Route::post('/texture', [BodyMappingController::class, 'applyTexture']);
Route::get('/presets', [BodyMappingController::class, 'presets']);
Route::get('/formats', [BodyMappingController::class, 'formats']);
Route::get('/{id}', [BodyMappingController::class, 'show']);
Route::get('/{id}/preview', [BodyMappingController::class, 'preview']);
Route::delete('/{id}', [BodyMappingController::class, 'destroy']);
