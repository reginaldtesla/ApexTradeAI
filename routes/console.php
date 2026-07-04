<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trading bot cycle. Matches the candle interval configured in config/binance.php
// (BOT_INTERVAL). Requires something to actually invoke the scheduler — see
// TRADING_BOT.md for how to run this on Windows since there is no cron here.
// Not using ->runInBackground(): on Windows that spawns a separate process,
// which flashes a visible console window. These commands run in a few
// seconds, so running inline (blocking the scheduler for that moment) is
// fine and avoids the extra window.
Schedule::command('trading:run')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Heartbeat: proves the scheduler is actually alive even when the bot is just
// holding, and screams in the log if trading:run hasn't fired recently.
Schedule::command('trading:summary')
    ->hourly();
