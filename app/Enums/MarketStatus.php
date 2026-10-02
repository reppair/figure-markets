<?php

namespace App\Enums;

enum MarketStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * The provider reports ten statuses, and only OPEN is tradeable. The
     * application cares about tradeable or not, so every other value maps
     * to Closed instead of failing a sync on a status never seen before.
     * See specs/design-decisions.md, decision 1.
     */
    public static function fromProvider(string $status): self
    {
        return $status === 'OPEN' ? self::Open : self::Closed;
    }
}
