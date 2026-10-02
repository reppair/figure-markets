<?php

namespace App\Services\FigureMarkets;

use Illuminate\Support\Facades\Http;

/**
 * Reads the public Figure Markets REST API. Knows the response envelope, never the market fields.
 */
class RestClient
{
    private const int TIMEOUT_SECONDS = 10;

    private const int RETRIES = 3;

    private const int RETRY_DELAY_MILLISECONDS = 500;

    /**
     * One page holds every market on UAT and production (design decision 12).
     */
    private const int PAGE_SIZE = 50;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function markets(): array
    {
        return Http::baseUrl(config('services.figure_markets.rest_url'))
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRIES, self::RETRY_DELAY_MILLISECONDS)
            ->get('/markets', ['size' => self::PAGE_SIZE])
            ->json('data');
    }
}
