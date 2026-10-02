<?php

use App\Livewire\MarketStats;
use App\Livewire\MarketWatch;
use App\Models\Market;
use App\Models\User;
use Livewire\Livewire;

it('renders on the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSeeLivewire(MarketWatch::class);
});

it('lists open markets in symbol order and leaves closed ones out', function () {
    Market::factory()->create(['symbol' => 'BBB-USD']);
    Market::factory()->create(['symbol' => 'AAA-USD']);
    Market::factory()->closed()->create(['symbol' => 'CCC-USD']);

    Livewire::test(MarketWatch::class)
        ->assertSeeInOrder(['AAA-USD', 'BBB-USD'])
        ->assertDontSee('CCC-USD');
});

it('selects the default market when it is open', function () {
    Market::factory()->create(['symbol' => 'AAA-USD']);
    Market::factory()->create(['symbol' => 'HASH-USDC']);

    Livewire::test(MarketWatch::class)
        ->assertSet('symbol', 'HASH-USDC')
        ->assertSeeLivewire(MarketStats::class)
        ->assertSeeHtml('data-lw-market="HASH-USDC"');
});

it('falls back to the first market when the default is not open', function () {
    Market::factory()->create(['symbol' => 'BBB-USD']);
    Market::factory()->create(['symbol' => 'AAA-USD']);
    Market::factory()->closed()->create(['symbol' => 'HASH-USDC']);

    Livewire::test(MarketWatch::class)
        ->assertSet('symbol', 'AAA-USD')
        ->assertSeeHtml('data-lw-market="AAA-USD"');
});

it('renders the card of the selected market', function () {
    Market::factory()->create(['symbol' => 'AAA-USD']);
    Market::factory()->create(['symbol' => 'BBB-USD']);

    Livewire::test(MarketWatch::class)
        ->set('symbol', 'BBB-USD')
        ->assertSeeHtml('data-lw-market="BBB-USD"');
});

it('falls back to the first market when an unknown symbol is selected', function () {
    Market::factory()->create(['symbol' => 'AAA-USD']);

    Livewire::test(MarketWatch::class)
        ->set('symbol', 'ZZZ-USD')
        ->assertSet('symbol', 'AAA-USD');
});

it('falls back to the first market when a closed market is selected', function () {
    Market::factory()->create(['symbol' => 'AAA-USD']);
    Market::factory()->closed()->create(['symbol' => 'CCC-USD']);

    Livewire::test(MarketWatch::class)
        ->set('symbol', 'CCC-USD')
        ->assertSet('symbol', 'AAA-USD');
});

it('shows the sync callout when the table has no open market', function () {
    Market::factory()->closed()->create();

    Livewire::test(MarketWatch::class)
        ->assertSet('symbol', null)
        ->assertSeeHtml('data-lw-no-markets')
        ->assertDontSeeHtml('wire:model.live="symbol"');
});
