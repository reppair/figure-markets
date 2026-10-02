<?php

use App\Services\FigureMarkets\MalformedMarketPayload;
use App\Services\FigureMarkets\WebSocketMarketPayload;
use Carbon\CarbonImmutable;

it('maps a market message to live attributes with the publish time', function () {
    $payload = WebSocketMarketPayload::fromMessage(jsonFixture('figure-markets/market-snapshot.json'));

    expect($payload->symbol)->toBe('HASH-USD')
        ->and($payload->toAttributes())->toMatchArray([
            'last_price' => '0.020000000000000000',
            'best_bid' => '0.020',
            'best_ask' => '0.022',
            'price_change_24h' => '0.000000000000000000',
            'percentage_change_24h' => '0',
            'high_24h' => '0.025',
            'low_24h' => '0.017',
            'volume_24h' => '1447.909714000000000000',
            'trade_count_24h' => 14025,
        ])
        ->not->toHaveKeys(['symbol', 'status', 'price_precision'])
        ->and($payload->publishedAt)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->format('Y-m-d\TH:i:s.uP')->toBe('2026-10-02T10:37:54.427234+00:00');
});

it('nulls bid and ask for a market without an order book', function () {
    $payload = WebSocketMarketPayload::fromMessage(jsonFixture('figure-markets/market-snapshot-no-book.json'));

    expect($payload)
        ->bestBid->toBeNull()
        ->bestAsk->toBeNull();
});

it('rejects a message without a required key', function (string $key) {
    $message = jsonFixture('figure-markets/market-snapshot.json');
    unset($message[$key]);

    WebSocketMarketPayload::fromMessage($message);
})->with(['marketId', 'publishTime'])->throws(MalformedMarketPayload::class);
