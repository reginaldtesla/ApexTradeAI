<?php

namespace App\Services\Trading;

use App\Models\BotState;

/**
 * Implements the position-sizing rule chosen for this bot:
 *
 *   - Start: split capital into an "active" stake and a "reserve" that is
 *     never directly re-risked.
 *   - Every trade risks exactly the current active_balance (all-in on the
 *     stake, never the reserve).
 *   - On a WIN: redistribute so the next stake is 50% of the new total
 *     (reserve + proceeds); the other 50% moves into reserve.
 *   - On a LOSS: reserve is untouched; whatever remains from the stake
 *     becomes the new active_balance (no top-up from reserve).
 *
 * This is a money-management rule, not a trading strategy — it does not
 * decide when to buy or sell, only how much to risk each time.
 */
class MoneyManager
{
    /**
     * Binance rejects market orders below its exchange-wide NOTIONAL filter
     * (currently $5 on BTCUSDT). Trading with a stake exactly at that floor
     * leaves zero room: a fractional-percent adverse price move drops the
     * position's live value below $5 and Binance will refuse the exit order,
     * leaving the bot stuck holding a position it cannot sell. This buffer
     * requires a stake meaningfully above the floor before entering, so a
     * normal price wiggle can't strand the position.
     */
    private const MIN_NOTIONAL_SAFETY_BUFFER = 1.15;

    public function __construct(
        private readonly float $reserveRatio,
        private readonly float $floorBalance,
        private readonly int $maxTrades,
        private readonly float $minNotional,
    ) {}

    public function initialiseIfNeeded(BotState $state, float $initialCapital): void
    {
        if ((float) $state->active_balance === 0.0 && (float) $state->reserve_balance === 0.0 && (int) $state->total_trades === 0) {
            $state->active_balance = $initialCapital * (1 - $this->reserveRatio);
            $state->reserve_balance = $initialCapital * $this->reserveRatio;
            $state->status = BotState::STATUS_ACTIVE;
            $state->save();
        }
    }

    public function canOpenPosition(BotState $state): bool
    {
        return $state->isActive()
            && ! $state->in_position
            && (float) $state->active_balance >= $this->safeMinNotional();
    }

    private function safeMinNotional(): float
    {
        return $this->minNotional * self::MIN_NOTIONAL_SAFETY_BUFFER;
    }

    public function stakeForNextTrade(BotState $state): float
    {
        return (float) $state->active_balance;
    }

    /**
     * Apply the win/loss redistribution rule after a trade closes, then
     * re-check the kill switches. Persists the state.
     */
    public function settleTrade(BotState $state, float $proceeds, bool $isWin): void
    {
        $newTotal = (float) $state->reserve_balance + $proceeds;

        if ($isWin) {
            $state->active_balance = $newTotal * 0.5;
            $state->reserve_balance = $newTotal * 0.5;
        } else {
            $state->active_balance = $proceeds;
            // reserve_balance untouched on a loss.
        }

        $state->in_position = false;
        $state->position_qty = null;
        $state->position_entry_price = null;
        $state->position_stake = null;
        $state->total_trades++;

        $state->status = $this->evaluateKillSwitches($state);
        $state->save();
    }

    private function evaluateKillSwitches(BotState $state): string
    {
        $total = (float) $state->active_balance + (float) $state->reserve_balance;

        if ($total <= $this->floorBalance) {
            return BotState::STATUS_STOPPED_FLOOR;
        }

        if ($this->maxTrades > 0 && $state->total_trades >= $this->maxTrades) {
            return BotState::STATUS_STOPPED_MAX_TRADES;
        }

        if ((float) $state->active_balance < $this->safeMinNotional()) {
            return BotState::STATUS_STOPPED_MIN_NOTIONAL;
        }

        return BotState::STATUS_ACTIVE;
    }
}
