<?php

namespace Tests\Unit;

use App\Services\Trading\MovingAverageSignal;
use PHPUnit\Framework\TestCase;

class MovingAverageSignalTest extends TestCase
{
    public function test_returns_neutral_when_not_enough_candles(): void
    {
        $closes = array_fill(0, 5, 100.0);

        $this->assertSame(MovingAverageSignal::NEUTRAL, MovingAverageSignal::signal($closes, 3, 10));
    }

    public function test_detects_bullish_crossover(): void
    {
        // Short MA starts below long MA, then a price jump pulls the short
        // MA above the long MA on the most recent candle.
        $closes = [10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 30];

        $this->assertSame(MovingAverageSignal::BULLISH, MovingAverageSignal::signal($closes, 3, 10));
    }

    public function test_detects_bearish_crossover(): void
    {
        $closes = [10, 10, 10, 10, 10, 10, 10, 10, 10, 10, -10];

        $this->assertSame(MovingAverageSignal::BEARISH, MovingAverageSignal::signal($closes, 3, 10));
    }

    public function test_returns_neutral_when_already_trending_without_a_fresh_cross(): void
    {
        // Short MA has been above long MA for a while — no new crossover
        // event, so it should not keep re-signalling bullish every cycle.
        $closes = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

        $signal = MovingAverageSignal::signal($closes, 3, 10);

        $this->assertNotSame(MovingAverageSignal::BULLISH, $signal);
    }
}
