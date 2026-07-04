<?php

use App\Http\Controllers\BotSettingsController;
use App\Http\Controllers\BotStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('dashboard.auth')->group(function () {
    Route::get('/', [BotStatusController::class, 'index'])->name('home');
    Route::get('/bot', [BotStatusController::class, 'index'])->name('bot.status');
    Route::get('/bot/live', [BotStatusController::class, 'live'])->name('bot.live');
    Route::get('/bot/candles', [BotStatusController::class, 'candles'])->name('bot.candles');
    Route::get('/settings', [BotSettingsController::class, 'index'])->name('bot.settings');
    Route::post('/settings', [BotSettingsController::class, 'update'])->name('bot.settings.update');
});
