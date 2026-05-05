<?php

use App\Http\Controllers\Webhook\MusicWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/music', [MusicWebhookController::class, 'handle'])->name('webhooks.music');

Route::middleware(['auth'])->prefix('music')->name('music.')->group(function () {
    Route::get('/dashboard', function () {
        return inertia('Customer/MusicDashboard');
    })->name('dashboard');

    Route::get('/connect', function () {
        return inertia('Customer/MusicConnect');
    })->name('connect');
});
