<?php

use App\Actions\HandleMarketUpdateAction;
use App\Events\MarketUpdated;
use App\Models\Market;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Event::fake([MarketUpdated::class]);
    $this->snapshot = jsonFixture('figure-markets/market-snapshot.json');
    $this->update = jsonFixture('figure-markets/market-update.json');
    $this->market = Market::factory()->create(['symbol' => 'HASH-USD', 'price_updated_at' => null]);
});

it('writes the live columns and dispatches the event with the updated market', function () {
    $result = app(HandleMarketUpdateAction::class)->handle($this->snapshot);

    expect($result)->toBeInstanceOf(Market::class)
        ->and($this->market->fresh())
        ->last_price->toBe('0.020000000000000000')
        ->best_bid->toBe('0.020000000000000000')
        ->best_ask->toBe('0.022000000000000000')
        ->trade_count_24h->toBe(14025)
        ->price_updated_at->format('Y-m-d H:i:s.u')->toBe('2026-10-02 10:37:54.427234');

    Event::assertDispatched(MarketUpdated::class, fn (MarketUpdated $event) => $event->market->is($this->market)
        && $event->market->trade_count_24h === 14025);
});

it('accepts an update published after the stored one', function () {
    $action = app(HandleMarketUpdateAction::class);
    $action->handle($this->snapshot);

    $result = $action->handle($this->update);

    expect($result)->not->toBeNull()
        ->and($this->market->fresh()->price_updated_at->format('H:i:s.u'))->toBe('10:37:55.734272');
    Event::assertDispatchedTimes(MarketUpdated::class, 2);
});

it('skips an update published before the stored one', function () {
    Log::spy();
    $action = app(HandleMarketUpdateAction::class);
    $action->handle($this->update);

    $result = $action->handle($this->snapshot);

    expect($result)->toBeNull()
        ->and($this->market->fresh()->price_updated_at->format('H:i:s.u'))->toBe('10:37:55.734272');
    Event::assertDispatchedTimes(MarketUpdated::class, 1);
    Log::shouldHaveReceived('warning')->once();
});

it('skips an update with the same publish time as the stored one', function () {
    $action = app(HandleMarketUpdateAction::class);
    $action->handle($this->snapshot);

    expect($action->handle($this->snapshot))->toBeNull();
    Event::assertDispatchedTimes(MarketUpdated::class, 1);
});

it('skips an update for a market not in the table', function () {
    Log::spy();
    $message = [...$this->snapshot, 'marketId' => 'NOPE-USD'];

    expect(app(HandleMarketUpdateAction::class)->handle($message))->toBeNull()
        ->and(Market::count())->toBe(1);
    Event::assertNotDispatched(MarketUpdated::class);
    Log::shouldHaveReceived('warning')->once();
});

it('skips a malformed message', function () {
    Log::spy();
    $message = ['message' => 'Invalid request', 'code' => 1];

    expect(app(HandleMarketUpdateAction::class)->handle($message))->toBeNull()
        ->and($this->market->fresh()->price_updated_at)->toBeNull();
    Event::assertNotDispatched(MarketUpdated::class);
    Log::shouldHaveReceived('warning')->once();
});

it('keeps the written row when the broadcast fails', function () {
    Event::swap(Event::getFacadeRoot()->dispatcher);
    Event::listen(MarketUpdated::class, fn () => throw new RuntimeException('Reverb unreachable'));
    $this->mock(ExceptionHandler::class)->shouldReceive('report')->once();

    $result = app(HandleMarketUpdateAction::class)->handle($this->snapshot);

    expect($result)->not->toBeNull()
        ->and($this->market->fresh()->price_updated_at)->not->toBeNull();
});
