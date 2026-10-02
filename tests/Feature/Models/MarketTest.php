<?php

use App\Enums\MarketStatus;
use App\Models\Market;
use Carbon\CarbonImmutable;

it('creates an open market with an order book by default', function () {
    $market = Market::factory()->create();

    $this->assertModelExists($market);
    expect($market->fresh())
        ->status->toBe(MarketStatus::Open)
        ->best_bid->not->toBeNull()
        ->best_ask->not->toBeNull()
        ->price_updated_at->toBeNull();
});

it('excludes closed markets from the open scope', function () {
    $open = Market::factory()->create();
    Market::factory()->closed()->create();

    expect(Market::open()->pluck('id')->all())->toBe([$open->id]);
});

it('returns every decimal column as a string with its cast precision', function (string $column, string $stored, string $expected) {
    $market = Market::factory()->create([$column => $stored]);

    expect($market->fresh()->{$column})->toBe($expected);
})->with([
    'last_price' => ['last_price', '0.02', '0.020000000000000000'],
    'best_bid' => ['best_bid', '0.019', '0.019000000000000000'],
    'best_ask' => ['best_ask', '0.02', '0.020000000000000000'],
    'price_change_24h' => ['price_change_24h', '-0.001', '-0.001000000000000000'],
    'high_24h' => ['high_24h', '0.025', '0.025000000000000000'],
    'low_24h' => ['low_24h', '0.017', '0.017000000000000000'],
    'volume_24h' => ['volume_24h', '1447.527114', '1447.527114000000000000'],
    'percentage_change_24h' => ['percentage_change_24h', '-0.000964', '-0.000964'],
]);

it('round-trips microseconds in the price timestamp', function () {
    $publishedAt = CarbonImmutable::parse('2026-10-02T10:37:54.427234Z');

    $market = Market::factory()->create(['price_updated_at' => $publishedAt]);

    expect($market->fresh()->price_updated_at)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->format('Y-m-d\TH:i:s.uP')->toBe('2026-10-02T10:37:54.427234+00:00');
});

it('nulls bid and ask for a market without an order book', function () {
    $market = Market::factory()->withoutOrderBook()->create();

    expect($market->fresh())
        ->best_bid->toBeNull()
        ->best_ask->toBeNull();
});

it('stores zero price, volume and trade count for an untraded market', function () {
    $market = Market::factory()->untraded()->create();

    expect($market->fresh())
        ->last_price->toBe('0.000000000000000000')
        ->volume_24h->toBe('0.000000000000000000')
        ->trade_count_24h->toBe(0);
});
