<?php

namespace App\Http\Controllers;

use App\Models\BotSettings;
use App\Models\BotState;
use App\Services\Binance\BinanceClient;
use App\Services\Binance\BinanceClientException;
use App\Services\Trading\MovingAverageSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BotStatusController extends Controller
{
    public function index()
    {
        $settings = BotSettings::effective();
        $state = BotState::where('symbol', $settings['symbol'])->first();
        $trades = $state
            ? $state->trades()->latest('id')->limit(50)->get()
            : collect();

        return view('bot.status', [
            'state' => $state,
            'trades' => $trades,
            'settings' => $settings,
            'summary' => $this->pnlSummary($state),
        ]);
    }

    /**
     * @return array{realized_pnl: float, wins: int, losses: int, win_rate: ?float, best_trade: ?float, worst_trade: ?float, return_pct: ?float, initial_capital: float}
     */
    private function pnlSummary(?BotState $state): array
    {
        $initialCapital = (float) config('binance.initial_capital');

        $closed = $state
            ? $state->trades()->whereIn('result', ['win', 'loss'])->get()
            : collect();

        $wins = $closed->where('result', 'win')->count();
        $losses = $closed->where('result', 'loss')->count();
        $realizedPnl = (float) $closed->sum(fn ($t) => (float) $t->pnl);

        return [
            'realized_pnl' => $realizedPnl,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => ($wins + $losses) > 0 ? $wins / ($wins + $losses) * 100 : null,
            'best_trade' => $closed->isNotEmpty() ? (float) $closed->max(fn ($t) => (float) $t->pnl) : null,
            'worst_trade' => $closed->isNotEmpty() ? (float) $closed->min(fn ($t) => (float) $t->pnl) : null,
            'return_pct' => $state && $initialCapital > 0 ? (($state->totalBalance() - $initialCapital) / $initialCapital) * 100 : null,
            'initial_capital' => $initialCapital,
        ];
    }

    /**
     * Lightweight JSON snapshot of "what the bot sees right now": live price,
     * moving averages, the signal it would act on, current state/balances,
     * recent trades, and a tail of its own activity log. Polled from the
     * dashboard for a live-updating preview without a full page reload.
     */
    public function live(BinanceClient $client): JsonResponse
    {
        $settings = BotSettings::effective();
        $symbol = $settings['symbol'];
        $state = BotState::where('symbol', $symbol)->first();

        $market = Cache::remember("bot-live-market:{$symbol}:{$settings['interval']}", 5, function () use ($client, $settings, $symbol) {
            try {
                $closes = array_map(
                    static fn (array $k) => (float) $k[4],
                    $client->klines($symbol, $settings['interval'], $settings['ma_long'] + 5)
                );

                return [
                    'price' => $client->currentPrice($symbol),
                    'averages' => MovingAverageSignal::currentAverages($closes, $settings['ma_short'], $settings['ma_long']),
                    'signal' => MovingAverageSignal::signal($closes, $settings['ma_short'], $settings['ma_long']),
                    'error' => null,
                ];
            } catch (BinanceClientException $e) {
                return ['price' => null, 'averages' => ['short' => null, 'long' => null], 'signal' => null, 'error' => $e->getMessage()];
            }
        });

        $pnlPct = null;
        if ($state?->in_position && $market['price'] && (float) $state->position_entry_price > 0) {
            $pnlPct = ($market['price'] - (float) $state->position_entry_price) / (float) $state->position_entry_price;
        }

        return response()->json([
            'symbol' => $symbol,
            'interval' => $settings['interval'],
            'market' => [
                'price' => $market['price'],
                'ma_short' => $market['averages']['short'],
                'ma_long' => $market['averages']['long'],
                'ma_short_period' => $settings['ma_short'],
                'ma_long_period' => $settings['ma_long'],
                'signal' => $market['signal'],
                'error' => $market['error'],
            ],
            'state' => $state ? [
                'status' => $state->status,
                'active' => $state->isActive(),
                'active_balance' => (float) $state->active_balance,
                'reserve_balance' => (float) $state->reserve_balance,
                'total_balance' => $state->totalBalance(),
                'total_trades' => $state->total_trades,
                'max_trades' => $settings['max_trades'],
                'in_position' => (bool) $state->in_position,
                'position_qty' => $state->position_qty !== null ? (float) $state->position_qty : null,
                'position_entry_price' => $state->position_entry_price !== null ? (float) $state->position_entry_price : null,
                'pnl_pct' => $pnlPct,
                'take_profit_pct' => (float) $settings['take_profit_pct'],
                'stop_loss_pct' => (float) $settings['stop_loss_pct'],
                'last_run_at' => $state->last_run_at?->toIso8601String(),
                'last_run_human' => $state->last_run_at?->diffForHumans(),
            ] : null,
            'trades' => $state ? $state->trades()->latest('id')->limit(10)->get()->map(fn ($t) => [
                'opened_at' => $t->opened_at?->format('Y-m-d H:i'),
                'stake' => (float) $t->stake,
                'entry_price' => (float) $t->entry_price,
                'exit_price' => $t->exit_price !== null ? (float) $t->exit_price : null,
                'pnl' => $t->pnl !== null ? (float) $t->pnl : null,
                'result' => $t->result,
                'close_reason' => $t->close_reason,
            ]) : [],
            'summary' => $this->pnlSummary($state),
            'log_tail' => $this->tailTradingLog(20),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * OHLC candles plus rolling MA9/MA21 overlays, for the candlestick chart
     * on the dashboard. Polled less often than live() since kline history
     * changes far less frequently than the ticking price/signal.
     */
    public function candles(BinanceClient $client): JsonResponse
    {
        $settings = BotSettings::effective();
        $symbol = $settings['symbol'];
        $limit = max(100, $settings['ma_long'] + 30);

        $payload = Cache::remember("bot-candles:{$symbol}:{$settings['interval']}:{$limit}", 10, function () use ($client, $settings, $symbol, $limit) {
            try {
                $klines = $client->klines($symbol, $settings['interval'], $limit);
            } catch (BinanceClientException $e) {
                return ['candles' => [], 'ma_short' => [], 'ma_long' => [], 'error' => $e->getMessage()];
            }

            $candles = array_map(static fn (array $k) => [
                'time' => (int) round($k[0] / 1000),
                'open' => (float) $k[1],
                'high' => (float) $k[2],
                'low' => (float) $k[3],
                'close' => (float) $k[4],
            ], $klines);

            $closes = array_map(static fn (array $c) => $c['close'], $candles);

            return [
                'candles' => $candles,
                'ma_short' => $this->rollingAverageSeries($candles, $closes, $settings['ma_short']),
                'ma_long' => $this->rollingAverageSeries($candles, $closes, $settings['ma_long']),
                'error' => null,
            ];
        });

        return response()->json([
            'symbol' => $symbol,
            'interval' => $settings['interval'],
            'ma_short_period' => $settings['ma_short'],
            'ma_long_period' => $settings['ma_long'],
            ...$payload,
        ]);
    }

    /**
     * @param  array<int, array{time: int, close: float}>  $candles
     * @param  array<int, float>  $closes
     * @return array<int, array{time: int, value: float}>
     */
    private function rollingAverageSeries(array $candles, array $closes, int $period): array
    {
        $series = [];
        $count = count($closes);

        for ($i = $period - 1; $i < $count; $i++) {
            $window = array_slice($closes, $i - $period + 1, $period);
            $series[] = [
                'time' => $candles[$i]['time'],
                'value' => array_sum($window) / $period,
            ];
        }

        return $series;
    }

    /**
     * @return array<int, string>
     */
    private function tailTradingLog(int $lines): array
    {
        $path = storage_path('logs/trading-bot-'.now()->format('Y-m-d').'.log');

        if (! is_file($path)) {
            return [];
        }

        $content = @file_get_contents($path, false, null, max(0, filesize($path) - 50_000));
        if ($content === false) {
            return [];
        }

        $all = preg_split('/\r?\n/', trim($content)) ?: [];

        return array_slice($all, -$lines);
    }
}
