<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MusicController;

Route::get('/stats', [MusicController::class, 'stats'])->name('api.music.stats');
Route::get('/recent', [MusicController::class, 'recent'])->name('api.music.recent');
Route::get('/top-artists', [MusicController::class, 'topArtists'])->name('api.music.top-artists');
Route::get('/top-tracks', [MusicController::class, 'topTracks'])->name('api.music.top-tracks');
Route::get('/sources', [MusicController::class, 'sources'])->name('api.music.sources');
Route::post('/connect', [MusicController::class, 'connect'])->name('api.music.connect');
