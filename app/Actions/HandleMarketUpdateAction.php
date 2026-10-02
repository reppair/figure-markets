<?php

namespace App\Actions;

use App\Events\MarketUpdated;
use App\Models\Market;
use App\Services\FigureMarkets\MalformedMarketPayload;
use App\Services\FigureMarkets\WebSocketMarketPayload;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies one decoded provider WebSocket message to the markets table and broadcasts it.
 */
class HandleMarketUpdateAction
{
    /**
     * Returns the updated market, or null when the message was skipped; the reason is in the log.
     * A failed broadcast is reported, not thrown: the row is already current and must not cost the connection.
     *
     * @param  array<string, mixed>  $message
     */
    public function handle(array $message): ?Market
    {
        try {
            $payload = WebSocketMarketPayload::fromMessage($message);
        } catch (MalformedMarketPayload $e) {
            Log::warning('Skipped a malformed market message', ['reason' => $e->getMessage(), 'message' => $message]);

            return null;
        }

        $market = Market::where('symbol', $payload->symbol)->first();

        if ($market === null) {
            Log::warning('Skipped an update for an unknown market', ['symbol' => $payload->symbol]);

            return null;
        }

        if ($market->price_updated_at !== null && $payload->publishedAt->lessThanOrEqualTo($market->price_updated_at)) {
            Log::warning('Skipped a stale market update', [
                'symbol' => $payload->symbol,
                'published_at' => $payload->publishedAt,
                'price_updated_at' => $market->price_updated_at,
            ]);

            return null;
        }

        // The row is written first; the component re-queries it when the event arrives.
        $market->update($payload->toAttributes());

        try {
            MarketUpdated::dispatch($market);
        } catch (Throwable $e) {
            report($e);
        }

        return $market;
    }
}
