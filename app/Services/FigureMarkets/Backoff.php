<?php

namespace App\Services\FigureMarkets;

/**
 * Reconnect delays for the listener: doubling from one second to a cap, with jitter so several
 * processes do not retry in step.
 */
class Backoff
{
    private const float INITIAL_SECONDS = 1.0;

    private const float MAX_SECONDS = 30.0;

    private const int JITTER_PERMILLE = 200;

    private float $current = self::INITIAL_SECONDS;

    public function next(): float
    {
        $delay = $this->current;
        $this->current = min($this->current * 2, self::MAX_SECONDS);

        $jitter = random_int(-self::JITTER_PERMILLE, self::JITTER_PERMILLE) / 1000;

        return round($delay * (1 + $jitter), 3);
    }

    public function reset(): void
    {
        $this->current = self::INITIAL_SECONDS;
    }
}
