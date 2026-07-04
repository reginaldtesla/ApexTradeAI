<?php

namespace Tests\Unit;

use App\Models\BotState;
use App\Services\Trading\MoneyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeManager(array $overrides = []): MoneyManager
    {
        return new MoneyManager(
            reserveRatio: $overrides['reserveRatio'] ?? 0.5,
            floorBalance: $overrides['floorBalance'] ?? 8.0,
            maxTrades: $overrides['maxTrades'] ?? 20,
            minNotional: $overrides['minNotional'] ?? 5.0,
        );
    }

    private function makeState(): BotState
    {
        return BotState::create([
            'symbol' => 'BTCUSDT',
            'active_balance' => 0,
            'reserve_balance' => 0,
            'in_position' => false,
            'total_trades' => 0,
            'status' => BotState::STATUS_ACTIVE,
        ]);
    }

    public function test_initialises_capital_into_active_and_reserve_split(): void
    {
        $state = $this->makeState();
        $this->makeManager()->initialiseIfNeeded($state, 10.0);

        $this->assertEqualsWithDelta(5.0, (float) $state->active_balance, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $state->reserve_balance, 0.0001);
    }

    public function test_does_not_reinitialise_once_trading_has_started(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager();
        $manager->initialiseIfNeeded($state, 10.0);
        $manager->settleTrade($state, proceeds: 6.0, isWin: true);

        // Even though total_trades resets would normally look "fresh" if we
        // (incorrectly) re-checked balances alone, initialiseIfNeeded must
        // never overwrite an already-running bot.
        $manager->initialiseIfNeeded($state, 10.0);

        $this->assertNotEqualsWithDelta(5.0, (float) $state->active_balance, 0.0001);
    }

    public function test_win_redistributes_half_of_new_total_into_active_and_half_into_reserve(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager();
        $manager->initialiseIfNeeded($state, 10.0); // active=5, reserve=5

        // Win: stake of 5 grows to proceeds of 6.
        $manager->settleTrade($state, proceeds: 6.0, isWin: true);

        // new_total = reserve(5) + proceeds(6) = 11; split 50/50.
        $this->assertEqualsWithDelta(5.5, (float) $state->active_balance, 0.0001);
        $this->assertEqualsWithDelta(5.5, (float) $state->reserve_balance, 0.0001);
        $this->assertSame(1, $state->total_trades);
        $this->assertFalse($state->in_position);
    }

    public function test_loss_leaves_reserve_untouched_and_shrinks_active_only(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager();
        $manager->initialiseIfNeeded($state, 10.0); // active=5, reserve=5

        // Loss: stake of 5 shrinks to proceeds of 4.
        $manager->settleTrade($state, proceeds: 4.0, isWin: false);

        $this->assertEqualsWithDelta(4.0, (float) $state->active_balance, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $state->reserve_balance, 0.0001);
    }

    public function test_floor_kill_switch_stops_the_bot(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager(['floorBalance' => 8.0]);
        $manager->initialiseIfNeeded($state, 10.0); // active=5, reserve=5

        // Big loss: total balance (reserve 5 + proceeds 2 = 7) drops to/below floor of 8.
        $manager->settleTrade($state, proceeds: 2.0, isWin: false);

        $this->assertSame(BotState::STATUS_STOPPED_FLOOR, $state->status);
        $this->assertFalse($manager->canOpenPosition($state));
    }

    public function test_max_trades_kill_switch_stops_the_bot(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager(['maxTrades' => 1, 'floorBalance' => 0.0]);
        $manager->initialiseIfNeeded($state, 10.0);

        $manager->settleTrade($state, proceeds: 5.5, isWin: true);

        $this->assertSame(BotState::STATUS_STOPPED_MAX_TRADES, $state->status);
    }

    public function test_min_notional_kill_switch_stops_the_bot_when_active_balance_too_small(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager(['minNotional' => 5.0, 'floorBalance' => 0.0, 'maxTrades' => 0]);
        $manager->initialiseIfNeeded($state, 10.0); // active=5, reserve=5

        // Loss shrinks active balance below the minimum order size.
        $manager->settleTrade($state, proceeds: 3.0, isWin: false);

        $this->assertSame(BotState::STATUS_STOPPED_MIN_NOTIONAL, $state->status);
        $this->assertFalse($manager->canOpenPosition($state));
    }

    public function test_cannot_open_position_when_active_balance_only_equals_min_notional(): void
    {
        // Binance rejects market orders below its exchange NOTIONAL filter, so
        // a stake with zero buffer above that floor risks getting stuck if the
        // price dips even slightly while the position is open. The bot must
        // require a safety margin above min_notional before entering.
        $state = $this->makeState();
        $manager = $this->makeManager(['minNotional' => 5.0]);
        $state->update(['active_balance' => 5.0, 'reserve_balance' => 5.0]);

        $this->assertFalse($manager->canOpenPosition($state));
    }

    public function test_can_open_position_when_active_balance_has_safety_buffer_above_min_notional(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager(['minNotional' => 5.0]);
        $state->update(['active_balance' => 6.0, 'reserve_balance' => 5.0]);

        $this->assertTrue($manager->canOpenPosition($state));
    }

    public function test_cannot_open_position_while_already_in_one(): void
    {
        $state = $this->makeState();
        $manager = $this->makeManager();
        $manager->initialiseIfNeeded($state, 10.0);
        $state->update(['in_position' => true]);

        $this->assertFalse($manager->canOpenPosition($state));
    }
}
