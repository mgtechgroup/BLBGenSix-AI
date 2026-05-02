<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AdminController;

Route::get('/dashboard', [AdminController::class, 'dashboard']);
Route::get('/users', [AdminController::class, 'users']);
Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
Route::post('/users/{id}/unban', [AdminController::class, 'unbanUser']);
Route::get('/verifications', [AdminController::class, 'verifications']);
Route::post('/verifications/{id}/approve', [AdminController::class, 'approveVerification']);
Route::post('/verifications/{id}/reject', [AdminController::class, 'rejectVerification']);
Route::get('/health', [AdminController::class, 'systemHealth']);
