<?php

use App\Events\MarketUpdated;
use App\Models\Market;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts the market on the private markets channel', function () {
    $market = Market::factory()->make(['id' => 7]);

    $event = new MarketUpdated($market);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-markets')
        ->and($event->broadcastAs())->toBe('market.updated')
        ->and($event->broadcastWith())->toBe($market->toArray())
        ->and($event->broadcastWith()['id'])->toBe(7);
});
