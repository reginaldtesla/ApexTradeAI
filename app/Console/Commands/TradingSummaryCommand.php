<?php

namespace App\Console\Commands;

use App\Models\BotSettings;
use App\Models\BotState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Heartbeat/summary check, meant to run independently of trading:run so you
 * have a record even during long stretches where the bot is just holding.
 * Also the main defence against the "set and forget" trap: if nobody has
 * actually wired up a scheduler, this is what will tell you.
 */
class TradingSummaryCommand extends Command
{
    protected $signature = 'trading:summary';

    protected $description = 'Log a heartbeat summary of the bot state; flags if the scheduler appears to have stopped running.';

    public function handle(): int
    {
        $symbol = BotSettings::effective()['symbol'];
        $state = BotState::where('symbol', $symbol)->first();

        if (! $state) {
            $message = "No bot state found for {$symbol} yet — trading:run has never completed a cycle.";
            $this->warn($message);
            Log::channel('trading')->warning($message);

            return self::SUCCESS;
        }

        $expectedIntervalMinutes = 5;
        $staleAfterMinutes = $expectedIntervalMinutes * 3;
        $minutesSinceLastRun = $state->last_run_at ? round($state->last_run_at->diffInSeconds(now()) / 60, 1) : null;
        $isStale = $minutesSinceLastRun === null || $minutesSinceLastRun > $staleAfterMinutes;

        $context = [
            'symbol' => $symbol,
            'status' => $state->status,
            'active_balance' => (float) $state->active_balance,
            'reserve_balance' => (float) $state->reserve_balance,
            'total_balance' => $state->totalBalance(),
            'in_position' => $state->in_position,
            'total_trades' => $state->total_trades,
            'last_run_at' => $state->last_run_at?->toDateTimeString() ?? 'never',
            'minutes_since_last_run' => $minutesSinceLastRun,
        ];

        $summary = sprintf(
            '[heartbeat] %s status=%s total=%.4f active=%.4f reserve=%.4f trades=%d in_position=%s last_run=%s',
            $symbol,
            $state->status,
            $state->totalBalance(),
            (float) $state->active_balance,
            (float) $state->reserve_balance,
            $state->total_trades,
            $state->in_position ? 'yes' : 'no',
            $context['last_run_at'],
        );

        $this->line($summary);

        if ($isStale) {
            $staleMessage = "Scheduler appears stopped: last cycle was {$context['minutes_since_last_run']} minutes ago (expected every {$expectedIntervalMinutes}). Nothing may be invoking trading:run.";
            $this->error($staleMessage);
            Log::channel('trading')->warning($staleMessage, $context);

            return self::FAILURE;
        }

        if (! $state->isActive()) {
            Log::channel('trading')->warning($summary.' (bot stopped by kill switch)', $context);

            return self::SUCCESS;
        }

        Log::channel('trading')->info($summary, $context);

        return self::SUCCESS;
    }
}
