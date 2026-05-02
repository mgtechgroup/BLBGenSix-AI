<?php

use Illuminate\Support\Facades\Route;
use Modules\Verification\Http\Controllers\VerificationController;

Route::get('/start', [VerificationController::class, 'start']);
Route::post('/upload-id', [VerificationController::class, 'uploadId']);
Route::post('/liveness', [VerificationController::class, 'livenessCheck']);
Route::post('/biometric-bind', [VerificationController::class, 'biometricBinding']);
Route::get('/status', [VerificationController::class, 'status']);
