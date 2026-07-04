<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row table of overrides on top of config/binance.php. Any column
 * left null falls back to the .env/config default. Read via
 * BotSettings::effective(), which is what trading:run and the settings page
 * both use so they can never disagree about the current rules.
 */
class BotSettings extends Model
{
    protected $fillable = [
        'symbol',
        'interval',
        'ma_short',
        'ma_long',
        'take_profit_pct',
        'stop_loss_pct',
        'reserve_ratio',
        'floor_balance',
        'max_trades',
        'min_notional',
    ];

    public const KEYS = [
        'symbol',
        'interval',
        'ma_short',
        'ma_long',
        'take_profit_pct',
        'stop_loss_pct',
        'reserve_ratio',
        'floor_balance',
        'max_trades',
        'min_notional',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], []);
    }

    /**
     * Merge stored overrides (non-null) with config/binance.php defaults.
     * initial_capital is deliberately excluded — it is only meaningful the
     * very first time the bot ever initialises its balances, so it stays a
     * one-time .env value rather than something editable mid-flight.
     *
     * @return array<string, mixed>
     */
    public static function effective(): array
    {
        $overrides = self::current();
        $defaults = config('binance');

        $result = [];
        foreach (self::KEYS as $key) {
            $result[$key] = $overrides->{$key} ?? $defaults[$key];
        }

        return $result;
    }

    /**
     * Which keys currently have an active override (for display purposes).
     *
     * @return array<int, string>
     */
    public static function overriddenKeys(): array
    {
        $overrides = self::current();

        return array_values(array_filter(self::KEYS, fn ($key) => $overrides->{$key} !== null));
    }
}
