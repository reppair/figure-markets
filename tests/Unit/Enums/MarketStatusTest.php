<?php

use App\Enums\MarketStatus;

it('maps the provider status OPEN to open', function () {
    expect(MarketStatus::fromProvider('OPEN'))->toBe(MarketStatus::Open);
});

it('maps every other documented provider status to closed', function (string $status) {
    expect(MarketStatus::fromProvider($status))->toBe(MarketStatus::Closed);
})->with([
    'UNKNOWN_MARKET_STATUS',
    'PENDING',
    'CLOSED',
    'PREOPEN',
    'SUSPENDED',
    'EXPIRED',
    'TERMINATED',
    'HALTED',
    'MATCH_AND_CLOSE',
]);

it('maps a value the provider has not documented to closed', function (string $status) {
    expect(MarketStatus::fromProvider($status))->toBe(MarketStatus::Closed);
})->with([
    'a future status' => 'DELISTED',
    'lowercase open' => 'open',
    'empty string' => '',
]);
