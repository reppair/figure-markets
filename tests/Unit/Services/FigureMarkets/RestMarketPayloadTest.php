<?php

use App\Enums\MarketStatus;
use App\Services\FigureMarkets\MalformedMarketPayload;
use App\Services\FigureMarkets\RestMarketPayload;

/**
 * @return array<string, mixed>
 */
function marketRecord(string $symbol): array
{
    return collect(jsonFixture('figure-markets/markets.json')['data'])->firstWhere('symbol', $symbol);
}

it('maps a market record to model attributes', function () {
    $payload = RestMarketPayload::fromRecord(marketRecord('HASH-USD'));

    expect($payload->toAttributes())->toBe([
        'symbol' => 'HASH-USD',
        'display_name' => 'HASH-USD',
        'base_asset' => 'HASH',
        'quote_asset' => 'USD',
        'market_type' => 'CRYPTO',
        'status' => MarketStatus::Open,
        'price_precision' => 3,
        'last_price' => '0.019000000000000000',
        'best_bid' => '0.019',
        'best_ask' => '0.020',
        'price_change_24h' => '-0.001000000000000000',
        'percentage_change_24h' => '-0.05',
        'high_24h' => '0.025',
        'low_24h' => '0.017',
        'volume_24h' => '1447.527114000000000000',
        'trade_count_24h' => 14027,
    ]);
});

it('nulls bid and ask for a market without an order book', function () {
    $payload = RestMarketPayload::fromRecord(marketRecord('FIGR_HELOC-USD'));

    expect($payload)
        ->bestBid->toBeNull()
        ->bestAsk->toBeNull();
});

it('keeps the provider zero price for an untraded market', function () {
    $payload = RestMarketPayload::fromRecord(marketRecord('USDC-USD'));

    expect($payload)
        ->lastPrice->toBe('0')
        ->volume24h->toBe('0')
        ->tradeCount24h->toBe(0);
});

it('maps a status other than OPEN to closed', function () {
    $payload = RestMarketPayload::fromRecord([...marketRecord('HASH-USD'), 'status' => 'HALTED']);

    expect($payload->status)->toBe(MarketStatus::Closed);
});

it('rejects a record without a symbol', function () {
    $record = marketRecord('HASH-USD');
    unset($record['symbol']);

    RestMarketPayload::fromRecord($record);
})->throws(MalformedMarketPayload::class, 'symbol');
