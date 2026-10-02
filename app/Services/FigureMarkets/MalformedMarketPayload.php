<?php

namespace App\Services\FigureMarkets;

use InvalidArgumentException;

class MalformedMarketPayload extends InvalidArgumentException
{
    public static function missing(string $key): self
    {
        return new self("Market payload is missing the required key [{$key}].");
    }
}
