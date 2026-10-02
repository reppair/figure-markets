<?php

namespace App\Actions;

use App\Enums\MarketStatus;
use App\Models\Market;
use App\Services\FigureMarkets\RestClient;
use App\Services\FigureMarkets\RestMarketPayload;
use Illuminate\Support\Facades\DB;

/**
 * Fills the markets table from the provider's REST list. Never writes price_updated_at.
 */
class SyncMarketsAction
{
    public function __construct(private RestClient $client) {}

    /**
     * Returns the number of markets written. Exceptions from the client or a malformed record bubble
     * before anything is written, and the writes run in one transaction, so a failed sync leaves the
     * table untouched (design decision 14).
     */
    public function handle(): int
    {
        $payloads = collect($this->client->markets())
            ->map(fn (array $record): RestMarketPayload => RestMarketPayload::fromRecord($record));

        DB::transaction(function () use ($payloads): void {
            // Write every market the provider listed, identity and live columns alike.
            $payloads->each(fn (RestMarketPayload $payload) => Market::updateOrCreate(
                ['symbol' => $payload->symbol],
                $payload->toAttributes(),
            ));

            // Close the open markets the provider no longer lists; rows are never deleted.
            Market::query()
                ->open()
                ->whereNotIn('symbol', $payloads->pluck('symbol'))
                ->update(['status' => MarketStatus::Closed]);
        });

        return $payloads->count();
    }
}
