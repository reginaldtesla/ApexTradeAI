<?php

namespace App\Console\Commands;

use App\Models\BotSettings;
use App\Models\BotState;
use App\Models\BotTrade;
use App\Services\Binance\BinanceClient;
use App\Services\Binance\BinanceClientException;
use App\Services\Trading\MoneyManager;
use App\Services\Trading\MovingAverageSignal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunTradingBotCommand extends Command
{
    protected $signature = 'trading:run {--force : Ignore the MA signal and force an entry if not already in a position (demo/testing only)}';

    protected $description = 'Run one cycle of the spot trading bot: check signal, manage the open position, enforce kill switches.';

    public function handle(BinanceClient $client, MoneyManager $money): int
    {
        $settings = BotSettings::effective();
        $symbol = $settings['symbol'];

        $state = BotState::firstOrCreate(
            ['symbol' => $symbol],
            [
                'active_balance' => 0,
                'reserve_balance' => 0,
                'in_position' => false,
                'total_trades' => 0,
                'status' => BotState::STATUS_ACTIVE,
            ]
        );

        $money->initialiseIfNeeded($state, (float) config('binance.initial_capital'));

        if (! $state->isActive()) {
            $message = "Bot is stopped ({$state->status}). Total balance: {$state->totalBalance()}. Not trading.";
            $this->warn($message);
            Log::channel('trading')->warning($message, ['symbol' => $symbol, 'status' => $state->status]);

            return self::SUCCESS;
        }

        try {
            $closes = array_map(
                static fn (array $k) => (float) $k[4],
                $client->klines($symbol, $settings['interval'], $settings['ma_long'] + 5)
            );
            $currentPrice = $client->currentPrice($symbol);
        } catch (BinanceClientException $e) {
            $this->error("Binance API error, skipping this cycle: {$e->getMessage()}");
            Log::channel('trading')->error('trading:run skipped cycle due to API error', ['error' => $e->getMessage()]);
            $state->update(['last_run_at' => now()]);

            return self::FAILURE;
        }

        $signal = MovingAverageSignal::signal($closes, $settings['ma_short'], $settings['ma_long']);

        if ($state->in_position) {
            $this->manageOpenPosition($client, $money, $state, $settings, $currentPrice, $signal);
        } else {
            if ($this->option('force')) {
                $this->warn('--force supplied: overriding signal to BULLISH for a forced demo entry.');
                $signal = MovingAverageSignal::BULLISH;
            }
            $this->maybeOpenPosition($client, $money, $state, $symbol, $signal, $currentPrice);
        }

        $state->update(['last_run_at' => now()]);

        return self::SUCCESS;
    }

    private function maybeOpenPosition(
        BinanceClient $client,
        MoneyManager $money,
        BotState $state,
        string $symbol,
        string $signal,
        float $currentPrice,
    ): void {
        if ($signal !== MovingAverageSignal::BULLISH) {
            $this->info("No entry signal ({$signal}). Holding {$state->active_balance} USDT in reserve/active, not trading.");
            Log::channel('trading')->debug('No entry signal', ['symbol' => $symbol, 'signal' => $signal]);

            return;
        }

        if (! $money->canOpenPosition($state)) {
            $message = 'Bullish signal but active balance is below the minimum notional or bot is not active — skipping entry.';
            $this->warn($message);
            Log::channel('trading')->warning($message, ['symbol' => $symbol, 'active_balance' => (float) $state->active_balance]);

            return;
        }

        $stake = $money->stakeForNextTrade($state);
        $order = $client->marketBuyByQuoteAmount($symbol, $stake);

        $executedQty = (float) $order['executedQty'];
        $spent = (float) ($order['cummulativeQuoteQty'] ?? $stake);
        $entryPrice = $executedQty > 0 ? $spent / $executedQty : $currentPrice;

        $state->update([
            'in_position' => true,
            'position_qty' => $executedQty,
            'position_entry_price' => $entryPrice,
            'position_stake' => $spent,
        ]);

        BotTrade::create([
            'bot_state_id' => $state->id,
            'symbol' => $symbol,
            'stake' => $spent,
            'qty' => $executedQty,
            'entry_price' => $entryPrice,
            'opened_at' => now(),
        ]);

        $this->info("Opened position: {$executedQty} {$symbol} at avg {$entryPrice} (stake {$spent} USDT).");
        Log::channel('trading')->info('Position opened', [
            'symbol' => $symbol,
            'qty' => $executedQty,
            'entry_price' => $entryPrice,
            'stake' => $spent,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function manageOpenPosition(
        BinanceClient $client,
        MoneyManager $money,
        BotState $state,
        array $settings,
        float $currentPrice,
        string $signal,
    ): void {
        $entryPrice = (float) $state->position_entry_price;
        $pnlPct = ($currentPrice - $entryPrice) / $entryPrice;

        $reason = match (true) {
            $pnlPct >= (float) $settings['take_profit_pct'] => 'take_profit',
            $pnlPct <= -(float) $settings['stop_loss_pct'] => 'stop_loss',
            $signal === MovingAverageSignal::BEARISH => 'signal_exit',
            default => null,
        };

        if ($reason === null) {
            $this->info(sprintf('Holding position, pnl %.2f%%.', $pnlPct * 100));
            Log::channel('trading')->debug('Holding position', ['pnl_pct' => round($pnlPct * 100, 4)]);

            return;
        }

        $order = $client->marketSellByQuantity($settings['symbol'], (float) $state->position_qty);
        $proceeds = (float) ($order['cummulativeQuoteQty'] ?? ($currentPrice * (float) $state->position_qty));
        $pnl = $proceeds - (float) $state->position_stake;
        $isWin = $pnl > 0;

        $trade = BotTrade::where('bot_state_id', $state->id)->whereNull('closed_at')->latest('id')->first();
        $trade?->update([
            'exit_price' => $currentPrice,
            'proceeds' => $proceeds,
            'pnl' => $pnl,
            'result' => $isWin ? 'win' : 'loss',
            'close_reason' => $reason,
            'closed_at' => now(),
        ]);

        $money->settleTrade($state, $proceeds, $isWin);

        $summary = sprintf(
            'Closed position (%s): pnl %.4f USDT (%s). New active=%.4f reserve=%.4f status=%s',
            $reason,
            $pnl,
            $isWin ? 'win' : 'loss',
            (float) $state->active_balance,
            (float) $state->reserve_balance,
            $state->status,
        );
        $this->info($summary);
        Log::channel('trading')->info('Position closed', [
            'reason' => $reason,
            'pnl' => $pnl,
            'result' => $isWin ? 'win' : 'loss',
            'active_balance' => (float) $state->active_balance,
            'reserve_balance' => (float) $state->reserve_balance,
            'total_trades' => $state->total_trades,
            'status' => $state->status,
        ]);

        if (! $state->isActive()) {
            $message = "Kill switch triggered: {$state->status}. Bot will not open new trades.";
            $this->warn($message);
            Log::channel('trading')->warning($message, ['status' => $state->status]);
        }
    }
}
