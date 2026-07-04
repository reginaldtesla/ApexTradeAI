<?php

namespace App\Services\Trading;

class MovingAverageSignal
{
    public const BULLISH = 'bullish';

    public const BEARISH = 'bearish';

    public const NEUTRAL = 'neutral';

    /**
     * Detects a moving-average crossover event (not just "short is above long",
     * which would otherwise keep re-signalling every cycle while already
     * trending). Needs at least $longPeriod + 1 closes.
     *
     * @param  array<int, float>  $closes  oldest first
     */
    public static function signal(array $closes, int $shortPeriod, int $longPeriod): string
    {
        if (count($closes) < $longPeriod + 1) {
            return self::NEUTRAL;
        }

        $shortNow = self::sma($closes, $shortPeriod, 0);
        $shortPrev = self::sma($closes, $shortPeriod, 1);
        $longNow = self::sma($closes, $longPeriod, 0);
        $longPrev = self::sma($closes, $longPeriod, 1);

        $crossedUp = $shortPrev <= $longPrev && $shortNow > $longNow;
        $crossedDown = $shortPrev >= $longPrev && $shortNow < $longNow;

        return match (true) {
            $crossedUp => self::BULLISH,
            $crossedDown => self::BEARISH,
            default => self::NEUTRAL,
        };
    }

    /**
     * Current (most recent) short/long SMA values, for display purposes.
     * Returns null values if there isn't enough data yet.
     *
     * @param  array<int, float>  $closes  oldest first
     * @return array{short: ?float, long: ?float}
     */
    public static function currentAverages(array $closes, int $shortPeriod, int $longPeriod): array
    {
        return [
            'short' => count($closes) >= $shortPeriod ? self::sma($closes, $shortPeriod, 0) : null,
            'long' => count($closes) >= $longPeriod ? self::sma($closes, $longPeriod, 0) : null,
        ];
    }

    /**
     * Simple moving average over the last $period closes, offset back by
     * $shiftFromEnd candles (0 = most recent window, 1 = one candle earlier).
     *
     * @param  array<int, float>  $closes  oldest first
     */
    private static function sma(array $closes, int $period, int $shiftFromEnd): float
    {
        $end = count($closes) - $shiftFromEnd;
        $window = array_slice($closes, $end - $period, $period);

        return array_sum($window) / count($window);
    }
}
