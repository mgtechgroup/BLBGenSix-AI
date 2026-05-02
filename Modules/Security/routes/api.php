<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\SecurityController;

Route::get('/devices', [SecurityController::class, 'myDevices']);
Route::delete('/devices/{id}', [SecurityController::class, 'removeDevice']);
Route::post('/devices/trust', [SecurityController::class, 'trustDevice']);
Route::get('/verification', [SecurityController::class, 'verificationStatus']);
Route::get('/session', [SecurityController::class, 'sessionInfo']);
Route::get('/audit-log', [SecurityController::class, 'auditLog']);
