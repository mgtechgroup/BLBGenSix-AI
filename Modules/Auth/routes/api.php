<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\BiometricAuthController;

Route::post('/register', [BiometricAuthController::class, 'store']);
Route::post('/login', [BiometricAuthController::class, 'authenticate']);
Route::post('/biometric/register', [BiometricAuthController::class, 'registerBiometric']);
Route::get('/webauthn/options', fn() => response()->json(\Asbiin\LaravelWebauthn\Facades\Webauthn::authenticateOptions()));
