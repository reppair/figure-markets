<?php

use App\Actions\SyncMarketsAction;
use App\Enums\MarketStatus;
use App\Models\Market;
use App\Services\FigureMarkets\MalformedMarketPayload;
use App\Services\FigureMarkets\RestClient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;

/**
 * @return array<int, array<string, mixed>>
 */
function marketRecords(): array
{
    return jsonFixture('figure-markets/markets.json')['data'];
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function fakeMarkets(array $records): void
{
    test()->mock(RestClient::class)->shouldReceive('markets')->once()->andReturn($records);
}

it('creates a row per market record', function () {
    fakeMarkets(marketRecords());

    $count = app(SyncMarketsAction::class)->handle();

    expect($count)->toBe(16)
        ->and(Market::count())->toBe(16)
        ->and(Market::where('symbol', 'HASH-USD')->first())
        ->display_name->toBe('HASH-USD')
        ->status->toBe(MarketStatus::Open)
        ->price_precision->toBe(3)
        ->last_price->toBe('0.019000000000000000')
        ->best_bid->toBe('0.019000000000000000')
        ->trade_count_24h->toBe(14027)
        ->price_updated_at->toBeNull();
});

it('updates an existing market and keeps its price timestamp', function () {
    $market = Market::factory()->create(['symbol' => 'HASH-USD', 'last_price' => '1', 'price_updated_at' => now()]);
    fakeMarkets(marketRecords());

    app(SyncMarketsAction::class)->handle();

    expect($market->fresh())
        ->last_price->toBe('0.019000000000000000')
        ->price_updated_at->not->toBeNull()
        ->and(Market::count())->toBe(16);
});

it('closes an open market absent from the response', function () {
    $absent = Market::factory()->create(['symbol' => 'GONE-USD']);
    fakeMarkets(marketRecords());

    app(SyncMarketsAction::class)->handle();

    expect($absent->fresh()->status)->toBe(MarketStatus::Closed);
});

it('reopens a closed market the provider lists as open', function () {
    $market = Market::factory()->closed()->create(['symbol' => 'HASH-USD']);
    fakeMarkets(marketRecords());

    app(SyncMarketsAction::class)->handle();

    expect($market->fresh()->status)->toBe(MarketStatus::Open);
});

it('writes nothing when a record is malformed', function () {
    $existing = Market::factory()->create(['symbol' => 'GONE-USD']);
    $records = marketRecords();
    unset($records[15]['symbol']);
    fakeMarkets($records);

    expect(fn () => app(SyncMarketsAction::class)->handle())->toThrow(MalformedMarketPayload::class)
        ->and(Market::count())->toBe(1)
        ->and($existing->fresh()->status)->toBe(MarketStatus::Open);
});

it('keeps existing rows when the client fails', function () {
    Market::factory()->count(2)->create();
    test()->mock(RestClient::class)->shouldReceive('markets')->once()->andThrow(new ConnectionException('timeout'));

    expect(fn () => app(SyncMarketsAction::class)->handle())->toThrow(ConnectionException::class)
        ->and(Market::open()->count())->toBe(2);
});

it('closes every market when the provider lists none', function () {
    Market::factory()->count(2)->create();
    fakeMarkets([]);

    $count = app(SyncMarketsAction::class)->handle();

    expect($count)->toBe(0)
        ->and(Market::open()->count())->toBe(0)
        ->and(Market::count())->toBe(2);
});

it('rolls back every write when a record fails a database constraint', function () {
    Market::factory()->create(['symbol' => 'GONE-USD']);
    $records = marketRecords();
    unset($records[15]['displayName']);
    fakeMarkets($records);

    expect(fn () => app(SyncMarketsAction::class)->handle())->toThrow(QueryException::class)
        ->and(Market::count())->toBe(1)
        ->and(Market::open()->count())->toBe(1);
});
